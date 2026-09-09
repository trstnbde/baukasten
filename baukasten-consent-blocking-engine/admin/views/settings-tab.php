<?php
/**
 * Consent tab.
 *
 * @package Baukasten\ConsentBlockingEngine
 *
 * @var array<string, mixed>                                                    $settings   Current settings.
 * @var array<string, array{label: string, description: string, required: bool}> $categories Category definitions.
 * @var array<string, string>                                                   $map        Handle to category.
 * @var array<string, array{type: string, host: string}>                        $handles    Handles seen on the front end.
 * @var int                                                                     $log_count  Rows in the consent log.
 * @var array<int, array{label: string, changed: bool, detail: string}>         $report     Last hardening report, possibly empty.
 */

namespace Baukasten\ConsentBlockingEngine;

defined( 'ABSPATH' ) || exit;

$baukasten_cbe_optional = Categories::optional_slugs();

// Third-party assets first: they are the ones that need a decision, and a
// site can easily print forty first-party block stylesheets around them.
uksort(
	$handles,
	static function ( string $a, string $b ) use ( $handles ): int {
		$a_host = (string) ( $handles[ $a ]['host'] ?? '' );
		$b_host = (string) ( $handles[ $b ]['host'] ?? '' );

		if ( ( '' === $a_host ) !== ( '' === $b_host ) ) {
			return '' === $a_host ? 1 : -1;
		}

		if ( $a_host !== $b_host ) {
			return strnatcasecmp( $a_host, $b_host );
		}

		return strnatcasecmp( $a, $b );
	}
);

?>
<p class="baukasten-intro">
	<?php
	esc_html_e(
		'Everything below is blocked for every visitor, in every response, whether the page came from a cache or not. A small script in the browser reads the consent cookie afterwards and puts back what that visitor allowed.',
		'baukasten-consent-blocking-engine'
	);
	?>
</p>

<div class="notice notice-info inline">
	<p>
		<?php
		esc_html_e(
			'This plugin blocks and records. It does not display a consent banner — that is the job of a theme or a separate addon, which writes the visitor\'s decision through the REST endpoint below.',
			'baukasten-consent-blocking-engine'
		);
		?>
		<code>POST <?php echo esc_html( rest_url( REST_Controller::NAMESPACE . '/consent' ) ); ?></code>
	</p>
