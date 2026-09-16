<?php
/**
 * Overview tab.
 *
 * @package Baukasten
 *
 * @var array<string, array<string, mixed>> $addons Every known addon, with its state on this site.
 */

namespace Baukasten;

defined( 'ABSPATH' ) || exit;

$baukasten_can_install  = current_user_can( 'install_plugins' );
$baukasten_can_activate = current_user_can( 'activate_plugins' );

?>
<p class="baukasten-intro">
	<?php
	esc_html_e(
		'Baukasten collects the settings of its addons on this screen. Each addon is a separate plugin: install the ones you need, activate them, and each adds its own tab here.',
		'baukasten'
	);
	?>
</p>

<table class="widefat striped baukasten-addons">
	<caption class="screen-reader-text"><?php esc_html_e( 'Baukasten addons', 'baukasten' ); ?></caption>
	<thead>
		<tr>
			<th scope="col"><?php esc_html_e( 'Addon', 'baukasten' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Description', 'baukasten' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Version', 'baukasten' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Status', 'baukasten' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $addons as $baukasten_addon ) : ?>
			<?php $baukasten_status = (string) $baukasten_addon['status']; ?>
			<tr>
				<th scope="row">
					<?php if ( $baukasten_addon['has_tab'] ) : ?>
						<a href="<?php echo esc_url( Admin::page_url( (string) $baukasten_addon['id'] ) ); ?>">
							<?php echo esc_html( (string) $baukasten_addon['title'] ); ?>
						</a>
					<?php else : ?>
						<?php echo esc_html( (string) $baukasten_addon['title'] ); ?>
					<?php endif; ?>
				</th>
				<td><?php echo esc_html( (string) $baukasten_addon['description'] ); ?></td>
				<td><?php echo esc_html( (string) $baukasten_addon['version'] ); ?></td>
				<td class="baukasten-addons-status">
					<span class="baukasten-status baukasten-status--<?php echo esc_attr( $baukasten_status ); ?>">
						<?php echo esc_html( Catalog::status_label( $baukasten_status ) ); ?>
					</span>

					<?php if ( Catalog::MISSING === $baukasten_status && $baukasten_can_install && '' !== $baukasten_addon['slug'] ) : ?>
						<a class="button button-secondary" href="<?php echo esc_url( Catalog::install_url( $baukasten_addon ) ); ?>">
							<?php esc_html_e( 'Install', 'baukasten' ); ?>
							<span class="screen-reader-text">
								<?php echo esc_html( (string) $baukasten_addon['title'] ); ?>
							</span>
						</a>
					<?php elseif ( Catalog::INACTIVE === $baukasten_status && $baukasten_can_activate && '' !== $baukasten_addon['file'] ) : ?>
						<a class="button button-secondary" href="<?php echo esc_url( Catalog::activate_url( $baukasten_addon ) ); ?>">
							<?php esc_html_e( 'Activate', 'baukasten' ); ?>
							<span class="screen-reader-text">
								<?php echo esc_html( (string) $baukasten_addon['title'] ); ?>
							</span>
						</a>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<?php if ( $baukasten_can_activate ) : ?>
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
