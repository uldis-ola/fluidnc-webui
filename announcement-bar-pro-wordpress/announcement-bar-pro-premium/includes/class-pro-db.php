<?php
/**
 * Premium database parts: the subscriber table (same name and columns as the single-plugin build 1.0.0), message
 * counters, A/B winner and the daily clean-up of unconfirmed sign-ups. Every query is prepared.
 *
 * @package AnnouncementBarProPremium
 */

namespace AnnouncementBarPro\Premium;

use AnnouncementBarPro\DB;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- own tables; nothing here is cached on purpose (counters, sign-ups).

/**
 * Premium tables and queries.
 */
class Pro_DB {
	const SCHEMA = 1;

	/**
	 * Subscriber table of the current site.
	 *
	 * @return string
	 */
	public static function subscribers_table() {
		global $wpdb;

		return $wpdb->prefix . 'abpro_subscribers';
	}

	/**
	 * Creates or updates the subscriber table of the current site (when its schema is older).
	 */
	public static function maybe_install() {
		if ( (int) get_option( 'abpro_premium_schema', 0 ) >= self::SCHEMA ) {
			return;
		}

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$s       = self::subscribers_table();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE $s (
  subscriber_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  email varchar(100) NOT NULL,
  locale varchar(20) NOT NULL DEFAULT '',
  message_id bigint(20) unsigned NOT NULL DEFAULT 0,
  status tinyint(1) NOT NULL DEFAULT 0,
  token char(64) NOT NULL DEFAULT '',
  unsubscribe char(64) NOT NULL DEFAULT '',
  consent varchar(400) NOT NULL DEFAULT '',
  date_added datetime NOT NULL,
  date_confirmed datetime NULL DEFAULT NULL,
  PRIMARY KEY  (subscriber_id),
  UNIQUE KEY email (email),
  KEY token (token),
  KEY unsubscribe (unsubscribe)
) $charset;"
		);

