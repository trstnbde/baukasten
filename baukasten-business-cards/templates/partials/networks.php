<?php
/**
 * Profiles elsewhere, and the card's own links.
 *
 * One grid, not two: the designs put the named networks and the three free links
 * in the same run of buttons, and splitting them into two headed sections would
 * be inventing a distinction the reader does not care about.
 *
 * Labelled by name rather than by logo. A brand mark is a trademark with its own
 * usage rules, and a GPL plugin has no business shipping a set of them.
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
			'url'     => (string) $baukasten_bc_card[ $baukasten_bc_key ],
			'label'   => $baukasten_bc_name,
			'initial' => Icons::initial( $baukasten_bc_key ),
		);
	}
}

foreach ( array( 1, 2, 3 ) as $baukasten_bc_index ) {
	$baukasten_bc_url = (string) ( $baukasten_bc_card[ 'custom_link_' . $baukasten_bc_index . '_url' ] ?? '' );

	if ( '' === $baukasten_bc_url ) {
		continue;
	}

	$baukasten_bc_label = (string) ( $baukasten_bc_card[ 'custom_link_' . $baukasten_bc_index . '_label' ] ?? '' );

	if ( '' === $baukasten_bc_label ) {
		$baukasten_bc_label = (string) wp_parse_url( $baukasten_bc_url, PHP_URL_HOST );
	}

	$baukasten_bc_links[] = array(
		'url'     => $baukasten_bc_url,
		'label'   => $baukasten_bc_label,
		'initial' => '',
	);
}

if ( array() === $baukasten_bc_links ) {
	return;
}

Skins::open_section( $baukasten_bc_skin, 'networks', __( 'My links', 'baukasten-business-cards' ) );

?>
<ul class="bkbc-networks__list">
	<?php foreach ( $baukasten_bc_links as $baukasten_bc_link ) : ?>
		<li>
			<a class="bkbc-button bkbc-button--secondary bkbc-button--block" href="<?php echo esc_url( (string) $baukasten_bc_link['url'] ); ?>" rel="me noopener">
				<?php if ( '' !== $baukasten_bc_link['initial'] ) : ?>
					<span class="bkbc-networks__initial" aria-hidden="true"><?php echo esc_html( (string) $baukasten_bc_link['initial'] ); ?></span>
				<?php else : ?>
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG built from a fixed table in Icons.
					echo Icons::get( 'link' );
					?>
				<?php endif; ?>
				<span><?php echo esc_html( (string) $baukasten_bc_link['label'] ); ?></span>
			</a>
		</li>
	<?php endforeach; ?>
</ul>
<?php

Skins::close_section();
