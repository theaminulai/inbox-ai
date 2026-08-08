<?php
/**
 * Parses and normalizes a raw AI analysis response.
 *
 * @package InboxAI\AI
 */

namespace InboxAI\AI;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ResponseValidator
 *
 * Categories have no fixed vocabulary — every form owns its own
 * admin-editable list (see {@see \InboxAI\CF7\CategoryTaxonomy}), so
 * there is deliberately no `CATEGORIES` constant or default category here
 * any more; {@see self::normalize_category()} matches against whatever list
 * the caller passes it, or — for a form with no list of its own — accepts
 * the AI's own free-form suggestion instead of leaving the message
 * uncategorized. Priorities remain a fixed, non-editable vocabulary
 * (matching every badge/CSS class already built around exactly these four
 * values), so that part is unchanged.
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
	 * Allowed customer-mood values. A fixed, non-editable vocabulary for the
	 * same reason priorities are — every mood badge/color already built
	 * around exactly these four. Computed on every AI analysis call (both a
	 * fresh submission's first analysis and every re-analysis after a
	 * customer reply — see {@see \InboxAI\AI\AnalysisQueue}), not a separate
	 * API call.
	 *
	 * @var string[]
	 */
	public const MOODS = array( 'positive', 'neutral', 'frustrated', 'angry' );

	/**
	 * Fallback mood used when the AI's response is missing or invalid.
	 *
	 * @var string
	 */
	public const DEFAULT_MOOD = 'neutral';

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
	 * Normalizes a raw category value from the AI response.
	 *
	 * When `$allowed` is non-empty (the submitting form has its own
	 * {@see \InboxAI\CF7\CategoryTaxonomy} terms — see
	 * {@see \InboxAI\AI\AnalysisQueue::process()}), the model was asked to
	 * pick one of them, so the result is matched case-insensitively against
	 * that list; a value that doesn't match anything in it normalizes to
	 * `''` rather than inventing a category the admin never created.
	 *
	 * When `$allowed` is empty (no categories configured for that form yet),
	 * {@see \InboxAI\AI\PromptBuilder::build_analysis_prompt()} instead asks
	 * the model to propose its own short category label — that free-form
	 * value is accepted as-is (trimmed, length-capped to match the
	 * `category` column's `VARCHAR(100)`), so the AI's own classification
	 * (`category`) still gets populated even for forms nobody has set up a
	 * fixed vocabulary for. This is deliberately independent from
	 * `source_category`, which is the fixed, form-defined value captured
	 * once at submission time and never touched by AI analysis.
	 *
	 * @param string   $value   Raw value from the AI response.
	 * @param string[] $allowed The category names the AI was actually asked
	 *                          to choose from for this particular message;
	 *                          empty if it was asked to propose its own.
	 *
	 * @return string
	 */
	public static function normalize_category( string $value, array $allowed ): string {
		$value = trim( $value );

		if ( array() === $allowed ) {
			return mb_substr( $value, 0, 100 );
		}

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
	 * Normalizes a raw mood value to one of {@see self::MOODS}.
	 *
	 * @param string $value Raw value from the AI response.
	 *
	 * @return string
	 */
	public static function normalize_mood( string $value ): string {
		$key = strtolower( trim( $value ) );

		return in_array( $key, self::MOODS, true ) ? $key : self::DEFAULT_MOOD;
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
