<?php
/**
 * New Tab Page tab.
 *
 * @package Baukasten\NewTab
 *
 * @var int                  $page_id    Selected page, or 0.
 * @var \WP_Post|null        $page       The selected page.
 * @var array<string, mixed> $settings   Settings, completed with the defaults.
 * @var bool                 $has_key    Whether an Unsplash access key is stored.
 * @var bool                 $is_private Whether Content Visibility keeps the page from logged-out visitors.
 * @var bool                 $is_front   Whether the page is also the homepage.
 * @var array<int, array>    $legal      Legal link candidates from `Legal_Links::candidates()`.
 * @var array<int, string>   $media      Thumbnail URL per selected attachment.
 */

namespace Baukasten\NewTab;

defined( 'ABSPATH' ) || exit;

$baukasten_nt_action = esc_url( admin_url( 'admin-post.php' ) );
$baukasten_nt_rows   = array_merge(
	(array) $settings['favorites'],
	array_fill(
		0,
		3,
		array(
			'title' => '',
			'url'   => '',
			'icon'  => '',
		)
	)
);
$baukasten_nt_cv_tab = class_exists( '\Baukasten\Admin' ) ? \Baukasten\Admin::page_url( 'content-visibility' ) : '';

?>
<p class="baukasten-intro">
	<?php esc_html_e( 'A page of the site that works as the start page of a browser: search with a choice of engines, a favourites bar, a greeting and a background. It is served without the theme, and makes no request to a third party unless a visitor agrees to photos from Unsplash.', 'baukasten-new-tab' ); ?>
</p>

<h2><?php esc_html_e( 'Page', 'baukasten-new-tab' ); ?></h2>

