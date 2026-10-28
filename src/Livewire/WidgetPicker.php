<?php

/**
 * Card widget picker.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Livewire;

use ArtisanPack\LivewireUiComponents\Traits\Toast;
use ArtisanPackUI\Ecommerce\Contracts\DescribesConfig;
use ArtisanPackUI\Ecommerce\Kanban\KanbanWidgetList;
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\KanbanCardWidget;
use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\Ecommerce\Registries\KanbanCardWidgetRegistry;
use ArtisanPackUI\Ecommerce\Services\KanbanCardWidgetService;
use ArtisanPackUI\Ecommerce\Services\KanbanColumnService;
use ArtisanPackUI\Ecommerce\Support\ConfigSchema;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Concerns\AuthorizesKanban;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\CardPresenter;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The card widget picker (spec §7.8, D6): which widgets a column's cards
 * show, or the board's default for columns that don't choose, in order, with
 * each widget's settings.
 *
 * The list holds registered widgets (label and provider); widgets are
 * added, removed, and reordered by dragging or with "Move up / down". A
 * widget that declares a `configSchema()` gets a form for its `text`,
 * `number`, `boolean`, and `select` fields; the engine validates on save
 * and its messages show on the fields. The picker says which list applies
 * — an empty column list falls back to the board default, an empty board
 * default to the engine's — and previews a card from the board with the
 * list as it stands (only once the settings are valid).
 *
 * The board opens it with `ec-kb-edit-widgets` (`columnId`, null for the
 * board default). Needs `kanbanBoard.update`.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
class WidgetPicker extends Component
{
    use AuthorizesKanban;
    use Toast;

    /**
     * Config field types the picker renders a control for.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const FIELD_TYPES = [ 'text', 'textarea', 'number', 'boolean', 'select' ];

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
     * The column being edited; null for the board default.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $columnId = null;

    /**
     * Whether the picker is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $open = false;

    /**
     * The list being edited: `{ key, config }` entries in order.
     *
     * @since 1.0.0
     *
     * @var array<int, array{key: string, config: array<string, mixed>}>
     */
    public array $entries = [];

    /**
     * The widget chosen in the "Add a widget" select.
     *
     * @since 1.0.0
     *
     * @var mixed
     */
    public mixed $addKey = '';

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
     * Opens the picker for a column, or the board default.
     *
     * @since 1.0.0
     *
     * @param  int|null  $columnId  The column; null for the board default.
     *
     * @return void
     */
    #[On( 'ec-kb-edit-widgets' )]
    public function openFor( ?int $columnId = null ): void
    {
        $board = $this->board();

        $this->authorizeKanban( 'kanbanBoard.update', $board );

        $column = null === $columnId ? null : $this->column( $columnId );

        if ( null !== $columnId && null === $column ) {
            return;
        }

        $this->resetValidation();

        $this->columnId = $column?->id;
        $this->entries  = KanbanWidgetList::normalize( null === $column ? ( $board->settings['card_widgets'] ?? [] ) : $column->card_widgets );
        $this->addKey   = '';
        $this->open     = true;
    }

    /**
     * Keeps a client-edited list well-formed: string keys, array config, no
     * repeated widgets.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedEntries(): void
    {
        $this->entries = KanbanWidgetList::normalize( $this->entries );
    }

    /**
     * Adds the chosen widget to the end of the list.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function addWidget(): void
    {
        $key = is_string( $this->addKey ) ? $this->addKey : '';

        if ( ! app( KanbanCardWidgetRegistry::class )->has( $key ) || in_array( $key, array_column( $this->entries, 'key' ), true ) ) {
            return;
        }

        $this->entries[] = [ 'key' => $key, 'config' => [] ];
        $this->addKey    = '';
    }

    /**
     * Removes the widget at `$index`.
     *
     * @since 1.0.0
     *
     * @param  int  $index  Position in the list.
     *
     * @return void
     */
    public function removeWidget( int $index ): void
    {
        unset( $this->entries[ $index ] );

        $this->entries = array_values( $this->entries );
        $this->resetValidation();
    }

    /**
     * Moves the widget at `$index` up (`-1`) or down (`1`).
     *
     * @since 1.0.0
     *
     * @param  int  $index      Position in the list.
     * @param  int  $direction  `-1` or `1`.
     *
     * @return void
     */
    public function moveWidget( int $index, int $direction ): void
    {
        $swap = $index + ( $direction < 0 ? -1 : 1 );

        if ( ! isset( $this->entries[ $index ], $this->entries[ $swap ] ) ) {
            return;
        }

        [ $this->entries[ $index ], $this->entries[ $swap ] ] = [ $this->entries[ $swap ], $this->entries[ $index ] ];
        $this->resetValidation();
    }

    /**
     * Puts the list in a dragged order: `$order` holds each entry's old
     * position, first to last.
     *
     * @since 1.0.0
     *
     * @param  array<int, mixed>  $order  Old positions in their new order.
     *
     * @return void
     */
    public function reorderWidgets( array $order ): void
    {
        $order = array_map( 'intval', array_filter( $order, 'is_numeric' ) );
        $keys  = array_keys( $this->entries );

        $sorted = $order;
        sort( $sorted );

        if ( $sorted !== $keys ) {
            return;
        }

        $this->entries = array_map( fn ( int $index ): array => $this->entries[ $index ], $order );
        $this->resetValidation();
    }

    /**
     * Saves the list through the engine.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function save(): void
    {
        $board = $this->board();

        $this->authorizeKanban( 'kanbanBoard.update', $board );

        $this->updatedEntries();

        $column = null === $this->columnId ? null : $this->column( $this->columnId );

        if ( null !== $this->columnId && null === $column ) {
            $this->open = false;
            $this->error( e( __( 'That column no longer exists, so its widgets were not saved.' ) ) );

            return;
        }

        $list   = $this->storedList();
        $actor  = auth()->id();
        $actor  = is_numeric( $actor ) ? (int) $actor : null;

        try {
            if ( null === $column ) {
                app( KanbanCardWidgetService::class )->setBoardWidgets( $board, $list, $actor );
            } else {
                app( KanbanColumnService::class )->update( $column, [ 'card_widgets' => $list ], $actor );
            }
        } catch ( ValidationException $exception ) {
            foreach ( $exception->errors() as $attribute => $messages ) {
                $this->addError( (string) preg_replace( '/^card_widgets/', 'entries', $attribute ), (string) ( $messages[0] ?? '' ) );
            }

            return;
        }

        $this->open = false;
        $this->dispatch( 'ec-kb-board-changed' );
        $this->success( e( __( 'Card widgets saved.' ) ) );
    }

    /**
     * Renders the picker.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        if ( ! $this->open ) {
            return view( 'ecommerce-kanban::livewire.widget-picker', [ 'title' => '', 'catalog' => [], 'rows' => [], 'addOptions' => [], 'source' => '', 'preview' => null, 'previewInvalid' => false ] );
        }

        $this->updatedEntries();

        $list  = $this->storedList();
        $valid = ! Validator::make( [ 'card_widgets' => $list ], KanbanWidgetList::rules( 'card_widgets', $list ) )->fails();

        $board   = $this->board();
        $column  = null === $this->columnId ? null : $this->column( $this->columnId );
        $catalog = $this->catalog();
        $used    = array_column( $this->entries, 'key' );

        return view( 'ecommerce-kanban::livewire.widget-picker', [
            'title'      => null === $column ? __( 'Default card widgets' ) : __( 'Card widgets: :column', [ 'column' => $column->displayLabel() ] ),
            'catalog'    => $catalog,
            'rows'       => array_map( fn ( array $entry ): array => [
                'key'      => $entry['key'],
                'label'    => $catalog[ $entry['key'] ]['label'] ?? $entry['key'],
                'provider' => $catalog[ $entry['key'] ]['provider'] ?? null,
                'missing'  => ! isset( $catalog[ $entry['key'] ] ),
                'fields'   => $this->fields( $entry['key'] ),
            ], $this->entries ),
            'addOptions' => array_values( array_map(
                static fn ( array $widget ): array => [ 'id' => $widget['key'], 'name' => $widget['label'] ],
                array_filter( $catalog, static fn ( array $widget ): bool => ! in_array( $widget['key'], $used, true ) ),
            ) ),
            'source'         => $this->sourceText( $board, $column ),
            'preview'        => $valid ? $this->preview( $board, $column ) : null,
            'previewInvalid' => ! $valid,
        ] );
    }

    /**
     * The list as it is stored: a plain key for a widget without settings,
     * `{ key, config }` otherwise. For fields the picker renders, empty
     * values are dropped and numbers and booleans cast to what the schema
     * declares; any other setting is kept unchanged.
     *
     * @since 1.0.0
     *
     * @return array<int, mixed>
     */
    private function storedList(): array
    {
        $list = [];

        foreach ( $this->entries as $entry ) {
            $config   = [];
            $rendered = array_filter( $this->fields( $entry['key'] ), static fn ( array $field ): bool => $field['supported'] );
            $types    = array_column( $rendered, 'type', 'name' );

            foreach ( (array) ( $entry['config'] ?? [] ) as $name => $value ) {
                $type = $types[ $name ] ?? null;

                // Settings the picker has no control for are kept exactly as stored.
                if ( null === $type ) {
                    $config[ $name ] = $value;

                    continue;
                }

                if ( null === $value || '' === $value || ( 'boolean' !== $type && false === $value ) ) {
                    continue;
                }

                $config[ $name ] = match ( true ) {
                    'number' === $type && is_numeric( $value )  => str_contains( (string) $value, '.' ) ? (float) $value : (int) $value,
                    'boolean' === $type                         => (bool) $value,
                    default                                     => $value,
                };
            }

            $list[] = [] === $config ? $entry['key'] : [ 'key' => $entry['key'], 'config' => $config ];
        }

        return $list;
    }

    /**
     * Registered widgets by key: label (the store's override first) and
     * provider.
     *
     * @since 1.0.0
     *
     * @return array<string, array{key: string, label: string, provider: string}>
     */
    private function catalog(): array
    {
        $registry  = app( KanbanCardWidgetRegistry::class );
        $overrides = KanbanCardWidget::query()->pluck( 'label', 'key' );
        $catalog   = [];

        foreach ( $registry->keys() as $key ) {
            $meta  = $registry->meta( $key );
            $label = $overrides[ $key ] ?? $meta['label'] ?? $registry->get( $key )->label();

            $catalog[ $key ] = [
                'key'      => $key,
                'label'    => (string) ( $label instanceof Closure ? $label() : $label ),
                'provider' => (string) ( $meta['provided_by'] ?? 'ecommerce' ),
            ];
        }

        return $catalog;
    }

    /**
     * The config fields the picker renders for a widget. Fields of other
     * types are listed with `supported: false`.
     *
     * @since 1.0.0
     *
     * @param  string  $key  Widget key.
     *
     * @return array<int, array{name: string, type: string, label: string, help: string|null, required: bool, options: array<int, mixed>, supported: bool}>
     */
    private function fields( string $key ): array
    {
        $registry = app( KanbanCardWidgetRegistry::class );
        $widget   = $registry->has( $key ) ? $registry->get( $key ) : null;

        if ( ! $widget instanceof DescribesConfig ) {
            return [];
        }

        return array_map( static fn ( array $field ): array => [
            'name'      => $field['name'],
            'type'      => $field['type'],
            'label'     => $field['label'],
            'help'      => $field['help'],
            'required'  => $field['required'],
            'options'   => array_map(
                static fn ( mixed $option ): array => [ 'id' => (string) ( $option['value'] ?? '' ), 'name' => (string) ( $option['label'] ?? $option['value'] ?? '' ) ],
                array_filter( (array) ( $field['options'] ?? [] ), 'is_array' ),
            ),
            'supported' => in_array( $field['type'], self::FIELD_TYPES, true ) && ! $field['multiple'],
        ], ConfigSchema::normalize( $widget->configSchema() ) );
    }

    /**
     * Which list applies, in words.
     *
     * @since 1.0.0
     *
     * @param  KanbanBoard        $board   The board.
     * @param  KanbanColumn|null  $column  The column, or null for the board default.
     *
     * @return string
     */
    private function sourceText( KanbanBoard $board, ?KanbanColumn $column ): string
    {
        $boardHasList = [] !== KanbanWidgetList::normalize( $board->settings['card_widgets'] ?? [] );

        return match ( true ) {
            null === $column && [] === $this->entries => __( 'No board default: columns without their own widgets use the store default.' ),
            null === $column                          => __( 'Columns without their own widgets use this list.' ),
            [] !== $this->entries                     => __( 'This column uses its own widgets. Remove them all to use the board default.' ),
            $boardHasList                             => __( 'This column has no widgets of its own, so it uses the board default.' ),
            default                                   => __( 'Neither this column nor the board has widgets of its own, so the store default applies.' ),
        };
    }

    /**
     * A card from the board rendered with the list as it stands, or null
     * when the board has no cards.
     *
     * @since 1.0.0
     *
     * @param  KanbanBoard        $board   The board.
     * @param  KanbanColumn|null  $column  The column, or null for the board default.
     *
     * @return array<string, mixed>|null
     */
    private function preview( KanbanBoard $board, ?KanbanColumn $column ): ?array
    {
        $cards = OrderBoardAssignment::query()->active()->where( 'board_id', $board->id )->with( 'order' )->orderBy( 'position' )->orderBy( 'id' );
        $card  = null === $column ? null : ( clone $cards )->where( 'substatus_id', $column->substatus_id )->first();
        $card ??= $cards->first();

        if ( null === $card || null === $card->order ) {
            return null;
        }

        $list   = $this->storedList();
        $host   = $column ?? $board->columns()->firstWhere( 'substatus_id', $card->substatus_id ) ?? $board->columns()->first();
        $sample = ( $host ?? new KanbanColumn( [ 'board_id' => $board->id, 'substatus_id' => $card->substatus_id ] ) )->replicate();
        $owner  = $board->replicate();

        $sample->id = $host?->id;

        if ( null === $column ) {
            $owner->settings      = [ ...(array) ( $board->settings ?? [] ), 'card_widgets' => $list ];
            $sample->card_widgets = [];
        } else {
            $sample->card_widgets = $list;
        }

        $sample->setRelation( 'board', $owner );

        return [ ...app( CardPresenter::class )->present( $card, $sample ), 'actions' => [] ];
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
        return KanbanColumn::query()->where( 'board_id', $this->boardId )->with( [ 'substatus', 'board' ] )->find( $columnId );
    }
}
