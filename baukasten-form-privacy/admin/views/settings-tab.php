<?php
/**
 * Form Privacy tab.
 *
 * @package Baukasten\FormPrivacy
 *
 * @var array<string, mixed>                                            $settings     Current settings.
 * @var bool                                                            $has_flamingo Whether Flamingo is active.
 * @var array{count: int, oldest: string}                               $stats        Stored messages.
 * @var int                                                             $contacts     Contacts left in the address book.
 * @var int                                                             $next_purge   Next retention run, Unix time, or 0.
 * @var array<int, array{label: string, changed: bool, detail: string}> $report       Last cleanup report, possibly empty.
 */

namespace Baukasten\FormPrivacy;

defined( 'ABSPATH' ) || exit;

$baukasten_fp_switches = array(
	'assets_on_demand' => array(
		__( 'Load Contact Form 7 only where a form is', 'baukasten-form-privacy' ),
		__( 'Its script and stylesheet load only on pages that show a form. Cloudflare Turnstile and Google reCAPTCHA, if set up, are kept off every page without one.', 'baukasten-form-privacy' ),
	),
	'strip_ip'         => array(
		__( 'Do not collect the sender’s IP address', 'baukasten-form-privacy' ),
		__( 'Contact Form 7 never sees the address. Side effects: IP rules on the Disallowed Comment Keys list stop matching, [_remote_ip] stays empty in mails, and the address is no longer part of the duplicate check.', 'baukasten-form-privacy' ),
	),
	'honeypot'         => array(
		__( 'Spam protection with a honeypot and a minimum fill time', 'baukasten-form-privacy' ),
		__( 'An invisible field that bots fill in, and a signed timestamp that catches forms sent back faster than a person could type. No request to anybody, no cookie, no JavaScript. It does not stop spam sent by hand.', 'baukasten-form-privacy' ),
	),
);

$baukasten_fp_storage = array(
	'store_submissions' => array(
		__( 'Store submissions in Flamingo', 'baukasten-form-privacy' ),
		__( 'Off: messages are only mailed, and the site keeps no copy.', 'baukasten-form-privacy' ),
	),
	'store_spam'        => array(
		__( 'Store submissions recognised as spam', 'baukasten-form-privacy' ),
		__( 'Off: spam is dropped instead of kept for review.', 'baukasten-form-privacy' ),
	),
);

$baukasten_fp_meta_labels = array(
	'serial_number'     => __( 'Serial number', 'baukasten-form-privacy' ),
	'remote_ip'         => __( 'IP address', 'baukasten-form-privacy' ),
	'user_agent'        => __( 'Browser (user agent)', 'baukasten-form-privacy' ),
	'url'               => __( 'Page address', 'baukasten-form-privacy' ),
	'date'              => __( 'Date', 'baukasten-form-privacy' ),
	'time'              => __( 'Time', 'baukasten-form-privacy' ),
	'post_id'           => __( 'Entry ID', 'baukasten-form-privacy' ),
	'post_name'         => __( 'Entry slug', 'baukasten-form-privacy' ),
	'post_title'        => __( 'Entry title', 'baukasten-form-privacy' ),
	'post_url'          => __( 'Entry address', 'baukasten-form-privacy' ),
	'post_author'       => __( 'Entry author', 'baukasten-form-privacy' ),
	'post_author_email' => __( 'Entry author’s email', 'baukasten-form-privacy' ),
	'site_title'        => __( 'Site title', 'baukasten-form-privacy' ),
	'site_description'  => __( 'Site tagline', 'baukasten-form-privacy' ),
	'site_url'          => __( 'Site address', 'baukasten-form-privacy' ),
	'site_admin_email'  => __( 'Site admin email', 'baukasten-form-privacy' ),
	'user_login'        => __( 'Logged-in user’s login', 'baukasten-form-privacy' ),
	'user_email'        => __( 'Logged-in user’s email', 'baukasten-form-privacy' ),
	'user_display_name' => __( 'Logged-in user’s name', 'baukasten-form-privacy' ),
);

$baukasten_fp_whitelist = Settings::meta_whitelist();

?>
<p class="baukasten-intro">
	<?php
	esc_html_e(
		'Contact Form 7 and Flamingo without the leaks: form assets only where a form is, no IP address, no address book, a retention period, an entry in the personal data export, and spam protection that asks nobody.',
		'baukasten-form-privacy'
	);
	?>
</p>

