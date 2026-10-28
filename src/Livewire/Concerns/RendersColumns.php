<?php

/**
 * Column rendering.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Concerns;

use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\EcommerceKanbanLivewire\Queries\ColumnCardsQuery;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\Automations;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\CardActions;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\CardPresenter;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\Icons;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\Statuses;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;

/**
 * Renders the board's columns, and just some of them (spec §12).
 *
 * Columns load lazily: {@see self::loadBoard()} loads the cards of the
 * columns on screen, and the others show skeletons until they scroll into
 * view and the page calls {@see self::loadColumns()}. Collapsed columns
 * never load cards. Every card query is fixed per column, so a column
 * costs the same however many cards the board holds.
 *
 * {@see self::columnsHtml()} renders single columns exactly as the board
 * would, for the page to morph in place: a move re-renders its two
 * columns, a real-time event the columns it names, and a poll the columns
 * whose cards changed ({@see SyncsBoard}).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
trait RendersColumns
{
    /**
     * Ids of the columns whose cards have loaded.
     *
     * @since 1.0.0
     *
     * @var array<int, int>
     */
    #[Locked]
    public array $opened = [];

    /**
     * Loads the cards, leaving the given columns collapsed. With `$visible`,
     * only those columns load now; the rest load as they scroll into view.
     *
     * @since 1.0.0
     *
     * @param  array<int, mixed>       $collapsed  Column ids the user collapsed before.
     * @param  array<int, mixed>|null  $visible    Column ids on screen; null for every column.
     *
     * @return void
     */
    public function loadBoard( array $collapsed = [], ?array $visible = null ): void
    {
        $all = $this->columns->pluck( 'id' )->map( static fn ( mixed $id ): int => (int) $id )->all();

        $this->collapsed = $this->ownColumnIds( $collapsed );
        $this->opened    = null === $visible ? $all : $this->ownColumnIds( $visible );
        $this->loaded    = true;

        $this->loadTagOptions();

        // Only the polling fallback reads the fingerprints; a board that
        // falls back later re-renders once on its first poll instead.
        if ( $this->realtimeData()['poll'] > 0 ) {
            $this->rememberSignatures();
        }
    }

    /**
     * Loads the cards of columns that scrolled into view, without rendering
     * the board. Returns each column's HTML keyed by column id for the page
     * to morph in, or `refresh: true` when the board should re-render
     * instead (in "Edit board" mode).
     *
     * @since 1.0.0
     *
     * @param  array<int, mixed>  $columnIds  The columns.
     *
     * @return array{refresh: bool, columns: array<int, string>}
     */
    #[Renderless]
    public function loadColumns( array $columnIds ): array
    {
        $ids = array_values( array_diff( $this->ownColumnIds( $columnIds ), $this->opened ) );

        if ( ! $this->loaded || [] === $ids ) {
            return [ 'refresh' => false, 'columns' => [] ];
        }

        $this->opened = [ ...$this->opened, ...$ids ];

        return $this->columnsHtml( $ids );
    }

    /**
     * Active automations firing into each column, keyed by column id, read
     * once per request.
     *
     * @since 1.0.0
     *
     * @return array<int, int>
     */
    #[Computed]
    public function automationCounts(): array
    {
        return Automations::countsInto( $this->board );
    }

    /**
     * Renders the given columns as the board would, keyed by column id, or
     * asks for a board render (`refresh: true`) in "Edit board" mode, where
     * columns depend on their neighbors.
     *
     * @since 1.0.0
     *
     * @param  array<int, int>  $columnIds  Columns on this board.
     *
     * @return array{refresh: bool, columns: array<int, string>}
     */
    protected function columnsHtml( array $columnIds ): array
    {
        if ( $this->editing ) {
            return [ 'refresh' => true, 'columns' => [] ];
        }

        $columns = $this->columns->filter( static fn ( KanbanColumn $column ): bool => in_array( (int) $column->id, $columnIds, true ) )->values();

        if ( $columns->isEmpty() ) {
            return [ 'refresh' => false, 'columns' => [] ];
        }

        $counts    = ColumnCardsQuery::counts( $this->board, $columns, $this->cardFilters() );
        $draggable = $this->cardsDraggable();
        $messages  = self::dragMessages();
        $html      = [];

        foreach ( $this->presentColumns( $columns, $counts ) as $column ) {
            $html[ $column['id'] ] = view( 'ecommerce-kanban::partials.column-item', [
                'column'       => $column,
                'draggable'    => $draggable,
                'dragMessages' => $messages,
            ] )->render();
        }

        return [ 'refresh' => false, 'columns' => $html ];
    }

    /**
     * The display data of `$columns`, loading cards only for columns that
     * are open and expanded.
     *
     * @since 1.0.0
     *
     * @param  Collection<int, KanbanColumn>                $columns  The columns.
     * @param  array<int, array{count: int, total: int}>  $counts   Filtered and total counts by column id.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function presentColumns( Collection $columns, array $counts ): array
    {
        $board       = $this->board;
        $filters     = $this->cardFilters();
        $filtered    = ! $filters->isEmpty();
        $automations = $this->automationCounts;
        $presenter   = app( CardPresenter::class );
        $actions     = new CardActions( $board, auth()->user() );
        $presented   = [];

        foreach ( $columns as $column ) {
            $id    = (int) $column->id;
            $open  = $this->columnOpen( $id );
            $cards = [];

            if ( $open && 0 < ( $counts[ $id ]['count'] ?? 0 ) ) {
                foreach ( ColumnCardsQuery::cards( $column, $this->limitFor( $id ), $filters ) as $card ) {
                    $cards[] = [
                        ...$presenter->present( $card, $column ),
                        'actions' => $actions->for( $card->order, $column ),
                    ];
                }
            }

            $presented[] = $this->presentColumn( $column, $counts, $cards, $filtered, $open, $automations[ $id ] ?? 0 );
        }

        return $presented;
    }

    /**
     * Whether a column shows its cards: the board has loaded, the column
     * has scrolled into view, and it isn't collapsed.
     *
     * @since 1.0.0
     *
     * @param  int  $columnId  The column.
     *
     * @return bool
     */
    protected function columnOpen( int $columnId ): bool
    {
        return $this->loaded && in_array( $columnId, $this->opened, true ) && ! in_array( $columnId, $this->collapsed, true );
    }

    /**
     * A column's display data.
     *
     * @since 1.0.0
     *
     * @param  KanbanColumn                               $column       The column.
     * @param  array<int, array{count: int, total: int}>  $counts       Filtered and total card counts by column id.
     * @param  array<int, array<string, mixed>>           $cards        The column's presented cards.
     * @param  bool                                       $filtered     Whether a filter is set.
     * @param  bool                                       $open         Whether the column shows its cards.
     * @param  int                                        $automations  Active automations firing into it.
     *
     * @return array<string, mixed>
     */
    private function presentColumn( KanbanColumn $column, array $counts, array $cards, bool $filtered, bool $open, int $automations ): array
    {
        $id    = (int) $column->id;
        $count = $counts[ $id ]['count'] ?? null;
        $total = $counts[ $id ]['total'] ?? null;

        return [
            'id'          => $id,
            'label'       => $column->displayLabel(),
            'color'       => self::safeHex( $column->color_override ?? $column->substatus?->color ),
            'icon'        => Icons::name( $column->icon_override ?? $column->substatus?->icon ),
            'wip_limit'   => null !== $column->wip_limit && $column->wip_limit > 0 ? (int) $column->wip_limit : null,
            'count'       => $total,
            'matching'    => $filtered ? $count : null,
            'exit'        => in_array( $column->substatus?->system_status, Statuses::EXIT, true ),
            'collapsed'   => in_array( $id, $this->collapsed, true ),
            'loaded'      => $open,
            'lazy'        => $this->loaded && ! $open,
            'automations' => $automations,
            'cards'       => $cards,
            'has_more'    => $open && null !== $count && $count > count( $cards ),
        ];
    }

    /**
     * Remembers each column's card fingerprint for the next poll.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function rememberSignatures(): void
    {
        $this->signatures = [
            'shape'   => $this->shapeSignature(),
            'columns' => ColumnCardsQuery::signatures( $this->board, $this->columns ),
        ];
    }

    /**
     * A fingerprint of the board's setup: the board's stored attributes,
     * each column's (in order) with its sub-status, and the automation
     * badges. When it changes, the whole board renders. Stored values are
     * used rather than `updated_at`, whose second precision can hide two
     * edits made in the same second.
     *
     * @since 1.0.0
     *
     * @return string
     */
    private function shapeSignature(): string
    {
        $columns = $this->columns->map( static fn ( KanbanColumn $column ): array => [
            'column'    => $column->getAttributes(),
            'substatus' => $column->substatus?->getAttributes(),
        ] )->all();

        return md5( (string) json_encode( [
            'board'       => $this->board->getAttributes(),
            'columns'     => $columns,
            'automations' => $this->automationCounts,
        ] ) );
    }

    /**
     * A `#rrggbb` color, or null for anything else.
     *
     * @since 1.0.0
     *
     * @param  mixed  $color  Raw color.
     *
     * @return string|null
     */
    private static function safeHex( mixed $color ): ?string
    {
        return is_string( $color ) && 1 === preg_match( '/^#[0-9A-Fa-f]{6}$/D', $color ) ? $color : null;
    }
}
