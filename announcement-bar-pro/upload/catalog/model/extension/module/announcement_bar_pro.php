<?php
defined('VERSION') || exit; // not callable directly (in the universal package these files also sit, unused, inside OpenCart 4)
/**
 * Announcement Bar Pro — catalog model (OpenCart 3.0.x)
 * @version 2.3.0
 */
class ModelExtensionModuleAnnouncementBarPro extends Model {
	/**
	 * Active messages inside their date window. Text comes from the visitor's language; if that is
	 * empty, from the store's default language. Store, customer group, page, product/category and weekly
	 * schedule filters are applied in the controller, so the SQL limit is generous.
	 * Older schemas (files upgraded, admin page not opened yet) get neutral values for the newer columns.
	 */
	public function getActiveMessages($language_id, $default_language_id = 0, $schema = 7) {
		$now = $this->db->escape(date('Y-m-d H:i:s'));
		$cur = "(cur.`text` IS NOT NULL AND cur.`text` <> '')";

		$v220 = (int)$schema >= 6
			? "m.`device`, IF(" . $cur . ", cur.`mobile_text`, def.`mobile_text`) AS `mobile_text`"
			: "'all' AS `device`, '' AS `mobile_text`";

		$v230 = (int)$schema >= 7
			? "m.`type`, m.`threshold`, m.`progress_bar`, m.`coupon`, m.`ab_test`, m.`weekdays`, m.`time_start`, m.`time_end`, m.`categories`, m.`products`, m.`style`, m.`button_style`,
				IF(" . $cur . ", cur.`success_text`, def.`success_text`) AS `success_text`,
				IF(" . $cur . ", cur.`text_b`, def.`text_b`) AS `text_b`,
				IF(" . $cur . ", cur.`mobile_text_b`, def.`mobile_text_b`) AS `mobile_text_b`,
				IF(" . $cur . ", cur.`link_text_b`, def.`link_text_b`) AS `link_text_b`"
			: "'standard' AS `type`, 0 AS `threshold`, 0 AS `progress_bar`, '' AS `coupon`, 0 AS `ab_test`, '' AS `weekdays`, '' AS `time_start`, '' AS `time_end`, '' AS `categories`, '' AS `products`, '' AS `style`, 'outline' AS `button_style`,
				'' AS `success_text`, '' AS `text_b`, '' AS `mobile_text_b`, '' AS `link_text_b`";

		$query = $this->db->query("SELECT m.`message_id`, m.`date_end`, m.`bg_color`, m.`text_color`, m.`link`, m.`link_new_tab`, m.`icon`, m.`countdown`, m.`stores`, m.`customer_groups`, m.`page_mode`, m.`page_routes`, m.`date_modified`,
				IF(" . $cur . ", cur.`text`, def.`text`) AS `text`,
				IF(" . $cur . ", cur.`link_text`, def.`link_text`) AS `link_text`,
				" . $v220 . ",
				" . $v230 . "
			FROM `" . DB_PREFIX . "abp_message` m
			LEFT JOIN `" . DB_PREFIX . "abp_message_description` cur ON (m.`message_id` = cur.`message_id` AND cur.`language_id` = '" . (int)$language_id . "')
			LEFT JOIN `" . DB_PREFIX . "abp_message_description` def ON (m.`message_id` = def.`message_id` AND def.`language_id` = '" . (int)$default_language_id . "')
			WHERE m.`status` = '1'
			  AND (m.`date_start` IS NULL OR m.`date_start` <= '" . $now . "')
			  AND (m.`date_end` IS NULL OR m.`date_end` > '" . $now . "')
			  AND (" . $cur . " OR (def.`text` IS NOT NULL AND def.`text` <> ''))
			ORDER BY m.`sort_order` ASC, m.`message_id` ASC
			LIMIT 500");

		return $query->rows;
	}

	/** Category IDs a product belongs to (for "product pages of products in a selected category"). */
	public function getProductCategories($product_id) {
		$query = $this->db->query("SELECT `category_id` FROM `" . DB_PREFIX . "product_to_category` WHERE `product_id` = '" . (int)$product_id . "'");

		return array_map('intval', array_column($query->rows, 'category_id'));
	}

	/**
	 * Statistics: +1 view, click or copy for variant A or B. Only enabled messages inside their date window are
	 * counted (variant B only while its A/B test is on); counters stop at the INT UNSIGNED maximum.
	 * $schema < 7: only the 2.2.0 columns (views / clicks) exist.
	 */
	public function track($message_id, $type, $variant = 'a', $schema = 7) {
		$columns = array(
			'view'  => array('a' => 'views', 'b' => 'views_b'),
			'click' => array('a' => 'clicks', 'b' => 'clicks_b'),
			'copy'  => array('a' => 'copies', 'b' => 'copies_b')
		);

		$variant = $variant === 'b' && (int)$schema >= 7 ? 'b' : 'a';

		if (!isset($columns[$type]) || ($type === 'copy' && (int)$schema < 7)) {
			return;
		}

		$column = $columns[$type][$variant];

		$this->db->query("UPDATE `" . DB_PREFIX . "abp_message` SET `" . $column . "` = IF(`" . $column . "` < 4294967295, `" . $column . "` + 1, `" . $column . "`) WHERE `message_id` = '" . (int)$message_id . "' AND `status` = '1' AND (`date_start` IS NULL OR `date_start` <= NOW()) AND (`date_end` IS NULL OR `date_end` > NOW())" . ($variant === 'b' ? " AND `ab_test` = '1'" : ''));
	}
}