<?php if ( ! $has_flamingo ) : ?>
	<div class="notice notice-info inline">
		<p>
			<?php esc_html_e( 'Flamingo is not active, so no submission is stored on this site. The storage, retention and cleanup settings appear once it is.', 'baukasten-form-privacy' ); ?>
		</p>
	</div>
<?php else : ?>
	<div class="notice notice-info inline">
		<p>
			<strong><?php esc_html_e( 'Flamingo’s address book is switched off.', 'baukasten-form-privacy' ); ?></strong>
			<?php esc_html_e( 'No contact is collected from forms, users or comments any more, and the Address Book screen is gone. That is not a setting.', 'baukasten-form-privacy' ); ?>
		</p>
	</div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="<?php echo esc_attr( Settings_Tab::ACTION_SAVE ); ?>" />
	<?php wp_nonce_field( Settings_Tab::NONCE ); ?>

	<table class="form-table" role="presentation">
		<tbody>
			<?php foreach ( $baukasten_fp_switches as $baukasten_fp_key => $baukasten_fp_text ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $baukasten_fp_text[0] ); ?></th>
					<td>
						<input type="hidden" name="settings[<?php echo esc_attr( $baukasten_fp_key ); ?>]" value="0" />
						<label for="baukasten-fp-<?php echo esc_attr( $baukasten_fp_key ); ?>">
							<input
								type="checkbox"
								id="baukasten-fp-<?php echo esc_attr( $baukasten_fp_key ); ?>"
								name="settings[<?php echo esc_attr( $baukasten_fp_key ); ?>]"
								value="1"
								<?php checked( ! empty( $settings[ $baukasten_fp_key ] ) ); ?>
							/>
							<?php esc_html_e( 'On', 'baukasten-form-privacy' ); ?>
						</label>
						<p class="description"><?php echo esc_html( $baukasten_fp_text[1] ); ?></p>
					</td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th scope="row">
					<label for="baukasten-fp-min-fill"><?php esc_html_e( 'Minimum fill time', 'baukasten-form-privacy' ); ?></label>
				</th>
				<td>
					<input
						type="number"
						class="small-text"
						id="baukasten-fp-min-fill"
						name="settings[min_fill_seconds]"
						min="0"
						max="600"
						value="<?php echo esc_attr( (string) Settings::min_fill_seconds() ); ?>"
					/>
					<?php esc_html_e( 'seconds', 'baukasten-form-privacy' ); ?>
				</td>
			</tr>

			<?php if ( $has_flamingo ) : ?>
				<?php foreach ( $baukasten_fp_storage as $baukasten_fp_key => $baukasten_fp_text ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $baukasten_fp_text[0] ); ?></th>
						<td>
							<input type="hidden" name="settings[<?php echo esc_attr( $baukasten_fp_key ); ?>]" value="0" />
							<label for="baukasten-fp-<?php echo esc_attr( $baukasten_fp_key ); ?>">
								<input
									type="checkbox"
									id="baukasten-fp-<?php echo esc_attr( $baukasten_fp_key ); ?>"
									name="settings[<?php echo esc_attr( $baukasten_fp_key ); ?>]"
									value="1"
									<?php checked( ! empty( $settings[ $baukasten_fp_key ] ) ); ?>
								/>
								<?php esc_html_e( 'On', 'baukasten-form-privacy' ); ?>
							</label>
							<p class="description"><?php echo esc_html( $baukasten_fp_text[1] ); ?></p>
						</td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row">
						<label for="baukasten-fp-retention"><?php esc_html_e( 'Retention period', 'baukasten-form-privacy' ); ?></label>
					</th>
					<td>
						<input
							type="number"
							class="small-text"
							id="baukasten-fp-retention"
							name="settings[retention_days]"
							min="1"
							max="3650"
							value="<?php echo esc_attr( (string) Settings::retention_days() ); ?>"
						/>
						<?php esc_html_e( 'days', 'baukasten-form-privacy' ); ?>
						<p class="description">
							<?php esc_html_e( 'Older messages are deleted for good once a day, spam and trash included. WP-Cron only runs when the site has visitors, so a server cron job keeps this punctual.', 'baukasten-form-privacy' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Metadata kept with a message', 'baukasten-form-privacy' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Metadata kept with a message', 'baukasten-form-privacy' ); ?></legend>
							<?php foreach ( $baukasten_fp_meta_labels as $baukasten_fp_key => $baukasten_fp_label ) : ?>
								<label for="baukasten-fp-meta-<?php echo esc_attr( $baukasten_fp_key ); ?>">
									<input
										type="checkbox"
										id="baukasten-fp-meta-<?php echo esc_attr( $baukasten_fp_key ); ?>"
										name="settings[meta_whitelist][]"
										value="<?php echo esc_attr( $baukasten_fp_key ); ?>"
										<?php checked( in_array( $baukasten_fp_key, $baukasten_fp_whitelist, true ) ); ?>
									/>
									<?php echo esc_html( $baukasten_fp_label ); ?>
									<code><?php echo esc_html( '_' . $baukasten_fp_key ); ?></code>
								</label><br />
							<?php endforeach; ?>
							<p class="description">
								<?php esc_html_e( 'Everything else Contact Form 7 hands Flamingo is dropped, and so are the raw answers of Akismet, reCAPTCHA and Turnstile.', 'baukasten-form-privacy' ); ?>
							</p>
							<p class="description">
								<?php
								echo wp_kses(
									__( 'Contact Form 7 has its own means as well: <code>do_not_store: true</code> under a form’s additional settings keeps that whole form out of Flamingo, and the form-tag option <code>do-not-store</code> keeps a single field out.', 'baukasten-form-privacy' ),
									array( 'code' => array() )
								);
								?>
							</p>
						</fieldset>
					</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<?php submit_button( __( 'Save settings', 'baukasten-form-privacy' ) ); ?>
