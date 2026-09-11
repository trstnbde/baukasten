<?php
/**
 * One card meta box.
 *
 * Every box is this file with a different group; the schema decides which
 * control each field gets.
 *
 * @package Baukasten\BusinessCards
 *
 * @var string                              $group  Field group being rendered.
 * @var string[]                            $keys   Unprefixed field keys in this group.
 * @var array<string, string>               $labels Field key to label.
 * @var array<string, string>               $notes  Field key to description.
 * @var array<string, string>               $types  Field key to schema type.
 * @var array<string, string>               $values Stored values, keyed by field key.
 * @var array<string, string>               $legacy Values from 1.0 that had no new home.
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

?>
<table class="form-table baukasten-bc-fields baukasten-bc-fields--<?php echo esc_attr( $group ); ?>" role="presentation">
	<tbody>
	<?php foreach ( $keys as $baukasten_bc_key ) : ?>
		<?php
		$baukasten_bc_type  = isset( $types[ $baukasten_bc_key ] ) ? (string) $types[ $baukasten_bc_key ] : 'text';
		$baukasten_bc_name  = Fields::meta_key( $baukasten_bc_key );
		$baukasten_bc_value = isset( $values[ $baukasten_bc_key ] ) ? $values[ $baukasten_bc_key ] : '';
		$baukasten_bc_label = isset( $labels[ $baukasten_bc_key ] ) ? $labels[ $baukasten_bc_key ] : $baukasten_bc_key;
		$baukasten_bc_note  = isset( $notes[ $baukasten_bc_key ] ) ? $notes[ $baukasten_bc_key ] : '';
		$baukasten_bc_old   = isset( $legacy[ $baukasten_bc_key ] ) ? $legacy[ $baukasten_bc_key ] : '';
		?>
		<tr>
			<th scope="row">
				<?php if ( 'layout' === $baukasten_bc_type ) : ?>
					<span><?php echo esc_html( $baukasten_bc_label ); ?></span>
				<?php elseif ( 'bool' === $baukasten_bc_type ) : ?>
					<?php /* The checkbox carries its own label; a second one here just says it twice. */ ?>
				<?php else : ?>
					<label for="<?php echo esc_attr( $baukasten_bc_name ); ?>"><?php echo esc_html( $baukasten_bc_label ); ?></label>
				<?php endif; ?>
			</th>
			<td>
				<?php if ( 'layout' === $baukasten_bc_type ) : ?>
					<?php
					$baukasten_bc_choices = Skins::choices();
					$baukasten_bc_current = isset( $baukasten_bc_choices[ $baukasten_bc_value ] )
						? $baukasten_bc_value
						: Skins::DEFAULT_SKIN;
					?>
					<fieldset>
						<legend class="screen-reader-text"><?php echo esc_html( $baukasten_bc_label ); ?></legend>
						<?php foreach ( $baukasten_bc_choices as $baukasten_bc_choice => $baukasten_bc_choice_label ) : ?>
							<label class="baukasten-bc-radio">
								<input
									type="radio"
									name="<?php echo esc_attr( $baukasten_bc_name ); ?>"
									value="<?php echo esc_attr( $baukasten_bc_choice ); ?>"
									<?php checked( $baukasten_bc_current, $baukasten_bc_choice ); ?>
								/>
								<?php echo esc_html( $baukasten_bc_choice_label ); ?>
							</label><br />
						<?php endforeach; ?>
					</fieldset>

				<?php elseif ( 'scheme' === $baukasten_bc_type ) : ?>
					<?php
					$baukasten_bc_schemes = array(
						'system' => __( 'Follow the reader', 'baukasten-business-cards' ),
						'light'  => __( 'Always light', 'baukasten-business-cards' ),
						'dark'   => __( 'Always dark', 'baukasten-business-cards' ),
					);

					$baukasten_bc_current = isset( $baukasten_bc_schemes[ $baukasten_bc_value ] )
						? $baukasten_bc_value
						: Fields::DEFAULT_SCHEME;
					?>
					<select id="<?php echo esc_attr( $baukasten_bc_name ); ?>" name="<?php echo esc_attr( $baukasten_bc_name ); ?>">
						<?php foreach ( $baukasten_bc_schemes as $baukasten_bc_scheme => $baukasten_bc_scheme_label ) : ?>
							<option value="<?php echo esc_attr( $baukasten_bc_scheme ); ?>" <?php selected( $baukasten_bc_current, $baukasten_bc_scheme ); ?>>
								<?php echo esc_html( $baukasten_bc_scheme_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>

				<?php elseif ( 'page' === $baukasten_bc_type ) : ?>
					<?php
					// wp_dropdown_pages() prints an escaped <select>; core calls it
					// the same way for the privacy policy picker, and so does the
					// Login Legal Pages addon.
					// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
					wp_dropdown_pages(
						array(
							'name'              => $baukasten_bc_name,
							'id'                => $baukasten_bc_name,
							'selected'          => absint( $baukasten_bc_value ),
							'show_option_none'  => __( '— No page —', 'baukasten-business-cards' ),
							'option_none_value' => '0',
							// Drafts are offered so a card can point at a page that
							// is not live yet; the footer will not print it until
							// it is.
							'post_status'       => array( 'publish', 'draft' ),
						)
					);
					// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
					?>

				<?php elseif ( 'bool' === $baukasten_bc_type ) : ?>
					<label>
						<input
							type="checkbox"
							id="<?php echo esc_attr( $baukasten_bc_name ); ?>"
							name="<?php echo esc_attr( $baukasten_bc_name ); ?>"
							value="1"
							<?php checked( '1', $baukasten_bc_value ); ?>
						/>
						<?php echo esc_html( $baukasten_bc_label ); ?>
					</label>

				<?php elseif ( 'image' === $baukasten_bc_type || 'file' === $baukasten_bc_type ) : ?>
					<?php
					$baukasten_bc_id      = absint( $baukasten_bc_value );
					$baukasten_bc_preview = '';

					if ( 0 < $baukasten_bc_id ) {
						$baukasten_bc_preview = 'image' === $baukasten_bc_type
							? (string) wp_get_attachment_image( $baukasten_bc_id, 'medium', false, array( 'alt' => '' ) )
							: esc_html( (string) get_the_title( $baukasten_bc_id ) );
					}
					?>
					<?php
					// The Apple pass field takes exactly one media type, so the
					// library is filtered to it rather than offering every file
					// on the site and failing later.
					$baukasten_bc_mime = 'wallet_apple_pass_id' === $baukasten_bc_key ? Pass::MIME : '';
					?>
					<div
						class="baukasten-bc-media"
						data-kind="<?php echo esc_attr( $baukasten_bc_type ); ?>"
						<?php echo '' !== $baukasten_bc_mime ? 'data-mime="' . esc_attr( $baukasten_bc_mime ) . '"' : ''; ?>
					>
						<input
							type="hidden"
							id="<?php echo esc_attr( $baukasten_bc_name ); ?>"
							name="<?php echo esc_attr( $baukasten_bc_name ); ?>"
							value="<?php echo esc_attr( (string) $baukasten_bc_id ); ?>"
							class="baukasten-bc-media__value"
						/>
						<div class="baukasten-bc-media__preview">
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() escapes its own output, and the file title branch is escaped above.
							echo $baukasten_bc_preview;
							?>
						</div>
						<p>
							<button type="button" class="button baukasten-bc-media__select">
								<?php echo 'image' === $baukasten_bc_type ? esc_html__( 'Choose image', 'baukasten-business-cards' ) : esc_html__( 'Choose file', 'baukasten-business-cards' ); ?>
							</button>
							<button type="button" class="button-link baukasten-bc-media__clear"<?php echo 0 < $baukasten_bc_id ? '' : ' hidden'; ?>>
								<?php esc_html_e( 'Remove', 'baukasten-business-cards' ); ?>
							</button>
						</p>
					</div>

				<?php elseif ( 'form' === $baukasten_bc_type ) : ?>
					<?php if ( Forms::is_available() ) : ?>
						<select id="<?php echo esc_attr( $baukasten_bc_name ); ?>" name="<?php echo esc_attr( $baukasten_bc_name ); ?>">
							<option value="0"><?php esc_html_e( '— No form —', 'baukasten-business-cards' ); ?></option>
							<?php foreach ( Forms::choices() as $baukasten_bc_form_id => $baukasten_bc_form_title ) : ?>
								<option value="<?php echo esc_attr( (string) $baukasten_bc_form_id ); ?>" <?php selected( (string) $baukasten_bc_form_id, $baukasten_bc_value ); ?>>
									<?php echo esc_html( $baukasten_bc_form_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'The form must contain an acceptance checkbox for the email consent, otherwise it cannot be submitted.', 'baukasten-business-cards' ); ?>
						</p>
					<?php else : ?>
						<input type="hidden" name="<?php echo esc_attr( $baukasten_bc_name ); ?>" value="<?php echo esc_attr( $baukasten_bc_value ); ?>" />
						<p class="description">
							<?php esc_html_e( 'Contact Form 7 is not active. Any form already chosen is kept and will come back when the plugin does.', 'baukasten-business-cards' ); ?>
						</p>
					<?php endif; ?>

				<?php elseif ( 'html' === $baukasten_bc_type || 'textarea' === $baukasten_bc_type ) : ?>
					<textarea
						id="<?php echo esc_attr( $baukasten_bc_name ); ?>"
						name="<?php echo esc_attr( $baukasten_bc_name ); ?>"
						rows="5"
						class="large-text"
					><?php echo esc_textarea( $baukasten_bc_value ); ?></textarea>

				<?php else : ?>
					<?php
					$baukasten_bc_input = 'text';

					if ( 'email' === $baukasten_bc_type ) {
						$baukasten_bc_input = 'email';
					} elseif ( 'url' === $baukasten_bc_type ) {
						$baukasten_bc_input = 'url';
					} elseif ( 'tel' === $baukasten_bc_type || 'tel_digits' === $baukasten_bc_type ) {
						$baukasten_bc_input = 'tel';
					}
					?>
					<input
						type="<?php echo esc_attr( $baukasten_bc_input ); ?>"
						id="<?php echo esc_attr( $baukasten_bc_name ); ?>"
						name="<?php echo esc_attr( $baukasten_bc_name ); ?>"
						value="<?php echo esc_attr( $baukasten_bc_value ); ?>"
						class="regular-text"
					/>
				<?php endif; ?>

				<?php if ( '' !== $baukasten_bc_note ) : ?>
					<p class="description"><?php echo esc_html( $baukasten_bc_note ); ?></p>
				<?php endif; ?>

				<?php if ( '' !== $baukasten_bc_old ) : ?>
					<p class="description baukasten-bc-legacy">
						<?php
						printf(
							/* translators: %s: the value this field held in the previous version. */
							esc_html__( 'Previously: %s — this is no longer used, and is shown so it is not lost.', 'baukasten-business-cards' ),
							'<code>' . esc_html( $baukasten_bc_old ) . '</code>'
						);
						?>
					</p>
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
