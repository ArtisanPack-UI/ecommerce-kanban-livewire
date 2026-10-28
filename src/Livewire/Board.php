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
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Concerns\RendersColumns;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Concerns\ShowsAutomationFeedback;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Concerns\SyncsBoard;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceKanbanLivewire\Queries\BoardsQuery;
use ArtisanPackUI\EcommerceKanbanLivewire\Queries\ColumnCardsQuery;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\Authorization;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\BoardToolbar;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\BoardUrls;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\Hooks;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\LastBoard;
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
 * {@see RendersColumns::loadBoard()} with the columns the user collapsed
 * last time (remembered per board in `localStorage`) and the columns on
 * screen, so collapsed columns never load their cards and the rest load as
 * they scroll into view. Moves, real-time events, and polls re-render only
 * the columns they change ({@see RendersColumns}).
 *
 * Cards move by drag-and-drop (columns are connected
 * `livewire-drag-and-drop` contexts) or the "Move to…" dialog
 * ({@see MovesCards}), and the filter bar narrows them ({@see FiltersCards}).
 * `?card={order}` opens the card slide-out ({@see CardDetail}). Orders are
 * added and removed by hand ({@see ManagesAssignments}), "Edit board" mode
 * edits the columns ({@see EditsColumns}) and opens the widget picker
 * ({@see WidgetPicker}), the automations panel ({@see AutomationsPanel})
 * lists what runs on a move, the mover is told which automations ran
 * ({@see ShowsAutomationFeedback}), and changes made elsewhere arrive over
 * Echo or polling ({@see SyncsBoard}). Satellites extend the board through
 * the hooks in {@see Hooks}.
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
    use RendersColumns;
    use ShowsAutomationFeedback;
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

        $expanding = in_array( $columnId, $this->collapsed, true );

        $this->collapsed = $expanding
            ? array_values( array_diff( $this->collapsed, [ $columnId ] ) )
            : [ ...$this->collapsed, $columnId ];

        if ( $expanding && ! in_array( $columnId, $this->opened, true ) ) {
            $this->opened[] = $columnId;
        }
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
        $board   = $this->board;
        $user    = auth()->user();
        $counts  = $this->loaded ? ColumnCardsQuery::counts( $board, $this->columns, $this->cardFilters() ) : [];
        $columns = $this->presentColumns( $this->columns, $counts );

        doAction( Hooks::BOARD_RENDERED, $board, $columns, $user );

        return view( 'ecommerce-kanban::livewire.board', [
            'board'        => $board,
            'columns'      => $columns,
            'toolbar'      => ( new BoardToolbar( $board, $user ) )->actions(),
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
}
