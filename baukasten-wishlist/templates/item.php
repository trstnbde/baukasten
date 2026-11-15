<?php
/**
 * One wish.
 *
 * The buttons sit at the foot of the card, in the same place on every card.
 * The question that confirms a purchase opens in place of the button that
 * asked for it, so it appears where the finger or pointer already is.
 *
 * @package Baukasten\Wishlist
 *
 * @var array<string, mixed> $baukasten_wl_item       The wish.
 * @var int                  $baukasten_wl_index      Its position in the list, from 0.
 * @var array<string, mixed> $baukasten_wl_args       Display arguments.
 * @var bool                 $baukasten_wl_open       Whether the list takes reports.
 * @var bool                 $baukasten_wl_show_price Whether prices are shown.
 */

namespace Baukasten\Wishlist;

defined( 'ABSPATH' ) || exit;

$baukasten_wl_done     = 0 >= (int) $baukasten_wl_item['remaining'];
$baukasten_wl_priority = (int) $baukasten_wl_item['priority'];
$baukasten_wl_top      = Fields::PRIORITY_HIGH === $baukasten_wl_priority;
$baukasten_wl_hidden   = ( 'open' === $baukasten_wl_args['status'] && $baukasten_wl_done )
	|| ( 'done' === $baukasten_wl_args['status'] && ! $baukasten_wl_done );
$baukasten_wl_item_id  = 'baukasten-wl-item-' . (int) $baukasten_wl_item['id'];
$baukasten_wl_quantity = (int) $baukasten_wl_item['quantity'];

// A single wish that is still wanted needs no status line: the button says it.
if ( $baukasten_wl_done ) {
	$baukasten_wl_status = __( 'Already bought', 'baukasten-wishlist' );
} elseif ( 1 < $baukasten_wl_quantity ) {
	$baukasten_wl_status = sprintf(
		/* translators: 1: number still wanted. 2: number wanted in all. */
		__( '%1$s of %2$s still wanted', 'baukasten-wishlist' ),
		number_format_i18n( (int) $baukasten_wl_item['remaining'] ),
		number_format_i18n( $baukasten_wl_quantity )
	);
} else {
	$baukasten_wl_status = '';
}

// The first image may be above the fold; every other one loads on demand.
$baukasten_wl_image_attrs = array(
	'alt'   => '',
	'sizes' => '(max-width: 640px) calc(100vw - 2rem), 320px',
);

if ( 0 === $baukasten_wl_index ) {
	$baukasten_wl_image_attrs['loading']       = 'eager';
	$baukasten_wl_image_attrs['fetchpriority'] = 'high';
} else {
	$baukasten_wl_image_attrs['loading'] = 'lazy';
}

?>
<li
	class="baukasten-wl-item<?php echo $baukasten_wl_done ? ' is-done' : ''; ?><?php echo $baukasten_wl_top ? ' is-top' : ''; ?>"
	id="<?php echo esc_attr( $baukasten_wl_item_id ); ?>"
	data-id="<?php echo esc_attr( (string) $baukasten_wl_item['id'] ); ?>"
	data-title="<?php echo esc_attr( wp_strip_all_tags( (string) ( $baukasten_wl_item['raw_title'] ?? $baukasten_wl_item['title'] ) ) ); ?>"
	data-priority="<?php echo esc_attr( (string) $baukasten_wl_priority ); ?>"
	data-price="<?php echo esc_attr( $baukasten_wl_show_price ? (string) $baukasten_wl_item['price'] : '' ); ?>"
	data-order="<?php echo esc_attr( (string) $baukasten_wl_item['order'] ); ?>"
	data-quantity="<?php echo esc_attr( (string) $baukasten_wl_quantity ); ?>"
	data-remaining="<?php echo esc_attr( (string) $baukasten_wl_item['remaining'] ); ?>"
	<?php echo $baukasten_wl_hidden ? 'hidden' : ''; ?>
