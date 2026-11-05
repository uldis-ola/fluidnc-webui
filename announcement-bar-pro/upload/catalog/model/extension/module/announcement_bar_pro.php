<?php
/**
 * Announcement Bar Pro — catalog model (OpenCart 3.0.x)
 * @version 2.0.0
 */
class ModelExtensionModuleAnnouncementBarPro extends Model {
	/**
	 * Active messages for the given language, inside their date window. Store, customer group
	 * and page filters are applied in the controller (they are JSON / route lists).
	 */
	public function getActiveMessages($language_id) {
		$now = $this->db->escape(date('Y-m-d H:i:s'));

		$query = $this->db->query("SELECT m.`message_id`, m.`date_end`, m.`bg_color`, m.`text_color`, m.`link`, m.`link_new_tab`, m.`icon`, m.`countdown`, m.`stores`, m.`customer_groups`, m.`page_mode`, m.`page_routes`, m.`date_modified`, md.`text`, md.`link_text`
			FROM `" . DB_PREFIX . "abp_message` m
			INNER JOIN `" . DB_PREFIX . "abp_message_description` md ON (m.`message_id` = md.`message_id` AND md.`language_id` = '" . (int)$language_id . "')
			WHERE m.`status` = '1'
			  AND (m.`date_start` IS NULL OR m.`date_start` <= '" . $now . "')
			  AND (m.`date_end` IS NULL OR m.`date_end` > '" . $now . "')
			  AND md.`text` <> ''
			ORDER BY m.`sort_order` ASC, m.`message_id` ASC
			LIMIT 50");

		return $query->rows;
	}
}
