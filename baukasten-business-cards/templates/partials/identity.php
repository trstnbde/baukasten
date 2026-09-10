<?php
/**
 * Who the card is for.
 *
 * @package Baukasten\BusinessCards
 *
 * @var array<string, mixed> $baukasten_bc_card Loaded card fields.
 * @var \WP_Post             $baukasten_bc_post The card post.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

$baukasten_bc_name = trim(
	implode(
		' ',
		array_filter(
			array(
				(string) ( $baukasten_bc_card['academic_title'] ?? '' ),
				(string) ( $baukasten_bc_card['first_name'] ?? '' ),
				(string) ( $baukasten_bc_card['last_name'] ?? '' ),
			)
		)
	)
);

$baukasten_bc_avatar = isset( $baukasten_bc_card['avatar_image_id'] )
	? wp_get_attachment_image(
		(int) $baukasten_bc_card['avatar_image_id'],
		'medium',
		false,
		array(
			'class'    => 'bkbc-avatar__image',
			'alt'      => '',
			'loading'  => 'eager',
			'decoding' => 'sync',
		)
	)
	: '';

?>
<header class="bkbc-identity">
	<?php if ( '' !== $baukasten_bc_avatar ) : ?>
		<div class="bkbc-avatar">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() escapes its own output.
			echo $baukasten_bc_avatar;
			?>
		</div>
	<?php endif; ?>

	<?php if ( isset( $baukasten_bc_card['salutation'] ) ) : ?>
		<p class="bkbc-identity__salutation"><?php echo esc_html( (string) $baukasten_bc_card['salutation'] ); ?></p>
	<?php endif; ?>

	<h1 class="bkbc-identity__name">
		<?php echo esc_html( '' !== $baukasten_bc_name ? $baukasten_bc_name : (string) get_the_title( $baukasten_bc_post ) ); ?>
	</h1>

	<?php if ( isset( $baukasten_bc_card['position'] ) ) : ?>
		<p class="bkbc-identity__position"><?php echo esc_html( (string) $baukasten_bc_card['position'] ); ?></p>
	<?php endif; ?>

	<?php if ( isset( $baukasten_bc_card['company'] ) ) : ?>
		<p class="bkbc-identity__company"><?php echo esc_html( (string) $baukasten_bc_card['company'] ); ?></p>
	<?php endif; ?>
</header>
