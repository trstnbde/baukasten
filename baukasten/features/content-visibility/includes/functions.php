<?php
/**
 * Public helpers for themes and other modules.
 *
 * @package Baukasten\ContentVisibility
 */

namespace Baukasten\ContentVisibility;

defined( 'ABSPATH' ) || exit;

/**
 * Returns the visibility of a post.
 *
 * @param int $post_id Post ID.
 * @return string Either `public` or `private`.
 */
function get_visibility( int $post_id ): string {
	return Visibility::get( $post_id );
}

/**
 * Whether a post is private, meaning logged-in users only.
 *
 * @param int $post_id Post ID.
 * @return bool True when the post is private.
 */
function is_private( int $post_id ): bool {
	return Visibility::is_private( $post_id );
}

/**
 * Whether the current visitor may see private content.
 *
 * @return bool True when private content may be shown.
 */
function viewer_may_see_private(): bool {
	return Frontend_Guard::viewer_may_see_private();
}
