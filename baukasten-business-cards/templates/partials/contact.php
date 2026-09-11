<?php
/**
 * The full contact list: label on the left, value on the right.
 *
 * @package Baukasten\BusinessCards
 *
 * @var array<string, mixed> $baukasten_bc_card Loaded card fields.
 * @var array<string, mixed> $baukasten_bc_skin The design being rendered.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

$baukasten_bc_rows = array(
	'email_work'      => array( __( 'Email (work)', 'baukasten-business-cards' ), 'mailto' ),
	'email_priv'      => array( __( 'Email (private)', 'baukasten-business-cards' ), 'mailto' ),
	'phone_work'      => array( __( 'Phone (work)', 'baukasten-business-cards' ), 'tel' ),
	'phone_priv'      => array( __( 'Phone (private)', 'baukasten-business-cards' ), 'tel' ),
	'mobile_work'     => array( __( 'Mobile (work)', 'baukasten-business-cards' ), 'tel' ),
	'mobile_priv'     => array( __( 'Mobile (private)', 'baukasten-business-cards' ), 'tel' ),
	'contact_website' => array( __( 'Website', 'baukasten-business-cards' ), 'url' ),
	'what3words_link' => array( __( 'what3words', 'baukasten-business-cards' ), 'url' ),
);

$baukasten_bc_any = Fields::has_any( $baukasten_bc_card, array_keys( $baukasten_bc_rows ) )
	|| isset( $baukasten_bc_card['contact_address'] );

if ( ! $baukasten_bc_any ) {
	return;
}

Skins::open_section( $baukasten_bc_skin, 'contact', __( 'Contact details', 'baukasten-business-cards' ) );

?>
<dl class="bkbc-contact__list">
	<?php foreach ( $baukasten_bc_rows as $baukasten_bc_key => $baukasten_bc_row ) : ?>
		<?php if ( isset( $baukasten_bc_card[ $baukasten_bc_key ] ) ) : ?>
			<?php
			$baukasten_bc_value = (string) $baukasten_bc_card[ $baukasten_bc_key ];

			if ( 'tel' === $baukasten_bc_row[1] ) {
				$baukasten_bc_href = 'tel:' . preg_replace( '/[^0-9+]/', '', $baukasten_bc_value );
			} elseif ( 'mailto' === $baukasten_bc_row[1] ) {
				$baukasten_bc_href = 'mailto:' . $baukasten_bc_value;
			} else {
				$baukasten_bc_href = $baukasten_bc_value;
			}
			?>
			<div class="bkbc-contact__row">
				<dt class="bkbc-contact__label"><?php echo esc_html( (string) $baukasten_bc_row[0] ); ?></dt>
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
			<dt class="bkbc-contact__label"><?php esc_html_e( 'Address', 'baukasten-business-cards' ); ?></dt>
			<dd class="bkbc-contact__value">
				<address><?php echo nl2br( esc_html( (string) $baukasten_bc_card['contact_address'] ) ); ?></address>
			</dd>
		</div>
	<?php endif; ?>
</dl>
<?php

Skins::close_section();
