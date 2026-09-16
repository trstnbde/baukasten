<?php
/**
 * The card's own links.
 *
 * Three free slots, each a label and an address. A slot without a label shows
 * the address's host, which is at least an honest description of where the
 * button goes.
 *
 * @package Baukasten\BusinessCards
 *
 * @var array<string, mixed> $baukasten_bc_card Loaded card fields.
 * @var array<string, mixed> $baukasten_bc_skin The design being rendered.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

$baukasten_bc_links = array();

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
		'url'   => $baukasten_bc_url,
		'label' => $baukasten_bc_label,
	);
}

if ( array() === $baukasten_bc_links ) {
	return;
}

Skins::open_section( $baukasten_bc_skin, 'links', __( 'My links', 'baukasten-business-cards' ) );

?>
<ul class="bkbc-links__list">
	<?php foreach ( $baukasten_bc_links as $baukasten_bc_link ) : ?>
		<li>
			<a class="bkbc-button bkbc-button--secondary bkbc-button--block" href="<?php echo esc_url( (string) $baukasten_bc_link['url'] ); ?>" rel="me noopener">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG built from a fixed table in Icons.
				echo Icons::get( 'link' );
				?>
				<span><?php echo esc_html( (string) $baukasten_bc_link['label'] ); ?></span>
			</a>
		</li>
	<?php endforeach; ?>
</ul>
<?php

Skins::close_section();
