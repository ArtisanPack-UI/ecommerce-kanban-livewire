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
 * naming an `ability` is dropped when the viewer doesn't hold it. Keys are
 * unique: of several actions with one key, the first is kept, unless the
 * caller names a preferred event for that key (a built-in action), in which
 * case the first action with that event is kept.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
final class ActionList
{
    /**
     * The well-formed actions the viewer may take, in order, one per key.
     *
     * A duplicate key would collide in the menu (its target is looked up by
     * key), so only one action per key is kept: the first, or for a key in
     * `$preferred` the first whose event is the one named there, so a
     * satellite action reusing a built-in key (`open`, `move`, `remove`)
     * can't replace the built-in. The kept action takes the position of the
     * first action with its key.
     *
     * @since 1.0.0
     * @since 2.0.0 Keeps one action per key; added `$preferred`.
     *
     * @param  mixed                    $actions    The filtered list.
     * @param  callable(string): bool  $allows     Whether the viewer holds an ability.
     * @param  array<string, string>   $preferred  Event to prefer for a key, keyed by key.
     *
     * @return array<int, array{key: string, label: string, icon: string|null, url: string|null, event: string|null}>
     */
    public static function normalize( mixed $actions, callable $allows, array $preferred = [] ): array
    {
        $result = [];

        foreach ( is_array( $actions ) ? $actions : [] as $action ) {
            $action = is_array( $action ) ? self::action( $action, $allows ) : null;

            if ( null === $action ) {
                continue;
            }

            $key = $action['key'];

            if ( ! array_key_exists( $key, $result ) ) {
                $result[ $key ] = $action;

                continue;
            }

            $wanted = $preferred[ $key ] ?? null;

            if ( null !== $wanted && $wanted !== $result[ $key ]['event'] && $wanted === $action['event'] ) {
                $result[ $key ] = $action;
            }
        }

        return array_values( $result );
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
