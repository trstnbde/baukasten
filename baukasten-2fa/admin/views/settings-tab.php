<?php
/**
 * Two-Factor tab.
 *
 * @package Baukasten\TwoFactor
 *
 * @var array<string, mixed> $settings Current settings.
 * @var bool                 $blocked  Whether Two Factor's allowlist filters this provider out.
 */

namespace Baukasten\TwoFactor;

defined( 'ABSPATH' ) || exit;

?>
<p class="baukasten-intro">
	<?php esc_html_e( 'Adds a second factor that asks another browser instead of asking for a code: when someone signs in, every other session that is already signed in shows a confirmation in its admin bar.', 'baukasten-2fa' ); ?>
</p>

<?php if ( ! class_exists( 'Two_Factor_Core' ) ) : ?>
	<div class="notice notice-warning inline">
		<p><?php esc_html_e( 'The Two Factor plugin is not active, so this method is not being offered to anyone.', 'baukasten-2fa' ); ?></p>
	</div>
<?php elseif ( $blocked ) : ?>
	<div class="notice notice-warning inline">
		<p><?php esc_html_e( 'The Two Factor settings list which methods this site allows, and this one is not on it. Until it is, nobody can select it on their profile.', 'baukasten-2fa' ); ?></p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( Settings_Tab::allow_url() ); ?>">
				<?php esc_html_e( 'Allow this method site-wide', 'baukasten-2fa' ); ?>
			</a>
		</p>
	</div>
<?php endif; ?>

<h2><?php esc_html_e( 'Timing', 'baukasten-2fa' ); ?></h2>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="<?php echo esc_attr( Settings_Tab::ACTION ); ?>" />
	<?php wp_nonce_field( Settings_Tab::NONCE ); ?>

	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row">
					<label for="baukasten-2fa-expiry"><?php esc_html_e( 'How long a request waits', 'baukasten-2fa' ); ?></label>
				</th>
				<td>
					<input
						type="number"
						class="small-text"
						id="baukasten-2fa-expiry"
						name="settings[expiry]"
						min="<?php echo esc_attr( (string) Settings::MIN_EXPIRY ); ?>"
						max="<?php echo esc_attr( (string) Settings::MAX_EXPIRY ); ?>"
						step="10"
						value="<?php echo esc_attr( (string) $settings['expiry'] ); ?>"
					/>
					<?php esc_html_e( 'seconds', 'baukasten-2fa' ); ?>
					<p class="description">
						<?php
						esc_html_e(
							'The other browser learns about the request through the WordPress heartbeat, which ticks about once a minute and slows down further in a tab that has been idle for a while. Allow for up to a minute before the confirmation appears — if people report that requests expire before they see them, raise this.',
							'baukasten-2fa'
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="baukasten-2fa-poll"><?php esc_html_e( 'Check every', 'baukasten-2fa' ); ?></label>
				</th>
				<td>
					<input
						type="number"
						class="small-text"
						id="baukasten-2fa-poll"
						name="settings[poll_interval]"
						min="2"
						max="30"
						value="<?php echo esc_attr( (string) $settings['poll_interval'] ); ?>"
					/>
					<?php esc_html_e( 'seconds', 'baukasten-2fa' ); ?>
					<p class="description">
						<?php esc_html_e( 'Only while a request is actually open. The rest of the time nothing is polled beyond the heartbeat that runs anyway.', 'baukasten-2fa' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Requests per user', 'baukasten-2fa' ); ?></th>
				<td>
					<label>
						<input
							type="number"
							class="small-text"
							name="settings[rate_limit]"
							min="1"
							max="100"
							value="<?php echo esc_attr( (string) $settings['rate_limit'] ); ?>"
						/>
						<?php esc_html_e( 'requests', 'baukasten-2fa' ); ?>
					</label>
					<label>
						<?php esc_html_e( 'within', 'baukasten-2fa' ); ?>
						<input
							type="number"
							class="small-text"
							name="settings[rate_window]"
							min="60"
							max="86400"
							step="60"
							value="<?php echo esc_attr( (string) $settings['rate_window'] ); ?>"
						/>
						<?php esc_html_e( 'seconds', 'baukasten-2fa' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Stops repeated sign-in attempts from filling somebody\'s admin bar with confirmations.', 'baukasten-2fa' ); ?>
					</p>
				</td>
			</tr>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'What the prompt shows', 'baukasten-2fa' ); ?></h2>

	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Context', 'baukasten-2fa' ); ?></th>
				<td>
					<label>
						<input
							type="checkbox"
							name="settings[show_context]"
							value="1"
							<?php checked( (bool) $settings['show_context'] ); ?>
						/>
						<?php esc_html_e( 'Show where the sign-in came from', 'baukasten-2fa' ); ?>
					</label>
					<p class="description">
						<?php
						esc_html_e(
							'A shortened address and a rough browser name, so the question "was that you?" can actually be answered. The full address and the full browser string are never stored.',
							'baukasten-2fa'
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Idle tabs', 'baukasten-2fa' ); ?></th>
				<td>
					<label>
						<input
							type="checkbox"
							name="settings[heartbeat_keepalive]"
							value="1"
							<?php checked( (bool) $settings['heartbeat_keepalive'] ); ?>
						/>
						<?php esc_html_e( 'Keep checking in an idle wp-admin tab', 'baukasten-2fa' ); ?>
					</label>
					<p class="description">
						<?php
						esc_html_e(
							'WordPress stops its heartbeat in a tab nobody has touched for a few minutes — which is usually exactly the tab that should be showing the confirmation. This keeps it going in the dashboard only, never on the public site.',
							'baukasten-2fa'
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="baukasten-2fa-retention"><?php esc_html_e( 'Keep records for', 'baukasten-2fa' ); ?></label>
				</th>
				<td>
					<input
						type="number"
						class="small-text"
						id="baukasten-2fa-retention"
						name="settings[retention_hours]"
						min="1"
						max="720"
						value="<?php echo esc_attr( (string) $settings['retention_hours'] ); ?>"
					/>
					<?php esc_html_e( 'hours', 'baukasten-2fa' ); ?>
					<p class="description">
						<?php esc_html_e( 'Answered and expired requests are deleted after this. They are kept at all only so repeated requests can be counted.', 'baukasten-2fa' ); ?>
					</p>
				</td>
			</tr>
		</tbody>
	</table>

	<?php submit_button(); ?>
</form>

<h2><?php esc_html_e( 'Turning it on', 'baukasten-2fa' ); ?></h2>

<p>
	<?php
	printf(
		/* translators: %s: link to the current user's profile screen. */
		esc_html__( 'Each person switches this on for themselves under Two-Factor Options on their %s.', 'baukasten-2fa' ),
		sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'profile.php#two-factor-options' ) ),
			esc_html__( 'profile', 'baukasten-2fa' )
		)
	);
	?>
</p>

<p class="description">
	<?php
	esc_html_e(
		'Worth keeping a second method switched on as well. If no other session is open when a request goes out, this one simply waits until it expires — the sign-in page then offers the other methods.',
		'baukasten-2fa'
	);
	?>
</p>