<table class="form-table" role="presentation">
	<tbody>
		<tr>
			<th scope="row"><?php esc_html_e( 'New tab page', 'baukasten-new-tab' ); ?></th>
			<td>
				<?php if ( $page instanceof \WP_Post ) : ?>
					<strong><?php echo esc_html( get_the_title( $page ) ); ?></strong>
					<code><?php echo esc_html( (string) get_permalink( $page ) ); ?></code>
					<p>
						<a class="button" href="<?php echo esc_url( (string) get_permalink( $page ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open the page', 'baukasten-new-tab' ); ?></a>
						<a href="<?php echo esc_url( admin_url( 'options-reading.php' ) ); ?>"><?php esc_html_e( 'Choose another page under Settings → Reading', 'baukasten-new-tab' ); ?></a>
					</p>
					<?php if ( $is_front ) : ?>
						<p class="description"><?php esc_html_e( 'This page is also the homepage, so the address of the site opens it.', 'baukasten-new-tab' ); ?></p>
					<?php endif; ?>
				<?php else : ?>
					<p><?php esc_html_e( 'No page is selected. The choice is made under Settings → Reading, like the homepage.', 'baukasten-new-tab' ); ?></p>
					<form method="post" action="<?php echo $baukasten_nt_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( Settings_Tab::ACTION_CREATE_PAGE ); ?>" />
						<?php wp_nonce_field( Settings_Tab::NONCE ); ?>
						<?php submit_button( __( 'Create page and select it', 'baukasten-new-tab' ), 'secondary', 'submit', false ); ?>
						<a href="<?php echo esc_url( admin_url( 'options-reading.php' ) ); ?>"><?php esc_html_e( 'Settings → Reading', 'baukasten-new-tab' ); ?></a>
					</form>
					<p class="description">
						<?php
						printf(
							/* translators: %s: page slug. */
							esc_html__( 'Creates a published page called "New Tab" at /%s/, if that address is free.', 'baukasten-new-tab' ),
							esc_html( Settings_Tab::PAGE_SLUG )
						);
						?>
					</p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Who can open it', 'baukasten-new-tab' ); ?></th>
			<td>
				<?php if ( 0 >= $page_id ) : ?>
					<?php esc_html_e( 'Decided per page, with the public/private switch of Content Visibility.', 'baukasten-new-tab' ); ?>
				<?php elseif ( $is_private ) : ?>
					<?php esc_html_e( 'Private: only logged-in users see it, everybody else is sent to the login screen.', 'baukasten-new-tab' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Public: anybody with the address sees it.', 'baukasten-new-tab' ); ?>
				<?php endif; ?>
				<p class="description">
					<?php esc_html_e( 'Switch it in the page editor, under Visibility. A public page shows its favourites to everybody — and favourites can give away addresses that are meant to stay private.', 'baukasten-new-tab' ); ?>
					<?php if ( '' !== $baukasten_nt_cv_tab ) : ?>
						<a href="<?php echo esc_url( $baukasten_nt_cv_tab ); ?>"><?php esc_html_e( 'Content Visibility', 'baukasten-new-tab' ); ?></a>
					<?php endif; ?>
				</p>
			</td>
		</tr>
	</tbody>
</table>

<form method="post" action="<?php echo $baukasten_nt_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>" class="baukasten-new-tab-form">
	<input type="hidden" name="action" value="<?php echo esc_attr( Settings_Tab::ACTION_SAVE ); ?>" />
	<?php wp_nonce_field( Settings_Tab::NONCE ); ?>

	<h2><?php esc_html_e( 'Search', 'baukasten-new-tab' ); ?></h2>

	<p><?php esc_html_e( 'The menu in the search box offers the engines that are switched on; the choice is remembered per browser. Typing @ and a shortcut in front of the terms searches one engine once. {q} in an address stands for the search terms.', 'baukasten-new-tab' ); ?></p>

	<table class="widefat striped baukasten-new-tab-engines">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'On', 'baukasten-new-tab' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Default', 'baukasten-new-tab' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Name', 'baukasten-new-tab' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Shortcut', 'baukasten-new-tab' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Address', 'baukasten-new-tab' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( (array) $settings['engines'] as $baukasten_nt_id => $baukasten_nt_engine ) : ?>
			<tr>
				<td><input type="checkbox" name="engines[<?php echo esc_attr( $baukasten_nt_id ); ?>][enabled]" value="1" <?php checked( ! empty( $baukasten_nt_engine['enabled'] ) ); ?> aria-label="<?php echo esc_attr( (string) $baukasten_nt_engine['label'] ); ?>" /></td>
				<td><input type="radio" name="default_engine" value="<?php echo esc_attr( $baukasten_nt_id ); ?>" <?php checked( $settings['default_engine'], $baukasten_nt_id ); ?> aria-label="<?php echo esc_attr( (string) $baukasten_nt_engine['label'] ); ?>" /></td>
				<td><input type="text" class="regular-text" name="engines[<?php echo esc_attr( $baukasten_nt_id ); ?>][label]" value="<?php echo esc_attr( (string) $baukasten_nt_engine['label'] ); ?>" maxlength="40" /></td>
				<td><input type="text" class="small-text" name="engines[<?php echo esc_attr( $baukasten_nt_id ); ?>][shortcut]" value="<?php echo esc_attr( (string) $baukasten_nt_engine['shortcut'] ); ?>" maxlength="8" /></td>
				<td><input type="url" class="large-text code" name="engines[<?php echo esc_attr( $baukasten_nt_id ); ?>][template]" value="<?php echo esc_attr( (string) $baukasten_nt_engine['template'] ); ?>" /></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Suggestions', 'baukasten-new-tab' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Suggestions', 'baukasten-new-tab' ); ?></legend>
						<?php
						$baukasten_nt_suggesters = array(
							'google'     => __( 'Google', 'baukasten-new-tab' ),
							'duckduckgo' => __( 'DuckDuckGo', 'baukasten-new-tab' ),
							'brave'      => __( 'Brave', 'baukasten-new-tab' ),
							'off'        => __( 'Off', 'baukasten-new-tab' ),
						);
						foreach ( $baukasten_nt_suggesters as $baukasten_nt_value => $baukasten_nt_name ) :
							?>
							<label><input type="radio" name="suggest" value="<?php echo esc_attr( $baukasten_nt_value ); ?>" <?php checked( $settings['suggest'], $baukasten_nt_value ); ?> /> <?php echo esc_html( $baukasten_nt_name ); ?></label><br />
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'The server asks the provider, not the browser: the provider sees what is typed, but not who typed it. Answers are cached for ten minutes.', 'baukasten-new-tab' ); ?></p>
					</fieldset>
				</td>
			</tr>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Background', 'baukasten-new-tab' ); ?></h2>

	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Source', 'baukasten-new-tab' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Source', 'baukasten-new-tab' ); ?></legend>
						<label><input type="radio" name="background" value="media" <?php checked( $settings['background'], 'media' ); ?> /> <?php esc_html_e( 'Images from the media library', 'baukasten-new-tab' ); ?></label><br />
						<label><input type="radio" name="background" value="unsplash" <?php checked( $settings['background'], 'unsplash' ); ?> /> <?php esc_html_e( 'Photos from the Unsplash API, after consent', 'baukasten-new-tab' ); ?></label><br />
						<label><input type="radio" name="background" value="off" <?php checked( $settings['background'], 'off' ); ?> /> <?php esc_html_e( 'None, a gradient', 'baukasten-new-tab' ); ?></label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Library images', 'baukasten-new-tab' ); ?></th>
				<td>
					<div class="baukasten-new-tab-media" data-baukasten-new-tab-media>
						<input type="hidden" name="media_ids" value="<?php echo esc_attr( implode( ',', array_keys( $media ) ) ); ?>" />
						<ul class="baukasten-new-tab-media__list">
							<?php foreach ( $media as $baukasten_nt_media_id => $baukasten_nt_thumb ) : ?>
							<li data-id="<?php echo esc_attr( (string) $baukasten_nt_media_id ); ?>"><img src="<?php echo esc_url( $baukasten_nt_thumb ); ?>" alt="" width="75" height="75" /></li>
							<?php endforeach; ?>
						</ul>
						<button type="button" class="button" data-baukasten-new-tab-choose><?php esc_html_e( 'Choose images', 'baukasten-new-tab' ); ?></button>
						<noscript><p class="description"><?php esc_html_e( 'Choosing images needs JavaScript.', 'baukasten-new-tab' ); ?></p></noscript>
					</div>
					<p class="description">
						<?php esc_html_e( 'One is picked at random on every page load. Meant for photos downloaded from unsplash.com: the Unsplash License allows that and asks for no credit, but credit is welcome — write it into the image caption, which the page shows in its footer. An empty caption shows nothing.', 'baukasten-new-tab' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="baukasten-new-tab-key"><?php esc_html_e( 'Unsplash access key', 'baukasten-new-tab' ); ?></label></th>
				<td>
					<input type="password" class="regular-text code" id="baukasten-new-tab-key" name="unsplash_key" value="" autocomplete="off" placeholder="<?php echo esc_attr( $has_key ? __( 'Stored — leave empty to keep it', 'baukasten-new-tab' ) : '' ); ?>" />
					<?php if ( $has_key ) : ?>
						<label><input type="checkbox" name="unsplash_key_delete" value="1" /> <?php esc_html_e( 'Delete the stored key', 'baukasten-new-tab' ); ?></label>
					<?php endif; ?>
					<p class="description"><?php esc_html_e( 'The Access Key of an application at unsplash.com/developers. It stays on the server and never reaches a browser. Photos are hotlinked from images.unsplash.com, as the API guidelines require, so the page asks visitors first and shows the photographer\'s name.', 'baukasten-new-tab' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="baukasten-new-tab-query"><?php esc_html_e( 'Unsplash search terms', 'baukasten-new-tab' ); ?></label></th>
				<td><input type="text" class="regular-text" id="baukasten-new-tab-query" name="unsplash_query" value="<?php echo esc_attr( (string) $settings['unsplash_query'] ); ?>" maxlength="100" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="baukasten-new-tab-orientation"><?php esc_html_e( 'Orientation', 'baukasten-new-tab' ); ?></label></th>
				<td>
					<select id="baukasten-new-tab-orientation" name="unsplash_orientation">
						<?php
						$baukasten_nt_orientations = array(
							'landscape' => __( 'Landscape', 'baukasten-new-tab' ),
							'portrait'  => __( 'Portrait', 'baukasten-new-tab' ),
							'squarish'  => __( 'Square', 'baukasten-new-tab' ),
							'any'       => __( 'Any', 'baukasten-new-tab' ),
						);
						foreach ( $baukasten_nt_orientations as $baukasten_nt_value => $baukasten_nt_name ) :
							?>
							<option value="<?php echo esc_attr( $baukasten_nt_value ); ?>" <?php selected( $settings['unsplash_orientation'], $baukasten_nt_value ); ?>><?php echo esc_html( $baukasten_nt_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="baukasten-new-tab-app"><?php esc_html_e( 'Application name', 'baukasten-new-tab' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="baukasten-new-tab-app" name="unsplash_app_name" value="<?php echo esc_attr( (string) $settings['unsplash_app_name'] ); ?>" placeholder="<?php echo esc_attr( Unsplash::app_name() ); ?>" />
					<p class="description"><?php esc_html_e( 'As registered at Unsplash; used as utm_source in the links to the photographer.', 'baukasten-new-tab' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="baukasten-new-tab-interval"><?php esc_html_e( 'Change every', 'baukasten-new-tab' ); ?></label></th>
				<td>
					<input type="number" class="small-text" id="baukasten-new-tab-interval" name="interval" value="<?php echo esc_attr( (string) $settings['interval'] ); ?>" min="0" max="1440" step="1" />
					<?php esc_html_e( 'minutes', 'baukasten-new-tab' ); ?>
					<p class="description"><?php esc_html_e( '0 changes the background only when the page is loaded. A hidden tab does not change.', 'baukasten-new-tab' ); ?></p>
				</td>
			</tr>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Favourites', 'baukasten-new-tab' ); ?></h2>

	<p><?php esc_html_e( 'The bar at the top. What does not fit moves into the » menu. Icons are fetched by the server when saving and once a week, and served from this site; without one, the bar draws the first letter.', 'baukasten-new-tab' ); ?></p>

	<table class="widefat striped baukasten-new-tab-favorites" data-baukasten-new-tab-favorites>
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Title', 'baukasten-new-tab' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Address', 'baukasten-new-tab' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Icon address (optional)', 'baukasten-new-tab' ); ?></th>
				<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Order', 'baukasten-new-tab' ); ?></span></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $baukasten_nt_rows as $baukasten_nt_index => $baukasten_nt_favorite ) : ?>
			<tr>
				<td><input type="text" class="regular-text" name="favorites[<?php echo esc_attr( (string) $baukasten_nt_index ); ?>][title]" value="<?php echo esc_attr( (string) $baukasten_nt_favorite['title'] ); ?>" maxlength="60" aria-label="<?php esc_attr_e( 'Title', 'baukasten-new-tab' ); ?>" /></td>
				<td><input type="url" class="regular-text code" name="favorites[<?php echo esc_attr( (string) $baukasten_nt_index ); ?>][url]" value="<?php echo esc_attr( (string) $baukasten_nt_favorite['url'] ); ?>" placeholder="https://" aria-label="<?php esc_attr_e( 'Address', 'baukasten-new-tab' ); ?>" /></td>
				<td><input type="url" class="regular-text code" name="favorites[<?php echo esc_attr( (string) $baukasten_nt_index ); ?>][icon]" value="<?php echo esc_attr( (string) $baukasten_nt_favorite['icon'] ); ?>" placeholder="https://" aria-label="<?php esc_attr_e( 'Icon address (optional)', 'baukasten-new-tab' ); ?>" /></td>
				<td class="baukasten-new-tab-favorites__order"></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p><button type="button" class="button" data-baukasten-new-tab-add hidden><?php esc_html_e( 'Add a favourite', 'baukasten-new-tab' ); ?></button></p>

	<h2><?php esc_html_e( 'Legal links', 'baukasten-new-tab' ); ?></h2>

	<p>
		<?php esc_html_e( 'The footer links the privacy policy, the terms and the imprint chosen for Login & Legal, named after their pages.', 'baukasten-new-tab' ); ?>
		<?php if ( class_exists( '\Baukasten\Admin' ) ) : ?>
			<a href="<?php echo esc_url( \Baukasten\Admin::page_url( 'login-legal-pages' ) ); ?>"><?php esc_html_e( 'Change them', 'baukasten-new-tab' ); ?></a>
		<?php endif; ?>
	</p>
	<ul class="baukasten-new-tab-legal">
		<?php foreach ( $legal as $baukasten_nt_link ) : ?>
		<li>
			<strong><?php echo esc_html( (string) $baukasten_nt_link['label'] ); ?></strong>:
			<?php if ( $baukasten_nt_link['usable'] ) : ?>
				<?php esc_html_e( 'shown', 'baukasten-new-tab' ); ?>
			<?php elseif ( 'private' === $baukasten_nt_link['reason'] ) : ?>
				<span class="baukasten-new-tab-warning"><?php esc_html_e( 'left out — the page is private, logged-out visitors would land on the login screen.', 'baukasten-new-tab' ); ?></span>
			<?php elseif ( 'unpublished' === $baukasten_nt_link['reason'] ) : ?>
				<span class="baukasten-new-tab-warning"><?php esc_html_e( 'left out — the page is not published.', 'baukasten-new-tab' ); ?></span>
			<?php else : ?>
				<span class="baukasten-new-tab-warning"><?php esc_html_e( 'left out — no page is chosen.', 'baukasten-new-tab' ); ?></span>
			<?php endif; ?>
		</li>
		<?php endforeach; ?>
	</ul>

	<p class="submit">
		<?php submit_button( __( 'Save changes', 'baukasten-new-tab' ), 'primary', 'submit', false ); ?>
		<?php submit_button( __( 'Save and test the Unsplash connection', 'baukasten-new-tab' ), 'secondary', 'test_unsplash', false ); ?>
	</p>
</form>

<form method="post" action="<?php echo $baukasten_nt_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>">
	<input type="hidden" name="action" value="<?php echo esc_attr( Settings_Tab::ACTION_CLEAR_CACHE ); ?>" />
	<?php wp_nonce_field( Settings_Tab::NONCE ); ?>
	<?php submit_button( __( 'Empty the cache', 'baukasten-new-tab' ), 'secondary', 'submit', false ); ?>
	<span class="description"><?php esc_html_e( 'Forgets the Unsplash photo pool and the cached suggestions.', 'baukasten-new-tab' ); ?></span>
</form>
