<?php
/**
 * The biography.
 *
 * @package Baukasten\BusinessCards
 *
 * @var array<string, mixed> $baukasten_bc_card Loaded card fields.
 * @var array<string, mixed> $baukasten_bc_skin The design being rendered.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

if ( ! isset( $baukasten_bc_card['bio_text'] ) ) {
	return;
}

Skins::open_section( $baukasten_bc_skin, 'bio', __( 'About', 'baukasten-business-cards' ) );

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- run through wp_kses_post() on the way in and again on the way out.
echo wpautop( wp_kses_post( (string) $baukasten_bc_card['bio_text'] ) );

Skins::close_section();
