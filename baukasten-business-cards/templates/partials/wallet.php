<?php
/**
 * Wallet badges.
 *
 * Signing a .pkpass needs an Apple certificate this plugin has no way to hold,
 * so these are links to wherever the pass is actually hosted rather than a
 * pass generator pretending to be one.
 *
 * @package Baukasten\BusinessCards
 *
 * @var array<string, mixed> $baukasten_bc_card Loaded card fields.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

if ( ! Fields::has_any( $baukasten_bc_card, array( 'wallet_apple_url', 'wallet_google_url' ) ) ) {
	return;
}

?>
<section class="bkbc-section bkbc-wallet">
	<h2 class="bkbc-section__title"><?php esc_html_e( 'Wallet', 'baukasten-business-cards' ); ?></h2>

	<ul class="bkbc-links__list">
		<?php if ( isset( $baukasten_bc_card['wallet_apple_url'] ) ) : ?>
			<li>
				<a class="bkbc-stack-button" href="<?php echo esc_url( (string) $baukasten_bc_card['wallet_apple_url'] ); ?>" rel="noopener">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG built from a fixed table in Icons.
					echo Icons::get( 'wallet' );
					?>
					<span><?php esc_html_e( 'Add to Apple Wallet', 'baukasten-business-cards' ); ?></span>
				</a>
			</li>
		<?php endif; ?>

		<?php if ( isset( $baukasten_bc_card['wallet_google_url'] ) ) : ?>
			<li>
				<a class="bkbc-stack-button" href="<?php echo esc_url( (string) $baukasten_bc_card['wallet_google_url'] ); ?>" rel="noopener">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG built from a fixed table in Icons.
					echo Icons::get( 'wallet' );
					?>
					<span><?php esc_html_e( 'Add to Google Wallet', 'baukasten-business-cards' ); ?></span>
				</a>
			</li>
		<?php endif; ?>
	</ul>
</section>
