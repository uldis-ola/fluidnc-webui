<?php
/**
 * OpenCart 3D Product Viewer — catalog model
 */
class ModelExtensionModuleProduct3d extends Model {
	public function getProduct3d($product_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_3d` WHERE `product_id` = '" . (int)$product_id . "' LIMIT 1");

		return $query->row;
	}
}
