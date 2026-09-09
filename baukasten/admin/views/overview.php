<?php
/**
 * Overview tab.
 *
 * @package Baukasten
 *
 * @var array<string, array<string, mixed>> $addons Registered addons.
 */

namespace Baukasten;

defined( 'ABSPATH' ) || exit;

?>
<p class="baukasten-intro">
	<?php
	esc_html_e(
		'Baukasten collects the settings of its addons on this screen. Each addon is a separate plugin: install it from the WordPress plugin directory, activate it, and it adds its own tab here.',
		'baukasten'
	);
	?>
</p>

<?php if ( empty( $addons ) ) : ?>
	<div class="notice notice-info inline">
		<p>
			<?php esc_html_e( 'No addons are active yet.', 'baukasten' ); ?>
			<?php if ( current_user_can( 'install_plugins' ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=baukasten+addon&tab=search&type=term' ) ); ?>">
					<?php esc_html_e( 'Browse Baukasten addons', 'baukasten' ); ?>
				</a>
			<?php endif; ?>
		</p>
	</div>
<?php else : ?>
	<table class="widefat striped baukasten-addons">
		<caption class="screen-reader-text"><?php esc_html_e( 'Active Baukasten addons', 'baukasten' ); ?></caption>
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Addon', 'baukasten' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Description', 'baukasten' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Version', 'baukasten' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $addons as $baukasten_addon ) : ?>
				<?php $baukasten_data = Addons::plugin_data( $baukasten_addon ); ?>
				<tr>
					<th scope="row">
						<a href="<?php echo esc_url( Admin::page_url( (string) $baukasten_addon['id'] ) ); ?>">
							<?php echo esc_html( (string) $baukasten_addon['title'] ); ?>
						</a>
					</th>
					<td><?php echo esc_html( $baukasten_data['description'] ); ?></td>
					<td><?php echo esc_html( $baukasten_data['version'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( current_user_can( 'activate_plugins' ) ) : ?>
		<p class="description baukasten-addons-hint">
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: link to the installed plugins screen. */
					__( 'Addons are managed like any other plugin, on the <a href="%s">Plugins</a> screen.', 'baukasten' ),
					esc_url( admin_url( 'plugins.php' ) )
				),
				array( 'a' => array( 'href' => array() ) )
			);
			?>
		</p>
	<?php endif; ?>
<?php endif; ?>
