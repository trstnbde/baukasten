<?php
/**
 * The wish's fields in Quick Edit.
 *
 * @package Baukasten\Wishlist
 *
 * @var array<int, string> $baukasten_wl_lists List ID to title.
 * @var array<int, string> $baukasten_wl_prios Priority to label.
 */

namespace Baukasten\Wishlist;

defined( 'ABSPATH' ) || exit;

?>
<fieldset class="inline-edit-col-right baukasten-wl-quick-edit">
	<div class="inline-edit-col">
		<?php wp_nonce_field( Meta_Boxes::NONCE_ACTION, Meta_Boxes::NONCE_FIELD, false ); ?>
		<label class="inline-edit-group">
			<span class="title"><?php esc_html_e( 'List', 'baukasten-wishlist' ); ?></span>
			<select name="<?php echo esc_attr( Fields::meta_key( 'list' ) ); ?>">
				<?php foreach ( $baukasten_wl_lists as $baukasten_wl_list_id => $baukasten_wl_list_title ) : ?>
					<option value="<?php echo esc_attr( (string) $baukasten_wl_list_id ); ?>"><?php echo esc_html( $baukasten_wl_list_title ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label class="inline-edit-group">
			<span class="title"><?php esc_html_e( 'Quantity', 'baukasten-wishlist' ); ?></span>
			<input type="number" name="<?php echo esc_attr( Fields::meta_key( 'quantity' ) ); ?>" min="1" max="<?php echo esc_attr( (string) Fields::MAX_QUANTITY ); ?>" step="1" class="small-text" />
		</label>
		<label class="inline-edit-group">
			<span class="title"><?php esc_html_e( 'Price', 'baukasten-wishlist' ); ?></span>
			<input type="text" inputmode="decimal" name="<?php echo esc_attr( Fields::meta_key( 'price' ) ); ?>" class="baukasten-wl-admin-price" placeholder="12,99" />
			<?php echo esc_html( Settings::currency() ); ?>
		</label>
		<label class="inline-edit-group">
			<span class="title"><?php esc_html_e( 'Priority', 'baukasten-wishlist' ); ?></span>
			<select name="<?php echo esc_attr( Fields::meta_key( 'priority' ) ); ?>">
				<?php foreach ( $baukasten_wl_prios as $baukasten_wl_key => $baukasten_wl_label ) : ?>
					<option value="<?php echo esc_attr( (string) $baukasten_wl_key ); ?>"><?php echo esc_html( $baukasten_wl_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
	</div>
</fieldset>
