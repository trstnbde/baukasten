<?php
/**
 * Classic: a banner, an overlapping portrait, and the contact list in full.
 *
 * The most conventional of the three, and the one that reads best when a card
 * carries a lot of detail.
 *
 * @package Baukasten\BusinessCards
 *
 * @var array<string, mixed> $baukasten_bc_card Loaded card fields.
 * @var \WP_Post             $baukasten_bc_post The card post.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

require PLUGIN_DIR . 'templates/partials/banner.php';

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
