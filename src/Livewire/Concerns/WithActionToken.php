<?php

/**
 * One-time action token concern.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Concerns;

use ArtisanPackUI\EcommerceKanbanLivewire\Support\ActionTokens;
use Throwable;

/**
 * Makes the board's destructive actions run once (spec §6). Mint a token
 * when the confirmation renders and pass it back as the action's argument:
 *
 * ```blade
 * <x-artisanpack-button wire:click="removeCard( @js( $removeToken ) )" spinner="removeCard" />
 * ```
 *
 * The action wraps its write in {@see self::withActionToken()}. The token is
 * consumed first, so a reused token is a no-op with a notice, and a write
 * that throws releases the token for a retry. Uses the Toast trait.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
trait WithActionToken
{
    /**
     * Mints a token for an action on a subject.
     *
     * @since 1.0.0
     *
     * @param  string      $action   The action name, e.g. `remove-card`.
     * @param  int|string  $subject  What the token is limited to.
     *
     * @return string
     */
    protected function actionToken( string $action, int|string $subject ): string
    {
        return ActionTokens::mint( auth()->user(), $this->actionTokenScope( $action, $subject ) );
    }

    /**
     * Runs a write once per token. Returns null without running `$write`
     * when the token is invalid, expired, or already used; the user gets a
     * notice instead. When `$write` throws, the token is released.
     *
     * @since 1.0.0
     *
     * @template TResult
     *
     * @param  string               $token    The token the client sent back.
     * @param  string               $action   The action name it was minted for.
     * @param  int|string           $subject  The subject it was minted for.
     * @param  callable(): TResult  $write    The write.
     *
     * @return TResult|null
     */
    protected function withActionToken( string $token, string $action, int|string $subject, callable $write ): mixed
    {
        $user  = auth()->user();
        $scope = $this->actionTokenScope( $action, $subject );

        if ( ! ActionTokens::isValid( $user, $scope, $token ) ) {
            $this->warning( e( __( 'This action has expired. Reload the page and try again.' ) ) );

            return null;
        }

        if ( ! ActionTokens::consume( $user, $action, $scope, $token ) ) {
            $this->warning( e( __( 'That action was already submitted. Nothing was changed the second time.' ) ) );

            return null;
        }

        try {
            return $write();
        } catch ( Throwable $exception ) {
            ActionTokens::release( $user, $action, $token );

            throw $exception;
        }
    }

    /**
     * The scope a token is bound to: this component, the action, and the subject.
     *
     * @since 1.0.0
     *
     * @param  string      $action   The action name.
     * @param  int|string  $subject  The subject.
     *
     * @return string
     */
    private function actionTokenScope( string $action, int|string $subject ): string
    {
        return static::class . '|' . $action . '|' . $subject;
    }
}
