{{--
    The card widget picker (spec §7.8): an ordered list of widgets with a
    settings form for each, a note on which list applies, and a preview of
    a card from the board. Widgets reorder by dragging (a
    livewire-drag-and-drop context) or with "Move up / down", the
    non-drag path.

    Built on the design system: Dialog (the note on which list applies is
    its description), Input, Select, Toggle and Textarea (sm), Button,
    Icon and the board's Card for the preview; the rows and the preview
    panel are token-only styles in resources/css/kanban.css. Save sits in
    the dialog footer and submits the form by its id.

    The list sets `role="list"` and each row `role="listitem"` and its
    label itself, so livewire-drag-and-drop keeps the list semantics instead
    of making the list a `group` and each row (which holds buttons and
    fields) a `role="button"`. The list gets the
    translated widget announcements (`Board::dragMessages( 'widgets' )`). A
    row's own error is a `role="alert"` the row's buttons point at, and a
    failed save moves focus to the first one. The preview card is rendered
    with `preview`, so it is no tab stop and no stand-in for the board's card.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
<div data-ec-kb-widget-picker>
    @php( $ecKbOptions = \ArtisanPackUI\EcommerceKanbanLivewire\Support\ControlOptions::class )
    <x-artisanpack-dialog wire:model="open" :title="$title" size="lg" class="ec-kb-widget-picker-dialog" data-ec-kb-widget-picker-dialog>
        <x-slot:description>
            <span data-ec-kb-widget-source>{{ $source }}</span>
        </x-slot:description>

        @if ( $open )
            <div class="ec-kb-widget-picker">
                <form id="ec-kb-widget-picker-form" x-on:submit.prevent="$wire.save().then( () => $nextTick( () => $el.querySelector( '[data-ec-kb-widget-error]' )?.focus() ) )" class="ec-kb-widget-picker-form">
                    @if ( [] === $rows )
                        <p class="ec-kb-widget-empty" data-ec-kb-widget-empty>{{ __( 'No widgets in this list.' ) }}</p>
                    @else
                        <ol
                            class="ec-kb-widget-list"
                            role="list"
                            aria-label="{{ __( 'Widgets, in the order cards show them' ) }}"
                            x-drag-context
                            data-drag-group="widgets"
                            data-drag-context-id="widgets"
                            data-drag-label="{{ __( 'Widgets' ) }}"
                            data-drag-messages="{{ json_encode( \ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Board::dragMessages( 'widgets' ) ) }}"
                            x-on:drag:end="$event.detail.toElement === $el && $event.detail.fromIndex !== $event.detail.toIndex && $wire.reorderWidgets( $event.detail.toOrderedIds.map( Number ) )"
                            data-ec-kb-widget-list
                        >
                            @foreach ( $rows as $index => $row )
                                @php( $ecKbRowErrorId = $errors->has( 'entries.' . $index ) || $errors->has( 'entries.' . $index . '.config' ) ? 'ec-kb-widget-' . $index . '-error' : null )
                                <li
                                    class="ec-kb-widget-row"
                                    role="listitem"
                                    aria-label="{{ $row['label'] }}"
                                    wire:key="widget-{{ $row['key'] }}"
                                    x-drag-item="{{ $index }}"
                                    data-drag-label="{{ $row['label'] }}"
                                    data-ec-kb-widget-row="{{ $row['key'] }}"
                                >
                                    <div class="ec-kb-widget-row-head">
                                        <x-artisanpack-icon name="grip-vertical" size="sm" class="ec-kb-widget-handle" />

                                        <div class="ec-kb-widget-row-text">
                                            <p class="ec-kb-widget-row-label">{{ $row['label'] }}</p>
                                            @if ( $row['missing'] )
                                                <p class="ec-kb-widget-missing" data-ec-kb-widget-missing>
                                                    <x-artisanpack-icon name="o-exclamation-triangle" size="sm" />
                                                    <span>{{ __( 'This widget is no longer installed and will be skipped.' ) }}</span>
                                                </p>
                                            @else
                                                <p class="ec-kb-widget-provider">{{ __( 'Provided by :provider', [ 'provider' => $row['provider'] ] ) }}</p>
                                            @endif
                                        </div>

                                        <div class="ec-kb-widget-row-tools">
                                            <x-artisanpack-button
                                                variant="ghost" color="neutral" size="sm" icon-only
                                                leading-icon="arrow-up"
                                                wire:click="moveWidget( {{ $index }}, -1 )"
                                                :disabled="$loop->first"
                                                aria-label="{{ __( 'Move :widget up', [ 'widget' => $row['label'] ] ) }}"
                                                data-ec-kb-widget-up
                                            />
                                            <x-artisanpack-button
                                                variant="ghost" color="neutral" size="sm" icon-only
                                                leading-icon="arrow-down"
                                                wire:click="moveWidget( {{ $index }}, 1 )"
                                                :disabled="$loop->last"
                                                aria-label="{{ __( 'Move :widget down', [ 'widget' => $row['label'] ] ) }}"
                                                data-ec-kb-widget-down
                                            />
                                            <x-artisanpack-button
                                                variant="ghost" color="error" size="sm" icon-only
                                                leading-icon="x"
                                                wire:click="removeWidget( {{ $index }} )"
                                                aria-label="{{ __( 'Remove :widget', [ 'widget' => $row['label'] ] ) }}"
                                                :aria-describedby="$ecKbRowErrorId"
                                                data-ec-kb-widget-remove
                                            />
                                        </div>
                                    </div>

                                    @if ( null !== $ecKbRowErrorId )
                                        <div id="{{ $ecKbRowErrorId }}" class="ec-kb-widget-errors" role="alert" tabindex="-1" data-ec-kb-widget-error>
                                            @error( 'entries.' . $index )
                                                <p class="ec-kb-widget-error">{{ $message }}</p>
                                            @enderror
                                            @error( 'entries.' . $index . '.config' )
                                                <p class="ec-kb-widget-error">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    @endif

                                    @if ( [] !== $row['fields'] )
                                        <div class="ec-kb-widget-config" data-ec-kb-widget-config="{{ $row['key'] }}">
                                            @foreach ( $row['fields'] as $field )
                                                @php( $model = 'entries.' . $index . '.config.' . $field['name'] )
                                                @php( $fieldId = 'ec-kb-widget-' . $index . '-' . $field['name'] )
                                                @if ( ! $field['supported'] )
                                                    <p class="ec-kb-widget-config-note">{{ __( ':field can be set in the admin.', [ 'field' => $field['label'] ] ) }}</p>
                                                @elseif ( 'boolean' === $field['type'] )
                                                    <x-artisanpack-toggle :id="$fieldId" size="sm" wire:model.live="{{ $model }}" :label="$field['label']" :description="$field['help']" />
                                                @elseif ( 'select' === $field['type'] )
                                                    <x-artisanpack-select :id="$fieldId" size="sm" wire:model.live="{{ $model }}" :label="$field['label']" :hint="$field['help']" :options="$ecKbOptions::from( $field['options'] )" :placeholder="__( 'Default' )" />
                                                @elseif ( 'textarea' === $field['type'] )
                                                    <x-artisanpack-textarea :id="$fieldId" wire:model.live.debounce.500ms="{{ $model }}" :label="$field['label']" :hint="$field['help']" class="ec-kb-widget-config-wide" />
                                                @elseif ( 'number' === $field['type'] )
                                                    <x-artisanpack-input :id="$fieldId" size="sm" type="number" wire:model.live.debounce.500ms="{{ $model }}" :label="$field['label']" :hint="$field['help']" />
                                                @else
                                                    <x-artisanpack-input :id="$fieldId" size="sm" wire:model.live.debounce.500ms="{{ $model }}" :label="$field['label']" :hint="$field['help']" />
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif

                    @if ( [] !== $addOptions )
                        <div class="ec-kb-widget-add">
                            <div class="ec-kb-widget-add-select">
                                <x-artisanpack-select
                                    id="ec-kb-widget-add"
                                    size="sm"
                                    wire:model="addKey"
                                    :label="__( 'Add a widget' )"
                                    :options="$ecKbOptions::from( $addOptions )"
                                    :placeholder="__( 'Choose a widget' )"
                                    data-ec-kb-widget-add
                                />
                            </div>
                            <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="plus" wire:click="addWidget" data-ec-kb-widget-add-button>{{ __( 'Add' ) }}</x-artisanpack-button>
                        </div>
                    @endif
                </form>

                <section class="ec-kb-widget-preview" aria-labelledby="ec-kb-widget-preview-title" data-ec-kb-widget-preview>
                    <h3 id="ec-kb-widget-preview-title" class="ec-kb-eyebrow">{{ __( 'Preview' ) }}</h3>
                    @if ( $previewInvalid )
                        <p class="ec-kb-widget-preview-note" data-ec-kb-widget-preview-invalid>{{ __( 'Fix the settings to see a preview.' ) }}</p>
                    @elseif ( null === $preview )
                        <p class="ec-kb-widget-preview-note">{{ __( 'Put an order on this board to preview its card.' ) }}</p>
                    @else
                        <x-artisanpack-ec-kb-card :card="$preview" preview />
                        <p class="ec-kb-widget-preview-note">{{ $previewSource }}</p>
                    @endif
                </section>
            </div>
        @endif

        <x-slot:footer>
            <x-artisanpack-button variant="outline" color="neutral" x-on:click="$wire.open = false">{{ __( 'Cancel' ) }}</x-artisanpack-button>
            <x-artisanpack-button type="submit" form="ec-kb-widget-picker-form" wire:loading.attr="disabled" wire:target="save" data-ec-kb-widget-save>{{ __( 'Save widgets' ) }}</x-artisanpack-button>
        </x-slot:footer>
    </x-artisanpack-dialog>
</div>
