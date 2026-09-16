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
	 * Contact Form 7's handle for the Turnstile script.
	 */
	const TURNSTILE_HANDLE = 'cloudflare-turnstile';

	/**
	 * Handle of the script that loads Turnstile once the form is used.
	 */
	const LOADER_HANDLE = 'baukasten-business-cards-form';

	/**
	 * Option holding the id of the form this plugin created.
	 */
	const OPTION_FORM_ID = 'baukasten_business_cards_form_id';

	/**
	 * Stand-in address for the privacy policy inside the consent sentence.
	 */
	const PLACEHOLDER_PRIVACY = 'https://baukasten.invalid/privacy';

	/**
	 * Stand-in address for the terms inside the consent sentence.
	 */
	const PLACEHOLDER_TERMS = 'https://baukasten.invalid/terms';

	/**
	 * Registers the hooks the form needs.
	 *
	 * The filters are added unconditionally and the function calls stay
	 * guarded, even though the plugin header now requires Contact Form 7.
	 * `Requires Plugins` is enforced at activation, not for ever after: an
	 * admin can switch Contact Form 7 off at any time, and a card route that
	 * answered that with a fatal error would take the whole card down over a
	 * form it could simply leave out.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'match_assets_to_card' ), 20 );
		add_filter( 'wpcf7_validate', array( __CLASS__, 'require_consent' ), 20, 2 );
		add_filter( 'wpcf7_form_elements', array( __CLASS__, 'localise_consent_links' ) );
	}

	/**
	 * The form this plugin created, if it is still there.
	 *
	 * The stored id alone is not enough — a form can be trashed or deleted, and
	 * an option pointing at a hole would make the button say "already exists"
	 * for ever with nothing to edit. Matching on the title is not used at all: a
	 * title is editable, and renaming the form is a reasonable thing to do.
	 *
	 * @return int Form id, or 0.
	 */
	public static function plugin_form_id(): int {
		$form_id = absint( get_option( self::OPTION_FORM_ID, 0 ) );

		if ( 0 >= $form_id ) {
			return 0;
		}

		$post = get_post( $form_id );

		if ( ! $post instanceof \WP_Post || self::CF7_POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) {
			delete_option( self::OPTION_FORM_ID );

			return 0;
		}

		return $form_id;
	}

	/**
	 * Builds the contact form a card can embed.
	 *
	 * Contact Form 7's own template is the starting point, so the messages and
	 * the second mail block come from it rather than from a copy here that would
	 * drift out of step with it.
	 *
	 * The form's text is stored, not translated at render time — a form body is
	 * content. It is written once, in the admin's language, and can be edited
	 * afterwards like any other form.
	 *
	 * @return int The new form's id, or 0 when it could not be made.
	 */
	public static function create(): int {
		if ( ! self::is_available() ) {
			return 0;
		}

		$form = \WPCF7_ContactForm::get_template(
			array( 'title' => __( 'Baukasten Addon: Business Cards', 'baukasten-business-cards' ) )
		);

		if ( ! $form instanceof \WPCF7_ContactForm ) {
			return 0;
		}

		$form->set_properties(
			array(
				'form'                => self::form_body(),
				'mail'                => self::mail(),
				'mail_2'              => array( 'active' => false ),

				/*
				 * Without this Contact Form 7 disables the submit button until
				 * the box is ticked and says nothing about why. With it the
				 * refusal is a message beside the checkbox — which is where
				 * `require_consent()` puts its own, so the two agree.
				 */
				'additional_settings' => "acceptance_as_validation: on\n",
			)
		);

		$form_id = absint( $form->save() );

		if ( 0 < $form_id ) {
			update_option( self::OPTION_FORM_ID, $form_id, true );
		}

		return $form_id;
	}

	/**
	 * Points the consent sentence at this card's own legal pages.
	 *
	 * The form is one post shared by every card; the pages it names may not be.
	 * So it carries two stand-in anchors, resolved here over the rendered HTML,
	 * once per view.
	 *
	 * A stand-in with no page behind it is unwrapped rather than left as a dead
	 * anchor: a link to nowhere in a consent sentence is worse than no link,
	 * because it looks like the visitor was given something to read.
	 *
	 * @param mixed $elements Rendered form HTML.
	 * @return string Form HTML.
	 */
	public static function localise_consent_links( $elements ): string {
		$elements = (string) $elements;

		if ( ! Renderer::is_card_route() ) {
			return $elements;
		}

		$card = Fields::load( get_queried_object_id() );

		$pairs = array(
			self::PLACEHOLDER_PRIVACY => 'privacy',
			self::PLACEHOLDER_TERMS   => 'terms',
		);

		foreach ( $pairs as $placeholder => $name ) {
			$url = Legal_Links::url( $card, $name );

			if ( '' !== $url ) {
				$elements = str_replace(
					'href="' . $placeholder . '"',
					'href="' . esc_url( $url ) . '" target="_self"',
					$elements
				);

				continue;
			}

			$elements = (string) preg_replace(
				'#<a href="' . preg_quote( $placeholder, '#' ) . '">(.*?)</a>#s',
				'$1',
				$elements
			);
		}

		return $elements;
	}

	/**
	 * Whether Contact Form 7 is active.
	 *
	 * Required by the plugin header, so on a healthy site this is always true;
	 * it is false in the window where an admin has switched Contact Form 7 off
	 * without switching this plugin off with it.
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

			self::defer_turnstile();

			return;
		}

		foreach ( self::CF7_HANDLES as $handle ) {
			wp_dequeue_script( $handle );
			wp_dequeue_style( $handle );
		}
	}

	/**
	 * Takes Turnstile out of the page load and hands it to the card's loader.
	 *
	 * Contact Form 7 enqueues Cloudflare's script on every page once the
	 * integration has keys, which on a card means every visitor's address goes
	 * to Cloudflare whether they ever touch the form or not. And with the
	 * Consent Blocking Engine active it is worse than that: the engine rightly
	 * treats Cloudflare as a third party, rewrites the tag to `text/plain`, and
	 * a card offers no way to consent — so no token is ever made and every
	 * submission is filed as spam, with nothing on the screen to say why.
	 *
	 * So the script is dequeued here and `assets/js/form.js` loads it on the
	 * first sign that somebody is using the form. The loader is served from this
	 * site, so the engine has no reason to hold it back, and the script it adds
	 * afterwards is added in the browser, where the engine does not rewrite
	 * anything. Whoever only looks at the card causes no request to Cloudflare.
	 *
	 * @return void
	 */
	private static function defer_turnstile(): void {
		$scripts = wp_scripts();

		if ( ! wp_script_is( self::TURNSTILE_HANDLE, 'enqueued' ) || ! isset( $scripts->registered[ self::TURNSTILE_HANDLE ] ) ) {
			return;
		}

		$src = (string) $scripts->registered[ self::TURNSTILE_HANDLE ]->src;

		wp_dequeue_script( self::TURNSTILE_HANDLE );

		if ( '' === $src ) {
			return;
		}

		wp_enqueue_script(
			self::LOADER_HANDLE,
			PLUGIN_URL . 'assets/js/form.js',
			array( 'contact-form-7' ),
			VERSION,
			array( 'in_footer' => true )
		);

		wp_localize_script(
			self::LOADER_HANDLE,
			'baukastenBusinessCardsForm',
			array(
				'turnstileSrc' => add_query_arg(
					array(
						'render' => 'explicit',
						'onload' => 'baukastenBusinessCardsTurnstileReady',
					),
					$src
				),
			)
		);
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

		/*
		 * Rendered as though inside the loop, for one reason: that is the only
		 * way Contact Form 7 sends the card's id along as `_wpcf7_container_post`.
		 * It fills that field from `get_the_ID()` when `in_the_loop()` is true
		 * and from nothing otherwise, and a filter cannot change it, because the
		 * filtered fields are merged in with `+=`. The card template is not a
		 * loop, so without this every submission would arrive as if from
		 * nowhere — and `require_consent()` could not tell a card's form from
		 * any other form on the site.
		 */
		global $wp_query;

		$restore = $wp_query instanceof \WP_Query ? $wp_query->in_the_loop : null;

		if ( null !== $restore ) {
			$wp_query->in_the_loop = true;
		}

		$html = do_shortcode( sprintf( '[contact-form-7 id="%d"]', $form_id ) );

		if ( null !== $restore ) {
			$wp_query->in_the_loop = $restore;
		}

		return $html;
	}

	/**
	 * The card a submission was sent from, if it was sent from one.
	 *
	 * @return int Card post ID, or 0.
	 */
	private static function submitted_from_card(): int {
		if ( ! function_exists( 'wpcf7_superglobal_post' ) ) {
			return 0;
		}

		$post_id = absint( wpcf7_superglobal_post( '_wpcf7_container_post' ) );

		if ( 0 === $post_id || Post_Type::POST_TYPE !== get_post_type( $post_id ) ) {
			return 0;
		}

		return 'publish' === get_post_status( $post_id ) ? $post_id : 0;
	}

	/**
	 * The created form's tags.
	 *
	 * The two addresses in the consent sentence are stand-ins, rewritten per
	 * card by `localise_consent_links()`. They cannot be shortcodes: Contact
	 * Form 7 scans a tag's content with `(?:([^[]*?)\[\/\2\])?`, so a single `[`
	 * inside would end the `[acceptance]` early. Plain anchors survive, and its
	 * acceptance module prints the content unescaped.
	 *
	 * @return string Form body.
	 */
	private static function form_body(): string {
		$consent = sprintf(
			/* translators: 1: opening link tag to the privacy policy. 2: closing link tag. 3: opening link tag to the terms. 4: closing link tag. */
			__( 'I have read the %1$sprivacy policy%2$s and the %3$sterms%4$s and consent to my email address being processed so that my message can be answered.', 'baukasten-business-cards' ),
			'<a href="' . self::PLACEHOLDER_PRIVACY . '">',
			'</a>',
			'<a href="' . self::PLACEHOLDER_TERMS . '">',
			'</a>'
		);

		$lines = array(
			'<label>' . __( 'Name', 'baukasten-business-cards' ),
			'    [text* your-name autocomplete:name]</label>',
			'',
			'<label>' . __( 'Email', 'baukasten-business-cards' ),
			'    [email* your-email autocomplete:email]</label>',
			'',
			'<label>' . __( 'Message', 'baukasten-business-cards' ),
			'    [textarea* your-message]</label>',
			'',
			'[acceptance email-consent] ' . $consent . ' [/acceptance]',
			'',
			// Prints nothing unless Turnstile is set up under Contact, Integration.
			// Without the tag Contact Form 7 puts the widget above the first field.
			'[turnstile size:flexible]',
			'',
			'[submit "' . __( 'Send message', 'baukasten-business-cards' ) . '"]',
		);

		return implode( "\n", $lines );
	}

	/**
	 * The created form's mail configuration.
	 *
	 * Contact Form 7's default references `[your-subject]`, which this form does
	 * not have — it would arrive as an empty pair of quotes in every subject
	 * line — so the lines that use it are replaced.
	 *
	 * `[email-consent]` in the body is the acceptance tag's own mail tag, which
	 * Contact Form 7 replaces with the consent sentence that was actually shown.
	 * For a consent that is the whole point of the checkbox, that is the record
	 * worth keeping.
	 *
	 * @return array<string, mixed> Mail properties.
	 */
	private static function mail(): array {
		$body = array(
			'[your-name] <[your-email]>',
			'',
			'[your-message]',
			'',
			'-- ',
			__( 'Sent from a business card at [_url]', 'baukasten-business-cards' ),
			__( 'Consent given: [email-consent]', 'baukasten-business-cards' ),
		);

		return array(
			'active'             => true,
			'subject'            => sprintf(
				/* translators: %s: the site title mail tag. */
				__( 'Business card enquiry via %s', 'baukasten-business-cards' ),
				'[_site_title]'
			),
			'sender'             => '[_site_title] <wordpress@' . self::mail_host() . '>',
			'recipient'          => '[_site_admin_email]',
			'body'               => implode( "\n", $body ),
			'additional_headers' => 'Reply-To: [your-email]',
			'attachments'        => '',
			'use_html'           => 0,
			'exclude_blank'      => 0,
		);
	}

	/**
	 * The host to send the created form's mail from.
	 *
	 * The same rule `wp_mail()` uses for its own default sender: the site's host
	 * without a leading `www.`, so the address stands a chance of passing the
	 * checks the admin's own address would not.
	 *
	 * @return string Host name.
	 */
	private static function mail_host(): string {
		$host = strtolower( (string) wp_parse_url( network_home_url(), PHP_URL_HOST ) );

		return str_starts_with( $host, 'www.' ) ? substr( $host, 4 ) : $host;
	}

	/**
	 * Fails a card submission whose email consent is missing or unticked.
	 *
	 * Priority 20, so this runs after Contact Form 7's own acceptance check and
	 * this wording is the one that survives.
	 *
	 * Only for submissions sent from a card. `wpcf7_validate` runs for every
	 * form on the site, and a rule that fails any form without a consent box
	 * would break the site's other forms — which it did, until this check.
	 *
	 * @param mixed $result Contact Form 7's validation result object.
	 * @param mixed $tags   The form's tag objects.
	 * @return mixed The validation result.
	 */
	public static function require_consent( $result, $tags ) {
		if ( ! is_object( $result ) || ! method_exists( $result, 'invalidate' ) ) {
			return $result;
		}

		if ( 0 === self::submitted_from_card() ) {
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
