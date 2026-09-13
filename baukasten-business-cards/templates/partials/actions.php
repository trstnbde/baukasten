<?php
/**
 * Save the contact, and put it in a wallet.
 *
 * The three ways to take the card away, in one row: a vCard every phone
 * understands, an Apple Wallet pass this site serves itself, and a Google Wallet
 * token this site turns into a save button.
 *
 * @package Baukasten\BusinessCards
 *
 * @var array<string, mixed> $baukasten_bc_card Loaded card fields.
 * @var \WP_Post             $baukasten_bc_post The card post.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

$baukasten_bc_has_pass = Pass::available( $baukasten_bc_card );

if ( ! isset( $baukasten_bc_card['enable_vcf'] ) && ! $baukasten_bc_has_pass && ! isset( $baukasten_bc_card['wallet_google_jwt'] ) ) {
	return;
}

?>
<div class="bkbc-actions">
	<?php if ( isset( $baukasten_bc_card['enable_vcf'] ) ) : ?>
		<a class="bkbc-button bkbc-button--primary" href="<?php echo esc_url( VCard::url( $baukasten_bc_post->ID ) ); ?>">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG built from a fixed table in Icons.
			echo Icons::get( 'contact' );
			?>
			<span><?php esc_html_e( 'Save contact (.vcf)', 'baukasten-business-cards' ); ?></span>
		</a>
	<?php endif; ?>

	<?php if ( $baukasten_bc_has_pass ) : ?>
		<a class="bkbc-button bkbc-button--secondary" href="<?php echo esc_url( Pass::url( $baukasten_bc_post->ID ) ); ?>">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG built from a fixed table in Icons.
			echo Icons::get( 'wallet' );
			?>
			<span><?php esc_html_e( 'Apple Wallet', 'baukasten-business-cards' ); ?></span>
		</a>
	<?php endif; ?>

	<?php if ( isset( $baukasten_bc_card['wallet_google_jwt'] ) ) : ?>
		<?php
		/*
		 * The address is built here, from the stored token, rather than kept as
		 * a link somebody pasted. Nothing reaches Google until this is tapped:
		 * no script, no image, no button asset fetched from their servers.
		 */
		$baukasten_bc_google = 'https://pay.google.com/gp/v/save/' . (string) $baukasten_bc_card['wallet_google_jwt'];
		?>
		<a class="bkbc-button bkbc-button--secondary" href="<?php echo esc_url( $baukasten_bc_google ); ?>" rel="noopener">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG built from a fixed table in Icons.
			echo Icons::get( 'wallet' );
			?>
			<span><?php esc_html_e( 'Google Wallet', 'baukasten-business-cards' ); ?></span>
		</a>
	<?php endif; ?>
</div>
