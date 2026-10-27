{{--
    Turns a rate-limited Livewire update (HTTP 429 from the
    `ecommerce-kanban.throttle` middleware) into a toast with the retry time,
    instead of Livewire's error modal.

    Uses Livewire's `request` hook, which Livewire 3 and 4 both support.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
<script data-ecommerce-kanban-rate-limit @if ( null !== ( $cspNonce = \Illuminate\Support\Facades\Vite::cspNonce() ) ) nonce="{{ $cspNonce }}" @endif>
    ( () => {
        const fallback = @js( __( 'Too many changes in a short time. Try again shortly.' ) );
        const title    = @js( __( 'Slow down' ) );

        const register = () => {
            if ( window.ecommerceKanbanRateLimitNotice || ! window.Livewire ) {
                return;
            }

            window.ecommerceKanbanRateLimitNotice = true;

            window.Livewire.hook( 'request', ( { fail } ) => {
                fail( ( { status, content, preventDefault } ) => {
                    if ( 429 !== status ) {
                        return;
                    }

                    let message = fallback;

                    try {
                        const body = JSON.parse( content );
                        message    = 'string' === typeof body.message ? body.message : fallback;
                    } catch ( error ) {
                        // Not this package's JSON body; keep the generic message.
                    }

                    preventDefault();

                    if ( 'function' === typeof window.toast ) {
                        window.toast( { toast: { type: 'warning', title, description: message, icon: '', css: 'alert-warning' } } );
                    }
                } );
            } );
        };

        window.Livewire ? register() : document.addEventListener( 'livewire:init', register );
    } )();
</script>
