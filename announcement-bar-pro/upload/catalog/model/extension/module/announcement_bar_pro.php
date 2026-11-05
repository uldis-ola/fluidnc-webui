<?php
/**
 * Announcement Bar Pro — catalog model (OpenCart 3.0.x)
 * @version 2.0.2
 */
class ModelExtensionModuleAnnouncementBarPro extends Model {
	/**
	 * Active messages inside their date window. Text comes from the visitor's language; if that is
	 * empty, from the store's default language. Store, customer group and page filters are applied
	 * in the controller (JSON / route lists), so the SQL limit is generous.
	 */
	public function getActiveMessages($language_id, $default_language_id = 0) {
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
}
