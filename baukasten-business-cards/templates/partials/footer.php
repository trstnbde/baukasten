<?php
/**
 * The legal links at the foot of every card.
 *
 * @package Baukasten\BusinessCards
 *
 * @var array<string, mixed> $baukasten_bc_card Loaded card fields.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

$baukasten_bc_privacy = (string) ( $baukasten_bc_card['footer_privacy_url'] ?? '' );

if ( '' === $baukasten_bc_privacy ) {
	// The site already knows where its privacy policy is; asking every card to
	// repeat it is how one of them ends up pointing at a deleted page.
	$baukasten_bc_privacy = (string) get_privacy_policy_url();
}

$baukasten_bc_legal = array();

if ( isset( $baukasten_bc_card['footer_imprint_url'] ) ) {
	$baukasten_bc_legal[] = array(
		'url'   => (string) $baukasten_bc_card['footer_imprint_url'],
		'label' => __( 'Imprint', 'baukasten-business-cards' ),
	);
}

if ( '' !== $baukasten_bc_privacy ) {
	$baukasten_bc_legal[] = array(
		'url'   => $baukasten_bc_privacy,
		'label' => __( 'Privacy policy', 'baukasten-business-cards' ),
	);
}

if ( isset( $baukasten_bc_card['footer_terms_url'] ) ) {
	$baukasten_bc_legal[] = array(
		'url'   => (string) $baukasten_bc_card['footer_terms_url'],
		'label' => __( 'Terms', 'baukasten-business-cards' ),
	);
}

if ( array() === $baukasten_bc_legal ) {
	return;
}

?>
<footer class="bkbc-footer">
	<nav aria-label="<?php esc_attr_e( 'Legal', 'baukasten-business-cards' ); ?>">
		<ul class="bkbc-footer__list">
			<?php foreach ( $baukasten_bc_legal as $baukasten_bc_item ) : ?>
				<li>
					<a href="<?php echo esc_url( $baukasten_bc_item['url'] ); ?>" target="_self">
						<?php echo esc_html( $baukasten_bc_item['label'] ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</nav>
</footer>
