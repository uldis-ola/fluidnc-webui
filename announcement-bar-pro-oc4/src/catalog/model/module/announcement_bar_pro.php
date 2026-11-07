<?php
namespace Opencart\Catalog\Model\Extension\AnnouncementBarPro\Module;
\defined('VERSION') || exit; // not callable directly
/**
 * Announcement Bar Pro — catalog model (OpenCart 4.0.x / 4.1.x)
 *
 * @version 2.4.0
 */
class AnnouncementBarPro extends \Opencart\System\Engine\Model {
	/**
	 * Active messages inside their date window. Text comes from the visitor's language; if that is
	 * empty, from the store's default language. Store, customer group, page, product/category and weekly
	 * schedule filters are applied in the controller, so the SQL limit is generous.
	 * Older schemas (files upgraded, admin page not opened yet) get neutral values for the newer columns.
	 */
	public function getActiveMessages(int $language_id, int $default_language_id = 0, int $schema = 8): array {
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

		// 2.4.0: placement, priority, options (JSON) and the per-language extra texts (tier labels, form texts).
		$v240 = (int)$schema >= 8
			? "m.`placement`, m.`priority`, m.`options`, IF(" . $cur . ", cur.`extra`, def.`extra`) AS `extra`"
			: "'' AS `placement`, 0 AS `priority`, '' AS `options`, '' AS `extra`";

		$query = $this->db->query("SELECT m.`message_id`, m.`date_end`, m.`bg_color`, m.`text_color`, m.`link`, m.`link_new_tab`, m.`icon`, m.`countdown`, m.`stores`, m.`customer_groups`, m.`page_mode`, m.`page_routes`, m.`date_modified`,
				IF(" . $cur . ", cur.`text`, def.`text`) AS `text`,
				IF(" . $cur . ", cur.`link_text`, def.`link_text`) AS `link_text`,
				" . $v220 . ",
				" . $v230 . ",
				" . $v240 . "
			FROM `" . DB_PREFIX . "abp_message` m
			LEFT JOIN `" . DB_PREFIX . "abp_message_description` cur ON (m.`message_id` = cur.`message_id` AND cur.`language_id` = '" . (int)$language_id . "')
			LEFT JOIN `" . DB_PREFIX . "abp_message_description` def ON (m.`message_id` = def.`message_id` AND def.`language_id` = '" . (int)$default_language_id . "')
			WHERE m.`status` = '1'
			  AND (m.`date_start` IS NULL OR m.`date_start` <= '" . $now . "')
			  AND (m.`date_end` IS NULL OR m.`date_end` > '" . $now . "')
			  AND (" . $cur . " OR (def.`text` IS NOT NULL AND def.`text` <> ''))
			ORDER BY " . ((int)$schema >= 8 ? "m.`priority` DESC, " : "") . "m.`sort_order` ASC, m.`message_id` ASC
			LIMIT 500");

		return $query->rows;
	}

	/** Category IDs a product belongs to (for "product pages of products in a selected category"). */
	public function getProductCategories(int $product_id): array {
		$query = $this->db->query("SELECT `category_id` FROM `" . DB_PREFIX . "product_to_category` WHERE `product_id` = '" . (int)$product_id . "'");

		return array_map('intval', array_column($query->rows, 'category_id'));
	}

	/**
	 * Statistics: +1 view, click or copy for variant A or B. Only enabled messages inside their date window are
	 * counted (variant B only while its A/B test is on); counters stop at the INT UNSIGNED maximum.
	 * $schema < 7: only the 2.2.0 columns (views / clicks) exist.
	 */
	public function track(int $message_id, string $type, string $variant = 'a', int $schema = 7): void {
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

	/* ------------------------------------------------------------------ *
	 * 2.4.0
	 * ------------------------------------------------------------------ */

	/** +1 confirmed sign-up of a message (counter only, no address). */
	public function countSubscribe(int $message_id): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "abp_message` SET `subscribes` = IF(`subscribes` < 4294967295, `subscribes` + 1, `subscribes`) WHERE `message_id` = '" . (int)$message_id . "'");
	}

