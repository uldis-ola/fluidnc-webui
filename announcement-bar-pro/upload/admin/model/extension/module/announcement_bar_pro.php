<?php
defined('VERSION') || exit; // not callable directly (in the universal package these files also sit, unused, inside OpenCart 4)
/**
 * Announcement Bar Pro — admin model (OpenCart 3.0.x)
 * Tables: {prefix}abp_message, {prefix}abp_message_description
 * @version 2.1.0
 */
class ModelExtensionModuleAnnouncementBarPro extends Model {
	const CODE           = 'module_announcement_bar_pro';
	const SCHEMA_VERSION = 5;

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
			PRIMARY KEY (`message_id`, `language_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

		// Schema 3: link_text VARCHAR(64) → TEXT (emoji are stored as entities and can exceed 64 bytes).
		$this->db->query("ALTER TABLE `" . DB_PREFIX . "abp_message_description` MODIFY `link_text` TEXT NOT NULL");

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
		foreach (array('abp_message', 'abp_message_description') as $table) {
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
		$message['message_description'] = array();

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "abp_message_description` WHERE `message_id` = '" . (int)$message_id . "'");

		foreach ($query->rows as $row) {
			$message['message_description'][(int)$row['language_id']] = array(
				'text'      => $row['text'],
				'link_text' => $row['link_text']
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
			ORDER BY m.`sort_order` ASC, m.`message_id` ASC");

		$rows = array();

		foreach ($query->rows as $row) {
			$rows[] = $this->decodeRow($row);
		}

		return $rows;
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
			"`page_routes` = '" . $this->db->escape((string)$data['page_routes']) . "'"
		);

		return implode(', ', $fields);
	}

	private function saveDescriptions($message_id, array $descriptions) {
		foreach ($descriptions as $language_id => $value) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "abp_message_description` SET `message_id` = '" . (int)$message_id . "', `language_id` = '" . (int)$language_id . "', `text` = '" . $this->db->escape((string)$value['text']) . "', `link_text` = '" . $this->db->escape((string)$value['link_text']) . "'");
		}
	}

	private function dateOrNull($value) {
		$value = (string)$value;

		if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $value)) {
			return 'NULL';
		}

		return "'" . $this->db->escape($value) . "'";
	}

	private function decodeRow(array $row) {
		foreach (array('stores', 'customer_groups') as $key) {
			$decoded = json_decode((string)$row[$key], true);
			$row[$key] = is_array($decoded) ? array_map('intval', $decoded) : array();
		}

		return $row;
	}
}
