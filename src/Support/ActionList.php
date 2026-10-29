<?php

/**
 * Hook-supplied action lists.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Support;

/**
 * Cleans an action list that went through a filter (card actions, the
 * board toolbar): each action needs a `key`, a `label`, and a `url` (safe
 * schemes only) or an `event`; `icon` must be a known icon; and an action
 * naming an `ability` is dropped when the viewer doesn't hold it.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
final class ActionList
{
    /**
     * The well-formed actions the viewer may take, in order.
     *
     * @since 1.0.0
     *
     * @param  mixed                    $actions  The filtered list.
     * @param  callable(string): bool  $allows   Whether the viewer holds an ability.
     *
     * @return array<int, array{key: string, label: string, icon: string|null, url: string|null, event: string|null}>
     */
    public static function normalize( mixed $actions, callable $allows ): array
    {
        $result = [];

        foreach ( is_array( $actions ) ? $actions : [] as $action ) {
            $action = is_array( $action ) ? self::action( $action, $allows ) : null;

            if ( null !== $action ) {
                $result[] = $action;
            }
        }

        return $result;
    }

    /**
     * A well-formed action the viewer may take, or null.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>     $action  Raw action.
     * @param  callable(string): bool  $allows  Whether the viewer holds an ability.
     *
     * @return array{key: string, label: string, icon: string|null, url: string|null, event: string|null}|null
     */
    private static function action( array $action, callable $allows ): ?array
    {
        $string = static fn ( mixed $value ): ?string => is_string( $value ) && '' !== trim( $value ) ? trim( $value ) : null;

        $key     = $string( $action['key'] ?? null );
        $label   = $string( $action['label'] ?? null );
        $url     = SafeUrl::of( $string( $action['url'] ?? null ) );
        $event   = $string( $action['event'] ?? null );
        $ability = $string( $action['ability'] ?? null );

        if ( null === $key || null === $label || ( null === $url && null === $event ) ) {
            return null;
        }

        if ( null !== $ability && ! $allows( $ability ) ) {
            return null;
        }

        return [
            'key'   => $key,
            'label' => $label,
            'icon'  => Icons::name( $string( $action['icon'] ?? null ) ),
            'url'   => $url,
            'event' => $event,
        ];
    }
}
