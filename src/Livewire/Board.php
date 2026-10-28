<?php

/**
 * Board component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Livewire;

use ArtisanPack\LivewireUiComponents\Traits\Toast;
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Concerns\AuthorizesKanban;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Concerns\EditsColumns;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Concerns\FiltersCards;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Concerns\ManagesAssignments;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Concerns\MovesCards;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Concerns\SyncsBoard;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceKanbanLivewire\Queries\BoardsQuery;
use ArtisanPackUI\EcommerceKanbanLivewire\Queries\ColumnCardsQuery;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\Authorization;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\BoardUrls;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\CardActions;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\CardPresenter;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\Icons;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\LastBoard;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\Statuses;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * An engine board: its columns left to right, each with its cards, count,
 * WIP indicator, collapse toggle, and "Show more" paging (spec §7.2).
 *
 * The first render draws the column headers only. The page then calls
 * {@see self::loadBoard()} with the columns the user collapsed last time
 * (remembered per board in `localStorage`), so collapsed columns never load
 * their cards.
 *
 * Cards move by drag-and-drop (columns are connected
 * `livewire-drag-and-drop` contexts) or the "Move to…" dialog
 * ({@see MovesCards}), and the filter bar narrows them ({@see FiltersCards}).
 * `?card={order}` opens the card slide-out ({@see CardDetail}). Orders are
 * added and removed by hand ({@see ManagesAssignments}), "Edit board" mode
 * edits the columns ({@see EditsColumns}) and opens the widget picker
 * ({@see WidgetPicker}), and changes made elsewhere arrive over Echo or
 * polling ({@see SyncsBoard}).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
class Board extends Component
{
    use AuthorizesKanban;
    use EditsColumns;
    use FiltersCards;
    use ManagesAssignments;
    use MovesCards;
    use SyncsBoard;
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
     * Whether the cards have been loaded.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Locked]
    public bool $loaded = false;

    /**
     * Ids of the collapsed columns.
     *
     * @since 1.0.0
     *
     * @var array<int, int>
     */
    #[Locked]
    public array $collapsed = [];

    /**
     * How many cards each column shows, keyed by column id; columns not
     * listed show `cards.per_column`.
     *
     * @since 1.0.0
     *
     * @var array<int, int>
     */
    #[Locked]
    public array $limits = [];

    /**
     * Mounts the board and remembers it as the user's last board.
     *
     * @since 1.0.0
     *
     * @param  int|KanbanBoard|string  $board  The board or its id.
     *
     * @return void
     */
    public function mount( KanbanBoard|int|string $board ): void
    {
        // Abort explicitly: an embedded or tested component isn't behind the
        // exception handler that turns a missing model into a 404.
        if ( ! $board instanceof KanbanBoard ) {
            $board = is_numeric( $board ) ? KanbanBoard::query()->find( (int) $board ) : null;

            abort_if( null === $board, 404 );
        }

        $this->authorizeKanban( 'kanbanBoard.view', $board );

        $this->boardId = (int) $board->id;

        LastBoard::remember( auth()->user(), $board );
    }

    /**
     * Re-checks access on every update.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function hydrate(): void
    {
        $this->authorizeKanban( 'kanbanBoard.view', $this->board );
    }

    /**
     * The board.
     *
     * @since 1.0.0
     *
     * @return KanbanBoard
     */
    #[Computed]
    public function board(): KanbanBoard
    {
        return KanbanBoard::query()->findOrFail( $this->boardId );
    }

    /**
     * The board's columns, in `position` order, with their sub-statuses.
     *
     * @since 1.0.0
     *
     * @return Collection<int, KanbanColumn>
     */
    #[Computed]
    public function columns(): Collection
    {
        $columns = $this->board->columns()->with( 'substatus' )->get();

        foreach ( $columns as $column ) {
            $column->setRelation( 'board', $this->board );
        }

        return $columns;
    }

    /**
     * Loads the cards, leaving the given columns collapsed.
     *
     * @since 1.0.0
     *
     * @param  array<int, mixed>  $collapsed  Column ids the user collapsed before.
     *
     * @return void
     */
    public function loadBoard( array $collapsed = [] ): void
    {
        $this->collapsed = $this->ownColumnIds( $collapsed );
        $this->loaded    = true;

        $this->loadTagOptions();
    }

    /**
     * Collapses or expands a column.
     *
     * @since 1.0.0
     *
     * @param  int  $columnId  The column.
     *
     * @return void
     */
    public function toggleColumn( int $columnId ): void
    {
        if ( [] === $this->ownColumnIds( [ $columnId ] ) ) {
            return;
        }

        $this->collapsed = in_array( $columnId, $this->collapsed, true )
            ? array_values( array_diff( $this->collapsed, [ $columnId ] ) )
            : [ ...$this->collapsed, $columnId ];
    }

    /**
     * Shows another page of cards in a column.
     *
     * @since 1.0.0
     *
     * @param  int  $columnId  The column.
     *
     * @return void
     */
    public function showMore( int $columnId ): void
    {
        if ( [] === $this->ownColumnIds( [ $columnId ] ) ) {
            return;
        }

        $this->limits[ $columnId ] = $this->limitFor( $columnId ) + self::perColumn();
    }

    /**
     * Renders the board.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $board    = $this->board;
        $columns  = $this->columns;
        $user     = auth()->user();
        $filters  = $this->cardFilters();
        $filtered = ! $filters->isEmpty();
        $counts   = $this->loaded ? ColumnCardsQuery::counts( $board, $columns, $filters ) : [];
        $cards    = [];

        if ( $this->loaded ) {
            $presenter = app( CardPresenter::class );
            $actions   = new CardActions( $board, $user );

            foreach ( $columns as $column ) {
                if ( in_array( (int) $column->id, $this->collapsed, true ) || 0 === ( $counts[ $column->id ]['count'] ?? 0 ) ) {
                    continue;
                }

                foreach ( ColumnCardsQuery::cards( $column, $this->limitFor( (int) $column->id ), $filters ) as $card ) {
                    $cards[ $column->id ][] = [
                        ...$presenter->present( $card, $column ),
                        'actions' => $actions->for( $card->order, $column ),
                    ];
                }
            }
        }

        return view( 'ecommerce-kanban::livewire.board', [
            'board'        => $board,
            'columns'      => $columns->map( fn ( KanbanColumn $column ): array => $this->presentColumn( $column, $counts, $cards, $filtered ) )->all(),
            'boards'       => BoardsQuery::visibleTo( $user ),
            'pickerUrl'    => BoardUrls::picker(),
            'storageKey'   => 'ecommerce-kanban.collapsed.' . $board->id,
            'canMove'      => $this->cardsDraggable(),
            'canEdit'      => Authorization::allows( $user, 'kanbanBoard.update', $board ),
            'dragMessages' => self::dragMessages(),
            'realtime'     => $this->realtimeData(),
            ...$this->filterBarData(),
            ...$this->moveDialogData( $counts ),
            ...$this->confirmMoveData(),
            ...$this->assignmentData(),
            ...$this->editColumnsData( $counts ),
        ] );
    }

    /**
     * Whether the viewer may drag cards: they hold `kanbanCard.move` and the
     * board isn't in edit mode (where headers drag instead).
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public function cardsDraggable(): bool
    {
        return ! $this->editing && Authorization::allows( auth()->user(), 'kanbanCard.move', $this->board );
    }

    /**
     * Cards a column page holds.
     *
     * @since 1.0.0
     *
     * @return int
     */
    public static function perColumn(): int
    {
        return max( 1, (int) config( 'artisanpack.ecommerce-kanban-livewire.cards.per_column', 25 ) );
    }

    /**
     * The drag-and-drop announcement templates, translated (spec §12). They
     * use the library's `{item}`, `{container}`, `{position}`, and `{total}`
     * placeholders, which it fills in on the client.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    public static function dragMessages(): array
    {
        return [
            'instructions'     => __( 'Press Space or Enter to pick up the card. While holding it, use the arrow keys to move it within a column or between columns, Home or End to jump to the top or bottom, Space or Enter to drop, and Escape to cancel.' ),
            'grabbed'          => __( '{item} grabbed. Position {position} of {total} in {container}.' ),
            'moved'            => __( '{item} moved to position {position} of {total} in {container}.' ),
            'movedToContainer' => __( '{item} moved to {container}, position {position} of {total}.' ),
            'dropped'          => __( '{item} dropped in {container}, position {position} of {total}.' ),
            'cancelled'        => __( 'Move cancelled. {item} returned to {container}, position {position} of {total}.' ),
            'rejected'         => __( '{item} cannot be dropped in {container}.' ),
            'reverted'         => __( '{item} moved back to {container}, position {position} of {total}.' ),
            'noContainer'      => __( 'There is no other column in that direction.' ),
            'item'             => __( 'Card' ),
            'container'        => __( 'the column' ),
        ];
    }

    /**
     * A column's display data.
     *
     * @since 1.0.0
     *
     * @param  KanbanColumn                                   $column    The column.
     * @param  array<int, array{count: int, total: int}>      $counts    Filtered and total card counts by column id.
     * @param  array<int, array<int, array<string, mixed>>>  $cards     Presented cards by column id.
     * @param  bool                                           $filtered  Whether a filter is set.
     *
     * @return array<string, mixed>
     */
    private function presentColumn( KanbanColumn $column, array $counts, array $cards, bool $filtered ): array
    {
        $id    = (int) $column->id;
        $count = $counts[ $id ]['count'] ?? null;
        $total = $counts[ $id ]['total'] ?? null;
        $shown = $cards[ $id ] ?? [];

        return [
            'id'        => $id,
            'label'     => $column->displayLabel(),
            'color'     => self::safeHex( $column->color_override ?? $column->substatus?->color ),
            'icon'      => Icons::name( $column->icon_override ?? $column->substatus?->icon ),
            'wip_limit' => null !== $column->wip_limit && $column->wip_limit > 0 ? (int) $column->wip_limit : null,
            'count'     => $total,
            'matching'  => $filtered ? $count : null,
            'exit'      => in_array( $column->substatus?->system_status, Statuses::EXIT, true ),
            'collapsed' => in_array( $id, $this->collapsed, true ),
            'cards'     => $shown,
            'has_more'  => null !== $count && $count > count( $shown ) && ! in_array( $id, $this->collapsed, true ),
        ];
    }

    /**
     * How many cards a column shows now.
     *
     * @since 1.0.0
     *
     * @param  int  $columnId  The column.
     *
     * @return int
     */
    private function limitFor( int $columnId ): int
    {
        return max( self::perColumn(), (int) ( $this->limits[ $columnId ] ?? 0 ) );
    }

    /**
     * The ids in `$ids` that are columns on this board.
     *
     * @since 1.0.0
     *
     * @param  array<int, mixed>  $ids  Candidate column ids.
     *
     * @return array<int, int>
     */
    private function ownColumnIds( array $ids ): array
    {
        $own = $this->columns->pluck( 'id' )->map( static fn ( mixed $id ): int => (int) $id )->all();
        $ids = array_map( 'intval', array_filter( $ids, 'is_numeric' ) );

        return array_values( array_unique( array_intersect( $ids, $own ) ) );
    }

    /**
     * A `#rrggbb` colour, or null for anything else.
     *
     * @since 1.0.0
     *
     * @param  mixed  $color  Raw colour.
     *
     * @return string|null
     */
    private static function safeHex( mixed $color ): ?string
    {
        return is_string( $color ) && 1 === preg_match( '/^#[0-9A-Fa-f]{6}$/D', $color ) ? $color : null;
    }
}
