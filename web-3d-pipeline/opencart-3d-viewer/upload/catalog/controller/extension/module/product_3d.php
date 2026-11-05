<?php
/**
 * OpenCart 3D Product Viewer v1.0.0 — catalog
 *
 * Notikums: catalog/view/product/product/before -> eventProductView
 * Tēmas failus nemaina: skatītāja HTML + skripti tiek pievienoti pirms {{ footer }},
 * un product_3d.js to novieto pie galerijas (CSS selektors iestatījumos).
 * Tēmām pieejami mainīgie: {{ model_3d }} (URL), {{ product_3d }} (masīvs), {{ product_3d_html }}.
 */
class ControllerExtensionModuleProduct3d extends Controller {
	const VERSION = '1.0.0';

	public function eventProductView(&$route, &$data, &$code) {
		if (!$this->config->get('module_product_3d_status') || empty($data['product_id'])) {
			return null;
		}

		$this->load->model('extension/module/product_3d');

		$row = $this->model_extension_module_product_3d->getProduct3d((int)$data['product_id']);

		if (!$row || $row['model_3d'] === '' || !is_file(DIR_IMAGE . $row['model_3d'])) {
			return null;
		}

		$placement = $row['placement'] !== '' ? $row['placement'] : (string)$this->config->get('module_product_3d_placement');

		if ($placement === 'disabled') {
			return null;
		}

		if (!in_array($placement, array('replace', 'before', 'after', 'manual'))) {
			$placement = 'replace';
		}

		$server = $this->request->server['HTTPS'] ? $this->config->get('config_ssl') : $this->config->get('config_url');
		$image_base = $server . 'image/';
		$js_base = $server . 'catalog/view/javascript/product_3d/';

		$poster = '';

		if ($row['poster_3d'] !== '' && is_file(DIR_IMAGE . $row['poster_3d'])) {
			$poster = $image_base . $this->encodePath($row['poster_3d']);
		} elseif ($this->config->get('module_product_3d_poster_fallback') && !empty($data['popup'])) {
			$poster = $data['popup'];
		}

		$auto_rotate = $row['auto_rotate'] !== '' ? (bool)$row['auto_rotate'] : (bool)$this->config->get('module_product_3d_auto_rotate');
		$orbit = $row['camera_orbit'] !== '' ? $row['camera_orbit'] : (string)$this->config->get('module_product_3d_camera_orbit');

		$this->language->load('extension/module/product_3d', 'product_3d_lang');
		$lang = $this->language->get('product_3d_lang');

		$viewer = array(
			'src'             => $image_base . $this->encodePath($row['model_3d']),
			'ios_src'         => ($row['ios_3d'] !== '' && is_file(DIR_IMAGE . $row['ios_3d'])) ? $image_base . $this->encodePath($row['ios_3d']) : '',
			'poster'          => $poster,
			'alt'             => isset($data['heading_title']) ? strip_tags(html_entity_decode($data['heading_title'], ENT_QUOTES, 'UTF-8')) : '',
			'placement'       => $placement,
			'selector'        => (string)$this->config->get('module_product_3d_selector'),
			'height'          => max(200, (int)$this->config->get('module_product_3d_height')),
			'bg_color'        => (string)$this->config->get('module_product_3d_bg_color'),
			'ar'              => (bool)$this->config->get('module_product_3d_ar'),
			'auto_rotate'     => $auto_rotate,
			'camera_controls' => (bool)$this->config->get('module_product_3d_camera_controls'),
			'shadow'          => (string)(float)$this->config->get('module_product_3d_shadow'),
			'exposure'        => (string)(float)$this->config->get('module_product_3d_exposure'),
			'camera_orbit'    => $orbit,
			'text_ar'         => $lang->get('text_ar'),
			'text_hint'       => $lang->get('text_hint'),
			'text_title'      => $lang->get('text_title')
		);

		$html = $this->load->view('extension/module/product_3d', array('viewer' => $viewer));

		$assets = array(
			'source'   => $this->config->get('module_product_3d_source') === 'cdn' ? 'cdn' : 'local',
			'js_base'  => $js_base,
			'version'  => self::VERSION
		);

		$scripts = $this->load->view('extension/module/product_3d_assets', $assets);

		$data['model_3d'] = $viewer['src'];
		$data['product_3d'] = $viewer;

		if ($placement === 'manual') {
			// tēma pati izvada {{ product_3d_html }} vajadzīgajā vietā
			$data['product_3d_html'] = $html;
			$data['footer'] = $scripts . (isset($data['footer']) ? $data['footer'] : '');
		} else {
			$data['product_3d_html'] = '';
			$data['footer'] = $html . $scripts . (isset($data['footer']) ? $data['footer'] : '');
		}

		return null;
	}

	private function encodePath($path) {
		return implode('/', array_map('rawurlencode', explode('/', $path)));
	}
}
