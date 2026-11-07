<?php
/**
 * Database: own tables (per site on multisite), created and updated with dbDelta(); every query is prepared.
 *
 * @package AnnouncementBarPro
 */

namespace AnnouncementBarPro;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- own tables; results that matter per request are cached in properties.

/**
 * Tables and queries.
 */
class DB {
	/** Message fields that hold texts (one row per message and language). */
	const TEXT_FIELDS = array( 'text', 'link_text', 'mobile_text', 'success_text', 'text_b', 'mobile_text_b', 'link_text_b', 'extra' );

	/** Counters, never written by the message form. */
	const COUNTERS = array( 'views', 'clicks', 'copies', 'views_b', 'clicks_b', 'copies_b', 'subscribes' );

	/**
	 * Active messages of this request, by locale.
	 *
	 * @var array
	 */
	private static $active = array();

	/**
	 * Table name with the site prefix.
	 *
	 * @param string $name messages | texts | subscribers | rate.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;

		$names = array(
			'messages'    => 'abpro_messages',
			'texts'       => 'abpro_message_texts',
			'subscribers' => 'abpro_subscribers',
			'rate'        => 'abpro_rate',
		);

		return $wpdb->prefix . $names[ $name ];
	}

	/**
	 * Creates or updates the tables of the current site.
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$m       = self::table( 'messages' );
		$t       = self::table( 'texts' );
		$s       = self::table( 'subscribers' );
		$r       = self::table( 'rate' );

		dbDelta(
			"CREATE TABLE $m (
  message_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  status tinyint(1) NOT NULL DEFAULT 1,
  sort_order int(11) NOT NULL DEFAULT 0,
  priority int(11) NOT NULL DEFAULT 0,
  date_start datetime NULL DEFAULT NULL,
  date_end datetime NULL DEFAULT NULL,
  bg_color varchar(7) NOT NULL DEFAULT '#1f2937',
  text_color varchar(7) NOT NULL DEFAULT '#ffffff',
  link varchar(255) NOT NULL DEFAULT '',
  link_new_tab tinyint(1) NOT NULL DEFAULT 0,
  icon varchar(32) NOT NULL DEFAULT '',
  countdown tinyint(1) NOT NULL DEFAULT 0,
  roles text NOT NULL,
  page_mode varchar(16) NOT NULL DEFAULT 'all',
  page_rules text NOT NULL,
  device varchar(8) NOT NULL DEFAULT 'all',
  type varchar(16) NOT NULL DEFAULT 'standard',
  threshold decimal(15,4) NOT NULL DEFAULT 0.0000,
  progress_bar tinyint(1) NOT NULL DEFAULT 1,
  coupon varchar(32) NOT NULL DEFAULT '',
  ab_test tinyint(1) NOT NULL DEFAULT 0,
  weekdays varchar(7) NOT NULL DEFAULT '',
  time_start varchar(5) NOT NULL DEFAULT '',
  time_end varchar(5) NOT NULL DEFAULT '',
  categories text NOT NULL,
  products text NOT NULL,
  style varchar(16) NOT NULL DEFAULT '',
  button_style varchar(16) NOT NULL DEFAULT 'filled',
  placement varchar(16) NOT NULL DEFAULT '',
  options longtext NOT NULL,
  views int(10) unsigned NOT NULL DEFAULT 0,
  clicks int(10) unsigned NOT NULL DEFAULT 0,
  copies int(10) unsigned NOT NULL DEFAULT 0,
  views_b int(10) unsigned NOT NULL DEFAULT 0,
  clicks_b int(10) unsigned NOT NULL DEFAULT 0,
  copies_b int(10) unsigned NOT NULL DEFAULT 0,
  subscribes int(10) unsigned NOT NULL DEFAULT 0,
  date_added datetime NOT NULL,
  date_modified datetime NOT NULL,
  PRIMARY KEY  (message_id),
  KEY status_dates (status,date_start,date_end)
) $charset;
CREATE TABLE $t (
  message_id bigint(20) unsigned NOT NULL,
  locale varchar(20) NOT NULL,
  text text NOT NULL,
  link_text text NOT NULL,
  mobile_text text NOT NULL,
  success_text text NOT NULL,
  text_b text NOT NULL,
  mobile_text_b text NOT NULL,
  link_text_b text NOT NULL,
  extra text NOT NULL,
  PRIMARY KEY  (message_id,locale)
) $charset;
CREATE TABLE $s (
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
) $charset;
CREATE TABLE $r (
  bucket char(40) NOT NULL,
  hits int(10) unsigned NOT NULL DEFAULT 0,
  expires int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (bucket),
  KEY expires (expires)
) $charset;"
		);

		update_option( 'abpro_schema', ABPRO_SCHEMA, false );
	}

	/**
	 * Drops the tables of the current site (uninstall with "delete data" on).
	 */
	public static function drop() {
		global $wpdb;

		foreach ( array( 'messages', 'texts', 'subscribers', 'rate' ) as $name ) {
			$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table( $name ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange -- table name from self::table().
		}
	}

	/*
	 * ------------------------------------------------------------------
	 * Messages
	 * ------------------------------------------------------------------
	 */

	/**
	 * Messages that are on and inside their date window now (site time), ordered by priority, sort order, ID —
	 * each with the texts of $locale (missing texts come from $fallback, then from any language).
	 *
	 * @param string $locale   Visitor's locale.
	 * @param string $fallback Site locale.
	 * @return array
	 */
	public static function active_messages( $locale, $fallback ) {
		global $wpdb;

		$key = $locale . '|' . $fallback;

		if ( isset( self::$active[ $key ] ) ) {
			return self::$active[ $key ];
		}

		$now  = current_time( 'mysql' );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table( 'messages' ) . ' WHERE status = 1 AND (date_start IS NULL OR date_start <= %s) AND (date_end IS NULL OR date_end > %s) ORDER BY priority DESC, sort_order ASC, message_id ASC LIMIT 100', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- table name.
				$now,
				$now
			),
			ARRAY_A
		);

