<?php
/**
 * Turns a Contact Form 7 submission into AI-ready prompt text.
 *
 * @package InboxAI\AI
 */

namespace InboxAI\AI;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PromptBuilder
 *
 * Contact Form 7 does not track a structured "label" per field separately
 * from the surrounding form HTML, so field names are prettified (e.g.
 * `your-message` becomes "Your Message") rather than guessed at by a fixed
 * field-name convention — this works for any form layout. The two prompts
 * built here (`build_analysis_prompt()`/`build_reply_prompt()`) substitute
 * into whatever template text is currently stored in
 * `Settings\Repository::get_prompts()` — the admin-editable human-readable
 * instructions stay exactly as configured on the Prompts tab; only a fixed,
 * non-editable output-format footer is appended, so parsing stays reliable
 * no matter how the admin edits the rest of the template.
 */
final class PromptBuilder {

	/**
	 * Form tag base types that never carry meaningful text content and are
	 * excluded from the formatted submission (file uploads, spam
	 * countermeasures).
	 *
	 * @var string[]
	 */
	private const SKIP_BASETYPES = array( 'file', 'file*', 'acceptance', 'quiz', 'captchar', 'captchac' );

	/**
	 * Form tag base types that carry a phone number.
	 *
	 * @var string[]
	 */
	private const PHONE_BASETYPES = array( 'tel' );

