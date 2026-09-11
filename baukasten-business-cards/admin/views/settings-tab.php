<?php
/**
 * Business Cards tab.
 *
 * @package Baukasten\BusinessCards
 *
 * @var string                $base       The base segment cards are served from.
 * @var bool                  $pretty     Whether the site serves pretty permalinks.
 * @var int                   $published  Number of published cards.
 * @var int                   $drafts     Number of draft cards.
 * @var bool                  $has_cf7    Whether Contact Form 7 is active.
 * @var int                   $form_id    Id of the form this plugin created, or 0.
 * @var bool                  $site_legal Whether Login Legal Pages supplies the legal links.
 * @var array<string, int>    $report     What the upgrade from 1.0 did.
 * @var array<string, string> $skins      Skin id to label.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

?>
<p class="baukasten-intro">
	<?php esc_html_e( 'A business card is a post. It has no content, only fields, and it is served on its own short URL with none of the theme attached.', 'baukasten-business-cards' ); ?>
</p>

<?php if ( 0 < $report['skins'] ) : ?>
	<div class="notice notice-info inline">
		<p>
			<?php
			printf(
				esc_html(
					/* translators: 1: number of cards. 2: name of the design they now use. */
					_n(
						'%1$s card used one of the old layouts. Those have been replaced by three designs, and it now uses %2$s. The old setting is kept on the card.',
						'%1$s cards used one of the old layouts. Those have been replaced by three designs, and they now use %2$s. The old settings are kept on the cards.',
						(int) $report['skins'],
						'baukasten-business-cards'
					)
				),
				esc_html( number_format_i18n( (int) $report['skins'] ) ),
				esc_html( $skins[ Skins::DEFAULT_SKIN ] ?? Skins::DEFAULT_SKIN )
			);
			?>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Post_Type::POST_TYPE ) ); ?>">
				<?php esc_html_e( 'Review them', 'baukasten-business-cards' ); ?>
			</a>
		</p>
	</div>
<?php endif; ?>

<?php if ( 0 < $report['apple_kept'] + $report['google_kept'] + $report['legal_kept'] ) : ?>
	<div class="notice notice-warning inline">
		<p>
			<?php esc_html_e( 'Some values from the previous version could not be moved to the new fields. Nothing was deleted — each is kept on its card and shown beside the field that replaced it:', 'baukasten-business-cards' ); ?>
		</p>
		<ul style="list-style:disc;margin-left:20px">
			<?php if ( 0 < $report['apple_kept'] ) : ?>
				<li><?php esc_html_e( 'Apple Wallet addresses pointing somewhere other than this media library. A pass is now a file this site serves, so it has to be uploaded.', 'baukasten-business-cards' ); ?></li>
			<?php endif; ?>
			<?php if ( 0 < $report['google_kept'] ) : ?>
				<li><?php esc_html_e( 'Google Wallet addresses that held no readable token.', 'baukasten-business-cards' ); ?></li>
			<?php endif; ?>
			<?php if ( 0 < $report['legal_kept'] ) : ?>
				<li><?php esc_html_e( 'Footer addresses that do not point at a page on this site.', 'baukasten-business-cards' ); ?></li>
			<?php endif; ?>
		</ul>
	</div>
<?php endif; ?>

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
			<th scope="row"><?php esc_html_e( 'Designs', 'baukasten-business-cards' ); ?></th>
			<td>
				<?php echo esc_html( implode( ', ', $skins ) ); ?>
				<p class="description">
					<?php esc_html_e( 'One layout in three looks. The design is chosen per card, together with whether it follows the visitor\'s light or dark setting.', 'baukasten-business-cards' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Legal links', 'baukasten-business-cards' ); ?></th>
			<td>
				<?php if ( $site_legal ) : ?>
					<?php esc_html_e( 'Taken from the Login Legal Pages addon, so every card shows the same imprint, privacy policy and terms.', 'baukasten-business-cards' ); ?>
					<?php if ( class_exists( '\Baukasten\Admin' ) ) : ?>
						<?php
						printf(
							' <a href="%s">%s</a>',
							esc_url( \Baukasten\Admin::page_url( 'login-legal-pages' ) ),
							esc_html__( 'Change them', 'baukasten-business-cards' )
						);
						?>
					<?php endif; ?>
				<?php else : ?>
					<?php esc_html_e( 'Chosen per card, from the site\'s pages. Install the Login Legal Pages addon to set them once for the whole site instead.', 'baukasten-business-cards' ); ?>
				<?php endif; ?>
			</td>
		</tr>
	</tbody>
</table>

<h2><?php esc_html_e( 'Contact form', 'baukasten-business-cards' ); ?></h2>

<?php if ( ! $has_cf7 ) : ?>
	<p><?php esc_html_e( 'Contact Form 7 is not active. Every other part of a card works without it; only the contact form field is hidden.', 'baukasten-business-cards' ); ?></p>
<?php elseif ( 0 < $form_id ) : ?>
	<p>
		<?php esc_html_e( 'The card contact form exists.', 'baukasten-business-cards' ); ?>
		<a href="<?php echo esc_url( (string) get_edit_post_link( $form_id, 'raw' ) ); ?>">
			<?php esc_html_e( 'Edit it', 'baukasten-business-cards' ); ?>
		</a>
	</p>
<?php else : ?>
	<p>
		<?php esc_html_e( 'A form with a name, an email address, a message and a consent checkbox that links the card\'s own privacy policy and terms. It is created once and can be edited afterwards like any other form.', 'baukasten-business-cards' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr( Settings_Tab::ACTION_CREATE_FORM ); ?>" />
		<?php wp_nonce_field( Settings_Tab::NONCE ); ?>
		<?php submit_button( __( 'Create the contact form', 'baukasten-business-cards' ), 'secondary', 'submit', false ); ?>
	</form>
<?php endif; ?>

<h2><?php esc_html_e( 'What a card address looks like', 'baukasten-business-cards' ); ?></h2>

<p>
	<?php esc_html_e( 'Each card gets eight random characters instead of a readable slug, assigned once and never changed, so a card cannot be found by guessing a name and a printed QR code keeps working.', 'baukasten-business-cards' ); ?>
</p>

<p class="description">
	<?php esc_html_e( 'Cards are deliberately outside the Content Visibility addon: a card is a link you hand to someone who is not logged in.', 'baukasten-business-cards' ); ?>
</p>
