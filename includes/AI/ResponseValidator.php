<?php
/**
 * Parses and normalizes a raw AI analysis response.
 *
 * @package CF7AIInbox\AI
 */

namespace CF7AIInbox\AI;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ResponseValidator
 *
 * Categories have no fixed vocabulary — every form owns its own
 * admin-editable list (see {@see \CF7AIInbox\CF7\CategoryTaxonomy}), so
 * there is deliberately no `CATEGORIES` constant or default category here
 * any more; {@see self::normalize_category()} is only ever as permissive as
 * whatever list the caller actually passes it. Priorities remain a fixed,
 * non-editable vocabulary (matching every badge/CSS class already built
 * around exactly these four values), so that part is unchanged.
 */
final class ResponseValidator {

	/**
	 * Allowed priorities, exactly as shown in the mockups.
	 *
	 * @var string[]
	 */
	public const PRIORITIES = array( 'urgent', 'high', 'normal', 'low' );

	/**
	 * Fallback priority used when the AI's response is missing or invalid.
	 *
	 * @var string
	 */
	public const DEFAULT_PRIORITY = 'normal';

	/**
	 * Parses a provider's raw text response into an associative array.
	 *
	 * Strips a leading/trailing markdown code fence if the model wrapped its
	 * JSON in one despite being asked not to — common enough across
	 * providers to handle defensively rather than fail on it.
	 *
	 * @param string $raw Raw text returned by the provider.
	 *
	 * @return array<string, mixed>|null Null if the response is not valid JSON.
	 */
	public static function extract_json( string $raw ): ?array {
		$cleaned = trim( $raw );
		$cleaned = (string) preg_replace( '/^```(?:json)?\s*/i', '', $cleaned );
		$cleaned = (string) preg_replace( '/\s*```$/', '', trim( $cleaned ) );

		$decoded = json_decode( trim( $cleaned ), true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Normalizes a raw category value to one of `$allowed` — the submitting
	 * form's own {@see \CF7AIInbox\CF7\CategoryTaxonomy} terms, as passed by
	 * {@see \CF7AIInbox\AI\AnalysisQueue::process()}.
	 *
	 * There is no fallback/default category to fall back to: a value that
	 * doesn't case-insensitively match anything in `$allowed` (including the
	 * case where `$allowed` is empty — a form with no categories of its own
	 * yet) normalizes to `''`, leaving the message uncategorized rather than
	 * inventing a category the admin never created.
	 *
	 * @param string   $value   Raw value from the AI response.
	 * @param string[] $allowed The category names the AI was actually asked
	 *                          to choose from for this particular message.
	 *
	 * @return string
	 */
	public static function normalize_category( string $value, array $allowed ): string {
		$value = trim( $value );

		foreach ( $allowed as $category ) {
			if ( 0 === strcasecmp( $category, $value ) ) {
				return $category;
			}
		}

		return '';
	}

	/**
	 * Normalizes a raw priority value to one of {@see self::PRIORITIES}.
	 *
	 * @param string $value Raw value from the AI response.
	 *
	 * @return string
	 */
	public static function normalize_priority( string $value ): string {
		$key = strtolower( trim( $value ) );

		return in_array( $key, self::PRIORITIES, true ) ? $key : self::DEFAULT_PRIORITY;
	}

	/**
	 * Normalizes a raw confidence value to an integer between 0 and 100.
	 *
	 * @param mixed $value Raw value from the AI response.
	 *
	 * @return int
	 */
	public static function normalize_confidence( $value ): int {
		$number = is_numeric( $value ) ? (int) round( (float) $value ) : 0;

		return max( 0, min( 100, $number ) );
	}
}
