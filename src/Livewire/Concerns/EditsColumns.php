<?php

/**
 * Board-native column editing.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Concerns;

use ArtisanPackUI\Ecommerce\Exceptions\KanbanColumnNotEmptyException;
use ArtisanPackUI\Ecommerce\Exceptions\KanbanOperationException;
use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use ArtisanPackUI\Ecommerce\Services\KanbanColumnService;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\AdminLinks;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\Statuses;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/**
 * "Edit board" mode (spec §7.8, D6): reorder columns by dragging their
 * headers or with "Move left / right", edit a column's label, color, icon,
 * and WIP limit in place, add a column for a sub-status that isn't on the
 * board yet, and remove a column — refused with the card count while cards
 * remain, unless they are moved to another column first.
 *
 * Every write needs `kanbanBoard.update` and goes through the engine's
 * `KanbanColumnService`, so the rules match the admin and REST, and the
 * engine's config-changed broadcast refreshes other viewers. Removing a
 * column uses a one-time action token.
 *
 * Uses {@see WithActionToken}, {@see AuthorizesKanban}, {@see MovesCards}
 * (for the acting user), and the Toast trait.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
trait EditsColumns
{
    /**
     * Whether edit mode is on.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Locked]
    public bool $editing = false;

    /**
     * Whether the column editor is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $columnDialog = false;

    /**
     * The column the editor edits.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $editingColumnId = null;

    /**
     * The editor's fields.
     *
     * @since 1.0.0
     *
     * @var array{label_override: mixed, color_override: mixed, icon_override: mixed, wip_limit: mixed}
     */
    public array $columnForm = [
        'label_override' => '',
        'color_override' => '',
        'icon_override'  => '',
        'wip_limit'      => '',
    ];

    /**
     * Whether the "Add column" dialog is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $addColumnDialog = false;

    /**
     * The sub-status the new column is for.
     *
     * @since 1.0.0
     *
     * @var mixed
     */
    public mixed $newSubstatusId = null;

    /**
     * The column waiting for removal confirmation.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $deletingColumnId = null;

    /**
     * Whether the removal confirmation is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $confirmingDeleteColumn = false;

    /**
     * The column to move the removed column's cards to.
     *
     * @since 1.0.0
     *
     * @var mixed
     */
    public mixed $deleteMoveTo = '';

    /**
     * Turns edit mode on.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function startEditing(): void
    {
        $this->authorizeKanban( 'kanbanBoard.update', $this->board );

        $this->editing = true;
    }

    /**
     * Turns edit mode off.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function stopEditing(): void
    {
        $this->editing = false;
    }

    /**
     * Puts the columns in the given order (a header drop).
     *
     * @since 1.0.0
     *
     * @param  array<int, mixed>  $columnIds  Every column id, left to right.
     *
     * @return string `moved`, or `rejected` (the client reverts the drop and refreshes).
     */
    public function reorderColumns( array $columnIds ): string
    {
        $this->authorizeKanban( 'kanbanBoard.update', $this->board );

        try {
            app( KanbanColumnService::class )->reorder( $this->board, array_values( $columnIds ), self::actorId() );
        } catch ( KanbanOperationException ) {
            $this->error( e( __( 'The columns changed while you were editing. The board has been refreshed.' ) ) );
            $this->skipRender();

            return self::REJECTED;
        }

        unset( $this->columns );

        return self::MOVED;
    }

    /**
     * Moves a column one place left (`-1`) or right (`1`): the keyboard and
     * single-pointer alternative to dragging its header.
     *
     * @since 1.0.0
     *
     * @param  int  $columnId   The column.
     * @param  int  $direction  `-1` or `1`.
     *
     * @return void
     */
    public function moveColumn( int $columnId, int $direction ): void
    {
        $ids   = $this->columns->pluck( 'id' )->map( static fn ( mixed $id ): int => (int) $id )->all();
        $index = array_search( $columnId, $ids, true );
        $swap  = false === $index ? null : $index + ( $direction < 0 ? -1 : 1 );

        if ( null === $swap || ! isset( $ids[ $swap ] ) ) {
            return;
        }

        [ $ids[ $index ], $ids[ $swap ] ] = [ $ids[ $swap ], $ids[ $index ] ];

        if ( self::MOVED === $this->reorderColumns( $ids ) ) {
            $this->dispatch( 'ec-kb-focus-column', columnId: $columnId, direction: $direction < 0 ? 'left' : 'right' );
        }
    }

    /**
     * Opens the editor for a column.
     *
     * @since 1.0.0
     *
     * @param  int  $columnId  The column.
     *
     * @return void
     */
    public function editColumn( int $columnId ): void
    {
        $this->authorizeKanban( 'kanbanBoard.update', $this->board );

        $column = $this->editableColumn( $columnId );

        if ( null === $column ) {
            return;
        }

        $this->resetValidation();

        $this->editingColumnId = (int) $column->id;
        $this->columnForm      = [
            'label_override' => (string) $column->label_override,
            'color_override' => (string) $column->color_override,
            'icon_override'  => (string) $column->icon_override,
            'wip_limit'      => null === $column->wip_limit ? '' : (string) $column->wip_limit,
        ];
        $this->columnDialog = true;
    }

    /**
     * Saves the editor; the engine's validation messages show on the fields.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function saveColumn(): void
    {
        $this->authorizeKanban( 'kanbanBoard.update', $this->board );

        $this->resetValidation();

        $column = null === $this->editingColumnId ? null : $this->editableColumn( $this->editingColumnId );

        if ( null === $column ) {
            $this->columnDialog = false;

            return;
        }

        $blank = static fn ( mixed $value ): mixed => is_string( $value ) && '' === trim( $value ) ? null : ( is_string( $value ) ? trim( $value ) : $value );
        $data  = [
            'label_override' => $blank( $this->columnForm['label_override'] ?? null ),
            'color_override' => $blank( $this->columnForm['color_override'] ?? null ),
            'icon_override'  => $blank( $this->columnForm['icon_override'] ?? null ),
            'wip_limit'      => $blank( $this->columnForm['wip_limit'] ?? null ),
        ];

        if ( is_string( $data['wip_limit'] ) && ctype_digit( $data['wip_limit'] ) ) {
            $data['wip_limit'] = (int) $data['wip_limit'];
        }

        if ( ! $this->runColumnWrite( fn () => app( KanbanColumnService::class )->update( $column, $data, self::actorId() ), 'columnForm.' ) ) {
            return;
        }

        $this->columnDialog    = false;
        $this->editingColumnId = null;
        unset( $this->columns );

        $this->success( e( __( 'Column ":column" saved.', [ 'column' => $column->displayLabel() ] ) ) );
    }

    /**
     * Opens the "Add column" dialog.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function openAddColumn(): void
    {
        $this->authorizeKanban( 'kanbanBoard.update', $this->board );
        $this->resetValidation();

        $this->newSubstatusId  = null;
        $this->addColumnDialog = true;
    }

    /**
     * Adds a column for the chosen sub-status, at the end of the board.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function addColumn(): void
    {
        $this->authorizeKanban( 'kanbanBoard.update', $this->board );

        $this->resetValidation();
        $this->validate( [ 'newSubstatusId' => [ 'required', 'integer' ] ], [], [ 'newSubstatusId' => __( 'sub-status' ) ] );

        $column = null;

        if ( ! $this->runColumnWrite( function () use ( &$column ): void {
            $column = app( KanbanColumnService::class )->create( $this->board, [ 'substatus_id' => (int) $this->newSubstatusId ], self::actorId() );
        }, '', [ 'substatus_id' => 'newSubstatusId' ] ) ) {
            return;
        }

        $this->addColumnDialog = false;
        unset( $this->columns );

        $this->success( e( __( 'Column ":column" added.', [ 'column' => $column?->displayLabel() ?? '' ] ) ) );
    }

    /**
     * Asks before removing a column.
     *
     * @since 1.0.0
     *
     * @param  int  $columnId  The column.
     *
     * @return void
     */
    public function confirmDeleteColumn( int $columnId ): void
    {
        $this->authorizeKanban( 'kanbanBoard.update', $this->board );

        if ( null === $this->editableColumn( $columnId ) ) {
            return;
        }

        $this->resetValidation();

        $this->deletingColumnId       = $columnId;
        $this->deleteMoveTo           = '';
        $this->confirmingDeleteColumn = true;
    }

    /**
     * Drops the pending removal.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function cancelDeleteColumn(): void
    {
        $this->deletingColumnId       = null;
        $this->confirmingDeleteColumn = false;
    }

    /**
     * Removes the pending column, once per token, moving its cards to the
     * chosen column first when it has any (which also needs `kanbanCard.move`).
     *
     * @since 1.0.0
     *
     * @param  string  $token  The token minted with the confirmation.
     *
     * @return void
     */
    public function deleteColumn( string $token ): void
    {
        $this->authorizeKanban( 'kanbanBoard.update', $this->board );

        $this->resetValidation();

        $column = null === $this->deletingColumnId || ! $this->confirmingDeleteColumn ? null : $this->editableColumn( $this->deletingColumnId );

        if ( null === $column ) {
            $this->cancelDeleteColumn();

            return;
        }

        $moveTo = is_numeric( $this->deleteMoveTo ) ? $this->editableColumn( (int) $this->deleteMoveTo ) : null;

        if ( null !== $moveTo ) {
            $this->authorizeKanban( 'kanbanCard.move', $this->board );
        }
        $label  = $column->displayLabel();

        try {
            $deleted = $this->withActionToken( $token, 'delete-column', (int) $column->id, fn (): bool => (bool) app( KanbanColumnService::class )->delete( $column, $moveTo, self::actorId() ) );
        } catch ( KanbanColumnNotEmptyException $exception ) {
            $this->addError( 'deleteMoveTo', trans_choice(
                'This column still has :count card. Choose a column to move it to.|This column still has :count cards. Choose a column to move them to.',
                $exception->cardCount,
                [ 'count' => $exception->cardCount ],
            ) );

            return;
        } catch ( KanbanOperationException $exception ) {
            $this->error( e( __( 'Column ":column" could not be removed: :reason', [ 'column' => $label, 'reason' => $exception->getMessage() ] ) ) );

            return;
        }

        $this->cancelDeleteColumn();

        if ( true === $deleted ) {
            $this->collapsed = array_values( array_diff( $this->collapsed, [ (int) $column->id ] ) );
            unset( $this->limits[ (int) $column->id ], $this->columns );

            $this->success( e( __( 'Column ":column" removed.', [ 'column' => $label ] ) ) );
        }
    }

    /**
     * The edit mode's view data.
     *
     * @since 1.0.0
     *
     * @param  array<int, array{count: int, total: int}>  $counts  Column counts.
     *
     * @return array<string, mixed>
     */
    protected function editColumnsData( array $counts ): array
    {
        $deleting = null === $this->deletingColumnId ? null : $this->columns->firstWhere( 'id', $this->deletingColumnId );
        $cards    = null === $deleting ? 0 : (int) ( $counts[ (int) $deleting->id ]['total'] ?? $deleting->cardCount() );

        return [
            'editing'          => $this->editing,
            'advancedUrl'      => $this->editing ? AdminLinks::boardEditUrl( $this->boardId ) : null,
            'editingColumn'    => null === $this->editingColumnId ? null : $this->columns->firstWhere( 'id', $this->editingColumnId )?->substatus?->label,
            'substatusOptions' => $this->addColumnDialog ? $this->substatusOptions() : [],
            'deletingColumn'   => null === $deleting ? null : [
                'label' => $deleting->displayLabel(),
                'cards' => $cards,
                'token' => $this->actionToken( 'delete-column', (int) $deleting->id ),
            ],
            'moveToColumns'    => null === $deleting ? [] : $this->columns
                ->reject( static fn ( KanbanColumn $column ): bool => (int) $column->id === (int) $deleting->id )
                ->map( static fn ( KanbanColumn $column ): array => [ 'id' => (int) $column->id, 'name' => $column->displayLabel() ] )
                ->values()
                ->all(),
        ];
    }

    /**
     * Sub-statuses that aren't on the board yet, grouped by system status.
     *
     * @since 1.0.0
     *
     * @return array<string, array<int, array{id: int, name: string}>>
     */
    private function substatusOptions(): array
    {
        $used    = $this->columns->pluck( 'substatus_id' )->map( static fn ( mixed $id ): int => (int) $id )->all();
        $options = [];

        $substatuses = OrderSubstatus::query()
            ->whereNotIn( 'id', $used )
            ->orderBy( 'system_status' )
            ->orderBy( 'position' )
            ->orderBy( 'id' )
            ->get();

        foreach ( $substatuses as $substatus ) {
            $options[ Statuses::label( 'system', $substatus->system_status ) ][] = [ 'id' => (int) $substatus->id, 'name' => (string) $substatus->label ];
        }

        return $options;
    }

    /**
     * A column on this board.
     *
     * @since 1.0.0
     *
     * @param  int  $columnId  The column.
     *
     * @return KanbanColumn|null
     */
    private function editableColumn( int $columnId ): ?KanbanColumn
    {
        return $this->columns->firstWhere( 'id', $columnId );
    }

    /**
     * Runs a column write, showing the engine's validation messages on the
     * component's fields and toasting refusals.
     *
     * @since 1.0.0
     *
     * @param  callable              $write   The write.
     * @param  string                $prefix  Field prefix for engine attributes (`columnForm.`).
     * @param  array<string, string> $fields  Engine attribute → component field overrides.
     *
     * @return bool Whether the write went through.
     */
    private function runColumnWrite( callable $write, string $prefix, array $fields = [] ): bool
    {
        try {
            $write();
        } catch ( ValidationException $exception ) {
            foreach ( $exception->errors() as $attribute => $messages ) {
                $this->addError( $fields[ $attribute ] ?? $prefix . $attribute, (string) ( $messages[0] ?? '' ) );
            }

            return false;
        } catch ( KanbanOperationException $exception ) {
            $this->error( e( $exception->getMessage() ) );

            return false;
        }

        return true;
    }
}
