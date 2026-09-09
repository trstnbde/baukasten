<?php
/**
 * Login & Legal tab.
 *
 * @package Baukasten\LoginLegalPages
 *
 * @var string[] $unreachable  Titles of selected pages visitors cannot open.
 * @var string   $slug         Configured login slug.
 * @var string   $login_url    Full login URL.
 * @var bool     $permalinks   Whether pretty permalinks are on.
 * @var string   $slug_taken   Title of a published post using the same slug.
 * @var int      $privacy_page ID of the privacy policy page, or 0.
 */

namespace Baukasten\LoginLegalPages;

defined( 'ABSPATH' ) || exit;

?>
<p class="baukasten-intro">
	<?php
	esc_html_e(
		'The login screen links to your privacy policy, terms and imprint, and is served from a readable address. Its WordPress header and the link back to the site are removed.',
		'baukasten-login-legal-pages'
	);
	?>
</p>

<?php if ( ! empty( $unreachable ) ) : ?>
	<div class="notice notice-warning inline">
		<p>
			<strong>
				<?php esc_html_e( 'Some selected pages are not reachable for logged-out visitors:', 'baukasten-login-legal-pages' ); ?>
			</strong>
		</p>
		<ul class="ul-disc">
			<?php foreach ( $unreachable as $baukasten_llp_title ) : ?>
				<li><?php echo esc_html( $baukasten_llp_title ); ?></li>
			<?php endforeach; ?>
		</ul>
		<p>
			<?php
			esc_html_e(
				'A visitor following one of those links from the login screen is sent straight back to the login form. Legal pages should normally be publicly visible.',
				'baukasten-login-legal-pages'
			);
			?>
		</p>
	</div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="<?php echo esc_attr( Settings_Tab::ACTION ); ?>" />
	<?php wp_nonce_field( Settings_Tab::NONCE ); ?>

	<h2><?php esc_html_e( 'Legal pages', 'baukasten-login-legal-pages' ); ?></h2>

	<p>
		<?php
		esc_html_e( 'These pages are linked below the login form. A link only appears once a published page is selected.', 'baukasten-login-legal-pages' );
		?>
	</p>

	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Privacy policy', 'baukasten-login-legal-pages' ); ?>
				</th>
				<td>
					<p>
						<?php if ( $privacy_page > 0 ) : ?>
							<strong><?php echo esc_html( get_the_title( $privacy_page ) ); ?></strong>
						<?php else : ?>
							<em><?php esc_html_e( 'No page selected.', 'baukasten-login-legal-pages' ); ?></em>
						<?php endif; ?>
					</p>
					<p class="description">
						<?php
						echo wp_kses(
							sprintf(
								/* translators: %s: link to the privacy settings screen. */
								__( 'WordPress stores this one itself, on <a href="%s">Settings &rsaquo; Privacy</a>. Shown as the first link.', 'baukasten-login-legal-pages' ),
								esc_url( admin_url( 'options-privacy.php' ) )
							),
							array( 'a' => array( 'href' => array() ) )
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="<?php echo esc_attr( Legal_Pages::OPTION_TERMS ); ?>">
						<?php esc_html_e( 'Terms of service', 'baukasten-login-legal-pages' ); ?>
					</label>
				</th>
				<td>
					<?php
					// wp_dropdown_pages() prints an escaped <select>; core calls it
					// the same way for the privacy policy picker.
					// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
					wp_dropdown_pages(
						array(
							'name'              => Legal_Pages::OPTION_TERMS,
							'id'                => Legal_Pages::OPTION_TERMS,
							'show_option_none'  => __( '&mdash; Select &mdash;', 'baukasten-login-legal-pages' ),
							'option_none_value' => '0',
							'selected'          => Legal_Pages::get_page_id( Legal_Pages::OPTION_TERMS ),
							'post_status'       => array( 'draft', 'publish' ),
						)
					);
					// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
					<p class="description"><?php esc_html_e( 'Shown as the second link.', 'baukasten-login-legal-pages' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="<?php echo esc_attr( Legal_Pages::OPTION_IMPRINT ); ?>">
						<?php esc_html_e( 'Imprint', 'baukasten-login-legal-pages' ); ?>
					</label>
				</th>
				<td>
					<?php
					// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
					wp_dropdown_pages(
						array(
							'name'              => Legal_Pages::OPTION_IMPRINT,
							'id'                => Legal_Pages::OPTION_IMPRINT,
							'show_option_none'  => __( '&mdash; Select &mdash;', 'baukasten-login-legal-pages' ),
							'option_none_value' => '0',
							'selected'          => Legal_Pages::get_page_id( Legal_Pages::OPTION_IMPRINT ),
							'post_status'       => array( 'draft', 'publish' ),
						)
					);
					// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
					<p class="description"><?php esc_html_e( 'Shown as the third link.', 'baukasten-login-legal-pages' ); ?></p>
				</td>
			</tr>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Login address', 'baukasten-login-legal-pages' ); ?></h2>

	<?php if ( ! $permalinks ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: link to the permalink settings screen. */
						__( 'The login address needs pretty permalinks. Until they are switched on under <a href="%s">Settings &rsaquo; Permalinks</a>, the login screen stays on <code>wp-login.php</code>.', 'baukasten-login-legal-pages' ),
						esc_url( admin_url( 'options-permalink.php' ) )
					),
					array(
						'a'    => array( 'href' => array() ),
						'code' => array(),
					)
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $slug_taken ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<?php
				printf(
					/* translators: %s: post title. */
					esc_html__( 'The content “%s” uses the same address and cannot be opened while the login screen answers there. Pick a different login address, or change that content\'s permalink.', 'baukasten-login-legal-pages' ),
					esc_html( $slug_taken )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row">
					<label for="<?php echo esc_attr( Login_URL::OPTION_SLUG ); ?>">
						<?php esc_html_e( 'Login address', 'baukasten-login-legal-pages' ); ?>
					</label>
				</th>
				<td>
					<code><?php echo esc_html( trailingslashit( home_url( '/' ) ) ); ?></code>
					<input
						type="text"
						class="regular-text code"
						id="<?php echo esc_attr( Login_URL::OPTION_SLUG ); ?>"
						name="<?php echo esc_attr( Login_URL::OPTION_SLUG ); ?>"
						value="<?php echo esc_attr( $slug ); ?>"
					/>
					<code>/</code>

					<p class="description">
						<?php
						printf(
							/* translators: %s: the login URL. */
							esc_html__( 'Every login link and form now points at %s. Opening wp-login.php redirects there; it is not blocked, so nobody can be locked out.', 'baukasten-login-legal-pages' ),
							esc_url( $login_url )
						);
						?>
					</p>
				</td>
			</tr>
		</tbody>
	</table>

	<?php submit_button( __( 'Save settings', 'baukasten-login-legal-pages' ) ); ?>
</form>