</form>

<?php if ( $has_flamingo ) : ?>
	<h2><?php esc_html_e( 'What is stored', 'baukasten-form-privacy' ); ?></h2>

	<table class="widefat striped baukasten-fp-stats">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Stored messages', 'baukasten-form-privacy' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( $stats['count'] ) ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Oldest message', 'baukasten-form-privacy' ); ?></th>
				<td>
					<?php
					echo esc_html(
						'' === $stats['oldest']
							? __( 'none', 'baukasten-form-privacy' )
							: wp_date( (string) get_option( 'date_format' ), (int) strtotime( $stats['oldest'] . ' UTC' ) )
					);
					?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Contacts left in the address book', 'baukasten-form-privacy' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( $contacts ) ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Next retention run', 'baukasten-form-privacy' ); ?></th>
				<td>
					<?php
					echo esc_html(
						0 === $next_purge
							? __( 'not scheduled', 'baukasten-form-privacy' )
							: wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_purge )
					);
					?>
				</td>
			</tr>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Clean up what is already stored', 'baukasten-form-privacy' ); ?></h2>

	<p>
		<?php esc_html_e( 'The settings above only change what is stored from now on. The cleanup deals with what is already there: it deletes every address book contact and contact tag, reduces the metadata of every stored message to the list above, and applies the retention period right away. Each step reports whether it changed anything; a second run should report no change everywhere. It never runs on its own.', 'baukasten-form-privacy' ); ?>
	</p>

	<?php if ( array() !== $report ) : ?>
		<table class="widefat striped baukasten-fp-report">
			<caption class="screen-reader-text"><?php esc_html_e( 'Result of the last cleanup', 'baukasten-form-privacy' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Step', 'baukasten-form-privacy' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Result', 'baukasten-form-privacy' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Detail', 'baukasten-form-privacy' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $report as $baukasten_fp_row ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( (string) $baukasten_fp_row['label'] ); ?></th>
						<td>
							<?php
							echo esc_html(
								! empty( $baukasten_fp_row['changed'] )
									? __( 'changed', 'baukasten-form-privacy' )
									: __( 'no change', 'baukasten-form-privacy' )
							);
							?>
						</td>
						<td><?php echo esc_html( (string) $baukasten_fp_row['detail'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<form
		method="post"
		action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
		onsubmit="return window.confirm( <?php echo esc_attr( (string) wp_json_encode( __( 'This deletes every Flamingo contact and every message older than the retention period for good, and strips metadata from the rest. Deleted data cannot be brought back. Continue?', 'baukasten-form-privacy' ) ) ); ?> );"
	>
		<input type="hidden" name="action" value="<?php echo esc_attr( Settings_Tab::ACTION_CLEAN ); ?>" />
		<?php wp_nonce_field( Settings_Tab::NONCE ); ?>
		<?php submit_button( __( 'Clean up now', 'baukasten-form-privacy' ), 'secondary', 'submit', false ); ?>
		<p class="description">
			<?php
			printf(
				/* translators: %s: WP-CLI command. */
				esc_html__( 'The same routine runs from the command line as %s.', 'baukasten-form-privacy' ),
				'<code>wp baukasten form-privacy clean</code>'
			);
			?>
		</p>
	</form>
<?php endif; ?>
