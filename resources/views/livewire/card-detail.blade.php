{{--
    The card slide-out (spec §7.6): a right-hand drawer with a mini order
    view, "Remove from board" (handed to the board's confirmation), and "Add
    to another board" (spec §7.6.1). The drawer traps focus while open and
    closes on Escape; closing it hands focus back to the card
    (`ec-kb-focus-card`, handled by the board).

    Built on the design system: Drawer, Badge, Button, Table (compact), List
    (bordered), Select (sm) and Timeline; the layout and the meta list are
    token-only styles in resources/css/kanban.css. Each section is labelled
    by its heading.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
<div data-ec-kb-card-detail>
    @php( $ecKbOptions = \ArtisanPackUI\EcommerceKanbanLivewire\Support\ControlOptions::class )
    <x-artisanpack-drawer
        wire:model.live="open"
        side="end"
        class="ec-kb-detail-drawer"
        :classes="[ 'description' => 'ec-kb-detail-badges' ]"
        :title="null === $detail ? '' : __( 'Order :number', [ 'number' => $detail['number'] ] )"
    >
        <x-slot:description>
            @if ( null !== $detail )
                <x-artisanpack-badge variant="soft" color="neutral" data-ec-kb-detail-status>{{ $detail['status'] }}</x-artisanpack-badge>
                @if ( null !== $detail['column'] )
                    <x-artisanpack-badge variant="outline" color="primary" data-ec-kb-detail-column><span class="apui-sr-only">{{ __( 'Column:' ) }}</span> {{ $detail['column'] }}</x-artisanpack-badge>
                @endif
            @endif
        </x-slot:description>

        @if ( null !== $detail )
            <p class="apui-sr-only" role="status" data-ec-kb-detail-announcer>{{ $announcement }}</p>

            <div class="ec-kb-detail" data-ec-kb-detail="{{ $detail['order_id'] }}">
                @if ( ( $canMove && null !== $detail['column_id'] ) || $canRemove || null !== $detail['admin_url'] )
                    <div class="ec-kb-detail-actions">
                        @if ( $canMove && null !== $detail['column_id'] )
                            <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="o-arrows-right-left" wire:click="moveTo" data-ec-kb-detail-move>{{ __( 'Move to…' ) }}</x-artisanpack-button>
                        @endif
                        @if ( null !== $detail['admin_url'] )
                            <x-artisanpack-button variant="ghost" color="primary" size="sm" leading-icon="external-link" :href="$detail['admin_url']" data-ec-kb-detail-admin>{{ __( 'Open in admin' ) }}</x-artisanpack-button>
                        @endif
                        @if ( $canRemove )
                            <x-artisanpack-button variant="ghost" color="error" size="sm" leading-icon="x" class="ec-kb-detail-remove" wire:click="removeFromBoard" data-ec-kb-detail-remove>{{ __( 'Remove from board' ) }}</x-artisanpack-button>
                        @endif
                    </div>
                @endif

                <div class="ec-kb-detail-pair">
                    <section aria-labelledby="ec-kb-detail-customer" class="ec-kb-detail-section">
                        <h3 id="ec-kb-detail-customer" class="ec-kb-eyebrow">{{ __( 'Customer' ) }}</h3>
                        <p class="ec-kb-detail-strong">{{ $detail['customer'] }}</p>
                        @if ( false !== filter_var( $detail['email'], FILTER_VALIDATE_EMAIL ) )
                            <p><a href="mailto:{{ rawurlencode( $detail['email'] ) }}" class="ec-kb-text-link" data-ec-kb-detail-email>{{ $detail['email'] }}</a></p>
                        @elseif ( '' !== $detail['email'] )
                            <p data-ec-kb-detail-email>{{ $detail['email'] }}</p>
                        @endif
                        @if ( null !== $detail['phone'] )
                            <p><span class="apui-sr-only">{{ __( 'Phone:' ) }}</span> {{ $detail['phone'] }}</p>
                        @endif
                        @if ( '' !== $detail['placed_at'] )
                            <p class="ec-kb-muted ec-kb-detail-small">{{ __( 'Placed :date', [ 'date' => $detail['placed_at'] ] ) }}</p>
                        @endif
                    </section>

                    <section aria-labelledby="ec-kb-detail-shipping" class="ec-kb-detail-section">
                        <h3 id="ec-kb-detail-shipping" class="ec-kb-eyebrow">{{ __( 'Shipping address' ) }}</h3>
                        @if ( [] === $detail['shipping'] )
                            <p class="ec-kb-muted" data-ec-kb-detail-no-address>{{ __( 'No shipping address' ) }}</p>
                        @else
                            <address class="ec-kb-detail-address" data-ec-kb-detail-address>
                                @foreach ( $detail['shipping'] as $line )
                                    {{ $line }}@if ( ! $loop->last )<br>@endif
                                @endforeach
                            </address>
                        @endif
                    </section>
                </div>

                <section aria-labelledby="ec-kb-detail-lines" class="ec-kb-detail-section">
                    <h3 id="ec-kb-detail-lines" class="ec-kb-eyebrow">{{ __( 'Items' ) }}</h3>
                    <x-artisanpack-table
                        density="compact"
                        :label="__( 'Items' )"
                        :sticky-header="false"
                        :headers="[
                            [ 'key' => 'name', 'label' => __( 'Item' ) ],
                            [ 'key' => 'sku', 'label' => __( 'SKU' ) ],
                            [ 'key' => 'quantity', 'label' => __( 'Qty' ), 'numeric' => true ],
                            [ 'key' => 'total', 'label' => __( 'Total' ), 'numeric' => true ],
                        ]"
                        :rows="collect( $detail['lines'] )->values()->map( fn ( array $line, int $index ): array => [ 'id' => $index ] + $line )->all()"
                        class="ec-kb-detail-lines"
                        data-ec-kb-detail-lines
                    >
                        @scope( 'cell_name', $line )
                            {{ $line['name'] }}
                            @if ( null !== $line['variant'] )
                                <span class="ec-kb-detail-variant">{{ $line['variant'] }}</span>
                            @endif
                        @endscope
                        @scope( 'cell_sku', $line )
                            <span class="ec-kb-mono">{{ $line['sku'] ?? '—' }}</span>
                        @endscope
                    </x-artisanpack-table>

                    <dl class="ec-kb-meta" data-ec-kb-detail-totals>
                        @foreach ( $detail['totals'] as $total )
                            <div @class( [ 'ec-kb-meta-row', 'ec-kb-meta-strong' => $total['strong'] ?? false ] )>
                                <dt>{{ $total['label'] }}</dt>
                                <dd>{{ $total['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>

                <section aria-labelledby="ec-kb-detail-status-heading" class="ec-kb-detail-section">
                    <h3 id="ec-kb-detail-status-heading" class="ec-kb-eyebrow">{{ __( 'Payment and fulfillment' ) }}</h3>
                    <dl class="ec-kb-detail-states">
                        @foreach ( [ 'payment' => __( 'Payment' ), 'fulfillment' => __( 'Fulfillment' ) ] as $stateKey => $stateLabel )
                            @php( $stateTone = \ArtisanPackUI\EcommerceKanbanLivewire\Support\WidgetViews::tone( $detail[ $stateKey . '_tone' ] ) )
                            <dt class="ec-kb-muted">{{ $stateLabel }}</dt>
                            <dd><x-artisanpack-badge variant="soft" size="sm" :color="$stateTone['color']" :leading-icon="$stateTone['icon']" :data-ec-kb-detail-state="$stateKey" :data-ec-kb-tone="$detail[ $stateKey . '_tone' ]">{{ $detail[ $stateKey ] }}</x-artisanpack-badge></dd>
                        @endforeach
                    </dl>
                </section>

                <section aria-labelledby="ec-kb-detail-boards" class="ec-kb-detail-section">
                    <h3 id="ec-kb-detail-boards" class="ec-kb-eyebrow">{{ __( 'Other boards' ) }}</h3>
                    @if ( [] === $detail['boards'] )
                        <p class="ec-kb-muted ec-kb-detail-small" data-ec-kb-detail-no-boards>{{ __( 'This order is on no other board.' ) }}</p>
                    @else
                        <x-artisanpack-list variant="bordered" class="ec-kb-detail-boards-list" data-ec-kb-detail-other-boards>
                            @foreach ( $detail['boards'] as $assignment )
                                <x-artisanpack-list-item wire:key="detail-board-{{ $loop->index }}" :title="$assignment['board']">
                                    <x-slot:description><span class="apui-sr-only">{{ __( 'Column:' ) }}</span> {{ $assignment['column'] }}</x-slot:description>
                                </x-artisanpack-list-item>
                            @endforeach
                        </x-artisanpack-list>
                    @endif

                    @if ( [] !== $addBoards )
                        <form wire:submit="addToBoard" class="ec-kb-detail-add" data-ec-kb-detail-add-board>
                            <div class="ec-kb-detail-add-board">
                                <x-artisanpack-select
                                    id="ec-kb-detail-add-board"
                                    size="sm"
                                    wire:model.live="addBoardId"
                                    :label="__( 'Add to another board' )"
                                    :options="$ecKbOptions::from( $addBoards )"
                                    :placeholder="__( 'Choose a board' )"
                                    data-ec-kb-detail-add-board-select
                                />
                            </div>

                            @if ( [] !== $addColumns )
                                <div class="ec-kb-detail-add-column">
                                    <x-artisanpack-select
                                        id="ec-kb-detail-add-column"
                                        size="sm"
                                        placeholder=""
                                        wire:model="addBoardColumnId"
                                        :label="__( 'Column' )"
                                        :options="$ecKbOptions::from( $addColumns )"
                                        data-ec-kb-detail-add-column
                                    />
                                </div>
                            @endif

                            <x-artisanpack-button type="submit" variant="outline" color="neutral" size="sm" leading-icon="plus" wire:loading.attr="disabled" wire:target="addToBoard" data-ec-kb-detail-add-board-submit>{{ __( 'Add to board' ) }}</x-artisanpack-button>
                        </form>
                    @endif
                </section>

                <section aria-labelledby="ec-kb-detail-timeline" class="ec-kb-detail-section">
                    <h3 id="ec-kb-detail-timeline" class="ec-kb-eyebrow">{{ __( 'Recent activity' ) }}</h3>
                    @if ( [] === $detail['timeline'] )
                        <p class="ec-kb-muted ec-kb-detail-small" data-ec-kb-detail-no-activity>{{ __( 'No activity yet.' ) }}</p>
                    @else
                        <x-artisanpack-timeline class="ec-kb-detail-timeline" data-ec-kb-detail-timeline>
                            @foreach ( $detail['timeline'] as $entry )
                                <x-artisanpack-timeline-item
                                    wire:key="detail-entry-{{ $entry['id'] }}"
                                    :title="$entry['text']"
                                    :time="'' !== $entry['date'] ? $entry['date'] : null"
                                    :datetime="$entry['date_iso']"
                                    :icon="$entry['icon']"
                                    :color="$entry['color']"
                                />
                            @endforeach
                        </x-artisanpack-timeline>
                    @endif
                </section>
            </div>
        @endif
    </x-artisanpack-drawer>
</div>
