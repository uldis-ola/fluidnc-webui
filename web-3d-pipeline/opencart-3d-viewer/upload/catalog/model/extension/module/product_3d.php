<?php
/**
 * OpenCart 3D Product Viewer — catalog model
 */
class ModelExtensionModuleProduct3d extends Model {
	public function getProduct3d($product_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_3d` WHERE `product_id` = '" . (int)$product_id . "' LIMIT 1");

		return $query->row;
	}

	/**
	 * Produkta attēlu failu nosaukumi bez paplašinājuma (piem. "apple_cinema_30").
	 * product_3d.js pēc tiem atrod galeriju jebkurā tēmā (arī ar citiem izmēriem, webp, lazy-load).
	 */
	public function getImageKeys($product_id) {
		$paths = array();

		$query = $this->db->query("SELECT `image` FROM `" . DB_PREFIX . "product` WHERE `product_id` = '" . (int)$product_id . "'");

		if (!empty($query->row['image'])) {
			$paths[] = $query->row['image'];
		}

		$query = $this->db->query("SELECT `image` FROM `" . DB_PREFIX . "product_image` WHERE `product_id` = '" . (int)$product_id . "' ORDER BY `sort_order` LIMIT 50");

		foreach ($query->rows as $row) {
			if (!empty($row['image'])) {
				$paths[] = $row['image'];
			}
		}

		$keys = array();

		foreach ($paths as $path) {
			$key = strtolower(pathinfo(str_replace('\\', '/', $path), PATHINFO_FILENAME));

			// pārāk īsi / vispārīgi nosaukumi var sakrist ar citiem attēliem
			if (strlen($key) >= 3 && !in_array($key, array('image', 'img', 'photo', 'placeholder', 'no_image'))) {
				$keys[$key] = $key;
			}
		}

		return array_values($keys);
	}
}
