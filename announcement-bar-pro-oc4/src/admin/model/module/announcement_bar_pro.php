<?php
namespace Opencart\Admin\Model\Extension\AnnouncementBarPro\Module;
\defined('VERSION') || exit; // not callable directly
/**
 * Announcement Bar Pro — admin model (OpenCart 4.0.2+ / 4.1)
 * Tables: {prefix}abp_message, {prefix}abp_message_description
 *
 * @version 2.3.0
 */
class AnnouncementBarPro extends \Opencart\System\Engine\Model {
	public const CODE           = 'module_announcement_bar_pro';
	public const SCHEMA_VERSION = 7;

	/**
	 * Creates tables (if missing) and fills missing default settings. Idempotent.
	 */
	public function ensureSchema(): void {
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
			PRIMARY KEY (`message_id`, `language_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

		// Schema 6 (2.2.0) and 7 (2.3.0): columns added only when missing (upgraded shops); see schemaColumns().
		foreach ($this->schemaColumns() as [$table, $column, $definition]) {
			$this->addColumnIfMissing($table, $column, $definition);
		}

		// Schema 4: shops upgraded from any older version (incl. 1.0.0, which had no schema key) keep
		// their own message colours; only a fresh install starts with automatic colours.
		// A reinstall that kept its messages (settings were deleted on uninstall) also keeps their colours.
		if (($settings || $this->getTotalMessages()) && !isset($settings[self::CODE . '_color_mode'])) {
			$settings[self::CODE . '_color_mode'] = 'custom';
		}

		$settings = array_merge($this->getDefaultSettings(), $settings);
		$settings[self::CODE . '_schema'] = self::SCHEMA_VERSION;

		$this->model_setting_setting->editSetting(self::CODE, $settings);

		// Schema 4/5 add the footer fallback events: re-register events, but only while the module is installed.
		$this->registerEvents();
	}

	/**
	 * Header event + footer fallback (themes without <body> in the header). Only while installed.
	 */
	public function registerEvents(bool $force = false): void {
		$this->load->model('setting/extension');

		if (!$force && !$this->model_setting_extension->getExtensionByCode('module', 'announcement_bar_pro')) {
			return;
		}

		$sep   = version_compare(VERSION, '4.0.2.0', '>=') ? '.' : '|';
		$route = 'extension/announcement_bar_pro/module/announcement_bar_pro';

		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('announcement_bar_pro');

		$events = [
			'common/header' => ['injectBar', 'inject the bar after <body>'],
			'common/footer' => ['injectBarFooter', 'mark the end of <body> (fallback)'],
			'*'             => ['resolveFallback', 'use the marker if the header had no <body>']
		];

		foreach ($events as $view => [$method, $text]) {
			$event = [
				'code'        => 'announcement_bar_pro',
				'description' => 'Announcement Bar Pro: ' . $text,
				'trigger'     => 'catalog/view/' . $view . '/after',
				'action'      => $route . $sep . $method,
				'status'      => 1,
				'sort_order'  => 0
			];

			if (version_compare(VERSION, '4.0.1.0', '<')) {
				// OpenCart 4.0.0.x: addEvent($code, $description, $trigger, $action, $status, $sort_order)
				$this->model_setting_event->addEvent($event['code'], $event['description'], $event['trigger'], $event['action'], true, 0);
			} else {
				// OpenCart 4.0.1.0+: addEvent(array $data)
				$this->model_setting_event->addEvent($event);
			}
		}
	}

	/**
	 * Columns added by schema 6 (2.2.0) and 7 (2.3.0): [table, column, definition]. Fresh installs get them in CREATE TABLE.
	 * TEXT columns are "TEXT NOT NULL" without a DEFAULT: MySQL (unlike MariaDB) refuses a literal default on
	 * TEXT; existing rows get '' when the column is added, and every INSERT sets the value.
	 */
	public function schemaColumns(): array {
		return [
			['abp_message', 'device', "VARCHAR(8) NOT NULL DEFAULT 'all'"],
			['abp_message', 'views', "INT(10) UNSIGNED NOT NULL DEFAULT '0'"],
			['abp_message', 'clicks', "INT(10) UNSIGNED NOT NULL DEFAULT '0'"],
			['abp_message_description', 'mobile_text', 'TEXT NOT NULL'],
			// 2.3.0
			['abp_message', 'type', "VARCHAR(16) NOT NULL DEFAULT 'standard'"],
			['abp_message', 'threshold', "DECIMAL(15,4) NOT NULL DEFAULT '0.0000'"],
			['abp_message', 'progress_bar', "TINYINT(1) NOT NULL DEFAULT '1'"],
			['abp_message', 'coupon', "VARCHAR(32) NOT NULL DEFAULT ''"],
			['abp_message', 'copies', "INT(10) UNSIGNED NOT NULL DEFAULT '0'"],
			['abp_message', 'ab_test', "TINYINT(1) NOT NULL DEFAULT '0'"],
			['abp_message', 'views_b', "INT(10) UNSIGNED NOT NULL DEFAULT '0'"],
			['abp_message', 'clicks_b', "INT(10) UNSIGNED NOT NULL DEFAULT '0'"],
			['abp_message', 'copies_b', "INT(10) UNSIGNED NOT NULL DEFAULT '0'"],
			['abp_message', 'weekdays', "VARCHAR(7) NOT NULL DEFAULT ''"],
			['abp_message', 'time_start', "VARCHAR(5) NOT NULL DEFAULT ''"],
			['abp_message', 'time_end', "VARCHAR(5) NOT NULL DEFAULT ''"],
			['abp_message', 'categories', 'TEXT NOT NULL'],
			['abp_message', 'products', 'TEXT NOT NULL'],
			['abp_message', 'style', "VARCHAR(16) NOT NULL DEFAULT ''"],
			['abp_message', 'button_style', "VARCHAR(16) NOT NULL DEFAULT 'outline'"],
			['abp_message_description', 'success_text', 'TEXT NOT NULL'],
			['abp_message_description', 'text_b', 'TEXT NOT NULL'],
			['abp_message_description', 'mobile_text_b', 'TEXT NOT NULL'],
			['abp_message_description', 'link_text_b', 'TEXT NOT NULL']
		];
	}

	/**
	 * ALTER TABLE … ADD COLUMN only when SHOW COLUMNS does not find the column (idempotent, safe to repeat).
	 * Table and column names come from schemaColumns() only, never from a request.
	 */
	public function addColumnIfMissing(string $table, string $column, string $definition): bool {
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

	/**
	 * Every message with its texts keyed by language code (export). Statistics are not included.
	 */
	public function getMessagesForExport(): array {
		$languages = array();
		$query     = $this->db->query("SELECT `language_id`, `code` FROM `" . DB_PREFIX . "language`");

		foreach ($query->rows as $row) {
			$languages[(int)$row['language_id']] = $row['code'];
		}

		$out   = array();
		$query = $this->db->query("SELECT `message_id` FROM `" . DB_PREFIX . "abp_message` ORDER BY `sort_order` ASC, `message_id` ASC");

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
	public function getCategoryNames(array $ids, int $language_id): array {
		$ids = array_values(array_filter(array_map('intval', $ids)));

		if (!$ids) {
			return array();
		}

		$query = $this->db->query("SELECT c.`category_id` AS `id`, COALESCE(NULLIF(cd.`name`, ''), (SELECT x.`name` FROM `" . DB_PREFIX . "category_description` x WHERE x.`category_id` = c.`category_id` LIMIT 1), '') AS `name` FROM `" . DB_PREFIX . "category` c LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (cd.`category_id` = c.`category_id` AND cd.`language_id` = '" . (int)$language_id . "') WHERE c.`category_id` IN (" . implode(',', $ids) . ")");

		return $this->names($query->rows, $ids);
	}

	/** id => name for the given product IDs (admin language; missing IDs are left out). */
	public function getProductNames(array $ids, int $language_id): array {
		$ids = array_values(array_filter(array_map('intval', $ids)));

		if (!$ids) {
			return array();
		}

		$query = $this->db->query("SELECT p.`product_id` AS `id`, COALESCE(NULLIF(pd.`name`, ''), (SELECT x.`name` FROM `" . DB_PREFIX . "product_description` x WHERE x.`product_id` = p.`product_id` LIMIT 1), p.`model`) AS `name` FROM `" . DB_PREFIX . "product` p LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (pd.`product_id` = p.`product_id` AND pd.`language_id` = '" . (int)$language_id . "') WHERE p.`product_id` IN (" . implode(',', $ids) . ")");

		return $this->names($query->rows, $ids);
	}

	/** Keeps the order of $ids; names are plain text (entities decoded). */
	private function names(array $rows, array $ids): array {
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

	public function getTotalMessages(): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "abp_message`");

		return (int)$query->row['total'];
	}

	public function getDefaultSettings(): array {
		return [
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
			self::CODE . '_schema'             => self::SCHEMA_VERSION
		];
	}

	public function saveSettings(array $data): void {
		$this->load->model('setting/setting');

		$current = $this->model_setting_setting->getSetting(self::CODE);

		$this->model_setting_setting->editSetting(self::CODE, array_merge($this->getDefaultSettings(), $current, $data));
	}

	public function isSchemaCurrent(?array $settings = null): bool {
		if ($settings === null) {
			$this->load->model('setting/setting');
			$settings = $this->model_setting_setting->getSetting(self::CODE);
		}

		return isset($settings[self::CODE . '_schema']) && (int)$settings[self::CODE . '_schema'] >= self::SCHEMA_VERSION && $this->tablesExist();
	}

	public function tablesExist(): bool {
		foreach (['abp_message', 'abp_message_description'] as $table) {
			$query = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(addcslashes(DB_PREFIX . $table, '_%')) . "'");

			if (!$query->num_rows) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Only called when the admin explicitly ticked "Delete messages on uninstall".
	 */
	public function dropTables(): void {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "abp_message_description`");
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "abp_message`");
	}

