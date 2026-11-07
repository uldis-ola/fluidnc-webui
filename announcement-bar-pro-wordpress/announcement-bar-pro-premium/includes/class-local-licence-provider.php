<?php
/**
 * Offline licence provider of version 1.0.0: key format and checksum only, no network.
 *
 * @package AnnouncementBarProPremium
 */

namespace AnnouncementBarPro\Premium;

defined( 'ABSPATH' ) || exit;

/**
 * Offline provider: format "ABP1-XXXX-XXXX-XXXX-XXXX" (alphabet without 0, 1, I, O), last character = checksum.
 */
class Local_Licence_Provider implements Licence_Provider {
	const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

	/**
	 * Normalised key ("abp1 xxxx…" → "ABP1-XXXX-…") or ''.
	 *
	 * @param mixed $key Key as typed.
	 * @return string
	 */
	public static function normalise( $key ) {
		$key = strtoupper( (string) preg_replace( '/[\s\-]+/', '', (string) $key ) );

		if ( ! preg_match( '/^ABP1([' . self::ALPHABET . ']{16})$/', $key, $m ) ) {
			return '';
		}

		return 'ABP1-' . implode( '-', str_split( $m[1], 4 ) );
	}

	/**
	 * Checksum character of the first 15 key characters.
	 *
	 * @param string $body 15 characters.
	 * @return string
	 */
	public static function checksum( $body ) {
		$sum = 0;

		foreach ( str_split( $body ) as $i => $char ) {
			$sum += ( $i + 3 ) * ( strpos( self::ALPHABET, $char ) + 1 );
		}

		return self::ALPHABET[ $sum % 32 ];
	}

	/**
	 * Valid format and checksum.
	 *
	 * @param mixed $key Key.
	 * @return bool
	 */
	public static function well_formed( $key ) {
		$key = self::normalise( $key );

		if ( '' === $key ) {
			return false;
		}

		$body = str_replace( '-', '', substr( $key, 5 ) );

		return self::checksum( substr( $body, 0, 15 ) ) === $body[15];
	}

	/**
	 * See Licence_Provider::activate().
	 *
	 * @param string $key Key.
	 * @return array
	 */
	public function activate( $key ) {
		if ( ! self::well_formed( $key ) ) {
			return array(
				'status'  => 'invalid',
				'message' => __( 'This licence key is not valid. Please check it and try again.', 'announcement-bar-pro-premium' ),
			);
		}

		return array(
			'status'  => 'valid',
			'message' => __( 'Pro features are unlocked. Thank you!', 'announcement-bar-pro-premium' ),
		);
	}

	/**
	 * See Licence_Provider::deactivate().
	 *
	 * @param string $key Key.
	 */
	public function deactivate( $key ) {
		// Nothing to release: no server knows about the key.
	}

	/**
	 * See Licence_Provider::status().
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public function status( $key ) {
		return self::well_formed( $key ) ? 'valid' : 'invalid';
	}
}
