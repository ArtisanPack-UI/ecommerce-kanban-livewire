{{--
    The card widget picker (spec §7.8): an ordered list of widgets with a
    settings form for each, a note on which list applies, and a preview of
    a card from the board. Widgets reorder by dragging (a
    livewire-drag-and-drop context) or with "Move up / down".

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
<div data-ec-kb-widget-picker>
    <x-artisanpack-dialog wire:model="open" :title="$title" size="lg" data-ec-kb-widget-picker-dialog>
        @if ( $open )
            <div class="grid gap-6 md:grid-cols-[1fr_18rem]">
                <x-artisanpack-form wire:submit="save">
                    <p class="text-sm ec-kb-muted" data-ec-kb-widget-source>{{ $source }}</p>

                    @if ( [] === $rows )
                        <p class="text-sm" data-ec-kb-widget-empty>{{ __( 'No widgets in this list.' ) }}</p>
                    @else
                        <ol
                            class="flex flex-col gap-3"
                            aria-label="{{ __( 'Widgets, in the order cards show them' ) }}"
                            x-drag-context
                            data-drag-group="widgets"
                            data-drag-context-id="widgets"
                            data-drag-label="{{ __( 'Widgets' ) }}"
                            x-on:drag:end="$event.detail.toElement === $el && $event.detail.fromIndex !== $event.detail.toIndex && $wire.reorderWidgets( $event.detail.toOrderedIds.map( Number ) )"
                            data-ec-kb-widget-list
                        >
                            @foreach ( $rows as $index => $row )
                                <li
                                    class="ec-kb-outline-list p-3"
                                    wire:key="widget-{{ $row['key'] }}"
                                    x-drag-item="{{ $index }}"
                                    data-drag-label="{{ $row['label'] }}"
                                    data-ec-kb-widget-row="{{ $row['key'] }}"
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="font-semibold">{{ $row['label'] }}</p>
                                            <p class="text-xs ec-kb-muted">
                                                @if ( $row['missing'] )
                                                    {{ __( 'This widget is no longer installed and will be skipped.' ) }}
                                                @else
                                                    {{ __( 'Provided by :provider', [ 'provider' => $row['provider'] ] ) }}
                                                @endif
                                            </p>
                                        </div>

                                        <div class="flex items-center gap-1">
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
                                                data-ec-kb-widget-remove
                                            />
                                        </div>
                                    </div>

                                    @error( 'entries.' . $index )
                                        <p class="ec-kb-error-text mt-1 text-sm">{{ $message }}</p>
                                    @enderror
                                    @error( 'entries.' . $index . '.config' )
                                        <p class="ec-kb-error-text mt-1 text-sm">{{ $message }}</p>
                                    @enderror

                                    @if ( [] !== $row['fields'] )
                                        <div class="mt-3 grid gap-3" data-ec-kb-widget-config="{{ $row['key'] }}">
                                            @foreach ( $row['fields'] as $field )
                                                @php( $model = 'entries.' . $index . '.config.' . $field['name'] )
                                                @php( $fieldId = 'ec-kb-widget-' . $index . '-' . $field['name'] )
                                                @if ( ! $field['supported'] )
                                                    <p class="text-sm ec-kb-muted">{{ __( ':field can be set in the admin.', [ 'field' => $field['label'] ] ) }}</p>
                                                @elseif ( 'boolean' === $field['type'] )
                                                    <x-artisanpack-toggle :id="$fieldId" wire:model.live="{{ $model }}" :label="$field['label']" :hint="$field['help']" />
                                                @elseif ( 'select' === $field['type'] )
                                                    <x-artisanpack-select :id="$fieldId" wire:model.live="{{ $model }}" :label="$field['label']" :hint="$field['help']" :options="\ArtisanPackUI\EcommerceKanbanLivewire\Support\ControlOptions::from( $field['options'] )" :placeholder="__( 'Default' )" />
                                                @elseif ( 'textarea' === $field['type'] )
                                                    <x-artisanpack-textarea :id="$fieldId" wire:model.live.debounce.500ms="{{ $model }}" :label="$field['label']" :hint="$field['help']" />
                                                @elseif ( 'number' === $field['type'] )
                                                    <x-artisanpack-input :id="$fieldId" type="number" wire:model.live.debounce.500ms="{{ $model }}" :label="$field['label']" :hint="$field['help']" />
                                                @else
                                                    <x-artisanpack-input :id="$fieldId" wire:model.live.debounce.500ms="{{ $model }}" :label="$field['label']" :hint="$field['help']" />
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif

                    @if ( [] !== $addOptions )
                        <div class="flex items-end gap-2">
                            <div class="flex-1">
                                <x-artisanpack-select
                                    id="ec-kb-widget-add"
                                    wire:model="addKey"
                                    :label="__( 'Add a widget' )"
                                    :options="\ArtisanPackUI\EcommerceKanbanLivewire\Support\ControlOptions::from( $addOptions )"
                                    :placeholder="__( 'Choose a widget' )"
                                    data-ec-kb-widget-add
                                />
                            </div>
                            <x-artisanpack-button size="sm" leading-icon="plus" wire:click="addWidget" data-ec-kb-widget-add-button>{{ __( 'Add' ) }}</x-artisanpack-button>
                        </div>
                    @endif

                    <x-slot:actions>
                        <x-artisanpack-button variant="ghost" color="neutral" x-on:click="$wire.open = false">{{ __( 'Cancel' ) }}</x-artisanpack-button>
                        <x-artisanpack-button type="submit" wire:loading.attr="disabled" wire:target="save" data-ec-kb-widget-save>{{ __( 'Save widgets' ) }}</x-artisanpack-button>
                    </x-slot:actions>
                </x-artisanpack-form>

                <section aria-labelledby="ec-kb-widget-preview-title" data-ec-kb-widget-preview>
                    <h3 id="ec-kb-widget-preview-title" class="mb-2 font-semibold">{{ __( 'Preview' ) }}</h3>
                    @if ( $previewInvalid )
                        <p class="text-sm ec-kb-muted" data-ec-kb-widget-preview-invalid>{{ __( 'Fix the settings to see a preview.' ) }}</p>
                    @elseif ( null === $preview )
                        <p class="text-sm ec-kb-muted">{{ __( 'Put an order on this board to preview its card.' ) }}</p>
                    @else
                        <x-artisanpack-ec-kb-card :card="$preview" />
                    @endif
                </section>
            </div>
        @endif
    </x-artisanpack-dialog>
</div>