		update_option( 'abpro_premium_schema', self::SCHEMA, false );
	}

	/*
	 * ------------------------------------------------------------------
	 * Message counters and A/B test
	 * ------------------------------------------------------------------
	 */

	/**
	 * Counts a view, click or code copy (only messages that are on; B only while the A/B test runs; capped).
	 *
	 * @param int    $id      Message ID.
	 * @param string $type    view | click | copy.
	 * @param string $variant a | b.
	 */
	public static function track( $id, $type, $variant ) {
		global $wpdb;

		$columns = array(
			'view'  => array( 'views', 'views_b' ),
			'click' => array( 'clicks', 'clicks_b' ),
			'copy'  => array( 'copies', 'copies_b' ),
		);

		if ( ! isset( $columns[ $type ] ) ) {
			return;
		}

		$col = $columns[ $type ][ 'b' === $variant ? 1 : 0 ];
		$ab  = 'b' === $variant ? 1 : 0;

		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET %i = %i + 1 WHERE message_id = %d AND status = 1 AND %i < 4294967295 AND (ab_test = 1 OR %d = 0)', DB::table( 'messages' ), $col, $col, (int) $id, $col, $ab ) );
	}

	/**
	 * Counts a confirmed sign-up of a message.
	 *
	 * @param int $id Message ID.
	 */
	public static function count_subscribe( $id ) {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET subscribes = subscribes + 1 WHERE message_id = %d AND subscribes < 4294967295', DB::table( 'messages' ), (int) $id ) );
	}

	/**
	 * Sets every counter of a message to 0.
	 *
	 * @param int $id Message ID.
	 */
	public static function reset_stats( $id ) {
		global $wpdb;

		$wpdb->update( DB::table( 'messages' ), array_fill_keys( DB::COUNTERS, 0 ), array( 'message_id' => (int) $id ) );
	}

	/**
	 * A/B test winner B: B texts become the main texts (where B has a text), the test ends, B counters become the
	 * main counters. False when the message has no A/B test.
	 *
	 * @param int $id Message ID.
	 * @return bool
	 */
	public static function use_variant_b( $id ) {
		global $wpdb;

		$row = DB::message( $id );

		if ( ! $row || ! (int) $row['ab_test'] ) {
			return false;
		}

		foreach ( $row['texts'] as $locale => $t ) {
			if ( '' === trim( $t['text_b'] ) ) {
				continue;
			}

			$wpdb->update(
				DB::table( 'texts' ),
				array(
					'text'          => $t['text_b'],
					'mobile_text'   => $t['mobile_text_b'],
					'link_text'     => '' !== $t['link_text_b'] ? $t['link_text_b'] : $t['link_text'],
					'text_b'        => '',
					'mobile_text_b' => '',
					'link_text_b'   => '',
				),
				array(
					'message_id' => (int) $id,
					'locale'     => $locale,
				)
			);
		}

		$wpdb->update(
			DB::table( 'messages' ),
			array(
				'ab_test'  => 0,
				'views'    => (int) $row['views_b'],
				'clicks'   => (int) $row['clicks_b'],
				'copies'   => (int) $row['copies_b'],
				'views_b'  => 0,
				'clicks_b' => 0,
				'copies_b' => 0,
			),
			array( 'message_id' => (int) $id )
		);
		DB::flush();

		return true;
	}

	/*
	 * ------------------------------------------------------------------
	 * Subscribers (dates in UTC). Status: 0 waiting for confirmation, 1 subscribed, 2 unsubscribed.
	 * An unsubscribed row is the proof of the opt-out: nothing but a confirmation by the owner of the address
	 * (double opt-in, resubscribe()) may change it.
	 * ------------------------------------------------------------------
	 */

	/**
	 * Daily clean-up: unconfirmed sign-ups older than 30 days.
	 */
	public static function cleanup() {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE status = 0 AND date_added < %s', self::subscribers_table(), gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ) );
	}

	/**
	 * Subscriber by address, or null.
	 *
	 * @param string $email Address.
	 * @return array|null
	 */
	public static function subscriber( $email ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE email = %s', self::subscribers_table(), strtolower( $email ) ), ARRAY_A );

		return $row ? $row : null;
	}

	/**
	 * Subscriber by the hash of a confirmation or unsubscribe token.
	 *
	 * @param string $column token | unsubscribe.
	 * @param string $hash   SHA-256 hex.
	 * @return array|null
	 */
	public static function subscriber_by_token( $column, $hash ) {
		global $wpdb;

		if ( ! preg_match( '/^[0-9a-f]{64}$/', (string) $hash ) ) {
			return null;
		}

		$column = 'token' === $column ? 'token' : 'unsubscribe';
		$row    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE %i = %s', self::subscribers_table(), $column, $hash ), ARRAY_A );

		return $row ? $row : null;
	}

	/**
	 * Stores a sign-up (new address, or a pending one again). A row that is unsubscribed is never changed here
	 * (each column keeps its value when status = 2; status is written last).
	 *
	 * @param array $data email, locale, message_id, status, token, unsubscribe, consent.
	 */
	public static function save_subscriber( array $data ) {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (email, locale, message_id, status, token, unsubscribe, consent, date_added) VALUES (%s, %s, %d, %d, %s, %s, %s, %s) ON DUPLICATE KEY UPDATE locale = IF(status = 2, locale, VALUES(locale)), message_id = IF(status = 2, message_id, VALUES(message_id)), token = IF(status = 2, token, VALUES(token)), unsubscribe = IF(status = 2, unsubscribe, VALUES(unsubscribe)), consent = IF(status = 2, consent, VALUES(consent)), date_added = IF(status = 2, date_added, VALUES(date_added)), date_confirmed = IF(status = 2, date_confirmed, NULL), status = IF(status = 2, status, VALUES(status))',
				self::subscribers_table(),
				strtolower( $data['email'] ),
				$data['locale'],
				(int) $data['message_id'],
				(int) $data['status'],
				$data['token'],
				$data['unsubscribe'],
				$data['consent'],
				gmdate( 'Y-m-d H:i:s' )
			)
		);
	}

	/**
	 * A new confirmation token for an unsubscribed address (only the token column of a status-2 row changes).
	 *
	 * @param int    $id   Subscriber ID.
	 * @param string $hash SHA-256 of the token.
	 */
	public static function set_resubscribe_token( $id, $hash ) {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET token = %s WHERE subscriber_id = %d AND status = 2', self::subscribers_table(), $hash, (int) $id ) );
	}

	/**
	 * Confirms a pending sign-up (status 0 only).
	 *
	 * @param int $id Subscriber ID.
	 */
	public static function confirm_subscriber( $id ) {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "UPDATE %i SET status = 1, token = '', date_confirmed = %s WHERE subscriber_id = %d AND status = 0", self::subscribers_table(), gmdate( 'Y-m-d H:i:s' ), (int) $id ) );
	}

	/**
	 * The owner of an unsubscribed address confirmed a new sign-up (status 2 only): subscribed again, with the new
	 * unsubscribe token; the original sign-up date and consent text stay.
	 *
	 * @param int    $id          Subscriber ID.
	 * @param string $unsubscribe SHA-256 of the new unsubscribe token.
	 */
	public static function resubscribe( $id, $unsubscribe ) {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "UPDATE %i SET status = 1, token = '', unsubscribe = %s, date_confirmed = %s WHERE subscriber_id = %d AND status = 2", self::subscribers_table(), $unsubscribe, gmdate( 'Y-m-d H:i:s' ), (int) $id ) );
	}

	/**
	 * Unsubscribes (status 2; the row stays as proof of the opt-out).
	 *
	 * @param int $id Subscriber ID.
	 */
	public static function unsubscribe( $id ) {
		global $wpdb;

		$wpdb->update(
			self::subscribers_table(),
			array(
				'status' => 2,
				'token'  => '',
			),
			array( 'subscriber_id' => (int) $id )
		);
	}

	/**
	 * Deletes every row of an address (erasure request). Returns the number of rows.
	 *
	 * @param string $email Address.
	 * @return int
	 */
	public static function erase_subscriber( $email ) {
		global $wpdb;

		return (int) $wpdb->delete( self::subscribers_table(), array( 'email' => strtolower( $email ) ), array( '%s' ) );
	}

	/**
	 * Number of subscribers by status (0 pending, 1 confirmed, 2 unsubscribed).
	 *
	 * @return int[]
	 */
	public static function subscriber_counts() {
		global $wpdb;

		$out  = array(
			0 => 0,
			1 => 0,
			2 => 0,
		);
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS total FROM %i GROUP BY status', self::subscribers_table() ), ARRAY_A );

		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['status'] ] = (int) $row['total'];
		}

		return $out;
	}

	/**
	 * Subscribers for the CSV export, a page at a time.
	 *
	 * @param int $offset First row.
	 * @param int $limit  Rows.
	 * @return array
	 */
	public static function subscribers( $offset, $limit ) {
		global $wpdb;

		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT email, status, locale, message_id, consent, date_added, date_confirmed FROM %i ORDER BY subscriber_id ASC LIMIT %d, %d', self::subscribers_table(), (int) $offset, (int) $limit ), ARRAY_A );
	}
}
