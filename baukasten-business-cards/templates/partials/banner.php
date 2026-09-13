<?php
/**
 * The banner, and the two buttons that sit on it.
 *
 * The buttons live here rather than in a toolbar of their own: all three designs
 * put them in the banner's top corner, and their colours are hard-coded on
 * purpose — they sit on an arbitrary photograph, and a themed token would make
 * them vanish against a light one.
 *
 * @package Baukasten\BusinessCards
 *
 * @var array<string, mixed> $baukasten_bc_card Loaded card fields.
 * @var array<string, mixed> $baukasten_bc_skin The design being rendered.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

$baukasten_bc_banner = isset( $baukasten_bc_card['banner_image_id'] )
	? wp_get_attachment_image(
		(int) $baukasten_bc_card['banner_image_id'],
		'large',
		false,
		array(
			'class'    => 'bkbc-banner__image',
			'alt'      => '',
			'loading'  => 'eager',
			'decoding' => 'sync',
		)
	)
	: '';

$baukasten_bc_tools = Fields::has_any( $baukasten_bc_card, array( 'show_theme_toggle', 'show_qr_modal' ) );

if ( '' === $baukasten_bc_banner && ! $baukasten_bc_tools ) {
	return;
}

?>
<div class="bkbc-banner<?php echo '' === $baukasten_bc_banner ? ' bkbc-banner--empty' : ''; ?>">
	<?php if ( '' !== $baukasten_bc_banner ) : ?>
		<div class="bkbc-media bkbc-banner__media">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() escapes its own output.
			echo $baukasten_bc_banner;
			?>
		</div>
	<?php endif; ?>

	<?php if ( $baukasten_bc_tools ) : ?>
		<div class="bkbc-tools">
			<?php if ( isset( $baukasten_bc_card['show_qr_modal'] ) ) : ?>
				<button type="button" class="bkbc-tools__button" data-bkbc-qr-open="bkbc-qr">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG built from a fixed table in Icons.
					echo Icons::get( 'qr' );
					?>
					<span class="screen-reader-text"><?php esc_html_e( 'Show this card as a QR code', 'baukasten-business-cards' ); ?></span>
				</button>
			<?php endif; ?>

			<?php if ( isset( $baukasten_bc_card['show_theme_toggle'] ) ) : ?>
				<button type="button" class="bkbc-tools__button" data-bkbc-theme-toggle aria-pressed="false">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG built from a fixed table in Icons.
					echo Icons::get( 'sun' );
					?>
					<span class="screen-reader-text"><?php esc_html_e( 'Switch between light and dark', 'baukasten-business-cards' ); ?></span>
				</button>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
