<?php
/**
 * The QR dialog.
 *
 * The code itself is drawn in the browser from the bundled encoder, so that
 * looking at a card never tells a QR service that somebody did.
 *
 * @package Baukasten\BusinessCards
 *
 * @var \WP_Post $baukasten_bc_post The card post.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

$baukasten_bc_url = (string) get_permalink( $baukasten_bc_post );

?>
<dialog id="bkbc-qr" class="bkbc-qr" aria-label="<?php esc_attr_e( 'This card as a QR code', 'baukasten-business-cards' ); ?>">
	<div class="bkbc-qr__inner">
		<button type="button" class="bkbc-qr__close" data-bkbc-qr-close>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG built from a fixed table in Icons.
			echo Icons::get( 'close' );
			?>
			<span class="screen-reader-text"><?php esc_html_e( 'Close', 'baukasten-business-cards' ); ?></span>
		</button>

		<div class="bkbc-qr__code" data-bkbc-qr-canvas data-url="<?php echo esc_attr( $baukasten_bc_url ); ?>"></div>

		<p class="bkbc-qr__url"><?php echo esc_html( $baukasten_bc_url ); ?></p>
	</div>
</dialog>
