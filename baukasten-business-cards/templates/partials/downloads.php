<?php
/**
 * Files attached to a card.
 *
 * @package Baukasten\BusinessCards
 *
 * @var array<string, mixed> $baukasten_bc_card Loaded card fields.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

$baukasten_bc_downloads = array();

foreach ( array( 1, 2, 3 ) as $baukasten_bc_index ) {
	$baukasten_bc_file_id = (int) ( $baukasten_bc_card[ 'download_' . $baukasten_bc_index . '_file_id' ] ?? 0 );

	if ( 0 >= $baukasten_bc_file_id ) {
		continue;
	}

	$baukasten_bc_url = wp_get_attachment_url( $baukasten_bc_file_id );

	if ( ! is_string( $baukasten_bc_url ) || '' === $baukasten_bc_url ) {
		continue;
	}

	$baukasten_bc_label = $baukasten_bc_card[ 'download_' . $baukasten_bc_index . '_label' ] ?? '';

	$baukasten_bc_downloads[] = array(
		'url'   => $baukasten_bc_url,
		'label' => '' !== $baukasten_bc_label
			? (string) $baukasten_bc_label
			: (string) get_the_title( $baukasten_bc_file_id ),
	);
}

if ( array() === $baukasten_bc_downloads ) {
	return;
}

?>
<section class="bkbc-section bkbc-downloads">
	<h2 class="bkbc-section__title"><?php esc_html_e( 'Downloads', 'baukasten-business-cards' ); ?></h2>

	<ul class="bkbc-links__list">
		<?php foreach ( $baukasten_bc_downloads as $baukasten_bc_download ) : ?>
			<li>
				<a class="bkbc-stack-button" href="<?php echo esc_url( $baukasten_bc_download['url'] ); ?>" download>
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG built from a fixed table in Icons.
					echo Icons::get( 'download' );
					?>
					<span><?php echo esc_html( $baukasten_bc_download['label'] ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
