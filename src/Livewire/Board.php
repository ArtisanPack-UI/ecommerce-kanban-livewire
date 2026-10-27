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

use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Concerns\AuthorizesKanban;
use ArtisanPackUI\EcommerceKanbanLivewire\Queries\BoardsQuery;
use ArtisanPackUI\EcommerceKanbanLivewire\Queries\ColumnCardsQuery;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\BoardUrls;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\CardActions;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\CardPresenter;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\Icons;
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
 * {@see self::loadBoard()} with the columns the user collapsed last time
 * (remembered per board in `localStorage`), so collapsed columns never load
 * their cards.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
class Board extends Component
{
    use AuthorizesKanban;

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
        $board   = $this->board;
        $columns = $this->columns;
        $user    = auth()->user();
        $counts  = $this->loaded ? ColumnCardsQuery::counts( $board, $columns ) : [];
        $cards   = [];

        if ( $this->loaded ) {
            $presenter = app( CardPresenter::class );
            $actions   = new CardActions( $board, $user );

            foreach ( $columns as $column ) {
                if ( in_array( (int) $column->id, $this->collapsed, true ) || 0 === ( $counts[ $column->id ] ?? 0 ) ) {
                    continue;
                }

                foreach ( ColumnCardsQuery::cards( $column, $this->limitFor( (int) $column->id ) ) as $card ) {
                    $cards[ $column->id ][] = [
                        ...$presenter->present( $card, $column ),
                        'actions' => $actions->for( $card->order, $column ),
                    ];
                }
            }
        }

        return view( 'ecommerce-kanban::livewire.board', [
            'board'      => $board,
            'columns'    => $columns->map( fn ( KanbanColumn $column ): array => $this->presentColumn( $column, $counts, $cards ) )->all(),
            'boards'     => BoardsQuery::visibleTo( $user ),
            'pickerUrl'  => BoardUrls::picker(),
            'storageKey' => 'ecommerce-kanban.collapsed.' . $board->id,
        ] );
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
     * A column's display data.
     *
     * @since 1.0.0
     *
     * @param  KanbanColumn                                     $column  The column.
     * @param  array<int, int>                                  $counts  Card counts by column id.
     * @param  array<int, array<int, array<string, mixed>>>  $cards   Presented cards by column id.
     *
     * @return array<string, mixed>
     */
    private function presentColumn( KanbanColumn $column, array $counts, array $cards ): array
    {
        $id    = (int) $column->id;
        $count = $counts[ $id ] ?? null;
        $shown = $cards[ $id ] ?? [];

        return [
            'id'        => $id,
            'label'     => $column->displayLabel(),
            'color'     => self::safeHex( $column->color_override ?? $column->substatus?->color ),
            'icon'      => Icons::name( $column->icon_override ?? $column->substatus?->icon ),
            'wip_limit' => null !== $column->wip_limit && $column->wip_limit > 0 ? (int) $column->wip_limit : null,
            'count'     => $count,
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