	public function addMessage(array $data): int {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "abp_message` SET " . $this->messageFields($data) . ", `date_added` = NOW(), `date_modified` = NOW()");

		$message_id = (int)$this->db->getLastId();

		$this->saveDescriptions($message_id, $data['message_description']);

		return $message_id;
	}

	/**
	 * Copy of a message with all languages and settings; the copy starts switched off with zero statistics.
	 * Returns the new message_id, or 0 when the source does not exist.
	 */
	public function duplicateMessage(int $message_id): int {
		$message = $this->getMessage($message_id);

		if (!$message) {
			return 0;
		}

		$message['status'] = 0;

		return $this->addMessage($message); // views / clicks are not copied: the new row starts at 0
	}

	public function resetStatistics(int $message_id): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "abp_message` SET `views` = '0', `clicks` = '0', `copies` = '0', `views_b` = '0', `clicks_b` = '0', `copies_b` = '0' WHERE `message_id` = '" . (int)$message_id . "'");
	}

	/**
	 * A/B test winner: variant B becomes the main text (in every language that has a B text), the test is switched
	 * off and B's counters become the main counters. Returns false when the message has no A/B test.
	 */
	public function useVariantB(int $message_id): bool {
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

	public function editMessage(int $message_id, array $data): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "abp_message` SET " . $this->messageFields($data) . ", `date_modified` = NOW() WHERE `message_id` = '" . (int)$message_id . "'");

		$this->db->query("DELETE FROM `" . DB_PREFIX . "abp_message_description` WHERE `message_id` = '" . (int)$message_id . "'");

		$this->saveDescriptions($message_id, $data['message_description']);
	}

	public function deleteMessage(int $message_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "abp_message` WHERE `message_id` = '" . (int)$message_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "abp_message_description` WHERE `message_id` = '" . (int)$message_id . "'");
	}

	public function getMessage(int $message_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "abp_message` WHERE `message_id` = '" . (int)$message_id . "'");

		if (!$query->num_rows) {
			return [];
		}

		$message = $this->decodeRow($query->row);
		$message['message_description'] = [];

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "abp_message_description` WHERE `message_id` = '" . (int)$message_id . "'");

		foreach ($query->rows as $row) {
			$message['message_description'][(int)$row['language_id']] = [
				'text'        => $row['text'],
				'link_text'   => $row['link_text'],
				'mobile_text'   => $row['mobile_text'] ?? '',
				'success_text'  => $row['success_text'] ?? '',
				'text_b'        => $row['text_b'] ?? '',
				'mobile_text_b' => $row['mobile_text_b'] ?? '',
				'link_text_b'   => $row['link_text_b'] ?? ''
			];
		}

		return $message;
	}

	/**
	 * `text` = admin language, else any language with text; `languages_with_text` flags missing translations.
	 */
	public function getMessages(int $language_id): array {
		$query = $this->db->query("SELECT m.*,
				COALESCE(NULLIF(md.`text`, ''), (SELECT x.`text` FROM `" . DB_PREFIX . "abp_message_description` x WHERE x.`message_id` = m.`message_id` AND x.`text` <> '' ORDER BY x.`language_id` LIMIT 1), '') AS `text`,
				(SELECT GROUP_CONCAT(y.`language_id`) FROM `" . DB_PREFIX . "abp_message_description` y WHERE y.`message_id` = m.`message_id` AND y.`text` <> '') AS `languages_with_text`
			FROM `" . DB_PREFIX . "abp_message` m
			LEFT JOIN `" . DB_PREFIX . "abp_message_description` md ON (m.`message_id` = md.`message_id` AND md.`language_id` = '" . (int)$language_id . "')
			ORDER BY m.`sort_order` ASC, m.`message_id` ASC");

		$rows = [];

		foreach ($query->rows as $row) {
			$rows[] = $this->decodeRow($row);
		}

		return $rows;
	}

	/**
	 * Every value is cast or escaped here; callers pass validated data.
	 */
	private function messageFields(array $data): string {
		$page_modes = ['all', 'home', 'include', 'exclude'];
		$device     = in_array($data['device'] ?? 'all', ['all', 'desktop', 'mobile'], true) ? ($data['device'] ?? 'all') : 'all';
		$styles     = ['solid', 'gradient', 'soft', 'minimal', 'glass'];

		return implode(', ', [
			"`status` = '" . (int)!empty($data['status']) . "'",
			"`sort_order` = '" . (int)$data['sort_order'] . "'",
			"`date_start` = " . $this->dateOrNull((string)$data['date_start']),
			"`date_end` = " . $this->dateOrNull((string)$data['date_end']),
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
			"`type` = '" . (isset($data['type']) && $data['type'] === 'free_shipping' ? 'free_shipping' : 'standard') . "'",
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
			"`button_style` = '" . (isset($data['button_style']) && in_array($data['button_style'], array('filled', 'outline', 'link'), true) ? $data['button_style'] : 'outline') . "'"
		]);
	}

	private function saveDescriptions(int $message_id, array $descriptions): void {
		foreach ($descriptions as $language_id => $value) {
			$fields = "`message_id` = '" . (int)$message_id . "', `language_id` = '" . (int)$language_id . "', `text` = '" . $this->db->escape((string)$value['text']) . "', `link_text` = '" . $this->db->escape((string)$value['link_text']) . "'";

			foreach (array('mobile_text', 'success_text', 'text_b', 'mobile_text_b', 'link_text_b') as $key) {
				$fields .= ", `" . $key . "` = '" . $this->db->escape(isset($value[$key]) ? (string)$value[$key] : '') . "'";
			}

			$this->db->query("INSERT INTO `" . DB_PREFIX . "abp_message_description` SET " . $fields);
		}
	}

	private function dateOrNull(string $value): string {
		if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $value)) {
			return 'NULL';
		}

		return "'" . $this->db->escape($value) . "'";
	}

	private function decodeRow(array $row): array {
		foreach (['stores', 'customer_groups', 'categories', 'products'] as $key) {
			if (!array_key_exists($key, $row)) {
				continue;
			}

			$decoded = json_decode((string)$row[$key], true);
			$row[$key] = is_array($decoded) ? array_map('intval', $decoded) : [];
		}

		return $row;
	}
}
