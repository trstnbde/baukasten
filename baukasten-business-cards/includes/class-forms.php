<?php
/**
 * Contact Form 7 on a card route.
 *
 * @package Baukasten\BusinessCards
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

/**
 * Embeds a form, and refuses a submission without the email consent.
 *
 * Two things are awkward about a form on a card. The template is swapped after
 * the theme would have run, so Contact Form 7's own decision about whether to
 * load its assets has already been made by the time the shortcode is expanded
 * in the body — its styles would arrive in the footer and anything it prints
 * from `wp_head` would not arrive at all. And the consent checkbox has to be
 * enforced on the server, because a checkbox is the easiest thing in the world
 * to remove from a form and never notice.
 */
final class Forms {

	/**
	 * Contact Form 7's post type.
	 */
	const CF7_POST_TYPE = 'wpcf7_contact_form';

	/**
	 * Contact Form 7's own asset handles.
	 *
	 * Only dequeued on a card that embeds no form, where they are dead weight.
	 */
	const CF7_HANDLES = array(
		'contact-form-7',
		'contact-form-7-rtl',
		'contact-form-7-html5-fallback',
		'swv',
		'jquery-ui-smoothness',
		'cloudflare-turnstile',
	);

	/**
	 * Registers the hooks the form needs.
	 *
	 * The filters are added unconditionally: a filter on a hook that never
	 * fires costs nothing, and Contact Form 7 can be activated at any time.
	 * Only the function calls are guarded.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'match_assets_to_card' ), 20 );
		add_filter( 'wpcf7_validate', array( __CLASS__, 'require_consent' ), 20, 2 );
	}

	/**
	 * Whether Contact Form 7 is active.
	 *
	 * @return bool True when its main class is loaded.
	 */
	public static function is_available(): bool {
		return class_exists( '\WPCF7_ContactForm' );
	}

	/**
	 * The forms a card can embed.
	 *
	 * @return array<int, string> Form ID to title.
	 */
	public static function choices(): array {
		if ( ! self::is_available() ) {
			return array();
		}

		$forms = get_posts(
			array(
				'post_type'              => self::CF7_POST_TYPE,
				'post_status'            => 'any',
				// A dropdown is not a place to render an unbounded query, and
				// a site with more than a hundred contact forms has a problem
				// this plugin is not going to solve.
				'numberposts'            => 100,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'suppress_filters'       => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$choices = array();

		foreach ( $forms as $form ) {
			if ( ! $form instanceof \WP_Post ) {
				continue;
			}

			$title = (string) get_the_title( $form );

			$choices[ (int) $form->ID ] = '' === $title
				? sprintf(
					/* translators: %d: form ID. */
					__( 'Form %d', 'baukasten-business-cards' ),
					(int) $form->ID
				)
				: $title;
		}

		return $choices;
	}

	/**
	 * Gives a card exactly the form assets it needs, and no others.
	 *
	 * Both directions matter. A card that embeds a form has to have the assets
	 * forced, because the shortcode is expanded from the template body, long
	 * after `wp_enqueue_scripts` — its styles would arrive in the footer and
	 * anything printed at `wp_head` time would not arrive at all. A card with
	 * no form should not carry the form plugin's script and stylesheet either,
	 * and current Contact Form 7 versions enqueue both on every page whether a
	 * form is present or not.
	 *
	 * The enqueue functions are idempotent, so forcing them is safe even where
	 * Contact Form 7 got there first.
	 *
	 * @return void
	 */
	public static function match_assets_to_card(): void {
		if ( ! Renderer::is_card_route() ) {
			return;
		}

		$card = Fields::load( get_queried_object_id() );

		if ( isset( $card['cf7_form_id'] ) ) {
			if ( function_exists( 'wpcf7_enqueue_scripts' ) ) {
				wpcf7_enqueue_scripts();
			}

			if ( function_exists( 'wpcf7_enqueue_styles' ) ) {
				wpcf7_enqueue_styles();
			}

			return;
		}

		foreach ( self::CF7_HANDLES as $handle ) {
			wp_dequeue_script( $handle );
			wp_dequeue_style( $handle );
		}
	}

	/**
	 * The form markup for a card, or an empty string.
	 *
	 * `do_shortcode()` prints an unregistered shortcode back out verbatim, so
	 * a card whose form plugin has been deactivated would show
	 * `[contact-form-7 id="42"]` to visitors. Hence the existence check.
	 *
	 * @param int $form_id Contact Form 7 post ID.
	 * @return string Rendered form, or an empty string.
	 */
	public static function render( int $form_id ): string {
		if ( 0 >= $form_id || ! shortcode_exists( 'contact-form-7' ) ) {
			return '';
		}

		return do_shortcode( sprintf( '[contact-form-7 id="%d"]', $form_id ) );
	}

	/**
	 * Fails a submission whose email consent is missing or unticked.
	 *
	 * Priority 20, so this runs after Contact Form 7's own acceptance check and
	 * this wording is the one that survives.
	 *
	 * @param mixed $result Contact Form 7's validation result object.
	 * @param mixed $tags   The form's tag objects.
	 * @return mixed The validation result.
	 */
	public static function require_consent( $result, $tags ) {
		if ( ! is_object( $result ) || ! method_exists( $result, 'invalidate' ) ) {
			return $result;
		}

		$tags       = is_array( $tags ) ? $tags : array();
		$acceptance = null;

		foreach ( $tags as $tag ) {
			// `basetype`, not `type`: an acceptance tag is usually written
			// `acceptance` but may be `acceptance*`, and only basetype is the
			// same for both.
			if ( is_object( $tag ) && isset( $tag->basetype ) && 'acceptance' === $tag->basetype ) {
				$acceptance = $tag;

				break;
			}
		}

		if ( null === $acceptance ) {
			/*
			 * No consent box in the form at all. Fail closed rather than let
			 * the form collect addresses without one — but say that the form
			 * is at fault, because it is, and the visitor has no checkbox to
			 * tick and no way to guess what is wanted.
			 */
			$first = reset( $tags );

			if ( is_object( $first ) ) {
				$result->invalidate(
					$first,
					__( 'This form is missing its consent checkbox and cannot be submitted. Please use one of the other ways to get in touch.', 'baukasten-business-cards' )
				);
			}

			return $result;
		}

		$name = isset( $acceptance->name ) ? (string) $acceptance->name : '';

		// An unticked checkbox posts nothing at all, so presence is the test.
		// Read through Contact Form 7's own accessor rather than $_POST: it is
		// what its acceptance module uses, and it keeps the two in step.
		$posted = function_exists( 'wpcf7_superglobal_post' )
			? (string) wpcf7_superglobal_post( $name )
			: '';

		if ( '' === $posted || '0' === $posted ) {
			$result->invalidate(
				$acceptance,
				__( 'Consent to processing your email address is required.', 'baukasten-business-cards' )
			);
		}

		return $result;
	}
}
