<?php
defined('VERSION') || exit; // not callable directly (in the universal package these files also sit, unused, inside OpenCart 4)
/**
 * Announcement Bar Pro — admin model (OpenCart 3.0.x)
 * Tables: {prefix}abp_message, {prefix}abp_message_description, {prefix}abp_subscriber, {prefix}abp_rate
 * @version 2.4.1
 */
class ModelExtensionModuleAnnouncementBarPro extends Model {
	const CODE           = 'module_announcement_bar_pro';
	const SCHEMA_VERSION = 8;

	/** @var \Announcementbarpro\Features|null */
	private $abpFeatures = null;

	/**
	 * Creates tables (if missing), migrates v1 settings, fills missing default settings.
	 * Idempotent — safe to call on every admin page load of this module.
	 */
	public function ensureSchema() {
		$this->load->model('setting/setting');

		$settings = $this->model_setting_setting->getSetting(self::CODE);

		if ($this->isSchemaCurrent($settings)) {
			return;
		}

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "abp_message` (
			`message_id` INT(11) NOT NULL AUTO_INCREMENT,
			`status` TINYINT(1) NOT NULL DEFAULT '1',
			`sort_order` INT(11) NOT NULL DEFAULT '0',
			`date_start` DATETIME NULL DEFAULT NULL,
			`date_end` DATETIME NULL DEFAULT NULL,
			`bg_color` VARCHAR(7) NOT NULL DEFAULT '#222222',
			`text_color` VARCHAR(7) NOT NULL DEFAULT '#ffffff',
			`link` VARCHAR(255) NOT NULL DEFAULT '',
			`link_new_tab` TINYINT(1) NOT NULL DEFAULT '0',
			`icon` VARCHAR(64) NOT NULL DEFAULT '',
			`countdown` TINYINT(1) NOT NULL DEFAULT '0',
			`stores` TEXT NOT NULL,
			`customer_groups` TEXT NOT NULL,
			`page_mode` VARCHAR(16) NOT NULL DEFAULT 'all',
			`page_routes` TEXT NOT NULL,
			`device` VARCHAR(8) NOT NULL DEFAULT 'all',
			`views` INT(10) UNSIGNED NOT NULL DEFAULT '0',
			`clicks` INT(10) UNSIGNED NOT NULL DEFAULT '0',
			`type` VARCHAR(16) NOT NULL DEFAULT 'standard',
			`threshold` DECIMAL(15,4) NOT NULL DEFAULT '0.0000',
			`progress_bar` TINYINT(1) NOT NULL DEFAULT '1',
			`coupon` VARCHAR(32) NOT NULL DEFAULT '',
			`copies` INT(10) UNSIGNED NOT NULL DEFAULT '0',
			`ab_test` TINYINT(1) NOT NULL DEFAULT '0',
			`views_b` INT(10) UNSIGNED NOT NULL DEFAULT '0',
			`clicks_b` INT(10) UNSIGNED NOT NULL DEFAULT '0',
			`copies_b` INT(10) UNSIGNED NOT NULL DEFAULT '0',
			`weekdays` VARCHAR(7) NOT NULL DEFAULT '',
			`time_start` VARCHAR(5) NOT NULL DEFAULT '',
			`time_end` VARCHAR(5) NOT NULL DEFAULT '',
			`categories` TEXT NOT NULL,
			`products` TEXT NOT NULL,
			`style` VARCHAR(16) NOT NULL DEFAULT '',
			`button_style` VARCHAR(16) NOT NULL DEFAULT 'outline',
			`placement` VARCHAR(16) NOT NULL DEFAULT '',
			`priority` INT(11) NOT NULL DEFAULT '0',
			`options` TEXT NOT NULL,
			`subscribes` INT(10) UNSIGNED NOT NULL DEFAULT '0',
			`date_added` DATETIME NOT NULL,
			`date_modified` DATETIME NOT NULL,
			PRIMARY KEY (`message_id`),
			KEY `status_dates` (`status`, `date_start`, `date_end`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "abp_message_description` (
			`message_id` INT(11) NOT NULL,
			`language_id` INT(11) NOT NULL,
			`text` TEXT NOT NULL,
			`link_text` TEXT NOT NULL,
			`mobile_text` TEXT NOT NULL,
			`success_text` TEXT NOT NULL,
			`text_b` TEXT NOT NULL,
			`mobile_text_b` TEXT NOT NULL,
			`link_text_b` TEXT NOT NULL,
			`extra` TEXT NOT NULL,
			PRIMARY KEY (`message_id`, `language_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

		$this->createSubscriberTables();

		// Schema 3: link_text VARCHAR(64) → TEXT (emoji are stored as entities and can exceed 64 bytes).
		$this->db->query("ALTER TABLE `" . DB_PREFIX . "abp_message_description` MODIFY `link_text` TEXT NOT NULL");

		// Schema 6 (2.2.0) and 7 (2.3.0): columns added only when missing (upgraded shops); see schemaColumns().
		foreach ($this->schemaColumns() as $column) {
			$this->addColumnIfMissing($column[0], $column[1], $column[2]);
		}

		$defaults = $this->getDefaultSettings();

		// v1.0.0 → v2: legacy single text becomes the first message (only if any text was actually set).
		$legacy_text = isset($settings[self::CODE . '_text']) && is_array($settings[self::CODE . '_text']) ? array_filter($settings[self::CODE . '_text'], function ($text) {
			return is_string($text) && trim($text) !== '';
		}) : array();

		if ($legacy_text && !$this->getTotalMessages()) {
			$description = array();

			foreach ($legacy_text as $language_id => $text) {
				$description[(int)$language_id] = array('text' => $text, 'link_text' => '');
			}

			$this->addMessage(array(
				'status'             => 1,
				'sort_order'         => 0,
				'date_start'         => '',
				'date_end'           => '',
				'bg_color'           => isset($settings[self::CODE . '_bg_color']) ? $settings[self::CODE . '_bg_color'] : $defaults[self::CODE . '_default_bg_color'],
				'text_color'         => isset($settings[self::CODE . '_text_color']) ? $settings[self::CODE . '_text_color'] : $defaults[self::CODE . '_default_text_color'],
				'link'               => '',
				'link_new_tab'       => 0,
				'icon'               => '',
				'countdown'          => 0,
				'stores'             => array(),
				'customer_groups'    => array(),
				'page_mode'          => 'all',
				'page_routes'        => '',
				'message_description'=> $description
			));

			if (isset($settings[self::CODE . '_bg_color'])) {
				$settings[self::CODE . '_default_bg_color'] = $settings[self::CODE . '_bg_color'];
			}

			if (isset($settings[self::CODE . '_text_color'])) {
				$settings[self::CODE . '_default_text_color'] = $settings[self::CODE . '_text_color'];
			}
		}

		unset($settings[self::CODE . '_text'], $settings[self::CODE . '_bg_color'], $settings[self::CODE . '_text_color']);

		// Schema 4: shops upgraded from any older version (incl. 1.0.0, which had no schema key) keep
		// their own message colours; only a fresh install starts with automatic colours.
		// A reinstall that kept its messages (settings were deleted on uninstall) also keeps their colours.
		if (($settings || $this->getTotalMessages()) && !isset($settings[self::CODE . '_color_mode'])) {
			$settings[self::CODE . '_color_mode'] = 'custom';
		}

		$settings = array_merge($defaults, $settings);

		// Key for the e-mail form tokens (HMAC); created once, never shown.
		if (empty($settings[self::CODE . '_secret']) || !is_string($settings[self::CODE . '_secret']) || strlen($settings[self::CODE . '_secret']) < 32) {
			$settings[self::CODE . '_secret'] = bin2hex(random_bytes(32));
		}

		// Written last: the storefront uses the 2.4.0 columns only once the schema value says they exist.
		unset($settings[self::CODE . '_schema']);
		$settings[self::CODE . '_schema'] = self::SCHEMA_VERSION;

		$this->model_setting_setting->editSetting(self::CODE, $settings);

		// Schema 4/5 add the footer fallback events: re-register events, but only while the module is installed.
		$this->registerEvents();
	}

	/**
	 * Header event + footer fallback (themes without <body> in the header). Only while installed,
	 * so opening the module page after "Uninstall" cannot switch the bar back on.
	 */
	public function registerEvents($force = false) {
		$this->load->model('setting/extension');

		if (!$force && !in_array('announcement_bar_pro', $this->model_setting_extension->getInstalled('module'), true)) {
			return;
		}

		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('announcement_bar_pro');
		$this->model_setting_event->addEvent('announcement_bar_pro', 'catalog/view/common/header/after', 'extension/module/announcement_bar_pro/injectBar');
		$this->model_setting_event->addEvent('announcement_bar_pro', 'catalog/view/common/footer/after', 'extension/module/announcement_bar_pro/injectBarFooter');
		$this->model_setting_event->addEvent('announcement_bar_pro', 'catalog/view/*/after', 'extension/module/announcement_bar_pro/resolveFallback');
		// 2.4.0 inline placements (cart page, under the product price, mini-cart); each fails silently without its hook.
		$this->model_setting_event->addEvent('announcement_bar_pro', 'catalog/view/checkout/cart/after', 'extension/module/announcement_bar_pro/injectCart');
		$this->model_setting_event->addEvent('announcement_bar_pro', 'catalog/view/product/product/after', 'extension/module/announcement_bar_pro/injectProduct');
		$this->model_setting_event->addEvent('announcement_bar_pro', 'catalog/view/common/cart/after', 'extension/module/announcement_bar_pro/injectMiniCart');
	}

	/**
	 * Schema 8: newsletter sign-ups (double opt-in) and the rate limiter of the public endpoints.
	 * Only hashes of the confirmation / unsubscribe tokens are stored.
	 */
	private function createSubscriberTables() {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "abp_subscriber` (
			`subscriber_id` INT(11) NOT NULL AUTO_INCREMENT,
			`email` VARCHAR(96) NOT NULL,
			`store_id` INT(11) NOT NULL DEFAULT '0',
			`language_id` INT(11) NOT NULL DEFAULT '0',
			`message_id` INT(11) NOT NULL DEFAULT '0',
			`status` TINYINT(1) NOT NULL DEFAULT '0',
			`token` CHAR(64) NOT NULL DEFAULT '',
			`unsubscribe` CHAR(64) NOT NULL DEFAULT '',
			`consent` VARCHAR(400) NOT NULL DEFAULT '',
			`date_added` DATETIME NOT NULL,
			`date_confirmed` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`subscriber_id`),
			UNIQUE KEY `email_store` (`email`, `store_id`),
			KEY `token` (`token`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "abp_rate` (
			`bucket` CHAR(40) NOT NULL,
			`hits` INT(10) UNSIGNED NOT NULL DEFAULT '0',
			`expires` INT(10) UNSIGNED NOT NULL DEFAULT '0',
			PRIMARY KEY (`bucket`),
			KEY `expires` (`expires`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
	}

	/**
	 * Columns added by schema 6 (2.2.0), 7 (2.3.0) and 8 (2.4.0): array(table, column, definition). Fresh installs get them in CREATE TABLE.
	 * TEXT columns are "TEXT NOT NULL" without a DEFAULT: MySQL (unlike MariaDB) refuses a literal default on
	 * TEXT; existing rows get '' when the column is added, and every INSERT sets the value.
	 */
	public function schemaColumns() {
		return array(
			array('abp_message', 'device', "VARCHAR(8) NOT NULL DEFAULT 'all'"),
			array('abp_message', 'views', "INT(10) UNSIGNED NOT NULL DEFAULT '0'"),
			array('abp_message', 'clicks', "INT(10) UNSIGNED NOT NULL DEFAULT '0'"),
			array('abp_message_description', 'mobile_text', 'TEXT NOT NULL'),
			// 2.3.0
			array('abp_message', 'type', "VARCHAR(16) NOT NULL DEFAULT 'standard'"),
			array('abp_message', 'threshold', "DECIMAL(15,4) NOT NULL DEFAULT '0.0000'"),
			array('abp_message', 'progress_bar', "TINYINT(1) NOT NULL DEFAULT '1'"),
			array('abp_message', 'coupon', "VARCHAR(32) NOT NULL DEFAULT ''"),
			array('abp_message', 'copies', "INT(10) UNSIGNED NOT NULL DEFAULT '0'"),
			array('abp_message', 'ab_test', "TINYINT(1) NOT NULL DEFAULT '0'"),
			array('abp_message', 'views_b', "INT(10) UNSIGNED NOT NULL DEFAULT '0'"),
			array('abp_message', 'clicks_b', "INT(10) UNSIGNED NOT NULL DEFAULT '0'"),
			array('abp_message', 'copies_b', "INT(10) UNSIGNED NOT NULL DEFAULT '0'"),
			array('abp_message', 'weekdays', "VARCHAR(7) NOT NULL DEFAULT ''"),
			array('abp_message', 'time_start', "VARCHAR(5) NOT NULL DEFAULT ''"),
			array('abp_message', 'time_end', "VARCHAR(5) NOT NULL DEFAULT ''"),
			array('abp_message', 'categories', 'TEXT NOT NULL'),
			array('abp_message', 'products', 'TEXT NOT NULL'),
			array('abp_message', 'style', "VARCHAR(16) NOT NULL DEFAULT ''"),
			array('abp_message', 'button_style', "VARCHAR(16) NOT NULL DEFAULT 'outline'"),
			array('abp_message_description', 'success_text', 'TEXT NOT NULL'),
			array('abp_message_description', 'text_b', 'TEXT NOT NULL'),
			array('abp_message_description', 'mobile_text_b', 'TEXT NOT NULL'),
			array('abp_message_description', 'link_text_b', 'TEXT NOT NULL'),
			// 2.4.0
			array('abp_message', 'placement', "VARCHAR(16) NOT NULL DEFAULT ''"),
			array('abp_message', 'priority', "INT(11) NOT NULL DEFAULT '0'"),
			array('abp_message', 'options', 'TEXT NOT NULL'),
			array('abp_message', 'subscribes', "INT(10) UNSIGNED NOT NULL DEFAULT '0'"),
			array('abp_message_description', 'extra', 'TEXT NOT NULL')
		);
	}

	/**
	 * ALTER TABLE … ADD COLUMN only when SHOW COLUMNS does not find the column (idempotent, safe to repeat).
	 * Table and column names come from schemaColumns() only, never from a request.
	 */
	public function addColumnIfMissing($table, $column, $definition) {
		if (!preg_match('/^[a-z_]+$/', $table) || !preg_match('/^[a-z_]+$/', $column)) {
			return false;
		}

		// Escape LIKE wildcards: "_" in the column name must match literally.
		$query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . $table . "` LIKE '" . $this->db->escape(addcslashes($column, '_%')) . "'");

		if ($query->num_rows) {
			return false;
		}

		$this->db->query("ALTER TABLE `" . DB_PREFIX . $table . "` ADD COLUMN `" . $column . "` " . $definition);

		return true;
	}

	public function getDefaultSettings() {
		return array(
			self::CODE . '_status'             => 0,
			self::CODE . '_position'           => 'top',
			self::CODE . '_sticky'             => 0,
			self::CODE . '_mode'               => 'rotate',
			self::CODE . '_interval'           => 5,
			self::CODE . '_speed'              => 60,
			self::CODE . '_font_size'          => 14,
			self::CODE . '_padding'            => 8,
			self::CODE . '_dismissible'        => 1,
			self::CODE . '_dismiss_days'       => 1,
			self::CODE . '_allow_html'         => 0,
			self::CODE . '_default_bg_color'   => '#222222',
			self::CODE . '_default_text_color' => '#ffffff',
			self::CODE . '_delete_data'        => 0,
			self::CODE . '_color_mode'         => 'auto',
			self::CODE . '_style'              => 'solid',
			self::CODE . '_transition'         => 'fade',
			self::CODE . '_arrows'             => 0,
			// 2.4.0
			self::CODE . '_max_messages'       => 0,
			self::CODE . '_autohide'           => 0,
			self::CODE . '_pause_button'       => 1,
			self::CODE . '_font'               => '',
			self::CODE . '_custom_css'         => '',
			self::CODE . '_ga4'                => 1,
			self::CODE . '_geo_cf'             => 0,
			self::CODE . '_geo_fallback'       => 'store',
			self::CODE . '_privacy_id'         => 0,
			self::CODE . '_double_optin'       => 1,
			self::CODE . '_signup_limit_ip'    => 10,
			self::CODE . '_signup_limit_total' => 200,
			self::CODE . '_consent_wait'       => 0,
			// 2.4.1 Cloudflare Turnstile: off until both keys are entered (the secret is never shown on any page)
			self::CODE . '_ts_site'            => '',
			self::CODE . '_ts_secret'          => '',
			self::CODE . '_schema'             => self::SCHEMA_VERSION
		);
	}

	/**
	 * Saves global settings while keeping internal keys (schema).
	 */
	public function saveSettings(array $data) {
		$this->load->model('setting/setting');

		$current = $this->model_setting_setting->getSetting(self::CODE);

		$this->model_setting_setting->editSetting(self::CODE, array_merge($this->getDefaultSettings(), $current, $data));
	}

	/**
	 * Drops module tables. Only called when the admin explicitly enabled "delete data on uninstall".
	 */
	public function dropTables() {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "abp_subscriber`");
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "abp_rate`");
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "abp_message_description`");
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "abp_message`");
	}

	public function isSchemaCurrent($settings = null) {
		if ($settings === null) {
			$this->load->model('setting/setting');
			$settings = $this->model_setting_setting->getSetting(self::CODE);
		}

		return isset($settings[self::CODE . '_schema']) && (int)$settings[self::CODE . '_schema'] >= self::SCHEMA_VERSION && $this->tablesExist();
	}

	public function tablesExist() {
		foreach (array('abp_message', 'abp_message_description', 'abp_subscriber', 'abp_rate') as $table) {
			// Escape LIKE wildcards: "_" in the prefix/table name must match literally.
			$query = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(addcslashes(DB_PREFIX . $table, '_%')) . "'");

			if (!$query->num_rows) {
				return false;
			}
		}

		return true;
	}

	public function addMessage(array $data) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "abp_message` SET " . $this->messageFields($data) . ", `date_added` = NOW(), `date_modified` = NOW()");

		$message_id = (int)$this->db->getLastId();

		$this->saveDescriptions($message_id, $data['message_description']);

		return $message_id;
	}

	/**
	 * Copy of a message with all languages and settings; the copy starts switched off with zero statistics.
	 * Returns the new message_id, or 0 when the source does not exist.
	 */
	public function duplicateMessage($message_id) {
		$message = $this->getMessage((int)$message_id);

		if (!$message) {
			return 0;
		}

		$message['status'] = 0;

		return $this->addMessage($message); // views / clicks are not copied: the new row starts at 0
	}

	public function resetStatistics($message_id) {
		$this->db->query("UPDATE `" . DB_PREFIX . "abp_message` SET `views` = '0', `clicks` = '0', `copies` = '0', `views_b` = '0', `clicks_b` = '0', `copies_b` = '0', `subscribes` = '0' WHERE `message_id` = '" . (int)$message_id . "'");
	}

	/**
	 * A/B test winner: variant B becomes the main text (in every language that has a B text), the test is switched
	 * off and B's counters become the main counters. Returns false when the message has no A/B test.
	 */
	public function useVariantB($message_id) {
		$message_id = (int)$message_id;
		$query      = $this->db->query("SELECT `ab_test` FROM `" . DB_PREFIX . "abp_message` WHERE `message_id` = '" . $message_id . "'");

		if (!$query->num_rows || !$query->row['ab_test']) {
			return false;
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "abp_message_description` SET `text` = `text_b`, `mobile_text` = `mobile_text_b`, `link_text` = IF(`link_text_b` <> '', `link_text_b`, `link_text`) WHERE `message_id` = '" . $message_id . "' AND `text_b` <> ''");
		$this->db->query("UPDATE `" . DB_PREFIX . "abp_message_description` SET `text_b` = '', `mobile_text_b` = '', `link_text_b` = '' WHERE `message_id` = '" . $message_id . "'");
		$this->db->query("UPDATE `" . DB_PREFIX . "abp_message` SET `ab_test` = '0', `views` = `views_b`, `clicks` = `clicks_b`, `copies` = `copies_b`, `views_b` = '0', `clicks_b` = '0', `copies_b` = '0', `date_modified` = NOW() WHERE `message_id` = '" . $message_id . "'");

		return true;
	}

	public function editMessage($message_id, array $data) {
		$message_id = (int)$message_id;

		$this->db->query("UPDATE `" . DB_PREFIX . "abp_message` SET " . $this->messageFields($data) . ", `date_modified` = NOW() WHERE `message_id` = '" . $message_id . "'");

		$this->db->query("DELETE FROM `" . DB_PREFIX . "abp_message_description` WHERE `message_id` = '" . $message_id . "'");

		$this->saveDescriptions($message_id, $data['message_description']);
	}

	public function deleteMessage($message_id) {
		$message_id = (int)$message_id;

		$this->db->query("DELETE FROM `" . DB_PREFIX . "abp_message` WHERE `message_id` = '" . $message_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "abp_message_description` WHERE `message_id` = '" . $message_id . "'");
	}

	public function getMessage($message_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "abp_message` WHERE `message_id` = '" . (int)$message_id . "'");

		if (!$query->num_rows) {
			return array();
		}

		$message = $this->decodeRow($query->row);
		$message['options'] = $this->features()->options(isset($message['options']) ? $message['options'] : '');
		$message['message_description'] = array();

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "abp_message_description` WHERE `message_id` = '" . (int)$message_id . "'");

		foreach ($query->rows as $row) {
			$message['message_description'][(int)$row['language_id']] = array(
				'text'        => $row['text'],
				'link_text'   => $row['link_text'],
				'mobile_text'   => isset($row['mobile_text']) ? $row['mobile_text'] : '',
				'success_text'  => isset($row['success_text']) ? $row['success_text'] : '',
				'text_b'        => isset($row['text_b']) ? $row['text_b'] : '',
				'mobile_text_b' => isset($row['mobile_text_b']) ? $row['mobile_text_b'] : '',
				'link_text_b'   => isset($row['link_text_b']) ? $row['link_text_b'] : '',
				'extra'         => $this->features()->extraOf(isset($row['extra']) ? $row['extra'] : '')
			);
		}

		return $message;
	}

	/**
	 * List rows. `text` = admin language, else any language that has text.
	 * `languages_with_text` = comma list of language_ids that have text (to flag missing translations).
	 */
	public function getMessages($language_id) {
		$query = $this->db->query("SELECT m.*,
				COALESCE(NULLIF(md.`text`, ''), (SELECT x.`text` FROM `" . DB_PREFIX . "abp_message_description` x WHERE x.`message_id` = m.`message_id` AND x.`text` <> '' ORDER BY x.`language_id` LIMIT 1), '') AS `text`,
				(SELECT GROUP_CONCAT(y.`language_id`) FROM `" . DB_PREFIX . "abp_message_description` y WHERE y.`message_id` = m.`message_id` AND y.`text` <> '') AS `languages_with_text`
			FROM `" . DB_PREFIX . "abp_message` m
			LEFT JOIN `" . DB_PREFIX . "abp_message_description` md ON (m.`message_id` = md.`message_id` AND md.`language_id` = '" . (int)$language_id . "')
			ORDER BY m.`priority` DESC, m.`sort_order` ASC, m.`message_id` ASC");

		$rows = array();

		foreach ($query->rows as $row) {
			$rows[] = $this->decodeRow($row);
		}

		return $rows;
	}

	/**
	 * Every message with its texts keyed by language code (export). Statistics are not included.
	 */
	public function getMessagesForExport() {
		$languages = array();
		$query     = $this->db->query("SELECT `language_id`, `code` FROM `" . DB_PREFIX . "language`");

		foreach ($query->rows as $row) {
			$languages[(int)$row['language_id']] = $row['code'];
		}

		$out   = array();
		$query = $this->db->query("SELECT `message_id` FROM `" . DB_PREFIX . "abp_message` ORDER BY `priority` DESC, `sort_order` ASC, `message_id` ASC");

		foreach ($query->rows as $row) {
			$message = $this->getMessage((int)$row['message_id']);
			$texts   = array();

			foreach ($message['message_description'] as $language_id => $description) {
				if (isset($languages[$language_id])) {
					$texts[$languages[$language_id]] = $description;
				}
			}

			$message['texts'] = $texts;
			$out[] = $message;
		}

		return $out;
	}

	/** id => name for the given category IDs (admin language; missing IDs are left out). */
	public function getCategoryNames(array $ids, $language_id) {
		$ids = array_values(array_filter(array_map('intval', $ids)));

		if (!$ids) {
			return array();
		}

		$query = $this->db->query("SELECT c.`category_id` AS `id`, COALESCE(NULLIF(cd.`name`, ''), (SELECT x.`name` FROM `" . DB_PREFIX . "category_description` x WHERE x.`category_id` = c.`category_id` LIMIT 1), '') AS `name` FROM `" . DB_PREFIX . "category` c LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (cd.`category_id` = c.`category_id` AND cd.`language_id` = '" . (int)$language_id . "') WHERE c.`category_id` IN (" . implode(',', $ids) . ")");

		return $this->names($query->rows, $ids);
	}

	/** id => name for the given product IDs (admin language; missing IDs are left out). */
	public function getProductNames(array $ids, $language_id) {
		$ids = array_values(array_filter(array_map('intval', $ids)));

		if (!$ids) {
			return array();
		}

		$query = $this->db->query("SELECT p.`product_id` AS `id`, COALESCE(NULLIF(pd.`name`, ''), (SELECT x.`name` FROM `" . DB_PREFIX . "product_description` x WHERE x.`product_id` = p.`product_id` LIMIT 1), p.`model`) AS `name` FROM `" . DB_PREFIX . "product` p LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (pd.`product_id` = p.`product_id` AND pd.`language_id` = '" . (int)$language_id . "') WHERE p.`product_id` IN (" . implode(',', $ids) . ")");

		return $this->names($query->rows, $ids);
	}

	/** Keeps the order of $ids; names are plain text (entities decoded). */
	private function names(array $rows, array $ids) {
		$found = array();

		foreach ($rows as $row) {
			$found[(int)$row['id']] = html_entity_decode((string)$row['name'], ENT_QUOTES, 'UTF-8');
		}

		$out = array();

		foreach ($ids as $id) {
			if (isset($found[$id])) {
				$out[$id] = $found[$id];
			}
		}

		return $out;
	}

	public function getTotalMessages() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "abp_message`");

		return (int)$query->row['total'];
	}

	/**
	 * Every value is cast or escaped here. Callers pass already-validated data.
	 */
	private function messageFields(array $data) {
		$page_modes = array('all', 'home', 'include', 'exclude');
		$devices    = array('all', 'desktop', 'mobile');
		$device     = isset($data['device']) && in_array($data['device'], $devices, true) ? $data['device'] : 'all';
		$styles     = array('solid', 'gradient', 'soft', 'minimal', 'glass');

		$fields = array(
			"`status` = '" . (int)!empty($data['status']) . "'",
			"`sort_order` = '" . (int)$data['sort_order'] . "'",
			"`date_start` = " . $this->dateOrNull($data['date_start']),
			"`date_end` = " . $this->dateOrNull($data['date_end']),
			"`bg_color` = '" . $this->db->escape((string)$data['bg_color']) . "'",
			"`text_color` = '" . $this->db->escape((string)$data['text_color']) . "'",
			"`link` = '" . $this->db->escape((string)$data['link']) . "'",
			"`link_new_tab` = '" . (int)!empty($data['link_new_tab']) . "'",
			"`icon` = '" . $this->db->escape((string)$data['icon']) . "'",
			"`countdown` = '" . (int)!empty($data['countdown']) . "'",
			"`stores` = '" . $this->db->escape(json_encode(array_values(array_map('intval', (array)$data['stores'])))) . "'",
			"`customer_groups` = '" . $this->db->escape(json_encode(array_values(array_map('intval', (array)$data['customer_groups'])))) . "'",
			"`page_mode` = '" . $this->db->escape(in_array($data['page_mode'], $page_modes, true) ? $data['page_mode'] : 'all') . "'",
			"`page_routes` = '" . $this->db->escape((string)$data['page_routes']) . "'",
			"`device` = '" . $this->db->escape($device) . "'",
			// 2.3.0 (missing keys get neutral values, e.g. in the v1 migration)
			"`type` = '" . (isset($data['type']) && in_array($data['type'], array('free_shipping', 'tiers'), true) ? $data['type'] : 'standard') . "'",
			"`threshold` = '" . (isset($data['threshold']) ? number_format(max(0, (float)$data['threshold']), 4, '.', '') : '0.0000') . "'",
			"`progress_bar` = '" . (isset($data['progress_bar']) ? (int)!empty($data['progress_bar']) : 1) . "'",
			"`coupon` = '" . $this->db->escape(isset($data['coupon']) && preg_match('/^[A-Z0-9_-]{0,32}$/', (string)$data['coupon']) ? (string)$data['coupon'] : '') . "'",
			"`ab_test` = '" . (int)!empty($data['ab_test']) . "'",
			"`weekdays` = '" . $this->db->escape(isset($data['weekdays']) ? (string)preg_replace('/[^1-7]/', '', (string)$data['weekdays']) : '') . "'",
			"`time_start` = '" . $this->db->escape(isset($data['time_start']) && preg_match('/^\d{2}:\d{2}$/', (string)$data['time_start']) ? (string)$data['time_start'] : '') . "'",
			"`time_end` = '" . $this->db->escape(isset($data['time_end']) && preg_match('/^\d{2}:\d{2}$/', (string)$data['time_end']) ? (string)$data['time_end'] : '') . "'",
			"`categories` = '" . $this->db->escape(json_encode(array_values(array_map('intval', isset($data['categories']) ? (array)$data['categories'] : array())))) . "'",
			"`products` = '" . $this->db->escape(json_encode(array_values(array_map('intval', isset($data['products']) ? (array)$data['products'] : array())))) . "'",
			"`style` = '" . (isset($data['style']) && in_array($data['style'], $styles, true) ? $data['style'] : '') . "'",
			"`button_style` = '" . (isset($data['button_style']) && in_array($data['button_style'], array('filled', 'outline', 'link'), true) ? $data['button_style'] : 'outline') . "'",
			// 2.4.0
			"`placement` = '" . (isset($data['placement']) && in_array($data['placement'], array('top', 'bottom', 'cart', 'product', 'minicart'), true) ? $data['placement'] : '') . "'",
			"`priority` = '" . (isset($data['priority']) ? max(0, min(100, (int)$data['priority'])) : 0) . "'",
			"`options` = '" . $this->db->escape($this->features()->encode($this->features()->options(isset($data['options']) ? $data['options'] : array()))) . "'"
		);

		return implode(', ', $fields);
	}

	private function saveDescriptions($message_id, array $descriptions) {
		foreach ($descriptions as $language_id => $value) {
			$fields = "`message_id` = '" . (int)$message_id . "', `language_id` = '" . (int)$language_id . "', `text` = '" . $this->db->escape((string)$value['text']) . "', `link_text` = '" . $this->db->escape((string)$value['link_text']) . "'";

			foreach (array('mobile_text', 'success_text', 'text_b', 'mobile_text_b', 'link_text_b') as $key) {
				$fields .= ", `" . $key . "` = '" . $this->db->escape(isset($value[$key]) ? (string)$value[$key] : '') . "'";
			}

			// 2.4.0: tier labels / texts and e-mail form texts as ASCII-only JSON (\u escapes survive a 3-byte utf8 link).
			$fields .= ", `extra` = '" . $this->db->escape(json_encode($this->features()->extraOf(isset($value['extra']) ? $value['extra'] : array()))) . "'";

			$this->db->query("INSERT INTO `" . DB_PREFIX . "abp_message_description` SET " . $fields);
		}
	}

	/* ------------------------------------------------------------------ *
	 * 2.4.0: lookups for the form and newsletter sign-ups
	 * ------------------------------------------------------------------ */

	/** @return \Announcementbarpro\Features */
	public function features() {
		if (!$this->abpFeatures) {
			require_once(DIR_SYSTEM . 'library/announcementbarpro/features.php');
			$this->abpFeatures = new \Announcementbarpro\Features();
		}

		return $this->abpFeatures;
	}

	/** Countries with status on: id, name, ISO code (country chips of the form). */
	public function getCountries() {
		$query = $this->db->query("SELECT `country_id` AS `id`, `name`, `iso_code_2` AS `iso` FROM `" . DB_PREFIX . "country` WHERE `status` = '1' ORDER BY `name` ASC");
		$out   = array();

		foreach ($query->rows as $row) {
			$out[] = array('id' => (int)$row['id'], 'name' => html_entity_decode((string)$row['name'], ENT_QUOTES, 'UTF-8'), 'iso' => (string)$row['iso']);
		}

		return $out;
	}

	/** Zones (regions) of one country: id + name. */
	public function getZones($country_id) {
		$query = $this->db->query("SELECT `zone_id` AS `id`, `name` FROM `" . DB_PREFIX . "zone` WHERE `country_id` = '" . (int)$country_id . "' AND `status` = '1' ORDER BY `name` ASC");
		$out   = array();

		foreach ($query->rows as $row) {
			$out[] = array('id' => (int)$row['id'], 'name' => html_entity_decode((string)$row['name'], ENT_QUOTES, 'UTF-8'));
		}

		return $out;
	}

	/**
	 * Existence checks for Features::validate(): country / coupon / product / category → the IDs that exist,
	 * zone → array(zone_id => country_id), image → true when the file exists under image/.
	 */
	public function lookup($kind, $values) {
		if ($kind === 'image') {
			return defined('DIR_IMAGE') && is_string($values) && $this->features()->imagePath($values) && is_file(DIR_IMAGE . $values);
		}

		$ids = array_values(array_filter(array_map('intval', (array)$values)));

		if (!$ids) {
			return array();
		}

		$in = implode(',', $ids);

		if ($kind === 'zone') {
			$query = $this->db->query("SELECT `zone_id`, `country_id` FROM `" . DB_PREFIX . "zone` WHERE `zone_id` IN (" . $in . ")");
			$out   = array();

			foreach ($query->rows as $row) {
				$out[(int)$row['zone_id']] = (int)$row['country_id'];
			}

			return $out;
		}

		$tables = array('country' => 'country', 'coupon' => 'coupon', 'product' => 'product', 'category' => 'category');

		if (!isset($tables[$kind])) {
			return array();
		}

		$query = $this->db->query("SELECT `" . $kind . "_id` AS `id` FROM `" . DB_PREFIX . $tables[$kind] . "` WHERE `" . $kind . "_id` IN (" . $in . ")");

		return array_map('intval', array_column($query->rows, 'id'));
	}

	/** Coupons whose name or code contains $filter (autocomplete), at most 10, active ones first. */
	public function searchCoupons($filter) {
		$like  = $this->db->escape(addcslashes((string)$filter, '%_\\'));
		$query = $this->db->query("SELECT `coupon_id`, `name`, `code`, `status`, `date_end` FROM `" . DB_PREFIX . "coupon` WHERE `name` LIKE '%" . $like . "%' OR `code` LIKE '%" . $like . "%' ORDER BY `status` DESC, `date_end` DESC LIMIT 10");

		return $query->rows;
	}

	/** One coupon (chip of the form), or array(). */
	public function getCoupon($coupon_id) {
		$query = $this->db->query("SELECT `coupon_id`, `name`, `code`, `status`, `date_start`, `date_end` FROM `" . DB_PREFIX . "coupon` WHERE `coupon_id` = '" . (int)$coupon_id . "'");

		return $query->row;
	}

	/** Sign-ups by status: array(0 => waiting for confirmation, 1 => confirmed, 2 => unsubscribed). */
	public function countSubscribers() {
		$out = array(0 => 0, 1 => 0, 2 => 0);

		if (!$this->tablesExist()) {
			return $out;
		}

		$query = $this->db->query("SELECT `status`, COUNT(*) AS `total` FROM `" . DB_PREFIX . "abp_subscriber` GROUP BY `status`");

		foreach ($query->rows as $row) {
			$out[(int)$row['status']] = (int)$row['total'];
		}

		return $out;
	}

	/** Every sign-up for the CSV export (never the token hashes). */
	public function getSubscribers() {
		$query = $this->db->query("SELECT s.`email`, s.`status`, s.`store_id`, l.`code` AS `language`, s.`message_id`, s.`consent`, s.`date_added`, s.`date_confirmed` FROM `" . DB_PREFIX . "abp_subscriber` s LEFT JOIN `" . DB_PREFIX . "language` l ON (l.`language_id` = s.`language_id`) ORDER BY s.`subscriber_id` ASC");

		return $query->rows;
	}

	/** Erases every sign-up of one e-mail address (all stores); returns how many rows existed. */
	public function deleteSubscriber($email) {
		$email = $this->db->escape((string)$email);
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "abp_subscriber` WHERE `email` = '" . $email . "'");

		$this->db->query("DELETE FROM `" . DB_PREFIX . "abp_subscriber` WHERE `email` = '" . $email . "'");

		return (int)$query->row['total'];
	}

	private function dateOrNull($value) {
		$value = (string)$value;

		if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $value)) {
			return 'NULL';
		}

		return "'" . $this->db->escape($value) . "'";
	}

	private function decodeRow(array $row) {
		foreach (array('stores', 'customer_groups', 'categories', 'products') as $key) {
			if (!array_key_exists($key, $row)) {
				continue;
			}

			$decoded = json_decode((string)$row[$key], true);
			$row[$key] = is_array($decoded) ? array_map('intval', $decoded) : array();
		}

		return $row;
	}
}
