<?php
/**
 * The full contact list: label on the left, value on the right.
 *
 * Addresses and numbers go through `Obfuscate`, so the page carries no literal
 * copy of either — see that class for what this does and does not achieve.
 *
 * @package Baukasten\BusinessCards
 *
 * @var array<string, mixed> $baukasten_bc_card Loaded card fields.
 * @var array<string, mixed> $baukasten_bc_skin The design being rendered.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

$baukasten_bc_rows = array(
	'email_work'      => array( __( 'Email (work)', 'baukasten-business-cards' ), 'mailto' ),
	'email_priv'      => array( __( 'Email (private)', 'baukasten-business-cards' ), 'mailto' ),
	'phone_work'      => array( __( 'Phone (work)', 'baukasten-business-cards' ), 'tel' ),
	'phone_priv'      => array( __( 'Phone (private)', 'baukasten-business-cards' ), 'tel' ),
	'mobile_work'     => array( __( 'Mobile (work)', 'baukasten-business-cards' ), 'tel' ),
	'mobile_priv'     => array( __( 'Mobile (private)', 'baukasten-business-cards' ), 'tel' ),
	'contact_website' => array( __( 'Website', 'baukasten-business-cards' ), 'url' ),
);

$baukasten_bc_any = Fields::has_any( $baukasten_bc_card, array_keys( $baukasten_bc_rows ) )
	|| Fields::has_any( $baukasten_bc_card, array( 'contact_address', 'what3words_link' ) );

if ( ! $baukasten_bc_any ) {
	return;
}

Skins::open_section( $baukasten_bc_skin, 'contact', __( 'My details', 'baukasten-business-cards' ) );

?>
<dl class="bkbc-contact__list">
	<?php foreach ( $baukasten_bc_rows as $baukasten_bc_key => $baukasten_bc_row ) : ?>
		<?php if ( isset( $baukasten_bc_card[ $baukasten_bc_key ] ) ) : ?>
			<?php
			$baukasten_bc_value = (string) $baukasten_bc_card[ $baukasten_bc_key ];

			if ( 'tel' === $baukasten_bc_row[1] ) {
				$baukasten_bc_href = Obfuscate::tel( $baukasten_bc_value );
				$baukasten_bc_text = Obfuscate::html( $baukasten_bc_value );
			} elseif ( 'mailto' === $baukasten_bc_row[1] ) {
				$baukasten_bc_href = Obfuscate::mailto( $baukasten_bc_value );
				$baukasten_bc_text = Obfuscate::html( $baukasten_bc_value );
			} else {
				$baukasten_bc_href = esc_url( $baukasten_bc_value );
				$baukasten_bc_text = esc_html( $baukasten_bc_value );
			}
			?>
			<div class="bkbc-contact__row">
				<dt class="bkbc-contact__label"><?php echo esc_html( (string) $baukasten_bc_row[0] ); ?></dt>
				<dd class="bkbc-contact__value">
					<?php /* Both sides are already escaped, or entity-encoded past the point of having anything to escape. */ ?>
					<a href="<?php echo $baukasten_bc_href; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- an escaped URL, or a fully entity-encoded one; see Obfuscate. ?>">
						<?php echo $baukasten_bc_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- as above. ?>
					</a>
				</dd>
			</div>
		<?php endif; ?>
	<?php endforeach; ?>

	<?php if ( isset( $baukasten_bc_card['what3words_link'] ) ) : ?>
		<?php
		/*
		 * what3words writes an address as ///three.little.words, and the three
		 * slashes are part of how it is read — they are the mark, in their own
		 * red. The stored value is the full link, so the words are taken off the
		 * end of it; anything that does not look like an address is shown as the
		 * plain link rather than dressed up as one.
		 */
		$baukasten_bc_w3w   = (string) $baukasten_bc_card['what3words_link'];
		$baukasten_bc_path  = trim( (string) wp_parse_url( $baukasten_bc_w3w, PHP_URL_PATH ), '/' );
		$baukasten_bc_words = preg_match( '/^[^\/]+\.[^\/]+\.[^\/]+$/u', $baukasten_bc_path ) ? $baukasten_bc_path : '';
		?>
		<div class="bkbc-contact__row">
			<dt class="bkbc-contact__label"><?php esc_html_e( 'what3words', 'baukasten-business-cards' ); ?></dt>
			<dd class="bkbc-contact__value">
				<a href="<?php echo esc_url( $baukasten_bc_w3w ); ?>" rel="noopener">
					<?php if ( '' !== $baukasten_bc_words ) : ?>
						<span class="bkbc-w3w"><span class="bkbc-w3w__mark" aria-hidden="true">///</span><?php echo esc_html( $baukasten_bc_words ); ?></span>
					<?php else : ?>
						<?php echo esc_html( $baukasten_bc_w3w ); ?>
					<?php endif; ?>
				</a>
			</dd>
		</div>
	<?php endif; ?>

	<?php if ( isset( $baukasten_bc_card['contact_address'] ) ) : ?>
		<div class="bkbc-contact__row">
			<dt class="bkbc-contact__label"><?php esc_html_e( 'Address', 'baukasten-business-cards' ); ?></dt>
			<dd class="bkbc-contact__value">
				<address><?php echo nl2br( esc_html( (string) $baukasten_bc_card['contact_address'] ) ); ?></address>
			</dd>
		</div>
	<?php endif; ?>
</dl>
<?php

Skins::close_section();
