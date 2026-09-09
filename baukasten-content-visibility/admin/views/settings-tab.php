<?php
/**
 * Content Visibility tab.
 *
 * @package Baukasten\ContentVisibility
 *
 * @var array{post_types: string[], blocked_response: string} $settings   Current settings.
 * @var string[]                                              $post_types Post types to choose from.
 * @var array<string, mixed>                                  $migration  Last migration record.
 */

namespace Baukasten\ContentVisibility;

defined( 'ABSPATH' ) || exit;

?>
<p class="baukasten-intro">
	<?php
	esc_html_e(
		'Every entry carries a public or private flag. Private entries are readable by logged-in users only, no matter which role they have, and are kept out of archives, search, feeds, sitemaps and the REST API.',
		'baukasten-content-visibility'
	);
	?>
</p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="<?php echo esc_attr( Settings_Tab::ACTION ); ?>" />
	<?php wp_nonce_field( Settings_Tab::NONCE ); ?>

	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Content types', 'baukasten-content-visibility' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text">
							<?php esc_html_e( 'Content types the visibility switch applies to', 'baukasten-content-visibility' ); ?>
						</legend>

						<?php foreach ( $post_types as $baukasten_cv_type ) : ?>
							<?php
							$baukasten_cv_object = get_post_type_object( $baukasten_cv_type );
							$baukasten_cv_label  = null !== $baukasten_cv_object
								? $baukasten_cv_object->labels->name
								: $baukasten_cv_type;
							$baukasten_cv_on     = array() === $settings['post_types']
								|| in_array( $baukasten_cv_type, $settings['post_types'], true );
							?>
							<label for="baukasten-cv-type-<?php echo esc_attr( $baukasten_cv_type ); ?>">
								<input
									type="checkbox"
									id="baukasten-cv-type-<?php echo esc_attr( $baukasten_cv_type ); ?>"
									name="post_types[]"
									value="<?php echo esc_attr( $baukasten_cv_type ); ?>"
									<?php checked( $baukasten_cv_on ); ?>
								/>
								<?php echo esc_html( (string) $baukasten_cv_label ); ?>
								<code><?php echo esc_html( $baukasten_cv_type ); ?></code>
							</label><br />
						<?php endforeach; ?>

						<p class="description">
							<?php
							esc_html_e(
								'Tick none to cover every public content type, including ones registered later by a theme or another plugin.',
								'baukasten-content-visibility'
							);
							?>
						</p>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Logged-out visitors', 'baukasten-content-visibility' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text">
							<?php esc_html_e( 'What a logged-out visitor gets when they open private content', 'baukasten-content-visibility' ); ?>
						</legend>

						<label>
							<input
								type="radio"
								name="blocked_response"
								value="<?php echo esc_attr( Settings::RESPONSE_LOGIN ); ?>"
								<?php checked( Settings::RESPONSE_LOGIN, $settings['blocked_response'] ); ?>
							/>
							<?php esc_html_e( 'Send them to the login form and back afterwards', 'baukasten-content-visibility' ); ?>
						</label><br />

						<label>
							<input
								type="radio"
								name="blocked_response"
								value="<?php echo esc_attr( Settings::RESPONSE_FORBIDDEN ); ?>"
								<?php checked( Settings::RESPONSE_FORBIDDEN, $settings['blocked_response'] ); ?>
							/>
							<?php esc_html_e( 'Answer with 403 Forbidden', 'baukasten-content-visibility' ); ?>
						</label>

						<p class="description">
							<?php
							esc_html_e(
								'Redirecting is friendlier for a members area. A 403 gives away less about what exists.',
								'baukasten-content-visibility'
							);
							?>
						</p>
					</fieldset>
				</td>
			</tr>
		</tbody>
	</table>

	<?php submit_button( __( 'Save settings', 'baukasten-content-visibility' ) ); ?>
</form>

<h2><?php esc_html_e( 'Existing content', 'baukasten-content-visibility' ); ?></h2>

<p>
	<?php
	esc_html_e(
		'Entries without a stored flag count as private. On activation every entry that existed was marked public, so switching the plugin on never takes a live site offline. Run it again after adding a content type that already has entries.',
		'baukasten-content-visibility'
	);
	?>
</p>

<?php if ( ! empty( $migration['time'] ) ) : ?>
	<p>
		<strong><?php esc_html_e( 'Last run:', 'baukasten-content-visibility' ); ?></strong>
		<?php
		printf(
			/* translators: 1: date and time, 2: number of entries. */
			esc_html__( '%1$s, %2$s entries marked public.', 'baukasten-content-visibility' ),
			esc_html(
				wp_date(
					(string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ),
					(int) $migration['time']
				)
			),
			esc_html( number_format_i18n( (int) ( $migration['count'] ?? 0 ) ) )
		);
		?>
	</p>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="<?php echo esc_attr( Settings_Tab::ACTION_MIGRATE ); ?>" />
	<?php wp_nonce_field( Settings_Tab::NONCE ); ?>
	<?php submit_button( __( 'Mark existing content public', 'baukasten-content-visibility' ), 'secondary', 'submit', false ); ?>
</form>
