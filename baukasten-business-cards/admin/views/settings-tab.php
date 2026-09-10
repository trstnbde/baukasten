<?php
/**
 * Business Cards tab.
 *
 * @package Baukasten\BusinessCards
 *
 * @var string $base      The base segment cards are served from.
 * @var bool   $pretty    Whether the site serves pretty permalinks.
 * @var int    $published Number of published cards.
 * @var int    $drafts    Number of draft cards.
 * @var bool   $has_cf7   Whether Contact Form 7 is active.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

?>
<p class="baukasten-intro">
	<?php esc_html_e( 'A business card is a post. It has no content, only fields, and it is served on its own short URL with none of the theme attached.', 'baukasten-business-cards' ); ?>
</p>

<h2><?php esc_html_e( 'Where cards live', 'baukasten-business-cards' ); ?></h2>

<table class="widefat striped">
	<caption class="screen-reader-text"><?php esc_html_e( 'Business card overview', 'baukasten-business-cards' ); ?></caption>
	<tbody>
		<tr>
			<th scope="row"><?php esc_html_e( 'Card address', 'baukasten-business-cards' ); ?></th>
			<td>
				<?php if ( $pretty ) : ?>
					<code><?php echo esc_html( home_url( '/' . $base . '/' ) ); ?></code>
					<?php
					printf(
						' <a href="%s">%s</a>',
						esc_url( admin_url( 'options-permalink.php' ) ),
						esc_html__( 'Change the base', 'baukasten-business-cards' )
					);
					?>
				<?php else : ?>
					<code><?php echo esc_html( home_url( '/?baukasten_card=' ) ); ?></code>
					<p class="description">
						<?php
						printf(
							/* translators: %s: link to the Permalinks screen. */
							esc_html__( 'This site uses plain permalinks, so cards have no short address. Choose a permalink structure on %s to give them one.', 'baukasten-business-cards' ),
							sprintf(
								'<a href="%s">%s</a>',
								esc_url( admin_url( 'options-permalink.php' ) ),
								esc_html__( 'Settings, Permalinks', 'baukasten-business-cards' )
							)
						);
						?>
					</p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Cards', 'baukasten-business-cards' ); ?></th>
			<td>
				<?php
				printf(
					/* translators: 1: number of published cards. 2: number of drafts. */
					esc_html__( '%1$s published, %2$s draft.', 'baukasten-business-cards' ),
					esc_html( number_format_i18n( $published ) ),
					esc_html( number_format_i18n( $drafts ) )
				);
				?>
				<?php
				printf(
					' <a href="%s">%s</a>',
					esc_url( admin_url( 'edit.php?post_type=' . Post_Type::POST_TYPE ) ),
					esc_html__( 'All cards', 'baukasten-business-cards' )
				);
				?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Contact form', 'baukasten-business-cards' ); ?></th>
			<td>
				<?php if ( $has_cf7 ) : ?>
					<?php esc_html_e( 'Contact Form 7 is active. A card can embed one of its forms, and the form must carry an acceptance checkbox for the email consent.', 'baukasten-business-cards' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Contact Form 7 is not active. Every other part of a card works without it; only the contact form field is hidden.', 'baukasten-business-cards' ); ?>
				<?php endif; ?>
			</td>
		</tr>
	</tbody>
</table>

<h2><?php esc_html_e( 'What a card address looks like', 'baukasten-business-cards' ); ?></h2>

<p>
	<?php esc_html_e( 'Each card gets eight random characters instead of a readable slug, assigned once and never changed, so a card cannot be found by guessing a name and a printed QR code keeps working.', 'baukasten-business-cards' ); ?>
</p>

<p class="description">
	<?php esc_html_e( 'Cards are deliberately outside the Content Visibility addon: a card is a link you hand to someone who is not logged in.', 'baukasten-business-cards' ); ?>
</p>
