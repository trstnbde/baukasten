<?php
/**
 * One wishlist.
 *
 * @package Baukasten\Wishlist
 *
 * @var \WP_Post                         $baukasten_wl_list     The list.
 * @var array<string, string>            $baukasten_wl_fields   Its fields.
 * @var array<int, array<string, mixed>> $baukasten_wl_items    Its wishes, sorted.
 * @var array<string, mixed>             $baukasten_wl_args     Display arguments.
 * @var bool                             $baukasten_wl_open     Whether it takes reports.
 * @var array<string, string>            $baukasten_wl_sorts    Sort key to label.
 * @var array<string, string>            $baukasten_wl_statuses Status key to label.
 * @var array<int, string>               $baukasten_wl_prios    Priority to label.
 * @var int[]                            $baukasten_wl_prices   Price filter bounds.
 * @var int|null                         $baukasten_wl_days     Days until the date.
 */

namespace Baukasten\Wishlist;

defined( 'ABSPATH' ) || exit;

$baukasten_wl_id         = 'baukasten-wl-' . $baukasten_wl_list->ID;
$baukasten_wl_title      = get_the_title( $baukasten_wl_list );
$baukasten_wl_header     = (int) get_post_thumbnail_id( $baukasten_wl_list );
$baukasten_wl_show_price = '1' === $baukasten_wl_fields['show_prices'];
$baukasten_wl_date       = '';

if ( '' !== $baukasten_wl_fields['date'] ) {
	$baukasten_wl_day = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $baukasten_wl_fields['date'] . ' 12:00', wp_timezone() );

	if ( false !== $baukasten_wl_day ) {
		$baukasten_wl_date = (string) wp_date( (string) get_option( 'date_format' ), $baukasten_wl_day->getTimestamp() );
	}
}

$baukasten_wl_meta = array_filter(
	array(
		'' !== $baukasten_wl_fields['person']
			/* translators: %s: name of the person the list is for. */
			? sprintf( __( 'For %s', 'baukasten-wishlist' ), $baukasten_wl_fields['person'] )
			: '',
		$baukasten_wl_fields['occasion'],
	)
);

$baukasten_wl_countdown = '';

if ( null !== $baukasten_wl_days && 0 <= $baukasten_wl_days ) {
	if ( 0 === $baukasten_wl_days ) {
		$baukasten_wl_countdown = __( 'Today!', 'baukasten-wishlist' );
	} elseif ( 1 === $baukasten_wl_days ) {
		$baukasten_wl_countdown = __( 'Tomorrow', 'baukasten-wishlist' );
	} else {
		/* translators: %s: number of days. */
		$baukasten_wl_countdown = sprintf( __( 'in %s days', 'baukasten-wishlist' ), number_format_i18n( $baukasten_wl_days ) );
	}
}

?>
<section
	class="baukasten-wl<?php echo $baukasten_wl_open ? '' : ' is-closed'; ?>"
	id="<?php echo esc_attr( $baukasten_wl_id ); ?>"
	data-list="<?php echo esc_attr( (string) $baukasten_wl_list->ID ); ?>"
	data-sort="<?php echo esc_attr( $baukasten_wl_args['sort'] ); ?>"
	data-status="<?php echo esc_attr( $baukasten_wl_args['status'] ); ?>"
	data-date="<?php echo esc_attr( $baukasten_wl_fields['date'] ); ?>"
	style="--baukasten-wl-accent: <?php echo esc_attr( $baukasten_wl_fields['accent'] ); ?>;"
	aria-labelledby="<?php echo esc_attr( $baukasten_wl_id . '-title' ); ?>"
