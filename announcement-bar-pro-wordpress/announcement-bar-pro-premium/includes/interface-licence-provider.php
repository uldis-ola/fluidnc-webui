<?php
/**
 * Licence provider interface (see class-licence.php).
 *
 * @package AnnouncementBarProPremium
 */

namespace AnnouncementBarPro\Premium;

defined( 'ABSPATH' ) || exit;

/**
 * What every licence provider offers.
 */
interface Licence_Provider {
	/**
	 * Activates a key for this site.
	 *
	 * @param string $key Licence key.
	 * @return array{status: string, message: string} status: valid | invalid | error.
	 */
	public function activate( $key );

	/**
	 * Releases the key of this site.
	 *
	 * @param string $key Licence key.
	 * @return void
	 */
	public function deactivate( $key );

	/**
	 * Status of a stored key without any network call: valid | invalid.
	 *
	 * @param string $key Licence key.
	 * @return string
	 */
	public function status( $key );
}
