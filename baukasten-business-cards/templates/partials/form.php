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

/*
 * Said where it happens, and only when it does: Turnstile is held back until
 * the form is used, and that is exactly the moment a third party gets involved.
 * No widget in the form, or Turnstile not deferred, and there is nothing to say.
 */
if ( str_contains( $baukasten_bc_form, 'cf-turnstile' ) && wp_script_is( Forms::LOADER_HANDLE, 'enqueued' ) ) {
	printf(
		'<p class="bkbc-form__notice">%s</p>',
		esc_html__( 'Spam protection by Cloudflare Turnstile is loaded as soon as you start using this form.', 'baukasten-business-cards' )
	);
}

Skins::close_section();
