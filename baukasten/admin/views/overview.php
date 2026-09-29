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
		'Content Visibility, Login Legal Pages and the Consent Blocking Engine are part of this plugin and always on. The addons below them are separate plugins: install the ones you need, activate them, and each adds its own tab here.',
		'baukasten'
	);
	?>
</p>

<table class="wp-list-table widefat plugins baukasten-addons">
	<caption class="screen-reader-text"><?php esc_html_e( 'Baukasten addons', 'baukasten' ); ?></caption>
	<thead>
		<tr>
			<th scope="col" class="manage-column column-name column-primary"><?php esc_html_e( 'Addon', 'baukasten' ); ?></th>
			<th scope="col" class="manage-column column-description"><?php esc_html_e( 'Description', 'baukasten' ); ?></th>
		</tr>
	</thead>
	<tbody id="the-list">
		<?php foreach ( $addons as $baukasten_addon ) : ?>
			<?php
			$baukasten_status = (string) $baukasten_addon['status'];
			$baukasten_on     = in_array( $baukasten_status, array( Catalog::ACTIVE, Catalog::BUILT_IN ), true );
			$baukasten_meta   = array();

			if ( '' !== (string) $baukasten_addon['version'] ) {
				/* translators: %s: addon version number. */
				$baukasten_meta[] = sprintf( __( 'Version %s', 'baukasten' ), (string) $baukasten_addon['version'] );
			}

			$baukasten_meta[] = Catalog::status_label( $baukasten_status );
			?>
			<tr class="<?php echo $baukasten_on ? 'active' : 'inactive'; ?>">
				<td class="plugin-title column-primary">
					<strong><?php echo esc_html( (string) $baukasten_addon['title'] ); ?></strong>
					<div class="row-actions visible">
						<?php if ( $baukasten_addon['has_tab'] ) : ?>
							<span class="settings">
								<a href="<?php echo esc_url( Admin::page_url( (string) $baukasten_addon['id'] ) ); ?>">
									<?php esc_html_e( 'Settings', 'baukasten' ); ?>
									<span class="screen-reader-text"><?php echo esc_html( (string) $baukasten_addon['title'] ); ?></span>
								</a>
							</span>
						<?php elseif ( Catalog::MISSING === $baukasten_status && $baukasten_can_install && '' !== $baukasten_addon['slug'] ) : ?>
							<span class="install">
								<a href="<?php echo esc_url( Catalog::install_url( $baukasten_addon ) ); ?>">
									<?php esc_html_e( 'Install', 'baukasten' ); ?>
									<span class="screen-reader-text"><?php echo esc_html( (string) $baukasten_addon['title'] ); ?></span>
								</a>
							</span>
						<?php elseif ( Catalog::INACTIVE === $baukasten_status && $baukasten_can_activate && '' !== $baukasten_addon['file'] ) : ?>
							<span class="activate">
								<a href="<?php echo esc_url( Catalog::activate_url( $baukasten_addon ) ); ?>">
									<?php esc_html_e( 'Activate', 'baukasten' ); ?>
									<span class="screen-reader-text"><?php echo esc_html( (string) $baukasten_addon['title'] ); ?></span>
								</a>
							</span>
						<?php endif; ?>
					</div>
					<button type="button" class="toggle-row">
						<span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'baukasten' ); ?></span>
					</button>
				</td>
				<td class="column-description desc">
					<div class="plugin-description">
						<p><?php echo esc_html( (string) $baukasten_addon['description'] ); ?></p>
					</div>
					<div class="<?php echo $baukasten_on ? 'active' : 'inactive'; ?> second plugin-version-author-uri">
						<?php echo esc_html( implode( ' | ', $baukasten_meta ) ); ?>
					</div>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
	<tfoot>
		<tr>
			<th scope="col" class="manage-column column-name column-primary"><?php esc_html_e( 'Addon', 'baukasten' ); ?></th>
			<th scope="col" class="manage-column column-description"><?php esc_html_e( 'Description', 'baukasten' ); ?></th>
		</tr>
	</tfoot>
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
