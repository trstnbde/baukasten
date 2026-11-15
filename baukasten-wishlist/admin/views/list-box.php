<?php
/**
 * The occasion box of a list.
 *
 * @package Baukasten\Wishlist
 *
 * @var array<string, string> $baukasten_wl_values Field values.
 */

namespace Baukasten\Wishlist;

defined( 'ABSPATH' ) || exit;

?>
<table class="form-table baukasten-wl-admin-form" role="presentation">
	<tbody>
		<tr>
			<th scope="row"><label for="baukasten-wl-person"><?php esc_html_e( 'For', 'baukasten-wishlist' ); ?></label></th>
			<td>
				<input type="text" class="regular-text" id="baukasten-wl-person" name="<?php echo esc_attr( Fields::meta_key( 'person' ) ); ?>" value="<?php echo esc_attr( $baukasten_wl_values['person'] ); ?>" maxlength="200" />
				<p class="description"><?php esc_html_e( 'Whose list it is, such as "Leni". Shown as "For Leni".', 'baukasten-wishlist' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="baukasten-wl-occasion"><?php esc_html_e( 'Occasion', 'baukasten-wishlist' ); ?></label></th>
			<td>
				<input type="text" class="regular-text" id="baukasten-wl-occasion" name="<?php echo esc_attr( Fields::meta_key( 'occasion' ) ); ?>" value="<?php echo esc_attr( $baukasten_wl_values['occasion'] ); ?>" maxlength="200" />
				<p class="description"><?php esc_html_e( 'Such as "2nd birthday", "Christmas" or "Wedding".', 'baukasten-wishlist' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="baukasten-wl-date"><?php esc_html_e( 'Date', 'baukasten-wishlist' ); ?></label></th>
			<td>
				<input type="date" id="baukasten-wl-date" name="<?php echo esc_attr( Fields::meta_key( 'date' ) ); ?>" value="<?php echo esc_attr( $baukasten_wl_values['date'] ); ?>" />
				<p class="description"><?php esc_html_e( 'The list counts down to it. Leave empty for a list without a day.', 'baukasten-wishlist' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="baukasten-wl-intro"><?php esc_html_e( 'Introduction', 'baukasten-wishlist' ); ?></label></th>
			<td>
				<textarea class="large-text" rows="4" id="baukasten-wl-intro" name="<?php echo esc_attr( Fields::meta_key( 'intro' ) ); ?>"><?php echo esc_textarea( $baukasten_wl_values['intro'] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'A few words above the wishes: sizes, what not to buy, where to send things. Plain text; blank lines start a paragraph.', 'baukasten-wishlist' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="baukasten-wl-accent"><?php esc_html_e( 'Accent colour', 'baukasten-wishlist' ); ?></label></th>
			<td>
				<input type="text" class="baukasten-wl-color" id="baukasten-wl-accent" name="<?php echo esc_attr( Fields::meta_key( 'accent' ) ); ?>" value="<?php echo esc_attr( $baukasten_wl_values['accent'] ); ?>" data-default-color="<?php echo esc_attr( Fields::DEFAULT_ACCENT ); ?>" />
				<p class="description"><?php esc_html_e( 'Buttons, the countdown and the header tint. White text sits on it, so choose a colour that is not too light.', 'baukasten-wishlist' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Options', 'baukasten-wishlist' ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Options', 'baukasten-wishlist' ); ?></legend>
					<label>
						<input type="hidden" name="<?php echo esc_attr( Fields::meta_key( 'show_prices' ) ); ?>" value="0" />
						<input type="checkbox" name="<?php echo esc_attr( Fields::meta_key( 'show_prices' ) ); ?>" value="1" <?php checked( '1', $baukasten_wl_values['show_prices'] ); ?> />
						<?php esc_html_e( 'Show prices', 'baukasten-wishlist' ); ?>
					</label>
					<br />
					<label>
						<input type="hidden" name="<?php echo esc_attr( Fields::meta_key( 'close_after' ) ); ?>" value="0" />
						<input type="checkbox" name="<?php echo esc_attr( Fields::meta_key( 'close_after' ) ); ?>" value="1" <?php checked( '1', $baukasten_wl_values['close_after'] ); ?> />
						<?php esc_html_e( 'Stop taking reports after the date', 'baukasten-wishlist' ); ?>
					</label>
				</fieldset>
				<p class="description"><?php esc_html_e( 'The header image is the featured image of the list, in the sidebar.', 'baukasten-wishlist' ); ?></p>
			</td>
		</tr>
	</tbody>
</table>
