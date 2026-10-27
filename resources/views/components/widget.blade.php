{{--
    `x-artisanpack-ec-kb-widget`: one widget payload from the engine's
    `KanbanCardRenderer`. Uses the view a satellite registered for the
    widget key through `ap.ecommerceKanbanLivewire.widgetView`, else the
    generic payload view; a view that throws renders nothing.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
@props( [
    'widget',
] )
{{ \ArtisanPackUI\EcommerceKanbanLivewire\Support\WidgetViews::render( $widget ) }}
