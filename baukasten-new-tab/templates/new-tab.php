<?php
/**
 * The New Tab Page.
 *
 * A complete document of its own: no `wp_head()`, no `wp_footer()`, nothing
 * of the theme. Every value comes from `Renderer::view()`; the script reads
 * its configuration from the JSON block at the end, because the policy this
 * page is sent with allows no inline script.
 *
 * @package Baukasten\NewTab
 */

namespace Baukasten\NewTab;

defined( 'ABSPATH' ) || exit;

$baukasten_nt_view       = Renderer::view();
$baukasten_nt_background = (array) $baukasten_nt_view['background'];
$baukasten_nt_media      = isset( $baukasten_nt_background['media'] ) ? (array) $baukasten_nt_background['media'] : array();
$baukasten_nt_photo      = isset( $baukasten_nt_background['photo'] ) ? (array) $baukasten_nt_background['photo'] : array();
$baukasten_nt_engines    = (array) $baukasten_nt_view['engines'];
$baukasten_nt_engine     = (string) $baukasten_nt_view['engine'];
$baukasten_nt_current    = $baukasten_nt_engines[0];

foreach ( $baukasten_nt_engines as $baukasten_nt_candidate ) {
	if ( $baukasten_nt_candidate['id'] === $baukasten_nt_engine ) {
		$baukasten_nt_current = $baukasten_nt_candidate;
	}
}

Renderer::start_compression();

?><!DOCTYPE html>
<html lang="<?php echo esc_attr( (string) $baukasten_nt_view['lang'] ); ?>" class="bk-mode-<?php echo esc_attr( (string) $baukasten_nt_background['mode'] ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta http-equiv="Content-Security-Policy" content="<?php echo esc_attr( Renderer::content_security_policy( $baukasten_nt_view, true ) ); ?>">
<meta name="referrer" content="no-referrer">
<meta name="color-scheme" content="dark light">
<title><?php echo esc_html( (string) $baukasten_nt_view['title'] ); ?></title>
<link rel="icon" href="<?php echo esc_url( (string) $baukasten_nt_view['icon'] ); ?>">
<link rel="preload" href="<?php echo esc_url( (string) $baukasten_nt_view['font'] ); ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?php echo esc_url( (string) $baukasten_nt_view['css'] ); ?>"><?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- No wp_head() on this page, by design. ?>
<style><?php echo esc_html( (string) $baukasten_nt_view['style'] ); ?></style>
<noscript><style><?php echo esc_html( (string) $baukasten_nt_view['nojs_style'] ); ?></style></noscript>
<script src="<?php echo esc_url( (string) $baukasten_nt_view['js'] ); ?>" defer></script><?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- No wp_head() on this page, by design. ?>
</head>
<body>
<div class="bk-bg" aria-hidden="true">
<?php if ( array() !== $baukasten_nt_media ) : ?>
	<img class="bk-bg__img" src="<?php echo esc_url( (string) $baukasten_nt_media['src'] ); ?>"
	<?php
	if ( '' !== $baukasten_nt_media['srcset'] ) :
		?>
		srcset="<?php echo esc_attr( (string) $baukasten_nt_media['srcset'] ); ?>" sizes="<?php echo esc_attr( (string) $baukasten_nt_media['sizes'] ); ?>"<?php endif; ?> width="<?php echo esc_attr( (string) $baukasten_nt_media['width'] ); ?>" height="<?php echo esc_attr( (string) $baukasten_nt_media['height'] ); ?>" alt="" decoding="async" fetchpriority="high">
<?php else : ?>
	<img class="bk-bg__img" alt="" decoding="async" hidden>
<?php endif; ?>
</div>

<?php if ( array() !== $baukasten_nt_view['favorites'] ) : ?>
<nav class="bk-bar" aria-label="<?php esc_attr_e( 'Favourites', 'baukasten-new-tab' ); ?>">
	<ul class="bk-bar__list">
		<?php foreach ( (array) $baukasten_nt_view['favorites'] as $baukasten_nt_favorite ) : ?>
		<li class="bk-bar__item"><a class="bk-bar__link" href="<?php echo esc_url( (string) $baukasten_nt_favorite['url'] ); ?>"><img src="<?php echo esc_attr( (string) $baukasten_nt_favorite['icon'] ); ?>" width="16" height="16" alt="" decoding="async"><span><?php echo esc_html( '' !== $baukasten_nt_favorite['title'] ? (string) $baukasten_nt_favorite['title'] : (string) wp_parse_url( (string) $baukasten_nt_favorite['url'], PHP_URL_HOST ) ); ?></span></a></li>
		<?php endforeach; ?>
	</ul>
	<div class="bk-bar__more" hidden>
		<button type="button" class="bk-bar__more-button" aria-haspopup="true" aria-expanded="false" aria-controls="bk-more" title="<?php esc_attr_e( 'More favourites', 'baukasten-new-tab' ); ?>"><span aria-hidden="true">»</span><span class="bk-sr"><?php esc_html_e( 'More favourites', 'baukasten-new-tab' ); ?></span></button>
		<ul class="bk-bar__menu" id="bk-more" hidden></ul>
	</div>
</nav>
<?php endif; ?>

