<?php
/**
 * The contact form.
 *
 * @package Baukasten\BusinessCards
 *
 * @var array<string, mixed> $baukasten_bc_card Loaded card fields.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

if ( ! isset( $baukasten_bc_card['cf7_form_id'] ) ) {
	return;
}

$baukasten_bc_form = Forms::render( (int) $baukasten_bc_card['cf7_form_id'] );

if ( '' === $baukasten_bc_form ) {
	return;
}

?>
<section class="bkbc-section bkbc-form">
	<h2 class="bkbc-section__title"><?php esc_html_e( 'Get in touch', 'baukasten-business-cards' ); ?></h2>

	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode output, escaped by the form plugin.
	echo $baukasten_bc_form;
	?>
</section>
