<?php

/**
 * Real-time board sync.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Concerns;

use ArtisanPackUI\Ecommerce\Broadcasting\KanbanBoardBroadcast;
use ArtisanPackUI\Ecommerce\Kanban\KanbanCardQuery;
use ArtisanPackUI\Ecommerce\Listeners\BroadcastKanbanCardMoved;
use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\CardActions;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\CardPresenter;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;

/**
 * Keeps an open board in step with changes made elsewhere (spec §7.7, D9).
 *
 * When `realtime.enabled` is on, the host has Laravel Echo, and the engine
 * broadcasts (`artisanpack.ecommerce.kanban.broadcast`), the page listens
 * on `private-ecommerce.kanban.board.{board}` for the engine's
 * `kanbanBoardEvent`. Card moves, reorders, additions, and removals go to
 * {@see self::refreshColumns()}, which re-renders just the columns they
 * name; a config change (or an event without columns) goes to
 * {@see self::boardEvent()}, which re-renders the board and re-reads the
 * columns. `card-updated` events go to {@see self::refreshCards()}, which
 * re-renders just those cards (and the slide-out, when it shows one of
 * them). Automation broadcasts become toasts for the actor
 * ({@see ShowsAutomationFeedback}).
 *
 * Without Echo the page calls {@see self::poll()} every `poll_interval`
 * seconds (0 turns polling off), pausing while a card is dragged or the tab
 * is hidden; a poll re-renders only the columns whose cards changed. The
 * client ignores the echo of the user's own in-flight move, and cancels a
 * drag when a remote change moves the dragged card.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
trait SyncsBoard
{
    /**
     * Most cards one refresh re-renders; more fall back to a board render.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public static int $maxCardRefresh = 25;

    /**
     * Fingerprints from the last load or poll: `shape` (the board's setup)
     * and `columns` (each column's cards, by column id).
     *
     * @since 1.0.0
     *
     * @var array{shape?: string, columns?: array<int, string>}
     */
    #[Locked]
    public array $signatures = [];

    /**
     * Re-renders the board after a remote change. Unknown event types render
     * nothing.
     *
     * @since 1.0.0
     *
     * @param  string  $type  The engine event type.
     *
     * @return void
     */
    public function boardEvent( string $type ): void
    {
        if ( ! in_array( $type, KanbanBoardBroadcast::TYPES, true ) ) {
            $this->skipRender();

            return;
        }

        $this->dispatch( 'ec-kb-board-refreshed' );

        if ( 'config-changed' === $type ) {
            $this->reloadColumns();
        }
    }

    /**
     * Re-renders the columns a remote card event names (its source and
     * target), without rendering the board. Returns each column's HTML keyed
     * by column id for the page to morph in, or `refresh: true` when the
     * board should re-render instead: a named column isn't on the board
     * (it was just added) or the board is in "Edit board" mode. Tells the
     * slide-out, which re-renders when it is open.
     *
     * @since 1.0.0
     *
     * @param  array<int, mixed>  $columnIds  The columns the event names.
     *
     * @return array{refresh: bool, columns: array<int, string>}
     */
    #[Renderless]
    public function refreshColumns( array $columnIds ): array
    {
        $this->dispatch( 'ec-kb-board-refreshed' );

        $wanted = array_values( array_unique( array_map( 'intval', array_filter( $columnIds, 'is_numeric' ) ) ) );
        $own    = $this->ownColumnIds( $wanted );

        if ( ! $this->loaded || [] === $wanted ) {
            return [ 'refresh' => false, 'columns' => [] ];
        }

        if ( count( $own ) !== count( $wanted ) ) {
            return [ 'refresh' => true, 'columns' => [] ];
        }

        return $this->columnsHtml( $own );
    }

    /**
     * The polling fallback. Compares each column's cards with the last poll
     * (one query) and returns only the columns that changed, keyed by column
     * id, for the page to morph in; `refresh: true` when the board's setup
     * changed (columns added, removed, reordered, or edited) and the board
     * should re-render. Nothing renders when nothing changed. Tells the
     * slide-out, which re-renders when it is open.
     *
     * @since 1.0.0
     *
     * @return array{refresh: bool, columns: array<int, string>}
     */
    #[Renderless]
    public function poll(): array
    {
        $this->dispatch( 'ec-kb-board-refreshed' );

        if ( ! $this->loaded ) {
            return [ 'refresh' => false, 'columns' => [] ];
        }

        $previous = $this->signatures;

        $this->reloadColumns();
        $this->rememberSignatures();

        if ( ( $previous['shape'] ?? null ) !== $this->signatures['shape'] ) {
            return [ 'refresh' => true, 'columns' => [] ];
        }

        $changed = array_keys( array_diff_assoc( $this->signatures['columns'] ?? [], $previous['columns'] ?? [] ) );

        return [] === $changed ? [ 'refresh' => false, 'columns' => [] ] : $this->columnsHtml( $changed );
    }

    /**
     * Re-renders the cards behind `card-updated` events, without
     * re-rendering the board. Returns each card's HTML keyed by order id for
     * the client to morph in, or `refresh: true` when the board should
     * re-render instead (a filter is set, so the change may hide or show the
     * card, or there are too many cards). Cards that aren't showing are
     * skipped. Tells the slide-out, so it refreshes when it shows one.
     *
     * @since 1.0.0
     *
     * @param  array<int, mixed>  $orderIds  The changed cards' orders.
     *
     * @return array{refresh: bool, cards: array<int, string>}
     */
    #[Renderless]
    public function refreshCards( array $orderIds ): array
    {
        $ids = array_slice( array_values( array_unique( array_map( 'intval', array_filter( $orderIds, 'is_numeric' ) ) ) ), 0, static::$maxCardRefresh + 1 );

        foreach ( $ids as $id ) {
            $this->dispatch( 'ec-kb-card-updated', orderId: $id );
        }

        if ( ! $this->loaded || [] === $ids ) {
            return [ 'refresh' => false, 'cards' => [] ];
        }

        if ( ! $this->cardFilters()->isEmpty() || count( $ids ) > static::$maxCardRefresh ) {
            return [ 'refresh' => true, 'cards' => [] ];
        }

        // The board's own card query, so `ap.ecommerce.kanban.cardQuery`
        // restrictions apply here as they do to a full render.
        $query = KanbanCardQuery::for( $this->board )->filter( $this->cardFilters() )->builder();
        $cards = $query->whereIn( $query->qualifyColumn( 'order_id' ), $ids )->get();

        $presenter = app( CardPresenter::class );
        $actions   = new CardActions( $this->board, auth()->user() );
        $draggable = $this->cardsDraggable();
        $html      = [];

        foreach ( $cards as $card ) {
            /** @var KanbanColumn|null $column */
            $column = $this->columns->firstWhere( 'substatus_id', (int) $card->substatus_id );

            if ( null === $column || null === $card->order || in_array( (int) $column->id, $this->collapsed, true ) ) {
                continue;
            }

            $html[ (int) $card->order_id ] = view( 'ecommerce-kanban::partials.card-item', [
                'card'      => [ ...$presenter->present( $card, $column ), 'actions' => $actions->for( $card->order, $column ) ],
                'draggable' => $draggable,
            ] )->render();
        }

        return [ 'refresh' => false, 'cards' => $html ];
    }

    /**
     * Re-renders the board when another component changed its setup (the
     * widget picker saved).
     *
     * @since 1.0.0
     *
     * @return void
     */
    #[On( 'ec-kb-board-changed' )]
    public function boardChanged(): void
    {
        unset( $this->board, $this->columns, $this->automationCounts );
    }

    /**
     * The real-time settings the page needs.
     *
     * @since 1.0.0
     *
     * @return array{echo: bool, channel: string, event: string, poll: int, user: int|null}
     */
    protected function realtimeData(): array
    {
        $enabled = (bool) config( 'artisanpack.ecommerce-kanban-livewire.realtime.enabled', true );
        $id      = auth()->id();

        return [
            'echo'    => $enabled && (bool) config( 'artisanpack.ecommerce.kanban.broadcast', false ),
            'channel' => BroadcastKanbanCardMoved::channel( $this->boardId ),
            'event'   => '.' . KanbanBoardBroadcast::NAME,
            'poll'    => $enabled ? max( 0, (int) config( 'artisanpack.ecommerce-kanban-livewire.realtime.poll_interval', 30 ) ) : 0,
            'user'    => is_numeric( $id ) ? (int) $id : null,
        ];
    }

    /**
     * Re-reads the board and its columns, and drops the collapsed and paging
     * state of columns that are gone.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function reloadColumns(): void
    {
        unset( $this->board, $this->columns, $this->automationCounts );

        $own             = $this->columns->pluck( 'id' )->map( static fn ( mixed $id ): int => (int) $id )->all();
        $this->collapsed = array_values( array_intersect( $this->collapsed, $own ) );
        $this->opened    = array_values( array_intersect( $this->opened, $own ) );
        $this->limits    = array_intersect_key( $this->limits, array_flip( $own ) );
    }
}