<main class="bk-main">
	<?php if ( '' !== (string) $baukasten_nt_view['greeting']['text'] ) : ?>
		<h1 class="bk-greeting"><span class="bk-greeting__prompt" aria-hidden="true">›</span> <span class="bk-greeting__text"><?php echo esc_html( (string) $baukasten_nt_view['greeting']['text'] ); ?></span><span class="bk-cursor" aria-hidden="true"></span></h1>
	<?php endif; ?>

	<form class="bk-search" role="search" method="get" action="<?php echo esc_url( (string) $baukasten_nt_view['action'] ); ?>">
		<div class="bk-search__box">
			<button type="button" class="bk-engine" aria-haspopup="listbox" aria-expanded="false" aria-controls="bk-engines" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: search engine name. */ __( 'Search engine: %s', 'baukasten-new-tab' ), $baukasten_nt_current['label'] ) ); ?>">
				<span class="bk-badge bk-badge--<?php echo esc_attr( (string) $baukasten_nt_current['id'] ); ?>" aria-hidden="true"><?php echo esc_html( (string) $baukasten_nt_current['badge'] ); ?></span>
				<svg class="bk-engine__chevron" viewBox="0 0 12 12" width="10" height="10" aria-hidden="true" focusable="false"><path d="M2 4l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</button>
			<ul class="bk-engines" id="bk-engines" role="listbox" aria-label="<?php esc_attr_e( 'Search engine', 'baukasten-new-tab' ); ?>" tabindex="-1" hidden>
				<li class="bk-engines__head" role="presentation"><?php esc_html_e( 'Search with', 'baukasten-new-tab' ); ?></li>
				<?php foreach ( $baukasten_nt_engines as $baukasten_nt_candidate ) : ?>
				<li class="bk-engines__option" role="option" id="bk-engine-<?php echo esc_attr( (string) $baukasten_nt_candidate['id'] ); ?>" data-engine="<?php echo esc_attr( (string) $baukasten_nt_candidate['id'] ); ?>" aria-selected="false" aria-checked="<?php echo $baukasten_nt_candidate['id'] === $baukasten_nt_engine ? 'true' : 'false'; ?>"><span class="bk-badge bk-badge--<?php echo esc_attr( (string) $baukasten_nt_candidate['id'] ); ?>" aria-hidden="true"><?php echo esc_html( (string) $baukasten_nt_candidate['badge'] ); ?></span><span class="bk-engines__name"><?php echo esc_html( (string) $baukasten_nt_candidate['label'] ); ?></span>
					<?php
					if ( '' !== $baukasten_nt_candidate['shortcut'] ) :
						?>
					<kbd>@<?php echo esc_html( (string) $baukasten_nt_candidate['shortcut'] ); ?></kbd><?php endif; ?></li>
				<?php endforeach; ?>
			</ul>
			<label class="bk-sr" for="bk-q"><?php esc_html_e( 'Search or enter address', 'baukasten-new-tab' ); ?></label>
			<input class="bk-search__input" id="bk-q" name="q" type="search" maxlength="<?php echo esc_attr( (string) Search::MAX_LENGTH ); ?>" placeholder="<?php echo esc_attr( sprintf( /* translators: %s: search engine name. */ __( 'Search with %s or enter address', 'baukasten-new-tab' ), $baukasten_nt_current['label'] ) ); ?>" autocomplete="off" autocapitalize="off" spellcheck="false" enterkeyhint="go" autofocus role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="bk-suggestions">
			<input type="hidden" name="e" value="<?php echo esc_attr( $baukasten_nt_engine ); ?>">
			<button type="submit" class="bk-search__submit"><svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><path d="M6.5 1a5.5 5.5 0 014.38 8.83l3.9 3.9-1.06 1.06-3.9-3.9A5.5 5.5 0 116.5 1zm0 1.5a4 4 0 100 8 4 4 0 000-8z" fill="currentColor"/></svg><span class="bk-sr"><?php esc_html_e( 'Search', 'baukasten-new-tab' ); ?></span></button>
		</div>
		<ul class="bk-suggestions" id="bk-suggestions" role="listbox" aria-label="<?php esc_attr_e( 'Suggestions', 'baukasten-new-tab' ); ?>" hidden></ul>
	</form>
</main>

<footer class="bk-footer">
	<p class="bk-footer__credit">
		<?php if ( 'media' === $baukasten_nt_background['mode'] && '' !== (string) ( $baukasten_nt_media['caption'] ?? '' ) ) : ?>
			<span class="bk-credit"><?php echo esc_html( (string) $baukasten_nt_media['caption'] ); ?></span>
		<?php elseif ( 'unsplash' === $baukasten_nt_background['mode'] && array() !== $baukasten_nt_photo && '' !== (string) $baukasten_nt_photo['name'] ) : ?>
			<span class="bk-credit">
			<?php
			printf(
				/* translators: 1: photographer, linked. 2: "Unsplash", linked. */
				esc_html__( 'Photo: %1$s on %2$s', 'baukasten-new-tab' ),
				'<a href="' . esc_url( (string) $baukasten_nt_photo['profile'] ) . '">' . esc_html( (string) $baukasten_nt_photo['name'] ) . '</a>',
				'<a href="' . esc_url( (string) $baukasten_nt_photo['site'] ) . '">Unsplash</a>'
			);
			?>
			</span>
		<?php else : ?>
			<span class="bk-credit"></span>
		<?php endif; ?>
	</p>
	<?php if ( array() !== $baukasten_nt_view['legal'] ) : ?>
	<nav class="bk-footer__legal" aria-label="<?php esc_attr_e( 'Legal', 'baukasten-new-tab' ); ?>">
		<?php foreach ( (array) $baukasten_nt_view['legal'] as $baukasten_nt_link ) : ?>
		<a href="<?php echo esc_url( (string) $baukasten_nt_link['url'] ); ?>"><?php echo esc_html( (string) $baukasten_nt_link['label'] ); ?></a>
		<?php endforeach; ?>
	</nav>
	<?php endif; ?>
</footer>
<script type="application/json" id="bk-config">
<?php
// JSON with every HTML-significant character escaped.
echo wp_json_encode( $baukasten_nt_view['config'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
?>
</script>
</body>
</html>
