<?php
/**
 * The contact form.
 *
 * @package Baukasten\BusinessCards
 *
 * @var array<string, mixed> $baukasten_bc_card Loaded card fields.
 * @var array<string, mixed> $baukasten_bc_skin The design being rendered.
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

Skins::open_section( $baukasten_bc_skin, 'form', __( 'Contact me', 'baukasten-business-cards' ) );

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode output, escaped by the form plugin.
echo $baukasten_bc_form;

Skins::close_section();
