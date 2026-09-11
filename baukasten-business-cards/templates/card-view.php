<?php
/**
 * The card document.
 *
 * Loaded through `template_include` instead of the theme's template. It is a
 * whole HTML document rather than a fragment, because the point of a card is
 * that it looks the same whatever theme the site is wearing.
 *
 * `wp_head()` and `wp_footer()` are still called: a contact form and its
 * captcha register themselves there, and a card that dropped them would have a
 * form nobody could submit.
 *
 * @package Baukasten\BusinessCards
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

$baukasten_bc_post = get_queried_object();

if ( ! $baukasten_bc_post instanceof \WP_Post ) {
	return;
}

$baukasten_bc_card   = Fields::load( $baukasten_bc_post->ID );
$baukasten_bc_skin   = Skins::get( (string) $baukasten_bc_card['card_layout'] );
$baukasten_bc_scheme = (string) $baukasten_bc_card['card_color_scheme'];

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
<body <?php body_class( 'bkbc bkbc--' . (string) $baukasten_bc_skin['id'] ); ?>>
<?php wp_body_open(); ?>

<div class="bkbc-stage">
	<article class="bkbc-card">
		<?php require PLUGIN_DIR . 'templates/layouts/card.php'; ?>
	</article>
</div>

<?php
if ( isset( $baukasten_bc_card['show_qr_modal'] ) ) {
	require PLUGIN_DIR . 'templates/partials/qr-modal.php';
}
?>

<?php wp_footer(); ?>
</body>
</html>
