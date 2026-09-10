<?php
/**
 * The card's own links.
 *
 * @package Baukasten\BusinessCards
 *
 * @var array<string, mixed> $baukasten_bc_card Loaded card fields.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

$baukasten_bc_links = array();

foreach ( array( 1, 2, 3 ) as $baukasten_bc_index ) {
	$baukasten_bc_url = $baukasten_bc_card[ 'custom_link_' . $baukasten_bc_index . '_url' ] ?? '';

	if ( '' === $baukasten_bc_url ) {
		continue;
	}

	$baukasten_bc_label = $baukasten_bc_card[ 'custom_link_' . $baukasten_bc_index . '_label' ] ?? '';

	$baukasten_bc_links[] = array(
		'url'   => (string) $baukasten_bc_url,
		'label' => '' !== $baukasten_bc_label
			? (string) $baukasten_bc_label
			: (string) wp_parse_url( (string) $baukasten_bc_url, PHP_URL_HOST ),
	);
}

if ( array() === $baukasten_bc_links ) {
	return;
}

?>
<section class="bkbc-section bkbc-links">
	<h2 class="bkbc-section__title"><?php esc_html_e( 'Links', 'baukasten-business-cards' ); ?></h2>

	<ul class="bkbc-links__list">
		<?php foreach ( $baukasten_bc_links as $baukasten_bc_link ) : ?>
			<li>
				<a class="bkbc-stack-button" href="<?php echo esc_url( $baukasten_bc_link['url'] ); ?>" rel="noopener">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG built from a fixed table in Icons.
					echo Icons::get( 'link' );
					?>
					<span><?php echo esc_html( $baukasten_bc_link['label'] ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