>
	<header class="baukasten-wl__header<?php echo 0 < $baukasten_wl_header ? ' has-image' : ''; ?>">
		<?php if ( 0 < $baukasten_wl_header ) : ?>
			<div class="baukasten-wl__banner">
				<?php echo wp_get_attachment_image( $baukasten_wl_header, 'large', false, array( 'alt' => '' ) ); ?>
			</div>
		<?php endif; ?>
		<div class="baukasten-wl__heading">
			<h2 class="baukasten-wl__title" id="<?php echo esc_attr( $baukasten_wl_id . '-title' ); ?>"><?php echo esc_html( $baukasten_wl_title ); ?></h2>
			<?php if ( array() !== $baukasten_wl_meta || '' !== $baukasten_wl_date ) : ?>
				<p class="baukasten-wl__meta">
					<?php echo esc_html( implode( ' · ', $baukasten_wl_meta ) ); ?>
					<?php if ( '' !== $baukasten_wl_date ) : ?>
						<?php echo array() !== $baukasten_wl_meta ? ' · ' : ''; ?>
						<time datetime="<?php echo esc_attr( $baukasten_wl_fields['date'] ); ?>"><?php echo esc_html( $baukasten_wl_date ); ?></time>
						<?php if ( '' !== $baukasten_wl_countdown ) : ?>
							<span class="baukasten-wl__countdown"><?php echo esc_html( $baukasten_wl_countdown ); ?></span>
						<?php endif; ?>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<?php if ( '' !== $baukasten_wl_fields['intro'] ) : ?>
				<div class="baukasten-wl__intro"><?php echo wp_kses_post( wpautop( esc_html( $baukasten_wl_fields['intro'] ) ) ); ?></div>
			<?php endif; ?>
			<?php if ( ! $baukasten_wl_open ) : ?>
				<p class="baukasten-wl__closed"><?php esc_html_e( 'This list no longer takes reports.', 'baukasten-wishlist' ); ?></p>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( array() === $baukasten_wl_items ) : ?>
		<p class="baukasten-wl__empty"><?php esc_html_e( 'There are no wishes on this list yet.', 'baukasten-wishlist' ); ?></p>
	<?php else : ?>
		<?php if ( $baukasten_wl_args['controls'] ) : ?>
			<div class="baukasten-wl__controls" hidden>
				<label class="baukasten-wl__control">
					<span><?php esc_html_e( 'Sort', 'baukasten-wishlist' ); ?></span>
					<select data-control="sort">
						<?php foreach ( $baukasten_wl_sorts as $baukasten_wl_key => $baukasten_wl_label ) : ?>
							<?php if ( ! $baukasten_wl_show_price && str_starts_with( $baukasten_wl_key, 'price' ) ) : ?>
								<?php continue; ?>
							<?php endif; ?>
							<option value="<?php echo esc_attr( $baukasten_wl_key ); ?>" <?php selected( $baukasten_wl_args['sort'], $baukasten_wl_key ); ?>><?php echo esc_html( $baukasten_wl_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="baukasten-wl__control">
					<span><?php esc_html_e( 'Show', 'baukasten-wishlist' ); ?></span>
					<select data-control="status">
						<?php foreach ( $baukasten_wl_statuses as $baukasten_wl_key => $baukasten_wl_label ) : ?>
							<option value="<?php echo esc_attr( $baukasten_wl_key ); ?>" <?php selected( $baukasten_wl_args['status'], $baukasten_wl_key ); ?>><?php echo esc_html( $baukasten_wl_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="baukasten-wl__control">
					<span><?php esc_html_e( 'Priority', 'baukasten-wishlist' ); ?></span>
					<select data-control="priority">
						<option value=""><?php esc_html_e( 'Any', 'baukasten-wishlist' ); ?></option>
						<?php foreach ( $baukasten_wl_prios as $baukasten_wl_key => $baukasten_wl_label ) : ?>
							<option value="<?php echo esc_attr( (string) $baukasten_wl_key ); ?>"><?php echo esc_html( $baukasten_wl_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<?php if ( $baukasten_wl_show_price && array() !== $baukasten_wl_prices ) : ?>
					<label class="baukasten-wl__control">
						<span><?php esc_html_e( 'Price', 'baukasten-wishlist' ); ?></span>
						<select data-control="price">
							<option value=""><?php esc_html_e( 'Any', 'baukasten-wishlist' ); ?></option>
							<?php foreach ( $baukasten_wl_prices as $baukasten_wl_step ) : ?>
								<option value="<?php echo esc_attr( (string) $baukasten_wl_step ); ?>">
									<?php
									/* translators: %s: a price such as "25 €". */
									echo esc_html( sprintf( __( 'up to %s', 'baukasten-wishlist' ), Settings::format_price( (string) $baukasten_wl_step ) ) );
									?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>
				<?php endif; ?>
				<p class="baukasten-wl__count" aria-live="polite"></p>
			</div>
		<?php endif; ?>

		<ul class="baukasten-wl__items" role="list">
			<?php foreach ( $baukasten_wl_items as $baukasten_wl_item ) : ?>
				<?php require __DIR__ . '/item.php'; ?>
			<?php endforeach; ?>
		</ul>
		<p class="baukasten-wl__nothing" hidden><?php esc_html_e( 'No wish matches this filter.', 'baukasten-wishlist' ); ?></p>
	<?php endif; ?>

	<p class="baukasten-wl__live baukasten-wl__sr" role="status" aria-live="polite"></p>
</section>
