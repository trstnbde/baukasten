<?php
/**
 * The four primary actions, as a grid of tiles.
 *
 * @package Baukasten\BusinessCards
 *
 * @var array<string, mixed> $baukasten_bc_card Loaded card fields.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

$baukasten_bc_actions = array();

if ( isset( $baukasten_bc_card['quick_tel'] ) ) {
	$baukasten_bc_actions[] = array(
		'href'  => 'tel:' . preg_replace( '/[^0-9+]/', '', (string) $baukasten_bc_card['quick_tel'] ),
		'icon'  => 'phone',
		'label' => __( 'Call', 'baukasten-business-cards' ),
	);
}

if ( isset( $baukasten_bc_card['quick_email'] ) ) {
	$baukasten_bc_actions[] = array(
		'href'  => 'mailto:' . (string) $baukasten_bc_card['quick_email'],
		'icon'  => 'mail',
		'label' => __( 'Email', 'baukasten-business-cards' ),
	);
}

if ( isset( $baukasten_bc_card['quick_whatsapp'] ) ) {
	$baukasten_bc_actions[] = array(
		'href'  => 'https://wa.me/' . ltrim( (string) $baukasten_bc_card['quick_whatsapp'], '+' ),
		'icon'  => 'chat',
		'label' => __( 'WhatsApp', 'baukasten-business-cards' ),
	);
}

if ( isset( $baukasten_bc_card['quick_website'] ) ) {
	$baukasten_bc_actions[] = array(
		'href'  => (string) $baukasten_bc_card['quick_website'],
		'icon'  => 'globe',
		'label' => __( 'Website', 'baukasten-business-cards' ),
	);
}

if ( array() === $baukasten_bc_actions ) {
	return;
}

?>
<nav class="bkbc-quick" aria-label="<?php esc_attr_e( 'Quick actions', 'baukasten-business-cards' ); ?>">
	<ul class="bkbc-quick__list">
		<?php foreach ( $baukasten_bc_actions as $baukasten_bc_action ) : ?>
			<li class="bkbc-quick__item">
				<a class="bkbc-quick__link" href="<?php echo esc_url( (string) $baukasten_bc_action['href'], array( 'http', 'https', 'tel', 'mailto' ) ); ?>">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG built from a fixed table in Icons.
					echo Icons::get( (string) $baukasten_bc_action['icon'] );
					?>
					<span><?php echo esc_html( (string) $baukasten_bc_action['label'] ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
