<?php
namespace Opencart\Catalog\Model\Extension\AnnouncementBarPro\Module;
/**
 * Announcement Bar Pro — catalog model (OpenCart 4.0.x / 4.1.x)
 *
 * @version 2.1.0
 */
class AnnouncementBarPro extends \Opencart\System\Engine\Model {
	/**
	 * Active messages inside their date window. Text comes from the visitor's language; if that is
	 * empty, from the store's default language. Store / customer group / page filters run in the controller.
	 */
	public function getActiveMessages(int $language_id, int $default_language_id = 0): array {
		$now = $this->db->escape(date('Y-m-d H:i:s'));

		$query = $this->db->query("SELECT m.`message_id`, m.`date_end`, m.`bg_color`, m.`text_color`, m.`link`, m.`link_new_tab`, m.`icon`, m.`countdown`, m.`stores`, m.`customer_groups`, m.`page_mode`, m.`page_routes`, m.`date_modified`,
				IF(cur.`text` IS NOT NULL AND cur.`text` <> '', cur.`text`, def.`text`) AS `text`,
				IF(cur.`text` IS NOT NULL AND cur.`text` <> '', cur.`link_text`, def.`link_text`) AS `link_text`
			FROM `" . DB_PREFIX . "abp_message` m
			LEFT JOIN `" . DB_PREFIX . "abp_message_description` cur ON (m.`message_id` = cur.`message_id` AND cur.`language_id` = '" . (int)$language_id . "')
			LEFT JOIN `" . DB_PREFIX . "abp_message_description` def ON (m.`message_id` = def.`message_id` AND def.`language_id` = '" . (int)$default_language_id . "')
			WHERE m.`status` = '1'
			  AND (m.`date_start` IS NULL OR m.`date_start` <= '" . $now . "')
			  AND (m.`date_end` IS NULL OR m.`date_end` > '" . $now . "')
			  AND ((cur.`text` IS NOT NULL AND cur.`text` <> '') OR (def.`text` IS NOT NULL AND def.`text` <> ''))
			ORDER BY m.`sort_order` ASC, m.`message_id` ASC
			LIMIT 500");

		return $query->rows;
	}

	/**
	 * Store's default catalog language id. 4.1.x keeps it in config_language_catalog, 4.0.x in
	 * config_language (the runtime config value is overwritten with the visitor's language).
	 */
	public function getDefaultLanguageId(int $store_id): int {
		$query = $this->db->query("SELECT l.`language_id` FROM `" . DB_PREFIX . "setting` s
			INNER JOIN `" . DB_PREFIX . "language` l ON (l.`code` = TRIM(BOTH '\"' FROM s.`value`))
			WHERE s.`key` IN ('config_language_catalog', 'config_language') AND s.`store_id` IN (0, '" . (int)$store_id . "')
			ORDER BY s.`store_id` DESC, (s.`key` = 'config_language_catalog') DESC
			LIMIT 1");

		return $query->num_rows ? (int)$query->row['language_id'] : 0;
	}
}
