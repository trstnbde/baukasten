<?php
/**
 * The four primary actions, as a grid of tiles.
 *
 * These are not fields of their own. They are the contact details the card
 * already carries, promoted to buttons — the first number, the first mobile,
 * the first address, the website. Two places to type the same telephone number
 * is two places for it to go out of date, and the one that gets forgotten is
 * always the one at the top of the card.
 *
 * WhatsApp comes off the mobile number because WhatsApp is a mobile service:
 * offering it for a desk line would produce a button that goes nowhere.
 *
 * @package Baukasten\BusinessCards
 *
 * @var array<string, mixed> $baukasten_bc_card Loaded card fields.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

/**
 * The first of several fields that has anything in it.
 *
 * @param array<string, mixed> $card Loaded card fields.
 * @param string[]             $keys Field keys, best first.
 * @return string The value, or an empty string.
 */
$baukasten_bc_first = static function ( array $card, array $keys ): string {
	foreach ( $keys as $key ) {
		if ( isset( $card[ $key ] ) && '' !== (string) $card[ $key ] ) {
			return (string) $card[ $key ];
		}
	}

	return '';
};

$baukasten_bc_phone   = $baukasten_bc_first( $baukasten_bc_card, array( 'phone_work', 'mobile_work', 'phone_priv', 'mobile_priv' ) );
$baukasten_bc_mobile  = $baukasten_bc_first( $baukasten_bc_card, array( 'mobile_work', 'mobile_priv' ) );
$baukasten_bc_email   = $baukasten_bc_first( $baukasten_bc_card, array( 'email_work', 'email_priv' ) );
$baukasten_bc_website = $baukasten_bc_first( $baukasten_bc_card, array( 'contact_website' ) );

$baukasten_bc_actions = array();

if ( '' !== $baukasten_bc_phone ) {
	$baukasten_bc_actions[] = array(
		'href'  => Obfuscate::tel( $baukasten_bc_phone ),
		'icon'  => 'phone',
		'label' => __( 'Call', 'baukasten-business-cards' ),
	);
}

if ( '' !== $baukasten_bc_email ) {
	$baukasten_bc_actions[] = array(
		'href'  => Obfuscate::mailto( $baukasten_bc_email ),
		'icon'  => 'mail',
		'label' => __( 'Email', 'baukasten-business-cards' ),
	);
}

if ( '' !== $baukasten_bc_mobile ) {
	$baukasten_bc_actions[] = array(
		'href'  => 'https://wa.me/' . Obfuscate::html( ltrim( (string) preg_replace( '/[^0-9+]/', '', $baukasten_bc_mobile ), '+' ) ),
		'icon'  => 'chat',
		'label' => __( 'WhatsApp', 'baukasten-business-cards' ),
	);
}

if ( '' !== $baukasten_bc_website ) {
	$baukasten_bc_actions[] = array(
		'href'  => esc_url( $baukasten_bc_website ),
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
				<?php
				/*
				 * The href is printed as it stands. It is either the output of
				 * esc_url(), or a string of character references with no literal
				 * character left in it to escape.
				 */
				?>
				<a class="bkbc-quick__link" href="<?php echo $baukasten_bc_action['href']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already an escaped URL or a fully entity-encoded one; see Obfuscate. ?>">
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
