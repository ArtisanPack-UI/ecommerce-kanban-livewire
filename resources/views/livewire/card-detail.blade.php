{{--
    The card slide-out (spec §7.6): a right-hand drawer with a mini order
    view, "Remove from board" (handed to the board's confirmation), and "Add
    to another board" (spec §7.6.1). The drawer traps focus while open and closes on Escape; closing it
    hands focus back to the card (`ec-kb-focus-card`, handled by the board).

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
<div data-ec-kb-card-detail>
    <x-artisanpack-drawer
        wire:model.live="open"
        side="end"
        :title="null === $detail ? '' : __( 'Order :number', [ 'number' => $detail['number'] ] )"
    >
        @if ( null !== $detail )
            <p class="apui-sr-only" role="status" data-ec-kb-detail-announcer>{{ $announcement }}</p>

            <div class="flex flex-col gap-6" data-ec-kb-detail="{{ $detail['order_id'] }}">
                <div class="flex flex-wrap gap-2">
                    <x-artisanpack-badge data-ec-kb-detail-status>{{ $detail['status'] }}</x-artisanpack-badge>
                    @if ( null !== $detail['column'] )
                        <x-artisanpack-badge variant="outline" color="primary" data-ec-kb-detail-column>{{ $detail['column'] }}</x-artisanpack-badge>
                    @endif
                </div>

                <div class="flex flex-wrap gap-2">
                    @if ( $canMove && null !== $detail['column_id'] )
                        <x-artisanpack-button size="sm" leading-icon="o-arrows-right-left" wire:click="moveTo" data-ec-kb-detail-move>{{ __( 'Move to…' ) }}</x-artisanpack-button>
                    @endif
                    @if ( $canRemove )
                        <x-artisanpack-button variant="ghost" color="error" size="sm" leading-icon="x" wire:click="removeFromBoard" data-ec-kb-detail-remove>{{ __( 'Remove from board' ) }}</x-artisanpack-button>
                    @endif
                    @if ( null !== $detail['admin_url'] )
                        <x-artisanpack-button variant="ghost" color="neutral" size="sm" leading-icon="external-link" :href="$detail['admin_url']" data-ec-kb-detail-admin>{{ __( 'Open in admin' ) }}</x-artisanpack-button>
                    @endif
                </div>

                <section aria-labelledby="ec-kb-detail-customer">
                    <h3 id="ec-kb-detail-customer" class="mb-1 font-semibold">{{ __( 'Customer' ) }}</h3>
                    <p>{{ $detail['customer'] }}</p>
                    <p><a href="mailto:{{ $detail['email'] }}" class="ec-kb-text-link">{{ $detail['email'] }}</a></p>
                    @if ( null !== $detail['phone'] )
                        <p><span class="apui-sr-only">{{ __( 'Phone:' ) }}</span> {{ $detail['phone'] }}</p>
                    @endif
                    @if ( '' !== $detail['placed_at'] )
                        <p class="text-sm ec-kb-muted">{{ __( 'Placed :date', [ 'date' => $detail['placed_at'] ] ) }}</p>
                    @endif
                </section>

                <section aria-labelledby="ec-kb-detail-shipping">
                    <h3 id="ec-kb-detail-shipping" class="mb-1 font-semibold">{{ __( 'Shipping address' ) }}</h3>
                    @if ( [] === $detail['shipping'] )
                        <p class="ec-kb-muted">{{ __( 'No shipping address' ) }}</p>
                    @else
                        <address class="not-italic" data-ec-kb-detail-address>
                            @foreach ( $detail['shipping'] as $line )
                                {{ $line }}@if ( ! $loop->last )<br>@endif
                            @endforeach
                        </address>
                    @endif
                </section>

                <section aria-labelledby="ec-kb-detail-lines">
                    <h3 id="ec-kb-detail-lines" class="mb-1 font-semibold">{{ __( 'Items' ) }}</h3>
                    <table class="w-full text-sm" data-ec-kb-detail-lines>
                        <thead>
                            <tr>
                                <th scope="col" class="py-1 text-start">{{ __( 'Item' ) }}</th>
                                <th scope="col" class="py-1 text-start">{{ __( 'SKU' ) }}</th>
                                <th scope="col" class="py-1 text-end">{{ __( 'Qty' ) }}</th>
                                <th scope="col" class="py-1 text-end">{{ __( 'Total' ) }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ( $detail['lines'] as $line )
                                <tr wire:key="detail-line-{{ $loop->index }}">
                                    <td>
                                        {{ $line['name'] }}
                                        @if ( null !== $line['variant'] )
                                            <span class="block text-xs ec-kb-muted">{{ $line['variant'] }}</span>
                                        @endif
                                    </td>
                                    <td class="font-mono text-xs">{{ $line['sku'] ?? '—' }}</td>
                                    <td class="text-end">{{ $line['quantity'] }}</td>
                                    <td class="text-end">{{ $line['total'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <dl class="mt-2 grid grid-cols-[1fr_auto] gap-x-4 gap-y-1 text-sm" data-ec-kb-detail-totals>
                        @foreach ( $detail['totals'] as $total )
                            <dt @class( [ 'font-semibold' => $total['strong'] ?? false ] )>{{ $total['label'] }}</dt>
                            <dd @class( [ 'text-end', 'font-semibold' => $total['strong'] ?? false ] )>{{ $total['value'] }}</dd>
                        @endforeach
                    </dl>
                </section>

                <section aria-labelledby="ec-kb-detail-status-heading">
                    <h3 id="ec-kb-detail-status-heading" class="mb-1 font-semibold">{{ __( 'Payment and fulfillment' ) }}</h3>
                    <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                        <dt class="ec-kb-muted">{{ __( 'Payment' ) }}</dt>
                        <dd data-ec-kb-detail-payment>{{ $detail['payment'] }}</dd>
                        <dt class="ec-kb-muted">{{ __( 'Fulfillment' ) }}</dt>
                        <dd data-ec-kb-detail-fulfillment>{{ $detail['fulfillment'] }}</dd>
                    </dl>
                </section>

                <section aria-labelledby="ec-kb-detail-boards">
                    <h3 id="ec-kb-detail-boards" class="mb-1 font-semibold">{{ __( 'Other boards' ) }}</h3>
                    @if ( [] === $detail['boards'] )
                        <p class="text-sm ec-kb-muted">{{ __( 'This order is on no other board.' ) }}</p>
                    @else
                        <ul class="flex flex-col gap-1 text-sm" data-ec-kb-detail-other-boards>
                            @foreach ( $detail['boards'] as $assignment )
                                <li wire:key="detail-board-{{ $loop->index }}">{{ __( ':board: :column', $assignment ) }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if ( [] !== $addBoards )
                        <x-artisanpack-form wire:submit="addToBoard" class="mt-3" data-ec-kb-detail-add-board>
                            <x-artisanpack-select
                                id="ec-kb-detail-add-board"
                                wire:model.live="addBoardId"
                                :label="__( 'Add to another board' )"
                                :options="\ArtisanPackUI\EcommerceKanbanLivewire\Support\ControlOptions::from( $addBoards )"
                                :placeholder="__( 'Choose a board' )"
                                data-ec-kb-detail-add-board-select
                            />

                            @if ( [] !== $addColumns )
                                <x-artisanpack-select
                                    id="ec-kb-detail-add-column"
                                    placeholder=""
                                    wire:model="addBoardColumnId"
                                    :label="__( 'Column' )"
                                    :options="\ArtisanPackUI\EcommerceKanbanLivewire\Support\ControlOptions::from( $addColumns )"
                                    data-ec-kb-detail-add-column
                                />
                            @endif

                            <x-slot:actions>
                                <x-artisanpack-button type="submit" size="sm" leading-icon="plus" wire:loading.attr="disabled" wire:target="addToBoard" data-ec-kb-detail-add-board-submit>{{ __( 'Add to board' ) }}</x-artisanpack-button>
                            </x-slot:actions>
                        </x-artisanpack-form>
                    @endif
                </section>

                <section aria-labelledby="ec-kb-detail-timeline">
                    <h3 id="ec-kb-detail-timeline" class="mb-1 font-semibold">{{ __( 'Recent activity' ) }}</h3>
                    @if ( [] === $detail['timeline'] )
                        <p class="text-sm ec-kb-muted">{{ __( 'No activity yet.' ) }}</p>
                    @else
                        <ol class="flex flex-col gap-2 text-sm" data-ec-kb-detail-timeline>
                            @foreach ( $detail['timeline'] as $entry )
                                <li wire:key="detail-entry-{{ $entry['id'] }}">
                                    <p>{{ $entry['text'] }}</p>
                                    @if ( '' !== $entry['date'] )
                                        <time class="text-xs ec-kb-muted" datetime="{{ $entry['date_iso'] }}">{{ $entry['date'] }}</time>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            </div>
        @endif
    </x-artisanpack-drawer>
</div>
