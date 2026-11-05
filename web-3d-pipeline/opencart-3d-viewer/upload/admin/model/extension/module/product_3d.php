<?php
/**
 * OpenCart 3D Product Viewer — admin model
 * Tabula: DB_PREFIX . 'product_3d' (viens ieraksts uz produktu).
 */
class ModelExtensionModuleProduct3d extends Model {
	public function createTable() {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "product_3d` (
			`product_id` INT(11) NOT NULL,
			`model_3d` VARCHAR(255) NOT NULL DEFAULT '',
			`ios_3d` VARCHAR(255) NOT NULL DEFAULT '',
			`poster_3d` VARCHAR(255) NOT NULL DEFAULT '',
			`placement` VARCHAR(16) NOT NULL DEFAULT '',
			`camera_orbit` VARCHAR(64) NOT NULL DEFAULT '',
			`auto_rotate` VARCHAR(1) NOT NULL DEFAULT '',
			`date_modified` DATETIME NOT NULL,
			PRIMARY KEY (`product_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
	}

	public function dropTable() {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "product_3d`");
	}

	public function getProduct3d($product_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_3d` WHERE `product_id` = '" . (int)$product_id . "' LIMIT 1");

		return $query->row;
	}

	public function saveProduct3d($product_id, array $data) {
		$product_id = (int)$product_id;

		if ($product_id <= 0) {
			return;
		}

		if ($data['model_3d'] === '') {
			$this->deleteProduct3d($product_id);

			return;
		}

		$this->db->query("REPLACE INTO `" . DB_PREFIX . "product_3d` SET
			`product_id` = '" . $product_id . "',
			`model_3d` = '" . $this->db->escape($data['model_3d']) . "',
			`ios_3d` = '" . $this->db->escape($data['ios_3d']) . "',
			`poster_3d` = '" . $this->db->escape($data['poster_3d']) . "',
			`placement` = '" . $this->db->escape($data['placement']) . "',
			`camera_orbit` = '" . $this->db->escape($data['camera_orbit']) . "',
			`auto_rotate` = '" . $this->db->escape($data['auto_rotate']) . "',
			`date_modified` = NOW()");
	}

	public function deleteProduct3d($product_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "product_3d` WHERE `product_id` = '" . (int)$product_id . "'");
	}

	public function getTotalProducts3d() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "product_3d`");

		return (int)$query->row['total'];
	}
}
