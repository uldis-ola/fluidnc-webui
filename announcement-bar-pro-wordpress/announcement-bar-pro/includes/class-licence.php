<?php
/**
 * Pro licence: a provider interface and the local provider of version 1.0.0.
 *
 * Version 1.0.0 never contacts any server. The local provider only checks the key's format and checksum, so a typo is
 * caught at once. A later version can register a remote provider (filter "abpro_licence_provider") that activates the
 * key on the vendor's licence server — only when the site owner presses "Activate", never in the background without
 * their consent. See LICENSING.md in the source repository.
 *
 * @package AnnouncementBarPro
 */

namespace AnnouncementBarPro;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/interface-licence-provider.php';
require_once __DIR__ . '/class-local-licence-provider.php';

/**
 * Stored licence and the Pro switch.
 */
class Licence {
	const OPTION = 'abpro_licence';

	/**
	 * Cached answer of is_pro() for this request.
	 *
	 * @var bool|null
	 */
	private static $pro = null;

	/**
	 * The active provider (filterable).
	 *
	 * @return Licence_Provider
	 */
	public static function provider() {
		$provider = apply_filters( 'abpro_licence_provider', null );

		return $provider instanceof Licence_Provider ? $provider : new Local_Licence_Provider();
	}

	/**
	 * Stored licence: array( key, status, activated ).
	 *
	 * @return array
	 */
	public static function get() {
		$data = get_option( self::OPTION, array() );
		$data = is_array( $data ) ? $data : array();

		return array(
			'key'       => isset( $data['key'] ) && is_string( $data['key'] ) ? $data['key'] : '',
			'status'    => isset( $data['status'] ) && is_string( $data['status'] ) ? $data['status'] : '',
			'activated' => isset( $data['activated'] ) ? (int) $data['activated'] : 0,
		);
	}

	/**
	 * True when the Pro features are unlocked on this site.
	 *
	 * @return bool
	 */
	public static function is_pro() {
		if ( null === self::$pro ) {
			$data      = self::get();
			$pro       = 'valid' === $data['status'] && 'valid' === self::provider()->status( $data['key'] );
			self::$pro = (bool) apply_filters( 'abpro_is_pro', $pro );
		}

		return self::$pro;
	}

	/**
	 * Activates a key (admin action). Returns the provider's answer.
	 *
	 * @param string $key Key as typed.
	 * @return array
	 */
	public static function activate( $key ) {
		$normal = Local_Licence_Provider::normalise( $key );
		$result = self::provider()->activate( '' !== $normal ? $normal : (string) $key );

		if ( 'valid' === $result['status'] ) {
			update_option(
				self::OPTION,
				array(
					'key'       => $normal,
					'status'    => 'valid',
					'activated' => time(),
				),
				false
			);
		}

		self::$pro = null;

		return $result;
	}

	/**
	 * Removes the key from this site.
	 */
	public static function deactivate() {
		$data = self::get();

		if ( '' !== $data['key'] ) {
			self::provider()->deactivate( $data['key'] );
		}

		delete_option( self::OPTION );
		self::$pro = null;
	}

	/**
	 * Key for display: only the last group visible.
	 *
	 * @return string
	 */
	public static function masked() {
		$key = self::get()['key'];

		return '' === $key ? '' : 'ABP1-••••-••••-••••-' . substr( $key, -4 );
	}

	/**
	 * Forgets the cached answer (tests, licence changes).
	 */
	public static function reset() {
		self::$pro = null;
	}
}