	/** Category IDs of several products (cart conditions). */
	public function getCategoriesOfProducts(array $product_ids): array {
		$ids = array_values(array_filter(array_map('intval', $product_ids)));

		if (!$ids) {
			return [];
		}

		$query = $this->db->query("SELECT DISTINCT `category_id` FROM `" . DB_PREFIX . "product_to_category` WHERE `product_id` IN (" . implode(',', $ids) . ")");

		return array_map('intval', array_column($query->rows, 'category_id'));
	}

	/** Country and zone of one address of the customer: array(country_id, zone_id), zeros when unknown. */
	public function getAddressCountry(int $address_id, int $customer_id): array {
		$query = $this->db->query("SELECT `country_id`, `zone_id` FROM `" . DB_PREFIX . "address` WHERE `address_id` = '" . (int)$address_id . "' AND `customer_id` = '" . (int)$customer_id . "'");

		return $query->num_rows ? [(int)$query->row['country_id'], (int)$query->row['zone_id']] : [0, 0];
	}

	/** Country ID by ISO 3166-1 alpha-2 code (CloudFlare CF-IPCountry), 0 when unknown. */
	public function getCountryIdByIso(string $iso): int {
		$query = $this->db->query("SELECT `country_id` FROM `" . DB_PREFIX . "country` WHERE `iso_code_2` = '" . $this->db->escape(strtoupper(substr((string)$iso, 0, 2))) . "' AND `status` = '1' LIMIT 1");

		return $query->num_rows ? (int)$query->row['country_id'] : 0;
	}

	/**
	 * Code of an OpenCart coupon that can be used right now (status on, inside its dates, total and per-customer
	 * uses left, login rule), else ''. Same rules as OpenCart's own coupon check.
	 */
	public function getUsableCoupon(int $coupon_id, int $customer_id): string {
		$query = $this->db->query("SELECT `coupon_id`, `code`, `logged`, `uses_total`, `uses_customer`, `date_start`, `date_end` FROM `" . DB_PREFIX . "coupon` WHERE `coupon_id` = '" . (int)$coupon_id . "' AND `status` = '1'");

		if (!$query->num_rows) {
			return '';
		}

		$coupon = $query->row;
		$today  = date('Y-m-d');
		$start  = substr((string)$coupon['date_start'], 0, 10);
		$end    = substr((string)$coupon['date_end'], 0, 10);

		if (($start !== '' && $start !== '0000-00-00' && $start > $today) || ($end !== '' && $end !== '0000-00-00' && $end < $today)) {
			return '';
		}

		if ((int)$coupon['logged'] && !$customer_id) {
			return '';
		}

		if ((int)$coupon['uses_total'] > 0) {
			$used = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "coupon_history` WHERE `coupon_id` = '" . (int)$coupon_id . "'");

			if ((int)$used->row['total'] >= (int)$coupon['uses_total']) {
				return '';
			}
		}

		if ((int)$coupon['uses_customer'] > 0 && $customer_id) {
			$used = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "coupon_history` WHERE `coupon_id` = '" . (int)$coupon_id . "' AND `customer_id` = '" . (int)$customer_id . "'");