</div>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="<?php echo esc_attr( Settings_Tab::ACTION ); ?>" />
	<?php wp_nonce_field( Settings_Tab::NONCE ); ?>

	<h2><?php esc_html_e( 'What is blocked', 'baukasten-consent-blocking-engine' ); ?></h2>

	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Measures', 'baukasten-consent-blocking-engine' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Blocking measures', 'baukasten-consent-blocking-engine' ); ?></legend>

						<?php
						$baukasten_cbe_measures = array(
							'block_scripts'        => __( 'Scripts that need consent', 'baukasten-consent-blocking-engine' ),
							'block_styles'         => __( 'Stylesheets that need consent', 'baukasten-consent-blocking-engine' ),
							'block_script_modules' => __( 'ES modules, including Interactivity API blocks', 'baukasten-consent-blocking-engine' ),
							'block_resource_hints' => __( 'dns-prefetch and preconnect hints to other hosts', 'baukasten-consent-blocking-engine' ),
							'block_oembed'         => __( 'Embedded content from other providers', 'baukasten-consent-blocking-engine' ),
							'block_gravatar'       => __( 'Avatars from gravatar.com', 'baukasten-consent-blocking-engine' ),
							'block_emoji'          => __( 'The emoji script loaded from s.w.org', 'baukasten-consent-blocking-engine' ),
						);

						$baukasten_cbe_audit = array(
							'block_comment_ip'          => __( 'Storing the IP address of a commenter in the database', 'baukasten-consent-blocking-engine' ),
							'block_speculative_loading' => __( 'Speculative loading (prefetch and prerender rules)', 'baukasten-consent-blocking-engine' ),
							'block_dashboard_requests'  => __( 'Dashboard requests to api.wordpress.org (news widget, browser check)', 'baukasten-consent-blocking-engine' ),
							'warn_insecure_urls'        => __( 'Warn in the dashboard when the site is not served over HTTPS', 'baukasten-consent-blocking-engine' ),
						);

						foreach ( $baukasten_cbe_measures as $baukasten_cbe_key => $baukasten_cbe_label ) :
							?>
							<label for="baukasten-cbe-<?php echo esc_attr( $baukasten_cbe_key ); ?>">
								<input
									type="checkbox"
									id="baukasten-cbe-<?php echo esc_attr( $baukasten_cbe_key ); ?>"
									name="settings[<?php echo esc_attr( $baukasten_cbe_key ); ?>]"
									value="1"
									<?php checked( ! empty( $settings[ $baukasten_cbe_key ] ) ); ?>
								/>
								<?php echo esc_html( $baukasten_cbe_label ); ?>
							</label><br />
						<?php endforeach; ?>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Always off', 'baukasten-consent-blocking-engine' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Measures that are not consent gated', 'baukasten-consent-blocking-engine' ); ?></legend>

						<?php foreach ( $baukasten_cbe_audit as $baukasten_cbe_key => $baukasten_cbe_label ) : ?>
							<label for="baukasten-cbe-<?php echo esc_attr( $baukasten_cbe_key ); ?>">
								<input
									type="checkbox"
									id="baukasten-cbe-<?php echo esc_attr( $baukasten_cbe_key ); ?>"
									name="settings[<?php echo esc_attr( $baukasten_cbe_key ); ?>]"
									value="1"
									<?php checked( ! empty( $settings[ $baukasten_cbe_key ] ) ); ?>
								/>
								<?php echo esc_html( $baukasten_cbe_label ); ?>
							</label><br />
						<?php endforeach; ?>

						<p class="description">
							<?php
							esc_html_e(
								'Nothing above is consent gated. A commenter cannot usefully be asked whether their IP address may be written to the database, and nobody visits a dashboard to read WordPress.org news — so these are simply switched off.',
								'baukasten-consent-blocking-engine'
							);
							?>
						</p>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="baukasten-cbe-external-default">
						<?php esc_html_e( 'Assets from other hosts', 'baukasten-consent-blocking-engine' ); ?>
					</label>
				</th>
				<td>
					<select
						id="baukasten-cbe-external-default"
						name="settings[external_default]"
					>
						<?php foreach ( $baukasten_cbe_optional as $baukasten_cbe_slug ) : ?>
							<option
								value="<?php echo esc_attr( $baukasten_cbe_slug ); ?>"
								<?php selected( Categories::external_default(), $baukasten_cbe_slug ); ?>
							>
								<?php echo esc_html( $categories[ $baukasten_cbe_slug ]['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php
						esc_html_e(
							'A script or stylesheet loaded from a host other than this one is put in this category unless it is listed below. Anything served from this site counts as necessary.',
							'baukasten-consent-blocking-engine'
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="baukasten-cbe-oembed-category"><?php esc_html_e( 'Embeds', 'baukasten-consent-blocking-engine' ); ?></label>
				</th>
				<td>
					<select id="baukasten-cbe-oembed-category" name="settings[oembed_category]">
						<?php foreach ( $baukasten_cbe_optional as $baukasten_cbe_slug ) : ?>
							<option value="<?php echo esc_attr( $baukasten_cbe_slug ); ?>" <?php selected( $settings['oembed_category'], $baukasten_cbe_slug ); ?>>
								<?php echo esc_html( $categories[ $baukasten_cbe_slug ]['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="baukasten-cbe-gravatar-category"><?php esc_html_e( 'Avatars', 'baukasten-consent-blocking-engine' ); ?></label>
				</th>
				<td>
					<select id="baukasten-cbe-gravatar-category" name="settings[gravatar_category]">
						<?php foreach ( $baukasten_cbe_optional as $baukasten_cbe_slug ) : ?>
							<option value="<?php echo esc_attr( $baukasten_cbe_slug ); ?>" <?php selected( $settings['gravatar_category'], $baukasten_cbe_slug ); ?>>
								<?php echo esc_html( $categories[ $baukasten_cbe_slug ]['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="baukasten-cbe-policy-version"><?php esc_html_e( 'Policy version', 'baukasten-consent-blocking-engine' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						class="regular-text"
						id="baukasten-cbe-policy-version"
						name="settings[policy_version]"
						value="<?php echo esc_attr( (string) $settings['policy_version'] ); ?>"
					/>
					<p class="description">
						<?php
						esc_html_e(
							'Written into every logged decision. Change it whenever the wording of your banner or privacy policy changes, so consent given before and after can be told apart.',
							'baukasten-consent-blocking-engine'
						);
						?>
					</p>
				</td>
			</tr>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Categories', 'baukasten-consent-blocking-engine' ); ?></h2>

	<table class="widefat striped baukasten-consent-categories">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Category', 'baukasten-consent-blocking-engine' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Slug', 'baukasten-consent-blocking-engine' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Description', 'baukasten-consent-blocking-engine' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $categories as $baukasten_cbe_slug => $baukasten_cbe_category ) : ?>
				<tr>
					<th scope="row">
						<?php echo esc_html( $baukasten_cbe_category['label'] ); ?>
						<?php if ( ! empty( $baukasten_cbe_category['required'] ) ) : ?>
							<span class="baukasten-consent-required">
								<?php esc_html_e( 'always allowed', 'baukasten-consent-blocking-engine' ); ?>
							</span>
						<?php endif; ?>
					</th>
					<td><code><?php echo esc_html( $baukasten_cbe_slug ); ?></code></td>
					<td><?php echo esc_html( $baukasten_cbe_category['description'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p class="description">
		<?php
		echo wp_kses(
			__( 'Add or rename categories with the <code>baukasten/consent/categories</code> filter.', 'baukasten-consent-blocking-engine' ),
			array( 'code' => array() )
		);
		?>
	</p>

	<h2><?php esc_html_e( 'Assets on this site', 'baukasten-consent-blocking-engine' ); ?></h2>

	<p>
		<?php
		esc_html_e(
			'Every front end page adds the scripts and stylesheets it printed to this list. Open the pages that matter — the home page, a post with an embed, the checkout — and the list fills itself.',
			'baukasten-consent-blocking-engine'
		);
		?>
	</p>

	<?php if ( array() === $handles ) : ?>
		<div class="notice notice-info inline">
			<p><?php esc_html_e( 'Nothing recorded yet. Open the front end once and come back.', 'baukasten-consent-blocking-engine' ); ?></p>
		</div>
	<?php else : ?>
		<table class="widefat striped baukasten-consent-handles">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Handle', 'baukasten-consent-blocking-engine' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Type', 'baukasten-consent-blocking-engine' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Loaded from', 'baukasten-consent-blocking-engine' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Category', 'baukasten-consent-blocking-engine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $handles as $baukasten_cbe_handle => $baukasten_cbe_asset ) : ?>
					<?php
					$baukasten_cbe_host    = (string) ( $baukasten_cbe_asset['host'] ?? '' );
					$baukasten_cbe_current = $map[ $baukasten_cbe_handle ] ?? '';
					?>
					<tr>
						<th scope="row">
							<label for="baukasten-cbe-handle-<?php echo esc_attr( $baukasten_cbe_handle ); ?>">
								<code><?php echo esc_html( $baukasten_cbe_handle ); ?></code>
							</label>
						</th>
						<td>
							<?php
							echo esc_html(
								'style' === ( $baukasten_cbe_asset['type'] ?? '' )
									? __( 'Stylesheet', 'baukasten-consent-blocking-engine' )
									: __( 'Script', 'baukasten-consent-blocking-engine' )
							);
							?>
						</td>
						<td>
							<?php if ( '' === $baukasten_cbe_host ) : ?>
								<?php esc_html_e( 'this site', 'baukasten-consent-blocking-engine' ); ?>
							<?php else : ?>
								<strong><?php echo esc_html( $baukasten_cbe_host ); ?></strong>
							<?php endif; ?>
						</td>
						<td>
							<select
								id="baukasten-cbe-handle-<?php echo esc_attr( $baukasten_cbe_handle ); ?>"
								name="categories[<?php echo esc_attr( $baukasten_cbe_handle ); ?>]"
							>
								<option value="">
									<?php
									printf(
										/* translators: %s: category label. */
										esc_html__( 'Default (%s)', 'baukasten-consent-blocking-engine' ),
										esc_html( $categories[ '' === $baukasten_cbe_host ? Categories::NECESSARY : Categories::external_default() ]['label'] )
									);
									?>
								</option>
								<?php foreach ( $categories as $baukasten_cbe_slug => $baukasten_cbe_category ) : ?>
									<option value="<?php echo esc_attr( $baukasten_cbe_slug ); ?>" <?php selected( $baukasten_cbe_current, $baukasten_cbe_slug ); ?>>
										<?php echo esc_html( $baukasten_cbe_category['label'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Cookie overview', 'baukasten-consent-blocking-engine' ); ?></h2>

	<p>
		<?php
		esc_html_e(
			'A place to keep the cookie list your privacy policy needs. Nothing reads it automatically — no site can be scanned reliably from the inside, and a wrong list is worse than none.',
			'baukasten-consent-blocking-engine'
		);
		?>
	</p>

	<textarea
		class="large-text code"
		rows="8"
		name="settings[cookie_notes]"
		aria-label="<?php esc_attr_e( 'Cookie overview', 'baukasten-consent-blocking-engine' ); ?>"
	><?php echo esc_textarea( (string) $settings['cookie_notes'] ); ?></textarea>

	<p class="description">
		<?php
		printf(
			/* translators: %s: cookie name. */
			esc_html__( 'This plugin sets exactly one cookie itself: %s, which holds the visitor\'s decision, an anonymous id and a timestamp for twelve months.', 'baukasten-consent-blocking-engine' ),
			'<code>' . esc_html( Consent_State::COOKIE ) . '</code>'
		);
		?>
	</p>

	<?php submit_button( __( 'Save settings', 'baukasten-consent-blocking-engine' ) ); ?>
</form>

<h2><?php esc_html_e( 'Privacy hardening', 'baukasten-consent-blocking-engine' ); ?></h2>

<p>
	<?php
	esc_html_e(
		'Puts the whole site into a defensible default state in one go: closes comments and pings on every entry, writes the privacy related WordPress settings, empties the credentials on Settings → Connectors, and switches on every measure above. Safe to run again — each step reports whether it changed anything.',
		'baukasten-consent-blocking-engine'
	);
	?>
</p>

<div class="notice notice-warning inline">
	<p>
		<strong><?php esc_html_e( 'This changes the whole site, not just this plugin.', 'baukasten-consent-blocking-engine' ); ?></strong>
		<?php
		esc_html_e(
			'Comments are closed everywhere, registration is switched off, search engines are asked not to index the site, and every connector is disconnected. None of it is undone by deactivating this plugin.',
			'baukasten-consent-blocking-engine'
		);
		?>
	</p>
</div>

<?php if ( array() !== $report ) : ?>
	<table class="widefat striped baukasten-consent-report">
		<caption class="screen-reader-text"><?php esc_html_e( 'Result of the last hardening run', 'baukasten-consent-blocking-engine' ); ?></caption>
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Step', 'baukasten-consent-blocking-engine' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Result', 'baukasten-consent-blocking-engine' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Detail', 'baukasten-consent-blocking-engine' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $report as $baukasten_cbe_row ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( (string) $baukasten_cbe_row['label'] ); ?></th>
					<td>
						<?php
						echo esc_html(
							! empty( $baukasten_cbe_row['changed'] )
								? __( 'changed', 'baukasten-consent-blocking-engine' )
								: __( 'no change', 'baukasten-consent-blocking-engine' )
						);
						?>
					</td>
					<td><?php echo esc_html( (string) $baukasten_cbe_row['detail'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<form
	method="post"
	action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
	onsubmit="return window.confirm( <?php echo wp_json_encode( __( 'This closes comments on every entry, asks search engines not to index the site and empties all connector credentials. Continue?', 'baukasten-consent-blocking-engine' ) ); ?> );"
>
	<input type="hidden" name="action" value="<?php echo esc_attr( Settings_Tab::ACTION_HARDEN ); ?>" />
	<?php wp_nonce_field( Settings_Tab::NONCE ); ?>
	<?php submit_button( __( 'Apply privacy hardening and defaults', 'baukasten-consent-blocking-engine' ), 'secondary', 'submit', false ); ?>
	<p class="description">
		<?php
		printf(
			/* translators: %s: WP-CLI command. */
			esc_html__( 'The same routine runs from the command line as %s.', 'baukasten-consent-blocking-engine' ),
			'<code>wp baukasten harden</code>'
		);
		?>
	</p>
</form>

<h2><?php esc_html_e( 'Consent log', 'baukasten-consent-blocking-engine' ); ?></h2>

<p>
	<?php
	printf(
		/* translators: %s: number of rows. */
		esc_html__( 'The log holds %s decisions. Every change writes a new row; nothing is ever overwritten, so the history can be reconstructed. It contains no IP address, no user id and no other personal data.', 'baukasten-consent-blocking-engine' ),
		esc_html( number_format_i18n( $log_count ) )
	);
	?>
</p>

<div class="notice notice-warning inline">
	<p>
		<strong><?php esc_html_e( 'Export before you delete this plugin.', 'baukasten-consent-blocking-engine' ); ?></strong>
		<?php
		esc_html_e(
			'Deleting it drops the log table, and with it the record you would need to demonstrate that consent was given.',
			'baukasten-consent-blocking-engine'
		);
		?>
	</p>
</div>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="<?php echo esc_attr( Settings_Tab::ACTION_EXPORT ); ?>" />
	<?php wp_nonce_field( Settings_Tab::NONCE ); ?>
	<button type="submit" class="button" name="format" value="csv"><?php esc_html_e( 'Export as CSV', 'baukasten-consent-blocking-engine' ); ?></button>
	<button type="submit" class="button" name="format" value="json"><?php esc_html_e( 'Export as JSON', 'baukasten-consent-blocking-engine' ); ?></button>
</form>

<h2><?php esc_html_e( 'Maintenance', 'baukasten-consent-blocking-engine' ); ?></h2>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="<?php echo esc_attr( Settings_Tab::ACTION_RESCAN ); ?>" />
	<?php wp_nonce_field( Settings_Tab::NONCE ); ?>
	<?php submit_button( __( 'Clear the asset list', 'baukasten-consent-blocking-engine' ), 'secondary', 'submit', false ); ?>
	<p class="description">
		<?php
		esc_html_e(
			'Forgets every handle recorded so far. The categories you assigned are kept. Useful after removing a plugin whose handles are still listed.',
			'baukasten-consent-blocking-engine'
		);
		?>
	</p>
</form>
