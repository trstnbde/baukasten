<?php
/**
 * Profiles elsewhere.
 *
 * A section of its own, apart from the card's free links: a network is
 * somewhere the reader may already be, recognised by its logo, while a free
 * link is somewhere they have never been and needs its label read. Mixing the
 * two in one run of buttons made both harder to scan.
 *
 * @package Baukasten\BusinessCards
 *
 * @var array<string, mixed> $baukasten_bc_card Loaded card fields.
 * @var array<string, mixed> $baukasten_bc_skin The design being rendered.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

$baukasten_bc_networks = array(
	'network_linkedin'  => 'LinkedIn',
	'network_xing'      => 'Xing',
	'network_github'    => 'GitHub',
	'network_mastodon'  => 'Mastodon',
	'network_facebook'  => 'Facebook',
	'network_instagram' => 'Instagram',
	'network_threads'   => 'Threads',
	'network_discord'   => 'Discord',
	'network_signal'    => 'Signal',
);

$baukasten_bc_links = array();

foreach ( $baukasten_bc_networks as $baukasten_bc_key => $baukasten_bc_name ) {
	if ( isset( $baukasten_bc_card[ $baukasten_bc_key ] ) ) {
		$baukasten_bc_links[] = array(
			'url'   => (string) $baukasten_bc_card[ $baukasten_bc_key ],
			'label' => $baukasten_bc_name,
			'key'   => $baukasten_bc_key,
		);
	}
}

if ( array() === $baukasten_bc_links ) {
	return;
}

Skins::open_section( $baukasten_bc_skin, 'networks', __( 'My networks', 'baukasten-business-cards' ) );

?>
<ul class="bkbc-networks__list">
	<?php foreach ( $baukasten_bc_links as $baukasten_bc_link ) : ?>
		<li>
			<a class="bkbc-button bkbc-button--secondary bkbc-button--block" href="<?php echo esc_url( (string) $baukasten_bc_link['url'] ); ?>" rel="me noopener">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG built from a fixed table in Icons.
				echo Icons::brand( (string) $baukasten_bc_link['key'] );
				?>
				<span><?php echo esc_html( (string) $baukasten_bc_link['label'] ); ?></span>
			</a>
		</li>
	<?php endforeach; ?>
</ul>
<?php

Skins::close_section();
