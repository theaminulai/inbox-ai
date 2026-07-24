<?php
/**
 * Maps a Contact Form 7 submission onto the `cf7ai_messages` schema.
 *
 * @package CF7AIInbox\CF7
 */

namespace CF7AIInbox\CF7;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIInbox\AI\PromptBuilder;

/**
 * Class SubmissionMapper
 *
 * Contact Form 7 doesn't have dedicated "subject"/"message" field types —
 * every form is free-form. This class makes a best-effort guess (a
 * `textarea`-type field is the message body; a field named like `*subject*`
 * is the subject; every other content field is kept in `fields` for the
 * Submitted Fields card) rather than assuming a fixed field-naming
 * convention, so it works with any form layout.
 */
final class SubmissionMapper {

	/**
	 * Form tag base types that never carry meaningful text content and are
	 * excluded from the mapped submission (file uploads, spam
	 * countermeasures).
	 *
	 * @var string[]
	 */
	private const SKIP_BASETYPES = array( 'file', 'file*', 'acceptance', 'quiz', 'captchar', 'captchac' );

	/**
	 * Maps one submission to the array {@see \CF7AIInbox\Database\MessageRepository::insert()}
	 * expects (minus `submission_hash`, added separately by the caller).
	 *
	 * @param \WPCF7_ContactForm $contact_form The form that was submitted.
	 * @param \WPCF7_Submission  $submission   The current submission.
	 *
	 * @return array<string, mixed>
	 */
	public static function map( \WPCF7_ContactForm $contact_form, \WPCF7_Submission $submission ): array {
		$posted       = $submission->get_posted_data();
		$fields       = array();
		$message_text = '';
		$subject_text = '';
		$longest      = '';

		foreach ( $contact_form->scan_form_tags() as $tag ) {
			if ( '' === $tag->name || in_array( $tag->basetype, self::SKIP_BASETYPES, true ) ) {
				continue;
			}

			$value = $posted[ $tag->name ] ?? '';

			if ( is_array( $value ) ) {
				$value = implode( ', ', array_map( 'strval', $value ) );
			}

			$value = trim( (string) $value );

			if ( '' === $value ) {
				continue;
			}

			$fields[ PromptBuilder::prettify_field_name( $tag->name ) ] = $value;

			if ( '' === $message_text && 'textarea' === $tag->basetype ) {
				$message_text = $value;
			}

			if ( '' === $subject_text && 1 === preg_match( '/subject/i', $tag->name ) ) {
				$subject_text = $value;
			}

			if ( strlen( $value ) > strlen( $longest ) ) {
				$longest = $value;
			}
		}

		// No textarea field found (some forms are just a single-line contact
		// form) — fall back to the longest submitted value as a reasonable
		// stand-in for "the message".
		if ( '' === $message_text ) {
			$message_text = $longest;
		}

		if ( '' === $subject_text ) {
			$subject_text = sprintf(
				/* translators: %s: form title */
				__( 'New submission via %s', 'cf7-ai-inbox' ),
				$contact_form->title()
			);
		}

		return array(
			'form_id'           => $contact_form->id(),
			'form_title'        => $contact_form->title(),
			'sender_name'       => PromptBuilder::find_visitor_name( $contact_form, $submission ) ?? '',
			'sender_email'      => PromptBuilder::find_visitor_email( $contact_form, $submission ) ?? '',
			'subject'           => $subject_text,
			'message'           => $message_text,
			'fields'            => $fields,
			'meta'              => array(
				'phone'       => PromptBuilder::find_visitor_phone( $contact_form, $submission ) ?? '',
				'company'     => PromptBuilder::find_visitor_company( $contact_form, $submission ) ?? '',
				'source_page' => (string) $submission->get_meta( 'url' ),
				'ip'          => (string) $submission->get_meta( 'remote_ip' ),
			),
			'channel'           => 'contact-form-7',
			'submission_status' => 'received',
			'mail_status'       => 'pending',
		);
	}

	/**
	 * Computes a dedup hash for one submission: the form id plus every
	 * posted field value plus a short (30-second) time bucket — so a
	 * browser back-button double-submit within the same window is treated
	 * as the same submission, while a genuinely new submission of identical
	 * content minutes later still gets its own row.
	 *
	 * @param \WPCF7_ContactForm $contact_form The form that was submitted.
	 * @param \WPCF7_Submission  $submission   The current submission.
	 *
	 * @return string
	 */
	public static function compute_hash( \WPCF7_ContactForm $contact_form, \WPCF7_Submission $submission ): string {
		$posted = $submission->get_posted_data();
		ksort( $posted );

		return md5( $contact_form->id() . '|' . wp_json_encode( $posted ) . '|' . floor( time() / 30 ) );
	}
}
