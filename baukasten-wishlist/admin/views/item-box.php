<?php
/**
 * The details box of a wish.
 *
 * @package Baukasten\Wishlist
 *
 * @var array<string, string> $baukasten_wl_values Field values.
 * @var array<int, string>    $baukasten_wl_lists  List ID to title.
 * @var array<int, string>    $baukasten_wl_prios  Priority to label.
 */

namespace Baukasten\Wishlist;

defined( 'ABSPATH' ) || exit;

?>
<table class="form-table baukasten-wl-admin-form" role="presentation">
	<tbody>
		<tr>
			<th scope="row"><label for="baukasten-wl-list"><?php esc_html_e( 'List', 'baukasten-wishlist' ); ?></label></th>
			<td>
				<?php if ( array() === $baukasten_wl_lists ) : ?>
					<p>
						<?php esc_html_e( 'There is no list yet.', 'baukasten-wishlist' ); ?>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Post_Types::LIST_TYPE ) ); ?>"><?php esc_html_e( 'Create a list', 'baukasten-wishlist' ); ?></a>
					</p>
				<?php else : ?>
					<select id="baukasten-wl-list" name="<?php echo esc_attr( Fields::meta_key( 'list' ) ); ?>" required>
						<option value=""><?php esc_html_e( '— Choose —', 'baukasten-wishlist' ); ?></option>
						<?php foreach ( $baukasten_wl_lists as $baukasten_wl_list_id => $baukasten_wl_list_title ) : ?>
							<option value="<?php echo esc_attr( (string) $baukasten_wl_list_id ); ?>" <?php selected( $baukasten_wl_values['list'], (string) $baukasten_wl_list_id ); ?>><?php echo esc_html( $baukasten_wl_list_title ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="baukasten-wl-quantity"><?php esc_html_e( 'Quantity', 'baukasten-wishlist' ); ?></label></th>
			<td>
				<input type="number" class="small-text" id="baukasten-wl-quantity" name="<?php echo esc_attr( Fields::meta_key( 'quantity' ) ); ?>" value="<?php echo esc_attr( $baukasten_wl_values['quantity'] ); ?>" min="1" max="<?php echo esc_attr( (string) Fields::MAX_QUANTITY ); ?>" step="1" />
				<p class="description"><?php esc_html_e( 'How many are wanted. Each guest reports one at a time.', 'baukasten-wishlist' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="baukasten-wl-price"><?php esc_html_e( 'Price', 'baukasten-wishlist' ); ?></label></th>
			<td>
				<input type="text" inputmode="decimal" class="small-text" id="baukasten-wl-price" name="<?php echo esc_attr( Fields::meta_key( 'price' ) ); ?>" value="<?php echo esc_attr( '' === $baukasten_wl_values['price'] ? '' : number_format_i18n( (float) $baukasten_wl_values['price'], 2 ) ); ?>" placeholder="12,99" />
				<span><?php echo esc_html( Settings::currency() ); ?></span>
				<p class="description"><?php esc_html_e( 'Approximate price of one. Optional.', 'baukasten-wishlist' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="baukasten-wl-priority"><?php esc_html_e( 'Priority', 'baukasten-wishlist' ); ?></label></th>
			<td>
				<select id="baukasten-wl-priority" name="<?php echo esc_attr( Fields::meta_key( 'priority' ) ); ?>">
					<?php foreach ( $baukasten_wl_prios as $baukasten_wl_key => $baukasten_wl_label ) : ?>
						<option value="<?php echo esc_attr( (string) $baukasten_wl_key ); ?>" <?php selected( $baukasten_wl_values['priority'], (string) $baukasten_wl_key ); ?>><?php echo esc_html( $baukasten_wl_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="baukasten-wl-url"><?php esc_html_e( 'Shop address', 'baukasten-wishlist' ); ?></label></th>
			<td>
				<input type="url" class="large-text code" id="baukasten-wl-url" name="<?php echo esc_attr( Fields::meta_key( 'url' ) ); ?>" value="<?php echo esc_attr( $baukasten_wl_values['url'] ); ?>" placeholder="https://" />
				<p class="description"><?php esc_html_e( 'Where the product can be bought. Opens in a new tab.', 'baukasten-wishlist' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="baukasten-wl-note"><?php esc_html_e( 'Note', 'baukasten-wishlist' ); ?></label></th>
			<td>
				<input type="text" class="large-text" id="baukasten-wl-note" name="<?php echo esc_attr( Fields::meta_key( 'note' ) ); ?>" value="<?php echo esc_attr( $baukasten_wl_values['note'] ); ?>" maxlength="200" />
				<p class="description"><?php esc_html_e( 'A short hint such as a size or colour. Optional.', 'baukasten-wishlist' ); ?></p>
			</td>
		</tr>
	</tbody>
</table>
<p class="description"><?php esc_html_e( 'The product image is the featured image, in the sidebar.', 'baukasten-wishlist' ); ?></p>
