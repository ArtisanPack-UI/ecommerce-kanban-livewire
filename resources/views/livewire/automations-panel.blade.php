{{--
    The automations panel (spec §7.9): a right-hand drawer listing what runs
    when a card moves, for the whole board or for one column ("into" and
    "out of"). With `kanbanBoard.update`, each automation has an on / off
    toggle and Delete (confirmed, once per token), and "Add" / "Edit" link
    to the admin's automation forms when the admin is installed.

    Built on the design system: Drawer, List (bordered), Icon (tile), Badge,
    Toggle, Button, and the Alert markup as an `alertdialog` for the inline
    delete confirmation (the Alert component fixes its own role).

    A toggle's `checked` property is the user's, and morph only updates its
    `checked` attribute, so a refused or failed `setActive()` would leave the
    switch flipped while the badge says otherwise (F12). Each toggle resets
    itself to the rendered state once its request settles, and every toggle
    does when a Livewire request fails (`ec-kb-request-failed`). That
    rendered state is the saved one: a request fails before `setActive()`
    saves (authorization, the rate limit), and nothing after the save can
    fail it.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
<div
    data-ec-kb-automations-panel
    x-data="{
        focusFirst( selectors ) {
            setTimeout( () => {
                // The drawer renders its panel at the end of <body>, outside this element.
                const panel = document.querySelector( '.ec-kb-automations-drawer' ) ?? this.$root;

                for ( const selector of selectors ) {
                    const element = panel.querySelector( selector );

                    if ( element ) {
                        return element.focus();
                    }
                }
            }, 100 );
        },
        syncToggles() {
            document.querySelectorAll( '.ec-kb-automations-drawer .ec-kb-automation-controls input[type=checkbox]' ).forEach( ( toggle ) => {
                toggle.checked = toggle.defaultChecked;
            } );
        },
    }"
    x-on:ec-kb-focus-automations.window="focusFirst( $event.detail.selectors )"
    x-on:ec-kb-request-failed.window="syncToggles()"
>
    <x-artisanpack-drawer
        wire:model.live="open"
        side="end"
        class="ec-kb-automations-drawer"
        :title="$title"
    >
        @if ( $open )
            <div class="ec-kb-automations">
                <div class="ec-kb-automations-intro">
                    <p class="ec-kb-muted">{{ __( 'Automations run when a card moves between columns, like emailing the customer when an order reaches Shipped.' ) }}</p>

                    @if ( null !== $addUrl )
                        <x-artisanpack-button size="sm" variant="outline" color="neutral" leading-icon="plus" :href="$addUrl" data-ec-kb-add-automation>{{ __( 'Add automation' ) }}</x-artisanpack-button>
                    @endif
                </div>

                @foreach ( $groups as $group )
                    <section class="ec-kb-automations-group" aria-labelledby="ec-kb-automations-{{ $group['key'] }}" data-ec-kb-automation-group="{{ $group['key'] }}">
                        <h3 id="ec-kb-automations-{{ $group['key'] }}" tabindex="-1" data-ec-kb-automations-heading @class( [ 'ec-kb-eyebrow ec-kb-automations-heading', 'apui-sr-only' => null === $group['heading'] ] )>{{ $group['heading'] ?? __( 'Automations' ) }}</h3>

                        @if ( [] === $group['rows'] )
                            <p class="ec-kb-automations-empty" data-ec-kb-automations-empty>{{ $group['empty'] }}</p>
                        @else
                            <x-artisanpack-list variant="bordered" class="ec-kb-automations-list">
                                @foreach ( $group['rows'] as $row )
                                    @php( $name = __( ':trigger, :from to :to', [ 'trigger' => $row['trigger'], 'from' => $row['from'], 'to' => $row['to'] ] ) )
                                    <li class="ec-kb-automation" wire:key="automation-{{ $group['key'] }}-{{ $row['id'] }}" data-ec-kb-automation="{{ $row['id'] }}" @if ( ! $row['active'] ) data-ec-kb-automation-off @endif>
                                        <x-artisanpack-icon name="o-bolt" variant="tile" size="sm" :color="$row['active'] ? 'primary' : 'neutral'" class="ec-kb-automation-tile" />

                                        <div class="ec-kb-automation-summary">
                                            <p class="ec-kb-automation-trigger">{{ $row['trigger'] }}</p>
                                            <p class="ec-kb-automation-route">
                                                <span>{{ $row['from'] }}</span>
                                                <x-artisanpack-icon name="o-arrow-right" size="sm" :mirror="true" class="ec-kb-automation-arrow" />
                                                <span class="apui-sr-only">{{ __( 'to' ) }}</span>
                                                <span>{{ $row['to'] }}</span>
                                            </p>
                                            <p class="ec-kb-automation-conditions">{{ $row['conditions'] }}</p>
                                        </div>

                                        <x-artisanpack-badge variant="soft" size="sm" :color="$row['active'] ? 'success' : 'neutral'" class="ec-kb-automation-state" data-ec-kb-automation-state>{{ $row['active'] ? __( 'On' ) : __( 'Off' ) }}</x-artisanpack-badge>

                                        @if ( $canUpdate )
                                            <div class="ec-kb-automation-controls">
                                                {{-- The accessible name starts with the visible label (SC 2.5.3). --}}
                                                <x-artisanpack-toggle
                                                    id="ec-kb-automation-{{ $group['key'] }}-{{ $row['id'] }}-active"
                                                    size="sm"
                                                    :label="__( 'Active' )"
                                                    :checked="$row['active']"
                                                    x-on:change="const toggle = $event.target; $wire.setActive( {{ (int) $row['id'] }}, toggle.checked ).then( () => $nextTick( () => toggle.checked = toggle.defaultChecked ) )"
                                                    aria-label="{{ __( 'Active: :name', [ 'name' => $name ] ) }}"
                                                    data-ec-kb-automation-toggle
                                                />

                                                <div class="ec-kb-automation-actions">
                                                    @if ( null !== $row['edit_url'] )
                                                        <x-artisanpack-button
                                                            variant="ghost"
                                                            color="primary"
                                                            size="sm"
                                                            leading-icon="pencil"
                                                            :href="$row['edit_url']"
                                                            aria-label="{{ __( 'Edit :name', [ 'name' => $name ] ) }}"
                                                            data-ec-kb-edit-automation
                                                        >{{ __( 'Edit' ) }}</x-artisanpack-button>
                                                    @endif
                                                    <x-artisanpack-button
                                                        variant="ghost"
                                                        color="error"
                                                        size="sm"
                                                        leading-icon="trash"
                                                        wire:click="confirmDelete( {{ $row['id'] }} )"
                                                        aria-label="{{ __( 'Delete :name', [ 'name' => $name ] ) }}"
                                                        data-ec-kb-delete-automation
                                                    >{{ __( 'Delete' ) }}</x-artisanpack-button>
                                                </div>
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </x-artisanpack-list>
                        @endif
                    </section>
                @endforeach

                @if ( null !== $deleting )
                    {{-- The Alert markup as an alertdialog. Takes focus when it appears; Escape cancels it rather than closing the drawer (the drawer listens on the document's capture phase, so this listens on the window's, which runs first). --}}
                    <div
                        class="apui-alert ec-kb-delete-automation"
                        data-scope="alert"
                        data-part="root"
                        data-color="error"
                        data-variant="soft"
                        data-surface="default"
                        role="alertdialog"
                        aria-labelledby="ec-kb-delete-automation-title"
                        aria-describedby="ec-kb-delete-automation-description"
                        tabindex="-1"
                        x-init="$nextTick( () => $el.querySelector( '[data-ec-kb-cancel-delete-automation]' )?.focus() )"
                        x-on:keydown.escape.window.capture.stop.prevent="$wire.cancelDelete()"
                        data-ec-kb-confirm-delete-automation
                    >
                        <span data-scope="alert" data-part="icon" aria-hidden="true"><x-artisanpack-icon name="trash" /></span>
                        <p data-scope="alert" data-part="title" id="ec-kb-delete-automation-title">{{ __( 'Delete the automation :name?', [ 'name' => __( ':trigger, :from to :to', [ 'trigger' => $deleting['trigger'], 'from' => $deleting['from'], 'to' => $deleting['to'] ] ) ] ) }}</p>
                        <div data-scope="alert" data-part="description" id="ec-kb-delete-automation-description">{{ __( 'It stops running right away. This cannot be undone.' ) }}</div>
                        <div data-scope="alert" data-part="actions">
                            <x-artisanpack-button size="sm" color="error" wire:click="deleteAutomation( {{ \Illuminate\Support\Js::from( $deleting['token'] ) }} )" wire:loading.attr="disabled" wire:target="deleteAutomation" data-ec-kb-delete-automation-button>{{ __( 'Delete automation' ) }}</x-artisanpack-button>
                            <x-artisanpack-button variant="ghost" color="neutral" size="sm" wire:click="cancelDelete" data-ec-kb-cancel-delete-automation>{{ __( 'Cancel' ) }}</x-artisanpack-button>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </x-artisanpack-drawer>
</div>
