<?php
defined('VERSION') || exit; // not callable directly (in the universal package these files also sit, unused, inside OpenCart 4)
/**
 * Announcement Bar Pro — catalog model (OpenCart 3.0.x)
 * @version 2.2.0
 */
class ModelExtensionModuleAnnouncementBarPro extends Model {
	/**
	 * Active messages inside their date window. Text comes from the visitor's language; if that is
	 * empty, from the store's default language. Store, customer group and page filters are applied
	 * in the controller (JSON / route lists), so the SQL limit is generous.
	 * $schema < 6 (files upgraded, admin page not opened yet): the 2.2.0 columns do not exist yet.
	 */
	public function getActiveMessages($language_id, $default_language_id = 0, $schema = 6) {
		$now = $this->db->escape(date('Y-m-d H:i:s'));

		$v220 = (int)$schema >= 6
			? "m.`device`, IF(cur.`text` IS NOT NULL AND cur.`text` <> '', cur.`mobile_text`, def.`mobile_text`) AS `mobile_text`"
			: "'all' AS `device`, '' AS `mobile_text`";

		$query = $this->db->query("SELECT m.`message_id`, m.`date_end`, m.`bg_color`, m.`text_color`, m.`link`, m.`link_new_tab`, m.`icon`, m.`countdown`, m.`stores`, m.`customer_groups`, m.`page_mode`, m.`page_routes`, m.`date_modified`,
				IF(cur.`text` IS NOT NULL AND cur.`text` <> '', cur.`text`, def.`text`) AS `text`,
				IF(cur.`text` IS NOT NULL AND cur.`text` <> '', cur.`link_text`, def.`link_text`) AS `link_text`,
				" . $v220 . "
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
	 * Statistics: +1 view or click. Only enabled messages are counted; the counter stops at the INT UNSIGNED maximum.
	 */
	public function track($message_id, $type) {
		$column = $type === 'click' ? 'clicks' : 'views';

		$this->db->query("UPDATE `" . DB_PREFIX . "abp_message` SET `" . $column . "` = IF(`" . $column . "` < 4294967295, `" . $column . "` + 1, `" . $column . "`) WHERE `message_id` = '" . (int)$message_id . "' AND `status` = '1'");
	}
}