	/**
	 * Formats a submission's posted data into a readable "Label: value" text
	 * block suitable for inclusion in an AI prompt.
	 *
	 * @param \WPCF7_ContactForm $contact_form The form that was submitted.
	 * @param \WPCF7_Submission  $submission   The current submission.
	 *
	 * @return string
	 */
	public static function format_submission( \WPCF7_ContactForm $contact_form, \WPCF7_Submission $submission ): string {
		$labels = self::get_content_field_labels( $contact_form );
		$posted = $submission->get_posted_data();

		$lines = array();

		foreach ( $posted as $name => $value ) {
			if ( ! isset( $labels[ $name ] ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$value = implode( ', ', array_map( 'strval', $value ) );
			}

			$value = trim( (string) $value );

			if ( '' === $value ) {
				continue;
			}

			$lines[] = $labels[ $name ] . ': ' . $value;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Finds the first submitted, validly-formatted email address from any
	 * `email`-type field on the form.
	 *
	 * @param \WPCF7_ContactForm $contact_form The form that was submitted.
	 * @param \WPCF7_Submission  $submission   The current submission.
	 *
	 * @return string|null
	 */
	public static function find_visitor_email( \WPCF7_ContactForm $contact_form, \WPCF7_Submission $submission ): ?string {
		$posted = $submission->get_posted_data();

		foreach ( $contact_form->scan_form_tags() as $tag ) {
			if ( 'email' !== $tag->basetype || empty( $posted[ $tag->name ] ) ) {
				continue;
			}

			$value = $posted[ $tag->name ];
			$value = is_array( $value ) ? reset( $value ) : $value;
			$value = trim( (string) $value );

			if ( is_email( $value ) ) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * Finds the first submitted value from any `tel`-type field on the form.
	 *
	 * @param \WPCF7_ContactForm $contact_form The form that was submitted.
	 * @param \WPCF7_Submission  $submission   The current submission.
	 *
	 * @return string|null
	 */
	public static function find_visitor_phone( \WPCF7_ContactForm $contact_form, \WPCF7_Submission $submission ): ?string {
		$posted = $submission->get_posted_data();

		foreach ( $contact_form->scan_form_tags() as $tag ) {
			if ( ! in_array( $tag->basetype, self::PHONE_BASETYPES, true ) || empty( $posted[ $tag->name ] ) ) {
				continue;
			}

			$value = $posted[ $tag->name ];
			$value = is_array( $value ) ? reset( $value ) : $value;
			$value = trim( (string) $value );

			if ( '' !== $value ) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * Guesses the visitor's display name from any field whose name looks
	 * like it holds one (`your-name`, `full-name`, `first-name`, etc.) —
	 * Contact Form 7 has no dedicated "name" field type the way it does for
	 * `email` and `tel`, so this is a naming-convention heuristic rather
	 * than a structural one.
	 *
	 * @param \WPCF7_ContactForm $contact_form The form that was submitted.
	 * @param \WPCF7_Submission  $submission   The current submission.
	 *
	 * @return string|null
	 */
	public static function find_visitor_name( \WPCF7_ContactForm $contact_form, \WPCF7_Submission $submission ): ?string {
		$posted = $submission->get_posted_data();

		foreach ( $contact_form->scan_form_tags() as $tag ) {
			if ( 'text' !== $tag->basetype ) {
				continue;
			}

			if ( 1 !== preg_match( '/name/i', $tag->name ) || empty( $posted[ $tag->name ] ) ) {
				continue;
			}

			$value = $posted[ $tag->name ];
			$value = is_array( $value ) ? reset( $value ) : $value;
			$value = trim( (string) $value );

			if ( '' !== $value ) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * Finds the first submitted value from any `text`-type field whose name
	 * suggests it holds a company/organization name.
	 *
	 * @param \WPCF7_ContactForm $contact_form The form that was submitted.
	 * @param \WPCF7_Submission  $submission   The current submission.
	 *
	 * @return string|null
	 */
	public static function find_visitor_company( \WPCF7_ContactForm $contact_form, \WPCF7_Submission $submission ): ?string {
		$posted = $submission->get_posted_data();

		foreach ( $contact_form->scan_form_tags() as $tag ) {
			if ( 'text' !== $tag->basetype ) {
				continue;
			}

			if ( 1 !== preg_match( '/company|organi[sz]ation|business/i', $tag->name ) || empty( $posted[ $tag->name ] ) ) {
				continue;
			}

			$value = $posted[ $tag->name ];
			$value = is_array( $value ) ? reset( $value ) : $value;
			$value = trim( (string) $value );

			if ( '' !== $value ) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * Turns a raw field name (e.g. `your-message`) into a readable label
	 * (e.g. "Your Message").
	 *
	 * @param string $name Raw field name.
	 *
	 * @return string
	 */
	public static function prettify_field_name( string $name ): string {
		return ucwords( str_replace( array( '-', '_' ), ' ', $name ) );
	}

	/**
	 * Builds the analysis prompt: the admin-configured template (with its
	 * placeholders substituted) plus a fixed, non-editable instruction
	 * telling the model to respond with only a single JSON object in the
	 * exact shape {@see \InboxAI\AI\ResponseValidator} expects.
	 *
	 * @param string                $template   Raw `analysis_prompt` template text,
	 *                                          from `Settings\Repository::get_prompts()`.
	 * @param array<string, string> $vars      Placeholder => value map. Expected keys:
	 *                                         `{message}`, `{customer_name}`, `{form_name}`,
	 *                                         `{submitted_fields}`, `{categories}`.
	 * @param string[]              $categories The category names the model is actually
	 *                                          allowed to choose from for this message —
	 *                                          the submitting form's own
	 *                                          {@see \InboxAI\CF7\CategoryTaxonomy}
	 *                                          terms. Empty for a form with no categories
	 *                                          of its own added yet, in which case the
	 *                                          model isn't asked for a category at all
	 *                                          (rather than being handed an empty "choose
	 *                                          one of:" list, or a made-up default).
	 *
	 * @return string
	 */
	public static function build_analysis_prompt( string $template, array $vars, array $categories = array() ): string {
		$prompt = strtr( $template, $vars );

		$category_key = array() !== $categories
			? sprintf( '"category" (exactly one of: %s), ', implode( ', ', $categories ) )
			: '';

		$prompt .= "\n\n" . sprintf(
			'Respond with ONLY a single valid JSON object (no markdown code fences, no commentary before or after) with exactly these keys: "summary" (a short 1-2 sentence string), %1$s"priority" (exactly one of: %2$s), "confidence" (an integer from 0 to 100), "reasoning" (a short 1-2 sentence string).',
			$category_key,
			implode( ', ', ResponseValidator::PRIORITIES )
		);

		return $prompt;
	}

	/**
	 * Builds the reply-draft prompt: the admin-configured template (with its
	 * placeholders substituted) plus a fixed instruction to return plain
	 * reply body text only.
	 *
	 * @param string                $template Raw `reply_prompt` template text,
	 *                                        from `Settings\Repository::get_prompts()`.
	 * @param array<string, string> $vars     Placeholder => value map. Expected keys:
	 *                                        `{tone}`, `{summary}`, `{message}`, `{signature}`.
	 *
	 * @return string
	 */
	public static function build_reply_prompt( string $template, array $vars ): string {
		$prompt = strtr( $template, $vars );

		$prompt .= "\n\n" . 'Respond with ONLY the reply body text — no subject line, no markdown formatting, no commentary before or after.';

		return $prompt;
	}

	/**
	 * Builds a map of form tag name => prettified label for every field that
	 * can carry meaningful text content.
	 *
	 * @param \WPCF7_ContactForm $contact_form The form to scan.
	 *
	 * @return array<string, string>
	 */
	private static function get_content_field_labels( \WPCF7_ContactForm $contact_form ): array {
		$labels = array();

		foreach ( $contact_form->scan_form_tags() as $tag ) {
			if ( '' === $tag->name || in_array( $tag->basetype, self::SKIP_BASETYPES, true ) ) {
				continue;
			}

			$labels[ $tag->name ] = self::prettify_field_name( $tag->name );
		}

		return $labels;
	}
}
