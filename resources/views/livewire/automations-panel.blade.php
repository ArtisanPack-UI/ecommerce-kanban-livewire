{{--
    The automations panel (spec §7.9): a right-hand drawer listing what runs
    when a card moves, for the whole board or for one column ("into" and
    "out of"). With `kanbanBoard.update`, each automation has an on / off
    toggle and Delete (confirmed, once per token), and "Add" / "Edit" link
    to the admin's automation forms when the admin is installed.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
<div
    data-ec-kb-automations-panel
    x-data="{
        focusFirst( selectors ) {
            setTimeout( () => {
                for ( const selector of selectors ) {
                    const element = this.$root.querySelector( selector );

                    if ( element ) {
                        return element.focus();
                    }
                }
            }, 100 );
        },
    }"
    x-on:ec-kb-focus-automations.window="focusFirst( $event.detail.selectors )"
>
    <x-artisanpack-drawer
        wire:model.live="open"
        right
        close-on-escape
        with-close-button
        :title="$title"
        class="w-11/12 max-w-lg"
    >
        @if ( $open )
            <div class="flex flex-col gap-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-base-content/70">{{ __( 'Automations run when a card moves between columns, like emailing the customer when an order reaches Shipped.' ) }}</p>

                    @if ( null !== $addUrl )
                        <x-artisanpack-button size="sm" variant="outline" icon="o-plus" :link="$addUrl" no-wire-navigate :label="__( 'Add automation' )" data-ec-kb-add-automation />
                    @endif
                </div>

                @foreach ( $groups as $group )
                    <section class="flex flex-col gap-2" aria-labelledby="ec-kb-automations-{{ $group['key'] }}" data-ec-kb-automation-group="{{ $group['key'] }}">
                        <h3 id="ec-kb-automations-{{ $group['key'] }}" tabindex="-1" data-ec-kb-automations-heading @class( [ 'font-semibold focus:outline-none', 'sr-only' => null === $group['heading'] ] )>{{ $group['heading'] ?? __( 'Automations' ) }}</h3>

                        @if ( [] === $group['rows'] )
                            <p class="text-sm text-base-content/70" data-ec-kb-automations-empty>{{ $group['empty'] }}</p>
                        @else
                            <ul class="flex list-none flex-col divide-y divide-base-300 rounded-box border border-base-300">
                                @foreach ( $group['rows'] as $row )
                                    @php( $name = __( ':trigger, :from to :to', [ 'trigger' => $row['trigger'], 'from' => $row['from'], 'to' => $row['to'] ] ) )
                                    <li class="flex flex-col gap-2 p-3" wire:key="automation-{{ $group['key'] }}-{{ $row['id'] }}" data-ec-kb-automation="{{ $row['id'] }}">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="font-semibold">{{ $row['trigger'] }}</p>
                                                <p class="flex flex-wrap items-center gap-1 text-sm">
                                                    <span>{{ $row['from'] }}</span>
                                                    <x-artisanpack-icon name="o-arrow-right" class="size-4" aria-hidden="true" />
                                                    <span class="sr-only">{{ __( 'to' ) }}</span>
                                                    <span>{{ $row['to'] }}</span>
                                                </p>
                                                <p class="text-xs text-base-content/70">{{ $row['conditions'] }}</p>
                                            </div>

                                            <x-artisanpack-badge :value="$row['active'] ? __( 'On' ) : __( 'Off' )" :class="$row['active'] ? 'badge-sm badge-success' : 'badge-sm badge-ghost'" data-ec-kb-automation-state />
                                        </div>

                                        @if ( $canUpdate )
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                {{-- The accessible name starts with the visible label (SC 2.5.3). --}}
                                                <x-artisanpack-toggle
                                                    id="ec-kb-automation-{{ $group['key'] }}-{{ $row['id'] }}-active"
                                                    :label="__( 'Active' )"
                                                    :checked="$row['active']"
                                                    wire:change="setActive( {{ $row['id'] }}, $event.target.checked )"
                                                    aria-label="{{ __( 'Active: :name', [ 'name' => $name ] ) }}"
                                                    data-ec-kb-automation-toggle
                                                />

                                                <div class="flex items-center gap-1">
                                                    @if ( null !== $row['edit_url'] )
                                                        <x-artisanpack-button
                                                            variant="ghost" size="sm"
                                                            icon="o-pencil"
                                                            :link="$row['edit_url']"
                                                            no-wire-navigate
                                                            :label="__( 'Edit' )"
                                                            aria-label="{{ __( 'Edit :name', [ 'name' => $name ] ) }}"
                                                            data-ec-kb-edit-automation
                                                        />
                                                    @endif
                                                    <x-artisanpack-button
                                                        variant="ghost" size="sm" color="error"
                                                        icon="o-trash"
                                                        wire:click="confirmDelete( {{ $row['id'] }} )"
                                                        :label="__( 'Delete' )"
                                                        aria-label="{{ __( 'Delete :name', [ 'name' => $name ] ) }}"
                                                        data-ec-kb-delete-automation
                                                    />
                                                </div>
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endforeach

                @if ( null !== $deleting )
                    {{-- Takes focus when it appears; Escape cancels it rather than closing the drawer. --}}
                    <div
                        class="flex flex-col gap-3 rounded-box border border-error p-3"
                        role="alertdialog"
                        aria-labelledby="ec-kb-delete-automation-title"
                        aria-describedby="ec-kb-delete-automation-description"
                        tabindex="-1"
                        x-init="$nextTick( () => $el.querySelector( '[data-ec-kb-cancel-delete-automation]' )?.focus() )"
                        x-on:keydown.escape.stop.prevent="$wire.cancelDelete()"
                        data-ec-kb-confirm-delete-automation
                    >
                        <p id="ec-kb-delete-automation-title" class="font-semibold">{{ __( 'Delete the automation :name?', [ 'name' => __( ':trigger, :from to :to', [ 'trigger' => $deleting['trigger'], 'from' => $deleting['from'], 'to' => $deleting['to'] ] ) ] ) }}</p>
                        <p id="ec-kb-delete-automation-description" class="text-sm">{{ __( 'It stops running right away. This cannot be undone.' ) }}</p>
                        <div class="flex justify-end gap-2">
                            <x-artisanpack-button variant="ghost" size="sm" :label="__( 'Cancel' )" wire:click="cancelDelete" data-ec-kb-cancel-delete-automation />
                            <x-artisanpack-button size="sm" color="error" :label="__( 'Delete automation' )" wire:click="deleteAutomation( {{ \Illuminate\Support\Js::from( $deleting['token'] ) }} )" spinner="deleteAutomation" data-ec-kb-delete-automation-button />
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </x-artisanpack-drawer>
</div>
