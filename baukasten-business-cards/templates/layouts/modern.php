<?php
/**
 * Modern: no banner, a round portrait, and the actions as a grid.
 *
 * Typography carries the card here, so there is no image above the name and
 * the quick actions come before anything else.
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
	require PLUGIN_DIR . 'templates/partials/quick-actions.php';
	require PLUGIN_DIR . 'templates/partials/bio.php';
	require PLUGIN_DIR . 'templates/partials/contact.php';
	require PLUGIN_DIR . 'templates/partials/networks.php';
	require PLUGIN_DIR . 'templates/partials/links.php';
	require PLUGIN_DIR . 'templates/partials/downloads.php';
	require PLUGIN_DIR . 'templates/partials/wallet.php';
	require PLUGIN_DIR . 'templates/partials/form.php';
	?>
</div>
