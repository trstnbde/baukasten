<?php
/**
 * Wishlist tab.
 *
 * @package Baukasten\Wishlist
 *
 * @var array<int, array<string, mixed>> $baukasten_wl_lists    Every list with its counts.
 * @var string                           $baukasten_wl_currency Currency symbol.
 * @var int                              $baukasten_wl_limit    Reports per address per window.
 */

namespace Baukasten\Wishlist;

defined( 'ABSPATH' ) || exit;

?>
<p class="baukasten-intro">
	<?php esc_html_e( 'A wishlist is one occasion for one person: a birthday, Christmas, a wedding. Its wishes carry an image, a quantity, a price, a priority and a shop address. The "Wishlist" block or the shortcode shows the list in any page or post, where guests sort, filter and mark what they bought — anonymously, one click, and undoable from the same browser.', 'baukasten-wishlist' ); ?>
</p>

<h2><?php esc_html_e( 'Lists', 'baukasten-wishlist' ); ?></h2>

<?php if ( array() === $baukasten_wl_lists ) : ?>
	<p><?php esc_html_e( 'There is no list yet.', 'baukasten-wishlist' ); ?></p>
<?php else : ?>
	<table class="widefat striped">
		<caption class="screen-reader-text"><?php esc_html_e( 'Wishlists', 'baukasten-wishlist' ); ?></caption>
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'List', 'baukasten-wishlist' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Date', 'baukasten-wishlist' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Wishes', 'baukasten-wishlist' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Shortcode', 'baukasten-wishlist' ); ?></th>
				<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'baukasten-wishlist' ); ?></span></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $baukasten_wl_lists as $baukasten_wl_list ) : ?>
				<tr>
					<td>
						<a href="<?php echo esc_url( (string) get_edit_post_link( (int) $baukasten_wl_list['id'] ) ); ?>"><strong><?php echo esc_html( (string) $baukasten_wl_list['title'] ); ?></strong></a>
						<?php if ( 'publish' !== $baukasten_wl_list['status'] ) : ?>
							— <span class="post-state"><?php echo esc_html( (string) get_post_status_object( (string) $baukasten_wl_list['status'] )?->label ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( '' === $baukasten_wl_list['fields']['date'] ? '—' : (string) mysql2date( (string) get_option( 'date_format' ), $baukasten_wl_list['fields']['date'] ) ); ?></td>
					<td>
						<?php
						printf(
							/* translators: 1: number of wishes. 2: things bought. 3: things wished for. */
							esc_html__( '%1$s (%2$s of %3$s bought)', 'baukasten-wishlist' ),
							esc_html( number_format_i18n( (int) $baukasten_wl_list['wishes'] ) ),
							esc_html( number_format_i18n( (int) $baukasten_wl_list['bought'] ) ),
							esc_html( number_format_i18n( (int) $baukasten_wl_list['total'] ) )
						);
						?>
					</td>
					<td><code><?php echo esc_html( Shortcode::for_list( (int) $baukasten_wl_list['id'] ) ); ?></code></td>
					<td>
						<?php if ( 0 < (int) $baukasten_wl_list['bought'] ) : ?>
							<form
								method="post"
								action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
								onsubmit="return window.confirm( <?php echo esc_attr( (string) wp_json_encode( __( 'Mark every wish on this list as not bought again? Guests who reported a purchase can no longer take it back.', 'baukasten-wishlist' ) ) ); ?> );"
							>
								<input type="hidden" name="action" value="<?php echo esc_attr( Settings_Tab::ACTION_RESET_LIST ); ?>" />
								<input type="hidden" name="list" value="<?php echo esc_attr( (string) $baukasten_wl_list['id'] ); ?>" />
								<?php wp_nonce_field( Settings_Tab::NONCE ); ?>
								<?php submit_button( __( 'Reset purchases', 'baukasten-wishlist' ), 'secondary small', 'submit', false ); ?>
							</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<p>
	<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Post_Types::LIST_TYPE ) ); ?>"><?php esc_html_e( 'Add list', 'baukasten-wishlist' ); ?></a>
	<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Post_Types::ITEM_TYPE ) ); ?>"><?php esc_html_e( 'All wishes', 'baukasten-wishlist' ); ?></a>
</p>

<h2><?php esc_html_e( 'Settings', 'baukasten-wishlist' ); ?></h2>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="<?php echo esc_attr( Settings_Tab::ACTION_SAVE ); ?>" />
	<?php wp_nonce_field( Settings_Tab::NONCE ); ?>

	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row"><label for="baukasten-wl-currency"><?php esc_html_e( 'Currency', 'baukasten-wishlist' ); ?></label></th>
				<td>
					<input type="text" class="small-text" id="baukasten-wl-currency" name="currency" value="<?php echo esc_attr( $baukasten_wl_currency ); ?>" maxlength="8" />
					<p class="description"><?php esc_html_e( 'Printed after every price, such as "€" or "CHF".', 'baukasten-wishlist' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="baukasten-wl-limit"><?php esc_html_e( 'Reports per connection', 'baukasten-wishlist' ); ?></label></th>
				<td>
					<input type="number" class="small-text" id="baukasten-wl-limit" name="rate_limit" value="<?php echo esc_attr( (string) $baukasten_wl_limit ); ?>" min="0" max="1000" step="1" />
					<p class="description"><?php esc_html_e( 'How many purchases one address may report or take back within ten minutes, so nobody can tick off a whole list for fun. 0 switches the limit off. The address is only used as a hash and never stored.', 'baukasten-wishlist' ); ?></p>
				</td>
			</tr>
		</tbody>
	</table>

	<?php submit_button( __( 'Save settings', 'baukasten-wishlist' ) ); ?>
</form>

<h2><?php esc_html_e( 'Privacy', 'baukasten-wishlist' ); ?></h2>

<p>
	<?php esc_html_e( 'Reporting a purchase asks for no name and stores none. The reporting browser keeps a random token in its local storage so it can take the report back; the site stores only a hash of that token. The page loads nothing from other hosts. Shop links open the shop only when a visitor clicks them.', 'baukasten-wishlist' ); ?>
</p>
