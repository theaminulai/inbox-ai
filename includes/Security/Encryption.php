<?php
/**
 * Symmetric encryption for secrets stored at rest (currently: the AI
 * provider API key).
 *
 * @package CF7AIInbox\Security
 */

namespace CF7AIInbox\Security;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Encryption
 *
 * Uses libsodium's secretbox when available (bundled with PHP since 7.2,
 * so this is the common path), falling back to OpenSSL AES-256-CBC on
 * exotic builds without sodium. The encryption key is generated once and
 * stored in its own option — never derived from WordPress's auth salts,
 * so rotating `AUTH_KEY`/`AUTH_SALT` in wp-config.php can never silently
 * make previously-encrypted values undecryptable.
 */
final class Encryption {

	/**
	 * Option name the (base64-encoded) encryption key is stored under.
	 *
	 * @var string
	 */
	private const KEY_OPTION = 'cf7ai_inbox_encryption_key';

	/**
	 * Encrypts a plaintext string for storage.
	 *
	 * @param string $plain Plaintext value. An empty string encrypts to an
	 *                       empty string (nothing to protect).
	 *
	 * @return string Encrypted, base64-safe value prefixed with the method
	 *                used (`sodium:` or `openssl:`), or '' on failure.
	 */
	public static function encrypt( string $plain ): string {
		if ( '' === $plain ) {
			return '';
		}

		$key = self::get_key();

		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( $plain, $nonce, $key );

			return 'sodium:' . base64_encode( $nonce . $cipher );
		}

		$iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );
		$iv        = openssl_random_pseudo_bytes( (int) $iv_length );
		$cipher    = openssl_encrypt( $plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $cipher ) {
			return '';
		}

		return 'openssl:' . base64_encode( $iv . $cipher );
	}

	/**
	 * Decrypts a value previously produced by {@see self::encrypt()}.
	 *
	 * @param string $cipher Encrypted value.
	 *
	 * @return string|null Decrypted plaintext, or null if it can't be
	 *                      decrypted (wrong key, corrupted value, empty input).
	 */
	public static function decrypt( string $cipher ): ?string {
		if ( '' === $cipher ) {
			return null;
		}

		$key = self::get_key();

		if ( 0 === strpos( $cipher, 'sodium:' ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$raw = base64_decode( substr( $cipher, 7 ), true );

			if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
				return null;
			}

			$nonce       = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher_text = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$plain       = sodium_crypto_secretbox_open( $cipher_text, $nonce, $key );

			return false === $plain ? null : $plain;
		}

		if ( 0 === strpos( $cipher, 'openssl:' ) ) {
			$raw    = base64_decode( substr( $cipher, 8 ), true );
			$iv_len = (int) openssl_cipher_iv_length( 'aes-256-cbc' );

			if ( false === $raw || strlen( $raw ) <= $iv_len ) {
				return null;
			}

			$iv          = substr( $raw, 0, $iv_len );
			$cipher_text = substr( $raw, $iv_len );
			$plain       = openssl_decrypt( $cipher_text, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

			return false === $plain ? null : $plain;
		}

		return null;
	}

	/**
	 * Returns the raw 32-byte encryption key, generating and persisting one
	 * on first use.
	 *
	 * @return string
	 */
	private static function get_key(): string {
		$stored = get_option( self::KEY_OPTION, '' );

		if ( is_string( $stored ) && '' !== $stored ) {
			$decoded = base64_decode( $stored, true );

			if ( false !== $decoded && 32 === strlen( $decoded ) ) {
				return $decoded;
			}
		}

		$key = random_bytes( 32 );

		update_option( self::KEY_OPTION, base64_encode( $key ), false );

		return $key;
	}
}
