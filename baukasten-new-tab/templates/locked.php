<?php
/**
 * The page a password protected new tab page shows instead of itself.
 *
 * A document of its own like the page, with nothing of the page in it: no
 * favourites, no greeting, no background, no search and no script. The CSS file
 * is the page's, and the inline style the page's policy hashes is gone, so the
 * policy for this one is the strictest it has.
 *
 * @package Baukasten\NewTab
 */

namespace Baukasten\NewTab;

defined( 'ABSPATH' ) || exit;

$baukasten_nt_view = Renderer::view();

Renderer::start_compression();

?><!DOCTYPE html>
<html lang="<?php echo esc_attr( (string) $baukasten_nt_view['lang'] ); ?>" class="bk-mode-off bk-locked">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta http-equiv="Content-Security-Policy" content="<?php echo esc_attr( Renderer::content_security_policy( $baukasten_nt_view, true ) ); ?>">
<meta name="referrer" content="no-referrer">
<meta name="color-scheme" content="dark light">
<title><?php echo esc_html( (string) $baukasten_nt_view['title'] ); ?></title>
<link rel="icon" href="<?php echo esc_url( (string) $baukasten_nt_view['icon'] ); ?>">
<link rel="stylesheet" href="<?php echo esc_url( (string) $baukasten_nt_view['css'] ); ?>"><?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- No wp_head() on this page, by design. ?>
</head>
<body>
<main class="bk-main">
	<h1 class="bk-greeting"><span class="bk-greeting__prompt" aria-hidden="true">›</span> <span class="bk-greeting__text"><?php esc_html_e( 'This page is password protected', 'baukasten-new-tab' ); ?></span></h1>
	<div class="bk-lock-form">
		<?php echo get_the_password_form( (int) $baukasten_nt_view['page_id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core's own, escaped form. ?>
	</div>
</main>
<?php if ( array() !== $baukasten_nt_view['legal'] ) : ?>
<footer class="bk-footer">
	<p class="bk-footer__credit"></p>
	<nav class="bk-footer__legal" aria-label="<?php esc_attr_e( 'Legal', 'baukasten-new-tab' ); ?>">
		<?php foreach ( (array) $baukasten_nt_view['legal'] as $baukasten_nt_link ) : ?>
		<a href="<?php echo esc_url( (string) $baukasten_nt_link['url'] ); ?>"><?php echo esc_html( (string) $baukasten_nt_link['label'] ); ?></a>
		<?php endforeach; ?>
	</nav>
</footer>
<?php endif; ?>
</body>
</html>
