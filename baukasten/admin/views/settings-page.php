<?php
/**
 * Settings screen frame.
 *
 * @package Baukasten
 *
 * @var array<string, array{title: string, render: callable}> $tabs    All tabs.
 * @var string                                                $current Id of the active tab.
 * @var array{0: string, 1: string}|null                      $notice  Notice from the last action.
 */

namespace Baukasten;

defined( 'ABSPATH' ) || exit;

?>
<div class="wrap baukasten-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Baukasten - Privacy Toolkit', 'baukasten' ); ?></h1>

	<?php if ( null !== $notice ) : ?>
		<div class="notice notice-<?php echo 'error' === $notice[0] ? 'error' : 'success'; ?> is-dismissible">
			<p><?php echo esc_html( $notice[1] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( count( $tabs ) > 1 ) : ?>
		<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Baukasten settings tabs', 'baukasten' ); ?>">
			<?php foreach ( $tabs as $baukasten_tab_id => $baukasten_tab ) : ?>
				<a
					href="<?php echo esc_url( Admin::page_url( (string) $baukasten_tab_id ) ); ?>"
					class="nav-tab<?php echo $baukasten_tab_id === $current ? ' nav-tab-active' : ''; ?>"
					<?php echo $baukasten_tab_id === $current ? ' aria-current="page"' : ''; ?>
				>
					<?php echo esc_html( (string) $baukasten_tab['title'] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

	<div class="baukasten-tab-panel">
		<?php call_user_func( $tabs[ $current ]['render'] ); ?>
	</div>
</div>
