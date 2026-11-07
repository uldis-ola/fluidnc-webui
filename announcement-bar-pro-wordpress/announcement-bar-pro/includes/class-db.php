<?php
/**
 * Database: own tables (per site on multisite), created and updated with dbDelta(); every query is prepared, table
 * names go through the %i identifier placeholder (WordPress 6.2+).
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

	/** Counters (written by an add-on), never written by the message form. */
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
	 * @param string $name messages | texts | rate.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;

		$names = array(
			'messages' => 'abpro_messages',
			'texts'    => 'abpro_message_texts',
			'rate'     => 'abpro_rate',
		);

		return $wpdb->prefix . $names[ $name ];
	}

	/**
	 * Creates or updates the tables of the current site. The message table keeps the columns of add-on features
	 * (placement, targeting, counters …) so their data survives when an add-on is switched off.
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$m       = self::table( 'messages' );
		$t       = self::table( 'texts' );
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
				'SELECT * FROM %i WHERE status = 1 AND (date_start IS NULL OR date_start <= %s) AND (date_end IS NULL OR date_end > %s) ORDER BY priority DESC, sort_order ASC, message_id ASC LIMIT 100',
				self::table( 'messages' ),
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
				'SELECT * FROM %i WHERE message_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- placeholders built from the ID count.
				array_merge( array( self::table( 'texts' ) ), $ids )
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

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE message_id = %d', self::table( 'messages' ), (int) $id ), ARRAY_A );

		if ( ! $row ) {
			return null;
		}

		$row['texts'] = array();
		$texts        = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE message_id = %d', self::table( 'texts' ), (int) $id ), ARRAY_A );

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

		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY priority DESC, sort_order ASC, message_id ASC', self::table( 'messages' ) ), ARRAY_A );

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
		$wpdb->query( $wpdb->prepare( 'INSERT INTO %i (bucket, hits, expires) VALUES (%s, 1, %d) ON DUPLICATE KEY UPDATE hits = IF(expires < %d, 1, hits + 1), expires = IF(expires < %d, %d, expires)', $t, $bucket, $now + $window, $now, $now, $now + $window ) );

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT hits FROM %i WHERE bucket = %s', $t, $bucket ) );
	}

	/**
	 * Daily clean-up: expired rate-limit rows (add-ons hook the same event for their own data).
	 */
	public static function cleanup() {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE expires < %d', self::table( 'rate' ), time() ) );
	}
}