>
	<div class="baukasten-wl-item__media">
		<?php if ( 0 < (int) $baukasten_wl_item['image'] ) : ?>
			<?php echo wp_get_attachment_image( (int) $baukasten_wl_item['image'], Post_Types::IMAGE_SIZE, false, $baukasten_wl_image_attrs ); ?>
		<?php else : ?>
			<span class="baukasten-wl-item__placeholder" aria-hidden="true"></span>
		<?php endif; ?>
		<?php if ( $baukasten_wl_top ) : ?>
			<span class="baukasten-wl-item__top"><?php esc_html_e( 'Top wish', 'baukasten-wishlist' ); ?></span>
		<?php endif; ?>
		<span class="baukasten-wl-item__badge"><?php esc_html_e( 'Already bought', 'baukasten-wishlist' ); ?></span>
	</div>

	<div class="baukasten-wl-item__body">
		<h3 class="baukasten-wl-item__title" id="<?php echo esc_attr( $baukasten_wl_item_id . '-title' ); ?>"><?php echo esc_html( (string) $baukasten_wl_item['title'] ); ?></h3>

		<?php if ( '' !== (string) $baukasten_wl_item['note'] ) : ?>
			<p class="baukasten-wl-item__note"><?php echo esc_html( (string) $baukasten_wl_item['note'] ); ?></p>
		<?php endif; ?>

		<p class="baukasten-wl-item__facts">
			<?php if ( $baukasten_wl_show_price && '' !== (string) $baukasten_wl_item['price'] ) : ?>
				<span class="baukasten-wl-item__price"><?php echo esc_html( Settings::format_price( (string) $baukasten_wl_item['price'] ) ); ?></span>
			<?php endif; ?>
			<span class="baukasten-wl-item__status"<?php echo '' === $baukasten_wl_status ? ' hidden' : ''; ?>><?php echo esc_html( $baukasten_wl_status ); ?></span>
		</p>

		<div class="baukasten-wl-item__actions">
			<?php if ( '' !== (string) $baukasten_wl_item['url'] ) : ?>
				<a
					class="baukasten-wl-shop"
					href="<?php echo esc_url( (string) $baukasten_wl_item['url'] ); ?>"
					target="_blank"
					rel="noopener noreferrer nofollow"
					aria-describedby="<?php echo esc_attr( $baukasten_wl_item_id . '-title' ); ?>"
				><?php esc_html_e( 'To the shop', 'baukasten-wishlist' ); ?><span class="baukasten-wl-shop__arrow" aria-hidden="true">↗</span><span class="baukasten-wl__sr"><?php esc_html_e( '(opens in a new tab)', 'baukasten-wishlist' ); ?></span></a>
			<?php endif; ?>

			<?php if ( $baukasten_wl_open ) : ?>
				<button
					type="button"
					class="baukasten-wl-button baukasten-wl-button--claim"
					data-action="ask"
					aria-describedby="<?php echo esc_attr( $baukasten_wl_item_id . '-title' ); ?>"
					hidden
				><?php esc_html_e( 'I bought this', 'baukasten-wishlist' ); ?></button>

				<div class="baukasten-wl-item__confirm" role="group" aria-labelledby="<?php echo esc_attr( $baukasten_wl_item_id . '-ask' ); ?>" hidden>
					<p id="<?php echo esc_attr( $baukasten_wl_item_id . '-ask' ); ?>"><?php esc_html_e( 'Really bought? Everybody will see it as taken care of; nobody sees who bought it.', 'baukasten-wishlist' ); ?></p>
					<div class="baukasten-wl-item__confirm-actions">
						<button type="button" class="baukasten-wl-button baukasten-wl-button--claim" data-action="claim"><?php esc_html_e( 'Yes, I bought it', 'baukasten-wishlist' ); ?></button>
						<button type="button" class="baukasten-wl-button baukasten-wl-button--ghost" data-action="cancel"><?php esc_html_e( 'Cancel', 'baukasten-wishlist' ); ?></button>
					</div>
				</div>

				<button
					type="button"
					class="baukasten-wl-link baukasten-wl-undo"
					data-action="undo"
					aria-describedby="<?php echo esc_attr( $baukasten_wl_item_id . '-title' ); ?>"
					hidden
				><?php esc_html_e( 'Take my report back', 'baukasten-wishlist' ); ?></button>

				<p class="baukasten-wl-item__error" hidden></p>
			<?php endif; ?>
		</div>
	</div>
</li>
