<?php
/**
 * The page a password protected card shows instead of itself.
 *
 * A whole document like the card, in the card's own design, and with nothing
 * of the card in it: no name, no photo, no contact details, no QR code and none
 * of its scripts. The title is generic and the headers keep it out of search
 * engines and caches (`Renderer::lock_headers()`).
 *
 * @package Baukasten\BusinessCards
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

$baukasten_bc_post = get_queried_object();

if ( ! $baukasten_bc_post instanceof \WP_Post ) {
	return;
}

$baukasten_bc_meta   = Fields::load( $baukasten_bc_post->ID );
$baukasten_bc_skin   = Skins::get( (string) $baukasten_bc_meta['card_layout'] );
$baukasten_bc_scheme = (string) $baukasten_bc_meta['card_color_scheme'];

// The footer partial wants a card; the lock page has none to give it.
$baukasten_bc_card = array();

?>
<!doctype html>
<html
	<?php language_attributes(); ?>
	data-bkbc-skin="<?php echo esc_attr( (string) $baukasten_bc_skin['id'] ); ?>"
	data-bkbc-scheme="<?php echo esc_attr( $baukasten_bc_scheme ); ?>"
	data-bkbc-default="<?php echo esc_attr( (string) $baukasten_bc_skin['scheme'] ); ?>"
>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'bkbc bkbc--' . (string) $baukasten_bc_skin['id'] . ' bkbc--locked' ); ?>>
<?php wp_body_open(); ?>

<div class="bkbc-stage">
	<article class="bkbc-card">
		<div class="bkbc-body bkbc-locked">
			<h1 class="bkbc-locked__title"><?php esc_html_e( 'This card is password protected', 'baukasten-business-cards' ); ?></h1>
			<div class="bkbc-locked__form">
				<?php echo get_the_password_form( $baukasten_bc_post ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core's own, escaped form. ?>
			</div>
			<?php require PLUGIN_DIR . 'templates/partials/footer.php'; ?>
		</div>
	</article>
</div>

<?php wp_footer(); ?>
</body>
</html>
