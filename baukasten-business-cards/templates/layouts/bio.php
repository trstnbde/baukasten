<?php
/**
 * Bio: a short header and then a column of full width buttons.
 *
 * Built for a card that is mostly links — the header is kept small so the
 * first button is above the fold on a phone, and every destination is one
 * tap target of the same size.
 *
 * @package Baukasten\BusinessCards
 *
 * @var array<string, mixed> $baukasten_bc_card Loaded card fields.
 * @var \WP_Post             $baukasten_bc_post The card post.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

?>
<div class="bkbc-body">
	<?php
	require PLUGIN_DIR . 'templates/partials/identity.php';
	require PLUGIN_DIR . 'templates/partials/bio.php';
	require PLUGIN_DIR . 'templates/partials/quick-actions.php';
	require PLUGIN_DIR . 'templates/partials/links.php';
	require PLUGIN_DIR . 'templates/partials/networks.php';
	require PLUGIN_DIR . 'templates/partials/downloads.php';
	require PLUGIN_DIR . 'templates/partials/wallet.php';
	require PLUGIN_DIR . 'templates/partials/contact.php';
	require PLUGIN_DIR . 'templates/partials/form.php';
	?>
</div>
