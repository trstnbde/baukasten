<?php
/**
 * The two buttons in a card's top corner.
 *
 * @package Baukasten\BusinessCards
 *
 * @var array<string, mixed> $baukasten_bc_card Loaded card fields.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

if ( ! Fields::has_any( $baukasten_bc_card, array( 'show_theme_toggle', 'show_qr_modal' ) ) ) {
	return;
}

?>
<div class="bkbc-toolbar">
	<?php if ( isset( $baukasten_bc_card['show_theme_toggle'] ) ) : ?>
		<button
			type="button"
			class="bkbc-toolbar__button"
			data-bkbc-theme-toggle
			aria-pressed="false"
		>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG built from a fixed table in Icons.
			echo Icons::get( 'sun' );
			?>
			<span class="screen-reader-text"><?php esc_html_e( 'Switch between light and dark', 'baukasten-business-cards' ); ?></span>
		</button>
	<?php endif; ?>

	<?php if ( isset( $baukasten_bc_card['show_qr_modal'] ) ) : ?>
		<button
			type="button"
			class="bkbc-toolbar__button"
			data-bkbc-qr-open="bkbc-qr"
		>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG built from a fixed table in Icons.
			echo Icons::get( 'qr' );
			?>
			<span class="screen-reader-text"><?php esc_html_e( 'Show this card as a QR code', 'baukasten-business-cards' ); ?></span>
		</button>
	<?php endif; ?>
</div>
