<?php
/**
 * One wish.
 *
 * @package Baukasten\Wishlist
 *
 * @var array<string, mixed> $baukasten_wl_item      The wish.
 * @var array<string, mixed> $baukasten_wl_args      Display arguments.
 * @var bool                 $baukasten_wl_open      Whether the list takes reports.
 * @var bool                 $baukasten_wl_show_price Whether prices are shown.
 * @var array<int, string>   $baukasten_wl_prios     Priority to label.
 */

namespace Baukasten\Wishlist;

defined( 'ABSPATH' ) || exit;

$baukasten_wl_done     = 0 >= (int) $baukasten_wl_item['remaining'];
$baukasten_wl_priority = (int) $baukasten_wl_item['priority'];
$baukasten_wl_hidden   = ( 'open' === $baukasten_wl_args['status'] && $baukasten_wl_done )
	|| ( 'done' === $baukasten_wl_args['status'] && ! $baukasten_wl_done );
$baukasten_wl_item_id  = 'baukasten-wl-item-' . (int) $baukasten_wl_item['id'];

if ( $baukasten_wl_done ) {
	$baukasten_wl_status = __( 'Already bought', 'baukasten-wishlist' );
} elseif ( 1 === (int) $baukasten_wl_item['quantity'] ) {
	$baukasten_wl_status = __( 'Still wanted', 'baukasten-wishlist' );
} else {
	$baukasten_wl_status = sprintf(
		/* translators: 1: number still wanted. 2: number wanted in all. */
		__( '%1$s of %2$s still wanted', 'baukasten-wishlist' ),
		number_format_i18n( (int) $baukasten_wl_item['remaining'] ),
		number_format_i18n( (int) $baukasten_wl_item['quantity'] )
	);
}

?>
<li
	class="baukasten-wl-item<?php echo $baukasten_wl_done ? ' is-done' : ''; ?> is-priority-<?php echo esc_attr( (string) $baukasten_wl_priority ); ?>"
	id="<?php echo esc_attr( $baukasten_wl_item_id ); ?>"
	data-id="<?php echo esc_attr( (string) $baukasten_wl_item['id'] ); ?>"
	data-title="<?php echo esc_attr( wp_strip_all_tags( (string) $baukasten_wl_item['title'] ) ); ?>"
	data-priority="<?php echo esc_attr( (string) $baukasten_wl_priority ); ?>"
	data-price="<?php echo esc_attr( $baukasten_wl_show_price ? (string) $baukasten_wl_item['price'] : '' ); ?>"
	data-order="<?php echo esc_attr( (string) $baukasten_wl_item['order'] ); ?>"
	data-quantity="<?php echo esc_attr( (string) $baukasten_wl_item['quantity'] ); ?>"
	data-remaining="<?php echo esc_attr( (string) $baukasten_wl_item['remaining'] ); ?>"
	<?php echo $baukasten_wl_hidden ? 'hidden' : ''; ?>
>
	<div class="baukasten-wl-item__media">
		<?php if ( 0 < (int) $baukasten_wl_item['image'] ) : ?>
			<?php
			echo wp_get_attachment_image(
				(int) $baukasten_wl_item['image'],
				'medium',
				false,
				array(
					'alt'     => '',
					'loading' => 'lazy',
					'sizes'   => '(max-width: 600px) 100vw, 320px',
				)
			);
			?>
		<?php else : ?>
			<span class="baukasten-wl-item__placeholder" aria-hidden="true"></span>
		<?php endif; ?>
		<span class="baukasten-wl-item__badge"><?php esc_html_e( 'Already bought', 'baukasten-wishlist' ); ?></span>
	</div>

	<div class="baukasten-wl-item__body">
		<p class="baukasten-wl-item__priority">
			<?php
			/* translators: %s: priority such as "High". */
			echo esc_html( sprintf( __( 'Priority: %s', 'baukasten-wishlist' ), $baukasten_wl_prios[ $baukasten_wl_priority ] ?? '' ) );
			?>
		</p>
		<h3 class="baukasten-wl-item__title" id="<?php echo esc_attr( $baukasten_wl_item_id . '-title' ); ?>"><?php echo esc_html( (string) $baukasten_wl_item['title'] ); ?></h3>

		<?php if ( '' !== (string) $baukasten_wl_item['note'] ) : ?>
			<p class="baukasten-wl-item__note"><?php echo esc_html( (string) $baukasten_wl_item['note'] ); ?></p>
		<?php endif; ?>

		<?php if ( $baukasten_wl_show_price && '' !== (string) $baukasten_wl_item['price'] ) : ?>
			<p class="baukasten-wl-item__price"><?php echo esc_html( Settings::format_price( (string) $baukasten_wl_item['price'] ) ); ?></p>
		<?php endif; ?>

		<p class="baukasten-wl-item__status"><?php echo esc_html( $baukasten_wl_status ); ?></p>

		<div class="baukasten-wl-item__actions">
			<?php if ( '' !== (string) $baukasten_wl_item['url'] ) : ?>
				<a
					class="baukasten-wl-button baukasten-wl-button--shop"
					href="<?php echo esc_url( (string) $baukasten_wl_item['url'] ); ?>"
					target="_blank"
					rel="noopener noreferrer nofollow"
					aria-describedby="<?php echo esc_attr( $baukasten_wl_item_id . '-title' ); ?>"
				><?php esc_html_e( 'To the shop', 'baukasten-wishlist' ); ?></a>
			<?php endif; ?>
			<?php if ( $baukasten_wl_open ) : ?>
				<button
					type="button"
					class="baukasten-wl-button baukasten-wl-button--claim"
					data-action="ask"
					aria-describedby="<?php echo esc_attr( $baukasten_wl_item_id . '-title' ); ?>"
					hidden
				><?php esc_html_e( 'I bought this', 'baukasten-wishlist' ); ?></button>
				<button
					type="button"
					class="baukasten-wl-link baukasten-wl-undo"
					data-action="undo"
					aria-describedby="<?php echo esc_attr( $baukasten_wl_item_id . '-title' ); ?>"
					hidden
				><?php esc_html_e( 'Take my report back', 'baukasten-wishlist' ); ?></button>
			<?php endif; ?>
		</div>

		<?php if ( $baukasten_wl_open ) : ?>
			<div class="baukasten-wl-item__confirm" hidden>
				<p><?php esc_html_e( 'Did you buy this? Everybody who opens the list will see it as taken care of. Nobody sees who bought it.', 'baukasten-wishlist' ); ?></p>
				<div class="baukasten-wl-item__confirm-actions">
					<button type="button" class="baukasten-wl-button baukasten-wl-button--claim" data-action="claim"><?php esc_html_e( 'Yes, I bought it', 'baukasten-wishlist' ); ?></button>
					<button type="button" class="baukasten-wl-link" data-action="cancel"><?php esc_html_e( 'Cancel', 'baukasten-wishlist' ); ?></button>
				</div>
			</div>
		<?php endif; ?>
	</div>
</li>
