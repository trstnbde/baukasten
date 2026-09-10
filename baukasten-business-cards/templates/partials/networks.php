<?php
/**
 * Profiles on other networks.
 *
 * Labelled by name rather than by logo: a brand mark is a trademark with its
 * own usage rules, and a GPL plugin has no business shipping a set of them.
 *
 * @package Baukasten\BusinessCards
 *
 * @var array<string, mixed> $baukasten_bc_card Loaded card fields.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

$baukasten_bc_networks = array(
	'network_linkedin'  => 'LinkedIn',
	'network_xing'      => 'Xing',
	'network_github'    => 'GitHub',
	'network_mastodon'  => 'Mastodon',
	'network_facebook'  => 'Facebook',
	'network_instagram' => 'Instagram',
	'network_threads'   => 'Threads',
	'network_discord'   => 'Discord',
	'network_signal'    => 'Signal',
);

if ( ! Fields::has_any( $baukasten_bc_card, array_keys( $baukasten_bc_networks ) ) ) {
	return;
}

?>
<section class="bkbc-section bkbc-networks">
	<h2 class="bkbc-section__title"><?php esc_html_e( 'Elsewhere', 'baukasten-business-cards' ); ?></h2>

	<ul class="bkbc-networks__list">
		<?php foreach ( $baukasten_bc_networks as $baukasten_bc_key => $baukasten_bc_name ) : ?>
			<?php if ( isset( $baukasten_bc_card[ $baukasten_bc_key ] ) ) : ?>
				<li class="bkbc-networks__item">
					<a class="bkbc-networks__link" href="<?php echo esc_url( (string) $baukasten_bc_card[ $baukasten_bc_key ] ); ?>" rel="me noopener">
						<span class="bkbc-networks__initial" aria-hidden="true"><?php echo esc_html( Icons::initial( $baukasten_bc_key ) ); ?></span>
						<span><?php echo esc_html( $baukasten_bc_name ); ?></span>
					</a>
				</li>
			<?php endif; ?>
		<?php endforeach; ?>
	</ul>
</section>
