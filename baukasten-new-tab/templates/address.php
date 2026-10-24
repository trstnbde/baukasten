<?php
/**
 * The page an address typed without JavaScript leads to.
 *
 * Not a redirect: `?q=<host>` forwarding to that host would make the site an
 * open redirect, the link a phishing mail wants. The visitor sees the
 * address and follows it themselves.
 *
 * @package Baukasten\NewTab
 */

namespace Baukasten\NewTab;

defined( 'ABSPATH' ) || exit;

$baukasten_nt_view    = Renderer::view();
$baukasten_nt_address = Renderer::address();

Renderer::start_compression();

?><!DOCTYPE html>
<html lang="<?php echo esc_attr( (string) $baukasten_nt_view['lang'] ); ?>" class="bk-mode-off bk-address">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<meta name="color-scheme" content="dark light">
<title><?php echo esc_html( (string) $baukasten_nt_view['title'] ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( (string) $baukasten_nt_view['css'] ); ?>"><?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- No wp_head() on this page, by design. ?>
</head>
<body>
<main class="bk-main">
	<h1 class="bk-greeting"><span class="bk-greeting__prompt" aria-hidden="true">›</span> <span class="bk-greeting__text"><?php esc_html_e( 'Open this address?', 'baukasten-new-tab' ); ?></span></h1>
	<p class="bk-address__link"><a href="<?php echo esc_url( $baukasten_nt_address ); ?>" rel="noreferrer noopener"><?php echo esc_html( $baukasten_nt_address ); ?></a></p>
	<p class="bk-address__back"><a href="<?php echo esc_url( (string) $baukasten_nt_view['action'] ); ?>"><?php esc_html_e( 'Back to the new tab page', 'baukasten-new-tab' ); ?></a></p>
</main>
</body>
</html>
