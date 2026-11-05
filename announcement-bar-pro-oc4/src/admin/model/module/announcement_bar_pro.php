<?php
namespace Opencart\Admin\Model\Extension\AnnouncementBarPro\Module;
/**
 * Announcement Bar Pro — admin model (OpenCart 4.0.2+ / 4.1)
 * Tables: {prefix}abp_message, {prefix}abp_message_description
 *
 * @version 2.0.3
 */
class AnnouncementBarPro extends \Opencart\System\Engine\Model {
	public const CODE           = 'module_announcement_bar_pro';
	public const SCHEMA_VERSION = 3;

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

		$settings = array_merge($this->getDefaultSettings(), $settings);
		$settings[self::CODE . '_schema'] = self::SCHEMA_VERSION;

		$this->model_setting_setting->editSetting(self::CODE, $settings);
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
				'text'      => $row['text'],
				'link_text' => $row['link_text']
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
			"`page_routes` = '" . $this->db->escape((string)$data['page_routes']) . "'"
		]);
	}

	private function saveDescriptions(int $message_id, array $descriptions): void {
		foreach ($descriptions as $language_id => $value) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "abp_message_description` SET `message_id` = '" . (int)$message_id . "', `language_id` = '" . (int)$language_id . "', `text` = '" . $this->db->escape((string)$value['text']) . "', `link_text` = '" . $this->db->escape((string)$value['link_text']) . "'");
		}
	}

	private function dateOrNull(string $value): string {
		if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $value)) {
			return 'NULL';
		}

		return "'" . $this->db->escape($value) . "'";
	}

	private function decodeRow(array $row): array {
		foreach (['stores', 'customer_groups'] as $key) {
			$decoded = json_decode((string)$row[$key], true);
			$row[$key] = is_array($decoded) ? array_map('intval', $decoded) : [];
		}

		return $row;
	}
}
