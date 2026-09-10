<?php
/**
 * The banner image at the top of a classic card.
 *
 * @package Baukasten\BusinessCards
 *
 * @var array<string, mixed> $baukasten_bc_card Loaded card fields.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

if ( ! isset( $baukasten_bc_card['banner_image_id'] ) ) {
	return;
}

$baukasten_bc_banner = wp_get_attachment_image(
	(int) $baukasten_bc_card['banner_image_id'],
	'large',
	false,
	array(
		'class'    => 'bkbc-banner__image',
		'alt'      => '',
		'loading'  => 'eager',
		'decoding' => 'sync',
	)
);

if ( '' === $baukasten_bc_banner ) {
	return;
}

?>
<div class="bkbc-banner">
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() escapes its own output.
	echo $baukasten_bc_banner;
	?>
</div>
