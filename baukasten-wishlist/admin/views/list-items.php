<?php
/**
 * The wishes of a list, on the list's screen.
 *
 * @package Baukasten\Wishlist
 *
 * @var \WP_Post[]         $baukasten_wl_items Wishes, drafts included.
 * @var string             $baukasten_wl_new   Address of "Add wish".
 * @var bool               $baukasten_wl_saved Whether the list was saved.
 * @var array<int, string> $baukasten_wl_prios Priority to label.
 */

namespace Baukasten\Wishlist;

defined( 'ABSPATH' ) || exit;

?>
<?php if ( ! $baukasten_wl_saved ) : ?>
	<p><?php esc_html_e( 'Save the list as a draft first, then add its wishes here.', 'baukasten-wishlist' ); ?></p>
<?php else : ?>
	<?php if ( array() === $baukasten_wl_items ) : ?>
		<p><?php esc_html_e( 'No wishes yet.', 'baukasten-wishlist' ); ?></p>
	<?php else : ?>
		<table class="widefat striped baukasten-wl-admin-items">
			<thead>
				<tr>
					<th scope="col" class="column-image"><span class="screen-reader-text"><?php esc_html_e( 'Image', 'baukasten-wishlist' ); ?></span></th>
					<th scope="col"><?php esc_html_e( 'Wish', 'baukasten-wishlist' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Priority', 'baukasten-wishlist' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Price', 'baukasten-wishlist' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Bought', 'baukasten-wishlist' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $baukasten_wl_items as $baukasten_wl_post ) : ?>
					<?php $baukasten_wl_data = Items::data( $baukasten_wl_post ); ?>
					<tr>
						<td class="column-image"><?php echo get_the_post_thumbnail( $baukasten_wl_post, array( 48, 48 ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( (string) get_edit_post_link( $baukasten_wl_post->ID ) ); ?>"><strong><?php echo esc_html( (string) $baukasten_wl_data['title'] ); ?></strong></a>
							<?php if ( 'publish' !== $baukasten_wl_post->post_status ) : ?>
								— <span class="post-state"><?php echo esc_html( (string) get_post_status_object( $baukasten_wl_post->post_status )?->label ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $baukasten_wl_prios[ (int) $baukasten_wl_data['priority'] ] ?? '' ); ?></td>
						<td><?php echo esc_html( Settings::format_price( (string) $baukasten_wl_data['price'] ) ); ?></td>
						<td>
							<?php
							printf(
								/* translators: 1: number bought. 2: number wished for. */
								esc_html__( '%1$s of %2$s', 'baukasten-wishlist' ),
								esc_html( number_format_i18n( (int) $baukasten_wl_data['bought'] ) ),
								esc_html( number_format_i18n( (int) $baukasten_wl_data['quantity'] ) )
							);
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
	<p><a class="button" href="<?php echo esc_url( $baukasten_wl_new ); ?>"><?php esc_html_e( 'Add wish', 'baukasten-wishlist' ); ?></a></p>
	<p class="description"><?php esc_html_e( 'Only published wishes appear on the page. The order "As arranged" follows each wish\'s Order field.', 'baukasten-wishlist' ); ?></p>
<?php endif; ?>
