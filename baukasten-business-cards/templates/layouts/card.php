<?php
/**
 * The card, in the order the three designs share.
 *
 * There is one of these, not one per design. The prototypes differ in typeface,
 * palette, spacing, how a photograph is treated and whether a section is drawn
 * as a framed plate — none of which is structure. Three near-identical layout
 * files would only have drifted apart.
 *
 * @package Baukasten\BusinessCards
 *
 * @var array<string, mixed> $baukasten_bc_card Loaded card fields.
 * @var array<string, mixed> $baukasten_bc_skin The design being rendered.
 * @var \WP_Post             $baukasten_bc_post The card post.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

require PLUGIN_DIR . 'templates/partials/banner.php';

?>
<div class="bkbc-body">
	<?php
	require PLUGIN_DIR . 'templates/partials/identity.php';
	require PLUGIN_DIR . 'templates/partials/actions.php';
	require PLUGIN_DIR . 'templates/partials/quick-actions.php';
	require PLUGIN_DIR . 'templates/partials/bio.php';
	require PLUGIN_DIR . 'templates/partials/contact.php';
	require PLUGIN_DIR . 'templates/partials/networks.php';
	require PLUGIN_DIR . 'templates/partials/links.php';
	require PLUGIN_DIR . 'templates/partials/downloads.php';
	require PLUGIN_DIR . 'templates/partials/form.php';
	require PLUGIN_DIR . 'templates/partials/footer.php';
	?>
</div>
