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
$baukasten_bc_layout = (string) $baukasten_bc_card['card_layout'];
$baukasten_bc_layout = in_array( $baukasten_bc_layout, Fields::LAYOUTS, true ) ? $baukasten_bc_layout : Fields::DEFAULT_LAYOUT;

?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'bkbc bkbc--' . $baukasten_bc_layout ); ?>>
<?php wp_body_open(); ?>

<div class="bkbc-stage">
	<article class="bkbc-card" data-layout="<?php echo esc_attr( $baukasten_bc_layout ); ?>">
		<?php require PLUGIN_DIR . 'templates/partials/toolbar.php'; ?>
		<?php require PLUGIN_DIR . 'templates/layouts/' . $baukasten_bc_layout . '.php'; ?>
		<?php require PLUGIN_DIR . 'templates/partials/footer.php'; ?>
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