			if ((int)$used->row['total'] >= (int)$coupon['uses_customer']) {
				return '';
			}
		}

		return strtoupper((string)$coupon['code']);
	}

	/**
	 * Rate limiter of the public endpoints: +1 in the current window of $bucket (already hashed, no raw IP is stored)
	 * and returns the number of hits in it. Old windows are removed now and then.
	 */
	public function hit(string $bucket, int $window): int {
		$now  = time();
		$key  = sha1($bucket . '|' . (int)floor($now / max(1, (int)$window)));

		$this->db->query("INSERT INTO `" . DB_PREFIX . "abp_rate` (`bucket`, `hits`, `expires`) VALUES ('" . $this->db->escape($key) . "', 1, '" . (int)($now + $window) . "') ON DUPLICATE KEY UPDATE `hits` = `hits` + 1");

		$query = $this->db->query("SELECT `hits` FROM `" . DB_PREFIX . "abp_rate` WHERE `bucket` = '" . $this->db->escape($key) . "'");

		if (mt_rand(1, 50) === 1) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "abp_rate` WHERE `expires` < '" . (int)$now . "'");
		}

		return $query->num_rows ? (int)$query->row['hits'] : 0;
	}

	/** Active message with the e-mail form switched on (the sign-up endpoint accepts nothing else). */
	public function getSignupMessage(int $message_id): array {
		$query = $this->db->query("SELECT `message_id`, `options`, `stores` FROM `" . DB_PREFIX . "abp_message` WHERE `message_id` = '" . (int)$message_id . "' AND `status` = '1' AND (`date_start` IS NULL OR `date_start` <= NOW()) AND (`date_end` IS NULL OR `date_end` > NOW())");

		return $query->row;
	}

	public function getSubscriber(string $email, int $store_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "abp_subscriber` WHERE `email` = '" . $this->db->escape((string)$email) . "' AND `store_id` = '" . (int)$store_id . "'");

		return $query->row;
	}

	public function getSubscriberByToken(string $column, string $hash): array {
		if (!in_array($column, ['token', 'unsubscribe'], true) || !preg_match('/^[0-9a-f]{64}$/', (string)$hash)) {
			return [];
		}

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "abp_subscriber` WHERE `" . $column . "` = '" . $this->db->escape($hash) . "' LIMIT 1");

		return $query->row;
	}

	/**
	 * New or renewed sign-up (status 0 = waiting for confirmation, 1 = confirmed right away). Only hashes of the tokens
	 * are stored. Returns the subscriber_id.
	 */
	public function saveSubscriber(array $data): int {
		$fields = "`email` = '" . $this->db->escape((string)$data['email']) . "', `store_id` = '" . (int)$data['store_id'] . "', `language_id` = '" . (int)$data['language_id'] . "', `message_id` = '" . (int)$data['message_id'] . "', `status` = '" . ((int)$data['status'] === 1 ? 1 : 0) . "', `token` = '" . $this->db->escape((string)$data['token']) . "', `unsubscribe` = '" . $this->db->escape((string)$data['unsubscribe']) . "', `consent` = '" . $this->db->escape((string)$data['consent']) . "', `date_added` = NOW(), `date_confirmed` = " . ((int)$data['status'] === 1 ? 'NOW()' : 'NULL');

		$this->db->query("INSERT INTO `" . DB_PREFIX . "abp_subscriber` SET " . $fields . " ON DUPLICATE KEY UPDATE " . $fields);

		$row = $this->getSubscriber((string)$data['email'], (int)$data['store_id']);

		return $row ? (int)$row['subscriber_id'] : 0;
	}

	/** Confirmation (double opt-in): status 1, token used up. */
	public function confirmSubscriber(int $subscriber_id): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "abp_subscriber` SET `status` = '1', `token` = '', `date_confirmed` = NOW() WHERE `subscriber_id` = '" . (int)$subscriber_id . "'");
	}

	public function unsubscribe(int $subscriber_id): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "abp_subscriber` SET `status` = '2', `token` = '' WHERE `subscriber_id` = '" . (int)$subscriber_id . "'");
	}

	/** Customer accounts with this e-mail: newsletter flag on (subscribe) or off (unsubscribe). Returns how many. */
	public function setCustomerNewsletter(string $email, bool $on): int {
		$query = $this->db->query("SELECT `customer_id` FROM `" . DB_PREFIX . "customer` WHERE LCASE(`email`) = '" . $this->db->escape(strtolower((string)$email)) . "'");

		foreach ($query->rows as $row) {
			$this->db->query("UPDATE `" . DB_PREFIX . "customer` SET `newsletter` = '" . ($on ? 1 : 0) . "' WHERE `customer_id` = '" . (int)$row['customer_id'] . "'");
		}

		return (int)$query->num_rows;
	}
}
