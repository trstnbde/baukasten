<?php
/**
 * The legal links at the foot of every card.
 *
 * Where they come from is Legal_Links' business, not this file's: a site running
 * the Login Legal Pages addon has already said where its imprint is.
 *
 * @package Baukasten\BusinessCards
 *
 * @var array<string, mixed> $baukasten_bc_card Loaded card fields.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

$baukasten_bc_legal = Legal_Links::all( $baukasten_bc_card );

if ( array() === $baukasten_bc_legal ) {
	return;
}

?>
<footer class="bkbc-footer">
	<nav aria-label="<?php esc_attr_e( 'Legal', 'baukasten-business-cards' ); ?>">
		<ul class="bkbc-footer__list">
			<?php foreach ( $baukasten_bc_legal as $baukasten_bc_item ) : ?>
				<li>
					<a href="<?php echo esc_url( (string) $baukasten_bc_item['url'] ); ?>" target="_self">
						<?php echo esc_html( (string) $baukasten_bc_item['label'] ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</nav>
</footer>
