<?php
/**
 * The full contact list.
 *
 * @package Baukasten\BusinessCards
 *
 * @var array<string, mixed> $baukasten_bc_card Loaded card fields.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

$baukasten_bc_rows = array(
	'email_work'      => array( 'mail', __( 'Email, work', 'baukasten-business-cards' ), 'mailto' ),
	'email_priv'      => array( 'mail', __( 'Email, private', 'baukasten-business-cards' ), 'mailto' ),
	'phone_work'      => array( 'phone', __( 'Phone, work', 'baukasten-business-cards' ), 'tel' ),
	'phone_priv'      => array( 'phone', __( 'Phone, private', 'baukasten-business-cards' ), 'tel' ),
	'mobile_work'     => array( 'mobile', __( 'Mobile, work', 'baukasten-business-cards' ), 'tel' ),
	'mobile_priv'     => array( 'mobile', __( 'Mobile, private', 'baukasten-business-cards' ), 'tel' ),
	'contact_website' => array( 'globe', __( 'Website', 'baukasten-business-cards' ), 'url' ),
	'what3words_link' => array( 'pin', __( 'what3words', 'baukasten-business-cards' ), 'url' ),
);

if ( ! Fields::has_any( $baukasten_bc_card, array_keys( $baukasten_bc_rows ) ) && ! isset( $baukasten_bc_card['contact_address'] ) ) {
	return;
}

?>
<section class="bkbc-section bkbc-contact">
	<h2 class="bkbc-section__title"><?php esc_html_e( 'Contact', 'baukasten-business-cards' ); ?></h2>

	<dl class="bkbc-contact__list">
		<?php foreach ( $baukasten_bc_rows as $baukasten_bc_key => $baukasten_bc_row ) : ?>
			<?php if ( isset( $baukasten_bc_card[ $baukasten_bc_key ] ) ) : ?>
				<?php
				$baukasten_bc_value = (string) $baukasten_bc_card[ $baukasten_bc_key ];

				if ( 'tel' === $baukasten_bc_row[2] ) {
					$baukasten_bc_href = 'tel:' . preg_replace( '/[^0-9+]/', '', $baukasten_bc_value );
				} elseif ( 'mailto' === $baukasten_bc_row[2] ) {
					$baukasten_bc_href = 'mailto:' . $baukasten_bc_value;
				} else {
					$baukasten_bc_href = $baukasten_bc_value;
				}
				?>
				<div class="bkbc-contact__row">
					<dt class="bkbc-contact__label">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG built from a fixed table in Icons.
						echo Icons::get( (string) $baukasten_bc_row[0] );
						?>
						<span><?php echo esc_html( (string) $baukasten_bc_row[1] ); ?></span>
					</dt>
					<dd class="bkbc-contact__value">
						<a href="<?php echo esc_url( $baukasten_bc_href, array( 'http', 'https', 'tel', 'mailto' ) ); ?>">
							<?php echo esc_html( $baukasten_bc_value ); ?>
						</a>
					</dd>
				</div>
			<?php endif; ?>
		<?php endforeach; ?>

		<?php if ( isset( $baukasten_bc_card['contact_address'] ) ) : ?>
			<div class="bkbc-contact__row">
				<dt class="bkbc-contact__label">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG built from a fixed table in Icons.
					echo Icons::get( 'pin' );
					?>
					<span><?php esc_html_e( 'Address', 'baukasten-business-cards' ); ?></span>
				</dt>
				<dd class="bkbc-contact__value">
					<address><?php echo nl2br( esc_html( (string) $baukasten_bc_card['contact_address'] ) ); ?></address>
				</dd>
			</div>
		<?php endif; ?>
	</dl>
</section>
