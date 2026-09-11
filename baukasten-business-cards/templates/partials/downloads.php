<?php
/**
 * Files attached to a card.
 *
 * @package Baukasten\BusinessCards
 *
 * @var array<string, mixed> $baukasten_bc_card Loaded card fields.
 * @var array<string, mixed> $baukasten_bc_skin The design being rendered.
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

	$baukasten_bc_label = (string) ( $baukasten_bc_card[ 'download_' . $baukasten_bc_index . '_label' ] ?? '' );
	$baukasten_bc_path  = get_attached_file( $baukasten_bc_file_id );
	$baukasten_bc_meta  = array();

	$baukasten_bc_type = strtoupper( (string) pathinfo( $baukasten_bc_url, PATHINFO_EXTENSION ) );

	if ( '' !== $baukasten_bc_type ) {
		$baukasten_bc_meta[] = $baukasten_bc_type;
	}

	if ( is_string( $baukasten_bc_path ) && is_readable( $baukasten_bc_path ) ) {
		$baukasten_bc_meta[] = size_format( (int) filesize( $baukasten_bc_path ), 1 );
	}

	$baukasten_bc_downloads[] = array(
		'url'   => $baukasten_bc_url,
		'label' => '' !== $baukasten_bc_label ? $baukasten_bc_label : (string) get_the_title( $baukasten_bc_file_id ),
		'meta'  => implode( ' · ', $baukasten_bc_meta ),
	);
}

if ( array() === $baukasten_bc_downloads ) {
	return;
}

Skins::open_section( $baukasten_bc_skin, 'downloads', __( 'Downloads', 'baukasten-business-cards' ) );

?>
<ul class="bkbc-downloads__list">
	<?php foreach ( $baukasten_bc_downloads as $baukasten_bc_download ) : ?>
		<li>
			<a class="bkbc-button bkbc-button--secondary bkbc-button--block bkbc-button--spread" href="<?php echo esc_url( (string) $baukasten_bc_download['url'] ); ?>" download>
				<span class="bkbc-downloads__name">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG built from a fixed table in Icons.
					echo Icons::get( 'download' );
					?>
					<span><?php echo esc_html( (string) $baukasten_bc_download['label'] ); ?></span>
				</span>
				<?php if ( '' !== $baukasten_bc_download['meta'] ) : ?>
					<span class="bkbc-tag"><?php echo esc_html( (string) $baukasten_bc_download['meta'] ); ?></span>
				<?php endif; ?>
			</a>
		</li>
	<?php endforeach; ?>
</ul>
<?php

Skins::close_section();