		self::$active[ $key ] = self::with_texts( is_array( $rows ) ? $rows : array(), $locale, $fallback );

		return self::$active[ $key ];
	}

	/**
	 * Adds the texts of one language to each row.
	 *
	 * @param array  $rows     Message rows.
	 * @param string $locale   Wanted locale.
	 * @param string $fallback Second choice.
	 * @return array
	 */
	private static function with_texts( array $rows, $locale, $fallback ) {
		global $wpdb;

		if ( ! $rows ) {
			return array();
		}

		$ids   = array_map( 'intval', array_column( $rows, 'message_id' ) );
		$texts = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table( 'texts' ) . ' WHERE message_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- placeholders built from the ID count.
				$ids
			),
			ARRAY_A
		);
		$by    = array();

		foreach ( (array) $texts as $t ) {
			$by[ (int) $t['message_id'] ][ $t['locale'] ] = $t;
		}

		foreach ( $rows as $i => $row ) {
			$list = isset( $by[ (int) $row['message_id'] ] ) ? $by[ (int) $row['message_id'] ] : array();
			$pick = null;

			foreach ( array( $locale, $fallback ) as $want ) {
				if ( isset( $list[ $want ] ) && '' !== trim( $list[ $want ]['text'] . $list[ $want ]['mobile_text'] ) ) {
					$pick = $list[ $want ];
					break;
				}
			}

			if ( null === $pick ) {
				foreach ( $list as $t ) {
					if ( '' !== trim( $t['text'] . $t['mobile_text'] ) ) {
						$pick = $t;
						break;
					}
				}
			}

			foreach ( self::TEXT_FIELDS as $field ) {
				$rows[ $i ][ $field ] = null === $pick ? '' : (string) $pick[ $field ];
			}

			$rows[ $i ]['locale'] = null === $pick ? '' : $pick['locale'];
		}

		return $rows;
	}

	/**
	 * Forgets the active messages of this request (after a save, in tests).
	 */
	public static function flush() {
		self::$active = array();
	}

	/**
	 * One message with all its texts (by locale), or null.
	 *
	 * @param int $id Message ID.
	 * @return array|null
	 */
	public static function message( $id ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'messages' ) . ' WHERE message_id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- table name.

		if ( ! $row ) {
			return null;
		}

		$row['texts'] = array();
		$texts        = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'texts' ) . ' WHERE message_id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- table name.

		foreach ( (array) $texts as $t ) {
			$row['texts'][ $t['locale'] ] = $t;
		}

		return $row;
	}

	/**
	 * Every message for the admin list (texts of $locale or the first language that has one).
	 *
	 * @param string $locale Admin locale.
	 * @return array
	 */
	public static function all_messages( $locale ) {
		global $wpdb;

		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table( 'messages' ) . ' ORDER BY priority DESC, sort_order ASC, message_id ASC', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no input.

		return self::with_texts( is_array( $rows ) ? $rows : array(), $locale, get_locale() );
	}

	/**
	 * Inserts or updates a message and its texts. Returns the message ID (0 on failure).
	 *
	 * @param int   $id    Message ID (0 = new).
	 * @param array $data  Columns (clean).
	 * @param array $texts Texts by locale (clean).
	 * @return int
	 */
	public static function save_message( $id, array $data, array $texts ) {
		global $wpdb;

		foreach ( self::COUNTERS as $counter ) {
			unset( $data[ $counter ] );
		}

		unset( $data['message_id'], $data['texts'] );

		$data['date_modified'] = current_time( 'mysql' );

		if ( $id > 0 ) {
			$ok = false !== $wpdb->update( self::table( 'messages' ), $data, array( 'message_id' => (int) $id ) );
		} else {
			$data['date_added'] = $data['date_modified'];
			$ok                 = false !== $wpdb->insert( self::table( 'messages' ), $data );
			$id                 = $ok ? (int) $wpdb->insert_id : 0;
		}

		if ( ! $ok || ! $id ) {
			return 0;
		}

		foreach ( $texts as $locale => $row ) {
			$clean = array(
				'message_id' => (int) $id,
				'locale'     => (string) $locale,
			);

			foreach ( self::TEXT_FIELDS as $field ) {
				$clean[ $field ] = isset( $row[ $field ] ) ? (string) $row[ $field ] : '';
			}

			$wpdb->replace( self::table( 'texts' ), $clean );
		}

		self::flush();

		return (int) $id;
	}

	/**
	 * Deletes a message and its texts.
	 *
	 * @param int $id Message ID.
	 */
	public static function delete_message( $id ) {
		global $wpdb;

		$wpdb->delete( self::table( 'messages' ), array( 'message_id' => (int) $id ), array( '%d' ) );
		$wpdb->delete( self::table( 'texts' ), array( 'message_id' => (int) $id ), array( '%d' ) );
		self::flush();
	}

	/**
	 * Copy of a message (off, counters 0). Returns the new ID or 0.
	 *
	 * @param int $id Message ID.
	 * @return int
	 */
	public static function duplicate_message( $id ) {
		$row = self::message( $id );

		if ( ! $row ) {
			return 0;
		}

		$texts = $row['texts'];
		unset( $row['texts'], $row['date_added'] );
		$row['status'] = 0;

		return self::save_message( 0, $row, $texts );
	}

	/**
	 * Switches a message on or off.
	 *
	 * @param int  $id Message ID.
	 * @param bool $on New status.
	 */
	public static function set_status( $id, $on ) {
		global $wpdb;

		$wpdb->update( self::table( 'messages' ), array( 'status' => $on ? 1 : 0 ), array( 'message_id' => (int) $id ), array( '%d' ), array( '%d' ) );
		self::flush();
	}

	/**
	 * Sets every counter of a message to 0.
	 *
	 * @param int $id Message ID.
	 */
	public static function reset_stats( $id ) {
		global $wpdb;

		$wpdb->update( self::table( 'messages' ), array_fill_keys( self::COUNTERS, 0 ), array( 'message_id' => (int) $id ) );
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

		$row = self::message( $id );

		if ( ! $row || ! (int) $row['ab_test'] ) {
			return false;
		}

		foreach ( $row['texts'] as $locale => $t ) {
			if ( '' === trim( $t['text_b'] ) ) {
				continue;
			}

			$wpdb->update(
				self::table( 'texts' ),
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
			self::table( 'messages' ),
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
		self::flush();

		return true;
	}

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
			'view'  => array(
				'a' => 'views',
				'b' => 'views_b',
			),
			'click' => array(
				'a' => 'clicks',
				'b' => 'clicks_b',
			),
			'copy'  => array(
				'a' => 'copies',
				'b' => 'copies_b',
			),
		);

		if ( ! isset( $columns[ $type ] ) ) {
			return;
		}

		$col = $columns[ $type ][ 'b' === $variant ? 'b' : 'a' ];
		$ab  = 'b' === $variant ? ' AND ab_test = 1' : '';

		// $col and $ab come from the whitelist above.
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table( 'messages' ) . " SET $col = $col + 1 WHERE message_id = %d AND status = 1 AND $col < 4294967295$ab", (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- whitelisted column.
	}

	/**
	 * Counts a confirmed sign-up of a message.
	 *
	 * @param int $id Message ID.
	 */
	public static function count_subscribe( $id ) {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table( 'messages' ) . ' SET subscribes = subscribes + 1 WHERE message_id = %d AND subscribes < 4294967295', (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- table name.
	}

	/*
	 * ------------------------------------------------------------------
	 * Rate limits
	 * ------------------------------------------------------------------
	 */

	/**
	 * Counts a hit in $bucket for the current window and returns the number of hits in it.
	 *
	 * @param string $bucket Hash (40 hex).
	 * @param int    $window Seconds.
	 * @return int
	 */
	public static function hit( $bucket, $window ) {
		global $wpdb;

		$now = time();
		$t   = self::table( 'rate' );

		// A new window starts at 1; an expired one is reset in the same statement (atomic).
		$wpdb->query( $wpdb->prepare( "INSERT INTO $t (bucket, hits, expires) VALUES (%s, 1, %d) ON DUPLICATE KEY UPDATE hits = IF(expires < %d, 1, hits + 1), expires = IF(expires < %d, %d, expires)", $bucket, $now + $window, $now, $now, $now + $window ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- table name.

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT hits FROM $t WHERE bucket = %s", $bucket ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- table name.
	}

	/**
	 * Daily clean-up: expired rate-limit rows, unconfirmed sign-ups older than 30 days.
	 */
	public static function cleanup() {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table( 'rate' ) . ' WHERE expires < %d', time() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- table name.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table( 'subscribers' ) . ' WHERE status = 0 AND date_added < %s', gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- table name.
	}

	/*
	 * ------------------------------------------------------------------
	 * Subscribers (dates in UTC)
	 * ------------------------------------------------------------------
	 */

	/**
	 * Subscriber by address, or null.
	 *
	 * @param string $email Address.
	 * @return array|null
	 */
	public static function subscriber( $email ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'subscribers' ) . ' WHERE email = %s', strtolower( $email ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- table name.

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

		$column = 'token' === $column ? 'token' : 'unsubscribe';
		$row    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'subscribers' ) . " WHERE $column = %s AND $column <> ''", $hash ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- whitelisted column.

		return $row ? $row : null;
	}

	/**
	 * Stores a sign-up (new or again).
	 *
	 * @param array $data email, locale, message_id, status, token, unsubscribe, consent.
	 */
	public static function save_subscriber( array $data ) {
		global $wpdb;

		$t = self::table( 'subscribers' );

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $t (email, locale, message_id, status, token, unsubscribe, consent, date_added) VALUES (%s, %s, %d, %d, %s, %s, %s, %s) ON DUPLICATE KEY UPDATE locale = VALUES(locale), message_id = VALUES(message_id), status = VALUES(status), token = VALUES(token), unsubscribe = VALUES(unsubscribe), consent = VALUES(consent), date_added = VALUES(date_added), date_confirmed = NULL", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- table name.
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
	 * Confirms a sign-up.
	 *
	 * @param int $id Subscriber ID.
	 */
	public static function confirm_subscriber( $id ) {
		global $wpdb;

		$wpdb->update(
			self::table( 'subscribers' ),
			array(
				'status'         => 1,
				'token'          => '',
				'date_confirmed' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'subscriber_id' => (int) $id )
		);
	}

	/**
	 * Unsubscribes (status 2; the row stays as proof of the opt-out).
	 *
	 * @param int $id Subscriber ID.
	 */
	public static function unsubscribe( $id ) {
		global $wpdb;

		$wpdb->update(
			self::table( 'subscribers' ),
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

		return (int) $wpdb->delete( self::table( 'subscribers' ), array( 'email' => strtolower( $email ) ), array( '%s' ) );
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
		$rows = $wpdb->get_results( 'SELECT status, COUNT(*) AS total FROM ' . self::table( 'subscribers' ) . ' GROUP BY status', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no input.

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

		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT email, status, locale, message_id, consent, date_added, date_confirmed FROM ' . self::table( 'subscribers' ) . ' ORDER BY subscriber_id ASC LIMIT %d, %d', (int) $offset, (int) $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- table name.
	}
}
