<?php

/**
 * Automations panel.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Livewire;

use ArtisanPack\LivewireUiComponents\Traits\Toast;
use ArtisanPackUI\Ecommerce\Events\KanbanBoardConfigChanged;
use ArtisanPackUI\Ecommerce\Models\KanbanAutomation;
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Concerns\AuthorizesKanban;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\AdminLinks;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\Authorization;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\Automations;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Event;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The automations panel (spec §7.9, D6): what fires when a card enters or
 * leaves a column. For the whole board it lists every automation; for a
 * column, those moving cards "into this column" and "out of this column".
 * Each row shows the trigger, from → to, a conditions summary, and whether
 * it is on.
 *
 * Anyone who can view the board can read the list. With
 * `kanbanBoard.update` an automation can be switched on or off and deleted
 * (confirmed, once per token); "Add" and "Edit" link to the admin's
 * automation forms when the admin is installed, and without it the panel
 * is list and toggle only (spec open question 1). Changes tell the board to
 * re-render its column badges and are broadcast as a board config change.
 *
 * The board opens it with `ec-kb-edit-automations` (`columnId`, null for
 * the whole board).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
class AutomationsPanel extends Component
{
    use AuthorizesKanban;
    use Toast;
    use WithActionToken;

    /**
     * The board's id.
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Locked]
    public int $boardId;

    /**
     * The column the panel shows; null for the whole board.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $columnId = null;

    /**
     * Whether the panel is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $open = false;

    /**
     * The automation waiting for delete confirmation.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $deletingId = null;

    /**
     * @since 1.0.0
     *
     * @param  int  $boardId  The board's id.
     *
     * @return void
     */
    public function mount( int $boardId ): void
    {
        $this->boardId = $boardId;

        $this->authorizeKanban( 'kanbanBoard.view', $this->board() );
    }

    /**
     * Re-checks access to the board on every update.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function hydrate(): void
    {
        $this->authorizeKanban( 'kanbanBoard.view', $this->board() );
    }

    /**
     * Opens the panel for a column, or the whole board.
     *
     * @since 1.0.0
     *
     * @param  int|null  $columnId  The column; null for the whole board.
     *
     * @return void
     */
    #[On( 'ec-kb-edit-automations' )]
    public function openFor( ?int $columnId = null ): void
    {
        $column = null === $columnId ? null : $this->column( $columnId );

        if ( null !== $columnId && null === $column ) {
            return;
        }

        $this->columnId   = $column?->id;
        $this->deletingId = null;
        $this->open       = true;
    }

    /**
     * Switches an automation on or off.
     *
     * @since 1.0.0
     *
     * @param  int  $automationId  The automation.
     *
     * @return void
     */
    public function toggleActive( int $automationId ): void
    {
        $board = $this->board();

        $this->authorizeKanban( 'kanbanBoard.update', $board );

        $automation = $this->automation( $automationId );

        if ( null === $automation ) {
            return;
        }

        $automation->is_active = ! $automation->is_active;
        $automation->save();

        $this->changed( $board );
        $this->success( $automation->is_active ? __( 'Automation switched on.' ) : __( 'Automation switched off.' ) );
    }

    /**
     * Asks to confirm deleting an automation.
     *
     * @since 1.0.0
     *
     * @param  int  $automationId  The automation.
     *
     * @return void
     */
    public function confirmDelete( int $automationId ): void
    {
        $this->authorizeKanban( 'kanbanBoard.update', $this->board() );

        $this->deletingId = null === $this->automation( $automationId ) ? null : $automationId;
    }

    /**
     * Dismisses the delete confirmation.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function cancelDelete(): void
    {
        $this->deletingId = null;
    }

    /**
     * Deletes the automation waiting for confirmation, once per token.
     *
     * @since 1.0.0
     *
     * @param  string  $token  The action token minted with the confirmation.
     *
     * @return void
     */
    public function deleteAutomation( string $token ): void
    {
        $board = $this->board();

        $this->authorizeKanban( 'kanbanBoard.update', $board );

        $automation = null === $this->deletingId ? null : $this->automation( $this->deletingId );

        $this->deletingId = null;

        if ( null === $automation ) {
            return;
        }

        $deleted = $this->withActionToken( $token, 'delete-automation', (int) $automation->id, static fn (): bool => (bool) $automation->delete() );

        if ( true === $deleted ) {
            $this->changed( $board );
            $this->success( __( 'Automation deleted.' ) );
        }
    }

    /**
     * Renders the panel.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $board     = $this->board();
        $canUpdate = Authorization::allows( auth()->user(), 'kanbanBoard.update', $board );
        $column    = null === $this->columnId ? null : $this->column( $this->columnId );
        $labels    = [];
        $groups    = [];

        if ( $this->open ) {
            $labels = KanbanColumn::query()
                ->where( 'board_id', $board->id )
                ->with( 'substatus' )
                ->get()
                ->mapWithKeys( static fn ( KanbanColumn $item ): array => [ (int) $item->id => $item->displayLabel() ] )
                ->all();

            $groups = $this->groups( $board, $column, $labels, $canUpdate );
        }

        $deleting = null;

        if ( $canUpdate && null !== $this->deletingId ) {
            foreach ( $groups as $group ) {
                foreach ( $group['rows'] as $row ) {
                    if ( $row['id'] === $this->deletingId ) {
                        $deleting = [ ...$row, 'token' => $this->actionToken( 'delete-automation', $row['id'] ) ];
                    }
                }
            }
        }

        return view( 'ecommerce-kanban::livewire.automations-panel', [
            'title'     => null === $column ? __( 'Automations on :board', [ 'board' => $board->name ] ) : __( 'Automations for :column', [ 'column' => $column->displayLabel() ] ),
            'groups'    => $groups,
            'canUpdate' => $canUpdate,
            'addUrl'    => $canUpdate ? AdminLinks::automationUrl( (int) $board->id ) : null,
            'deleting'  => $deleting,
        ] );
    }

    /**
     * The lists the panel shows: every automation for the board, or "into"
     * and "out of" for a column.
     *
     * @since 1.0.0
     *
     * @param  KanbanBoard         $board      The board.
     * @param  KanbanColumn|null   $column     The column, or null for the board.
     * @param  array<int, string>  $labels     Column labels by id.
     * @param  bool                $canUpdate  Whether the viewer may edit.
     *
     * @return array<int, array{key: string, heading: string|null, empty: string, rows: array<int, array<string, mixed>>}>
     */
    private function groups( KanbanBoard $board, ?KanbanColumn $column, array $labels, bool $canUpdate ): array
    {
        $automations = KanbanAutomation::query()->where( 'board_id', $board->id )->orderBy( 'id' )->get();
        $present     = fn ( Collection $items ): array => $items->map( fn ( KanbanAutomation $automation ): array => $this->present( $automation, $labels, $canUpdate ) )->values()->all();

        if ( null === $column ) {
            return [
                [ 'key' => 'board', 'heading' => null, 'empty' => __( 'This board has no automations.' ), 'rows' => $present( $automations ) ],
            ];
        }

        $id = (int) $column->id;

        return [
            [
                'key'     => 'into',
                'heading' => __( 'Into this column' ),
                'empty'   => __( 'Nothing runs when a card moves into this column.' ),
                'rows'    => $present( $automations->filter( static fn ( KanbanAutomation $automation ): bool => $id === (int) $automation->to_column_id ) ),
            ],
            [
                'key'     => 'out',
                'heading' => __( 'Out of this column' ),
                'empty'   => __( 'Nothing runs only when a card leaves this column.' ),
                'rows'    => $present( $automations->filter( static fn ( KanbanAutomation $automation ): bool => $id === (int) $automation->from_column_id ) ),
            ],
        ];
    }

    /**
     * An automation's display row.
     *
     * @since 1.0.0
     *
     * @param  KanbanAutomation    $automation  The automation.
     * @param  array<int, string>  $labels      Column labels by id.
     * @param  bool                $canUpdate   Whether the viewer may edit.
     *
     * @return array{id: int, trigger: string, from: string, to: string, conditions: string, active: bool, edit_url: string|null}
     */
    private function present( KanbanAutomation $automation, array $labels, bool $canUpdate ): array
    {
        return [
            'id'         => (int) $automation->id,
            'trigger'    => Automations::triggerLabel( (string) $automation->trigger_key ),
            'from'       => null === $automation->from_column_id ? __( 'Any column' ) : ( $labels[ (int) $automation->from_column_id ] ?? __( 'Unknown column' ) ),
            'to'         => $labels[ (int) $automation->to_column_id ] ?? __( 'Unknown column' ),
            'conditions' => Automations::conditionsSummary( $automation->conditions ),
            'active'     => (bool) $automation->is_active,
            'edit_url'   => $canUpdate ? AdminLinks::automationUrl( (int) $automation->board_id, (int) $automation->id ) : null,
        ];
    }

    /**
     * Re-renders the board here and tells other open boards.
     *
     * @since 1.0.0
     *
     * @param  KanbanBoard  $board  The board.
     *
     * @return void
     */
    private function changed( KanbanBoard $board ): void
    {
        $this->dispatch( 'ec-kb-board-changed' );

        $actor = auth()->id();

        Event::dispatch( new KanbanBoardConfigChanged( $board, KanbanBoardConfigChanged::BOARD_UPDATED, null, is_numeric( $actor ) ? (int) $actor : null ) );
    }

    /**
     * The board.
     *
     * @since 1.0.0
     *
     * @return KanbanBoard
     */
    private function board(): KanbanBoard
    {
        return KanbanBoard::query()->findOrFail( $this->boardId );
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
    private function column( int $columnId ): ?KanbanColumn
    {
        return KanbanColumn::query()->where( 'board_id', $this->boardId )->with( 'substatus' )->find( $columnId );
    }

    /**
     * An automation on this board.
     *
     * @since 1.0.0
     *
     * @param  int  $automationId  The automation.
     *
     * @return KanbanAutomation|null
     */
    private function automation( int $automationId ): ?KanbanAutomation
    {
        return KanbanAutomation::query()->where( 'board_id', $this->boardId )->find( $automationId );
    }
}
