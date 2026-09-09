<?php
/**
 * Multi-Domain tab.
 *
 * @package Baukasten\MultiDomain
 *
 * @var array<string, int> $map Host name to page ID.
 */

namespace Baukasten\MultiDomain;

defined( 'ABSPATH' ) || exit;

?>
<p class="baukasten-intro">
	<?php
	esc_html_e(
		'A page can be the front page of a domain of its own. Assign the domain in the page list — as a column, in the quick edit, or in the page editor — and every visitor arriving on that domain sees that page instead of the usual front page.',
		'baukasten-multi-domain'
	);
	?>
</p>

<h2><?php esc_html_e( 'Domains in use', 'baukasten-multi-domain' ); ?></h2>

<?php if ( array() === $map ) : ?>
	<div class="notice notice-info inline">
		<p>
			<?php esc_html_e( 'No domain is assigned yet.', 'baukasten-multi-domain' ); ?>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>">
				<?php esc_html_e( 'Open the page list', 'baukasten-multi-domain' ); ?>
			</a>
		</p>
	</div>
<?php else : ?>
	<table class="widefat striped baukasten-domain-map">
		<caption class="screen-reader-text"><?php esc_html_e( 'Domains and the pages they serve', 'baukasten-multi-domain' ); ?></caption>
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Domain', 'baukasten-multi-domain' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Front page', 'baukasten-multi-domain' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'baukasten-multi-domain' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $map as $baukasten_md_host => $baukasten_md_page_id ) : ?>
				<?php
				$baukasten_md_page  = get_post( (int) $baukasten_md_page_id );
				$baukasten_md_title = $baukasten_md_page instanceof \WP_Post
					? get_the_title( $baukasten_md_page )
					: '';
				?>
				<tr>
					<th scope="row"><code><?php echo esc_html( (string) $baukasten_md_host ); ?></code></th>
					<td>
						<?php if ( '' === $baukasten_md_title ) : ?>
							<em><?php esc_html_e( 'This page no longer exists.', 'baukasten-multi-domain' ); ?></em>
						<?php else : ?>
							<a href="<?php echo esc_url( (string) get_edit_post_link( (int) $baukasten_md_page_id ) ); ?>">
								<?php echo esc_html( $baukasten_md_title ); ?>
							</a>
						<?php endif; ?>
					</td>
					<td>
						<?php
						echo esc_html(
							$baukasten_md_page instanceof \WP_Post && 'publish' === $baukasten_md_page->post_status
								? __( 'Published', 'baukasten-multi-domain' )
								: __( 'Not published — visitors will not see it', 'baukasten-multi-domain' )
						);
						?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<h2><?php esc_html_e( 'Maintenance', 'baukasten-multi-domain' ); ?></h2>

<p>
	<?php
	esc_html_e(
		'This table is a copy of what the pages say, kept as a single option so the front end can route without a database query. A database import or a plugin writing meta directly can put the two out of step; rebuilding reads every page again and starts over.',
		'baukasten-multi-domain'
	);
	?>
</p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="<?php echo esc_attr( Settings_Tab::ACTION_REBUILD ); ?>" />
	<?php wp_nonce_field( Settings_Tab::NONCE ); ?>
	<?php submit_button( __( 'Rebuild the map from the pages', 'baukasten-multi-domain' ), 'secondary', 'submit', false ); ?>
</form>
