<?php
/**
 * Instantiates the right AI provider class for a stored provider id.
 *
 * @package InboxAI\AI
 */

namespace InboxAI\AI;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InboxAI\Interfaces\AIProviderInterface;

/**
 * Class ProviderFactory
 *
 * Shared between the Settings page's AI Provider tab (configuration) and
 * the AI Inbox List page's analysis queue (usage) — both talk to the same
 * provider classes through this one factory.
 */
final class ProviderFactory {

	/**
	 * Provider id => class name.
	 *
	 * @var array<string, class-string<AIProviderInterface>>
	 */
	private const PROVIDERS = array(
		'openai'    => OpenAIProvider::class,
		'anthropic' => AnthropicProvider::class,
		'google'    => GeminiProvider::class,
	);

	/**
	 * Creates the provider instance for a given id.
	 *
	 * @param string $provider_id One of `openai`, `anthropic`, `google`.
	 *
	 * @return AIProviderInterface|null Null if the id isn't recognized.
	 */
	public static function create( string $provider_id ): ?AIProviderInterface {
		if ( ! isset( self::PROVIDERS[ $provider_id ] ) ) {
			return null;
		}

		$class = self::PROVIDERS[ $provider_id ];

		return new $class();
	}

	/**
	 * Every registered provider id, in mockup/UI display order.
	 *
	 * @return string[]
	 */
	public static function ids(): array {
		return array_keys( self::PROVIDERS );
	}
}
