<?php
/**
 * OpenCart 3D Product Viewer v1.1.0 — catalog
 *
 * Notikumi (tēmas failus nemaina):
 *   catalog/view/product/product/before -> eventProductViewBefore: mainīgie tēmām
 *       {{ model_3d }} (URL), {{ product_3d }} (masīvs), {{ product_3d_html }} (manuālam novietojumam)
 *   catalog/view/product/product/after  -> eventProductViewAfter: skatītāja HTML + skripti tiek
 *       ievietoti gatavajā lapā pirms </body> (strādā arī tēmām, kas neizvada {{ footer }}).
 * Novietojumu nosaka product_3d.js: CSS selektors no iestatījumiem vai automātiska galerijas
 * atrašana pēc šī produkta attēlu failu nosaukumiem (neatkarīgi no tēmas marķējuma).
 */
class ControllerExtensionModuleProduct3d extends Controller {
	const VERSION = '1.2.1';

	/** Viena pieprasījuma kešs (before + after notikumi) */
	private static $cache = array();

	public function eventProductViewBefore(&$route = null, &$data = null, &$code = null) {
		if (!is_array($data)) {
			return null;
		}

		$viewer = $this->buildViewer($data);

		if ($viewer) {
			$data['model_3d'] = $viewer['src'];
			$data['product_3d'] = $viewer;
			$data['product_3d_html'] = $viewer['placement'] === 'manual' ? $viewer['html'] : '';
		}

		return null;
	}

	public function eventProductViewAfter(&$route = null, &$data = null, &$output = null) {
		if (!is_array($data)) {
			return null;
		}

		$viewer = $this->buildViewer($data);

		if (!$viewer || !is_string($output) || strpos($output, 'data-product-3d-assets') !== false) {
			return null;
		}

		// manuālā režīmā tēma pati izvada {{ product_3d_html }}; ja tā to neizdara, ievietojam automātiski
		$needs_html = $viewer['placement'] !== 'manual' || strpos($output, 'id="product-3d"') === false;
		$inject = ($needs_html ? $viewer['html'] : '') . $viewer['assets'];

		$pos = strripos($output, '</body>');

		if ($pos !== false) {
			$output = substr($output, 0, $pos) . $inject . substr($output, $pos);
		} else {
			$output .= $inject;
		}

		return null;
	}

	/** Saderība ar 1.0.x notikumu (ja modulis atjaunināts bez Uninstall/Install) */
	public function eventProductView(&$route = null, &$data = null, &$code = null) {
		if (!is_array($data)) {
			return null;
		}

		$this->eventProductViewBefore($route, $data, $code);

		$viewer = $this->buildViewer($data);

		if ($viewer) {
			$html = $viewer['placement'] === 'manual' ? '' : $viewer['html'];
			$data['footer'] = $html . $viewer['assets'] . (isset($data['footer']) ? $data['footer'] : '');
		}

		return null;
	}

	private function buildViewer(array $data) {
		if (!$this->config->get('module_product_3d_status') || empty($data['product_id'])) {
			return null;
		}

		$product_id = (int)$data['product_id'];

		if (array_key_exists($product_id, self::$cache)) {
			return self::$cache[$product_id];
		}

		self::$cache[$product_id] = null;

		$this->load->model('extension/module/product_3d');

		$row = $this->model_extension_module_product_3d->getProduct3d($product_id);

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

		// tikai ceļš (bez protokola un domēna)
		$server = $this->request->server['HTTPS'] ? $this->config->get('config_ssl') : $this->config->get('config_url');
		$path = trim((string)parse_url((string)$server, PHP_URL_PATH), '/');
		$server = '/' . ($path !== '' ? $path . '/' : '');
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

		$lang = $this->loadTexts();

		$viewer = array(
			'src'             => $image_base . $this->encodePath($row['model_3d']),
			'ios_src'         => ($row['ios_3d'] !== '' && is_file(DIR_IMAGE . $row['ios_3d'])) ? $image_base . $this->encodePath($row['ios_3d']) : '',
			'poster'          => $poster,
			'alt'             => isset($data['heading_title']) ? strip_tags(html_entity_decode($data['heading_title'], ENT_QUOTES, 'UTF-8')) : '',
			'placement'       => $placement,
			'selector'        => trim((string)$this->config->get('module_product_3d_selector')),
			'image_keys'      => implode('|', $this->model_extension_module_product_3d->getImageKeys($product_id)),
			'height'          => max(200, (int)$this->config->get('module_product_3d_height')),
			'bg_color'        => (string)$this->config->get('module_product_3d_bg_color'),
			'ar'              => (bool)$this->config->get('module_product_3d_ar'),
			'auto_rotate'     => $auto_rotate,
			'camera_controls' => (bool)$this->config->get('module_product_3d_camera_controls'),
			'shadow'          => (string)(float)$this->config->get('module_product_3d_shadow'),
			'exposure'        => (string)(float)$this->config->get('module_product_3d_exposure'),
			'camera_orbit'    => $orbit,
			'text_ar'         => $lang['text_ar'],
			'text_hint'       => $lang['text_hint'],
			'text_title'      => $lang['text_title']
		);

		$viewer['html'] = $this->load->view('extension/module/product_3d', array('viewer' => $viewer));
		$viewer['assets'] = $this->load->view('extension/module/product_3d_assets', array(
			'source'  => $this->config->get('module_product_3d_source') === 'cdn' ? 'cdn' : 'local',
			'js_base' => $js_base,
			'version' => self::VERSION
		));

		self::$cache[$product_id] = $viewer;

		return $viewer;
	}

	/**
	 * Teksti pēc veikala valodas. Ja precīzas mapes nav (piem. kods "lv" vai "lv-LV", bet fails ir lv-lv),
	 * meklē pēc pirmajiem 2 burtiem; citādi angļu.
	 */
	private function loadTexts() {
		$defaults = array(
			'text_ar'    => 'View in your space (AR)',
			'text_hint'  => 'Drag to rotate, scroll or pinch to zoom',
			'text_title' => '3D view'
		);

		$code = isset($this->session->data['language']) ? (string)$this->session->data['language'] : (string)$this->config->get('config_language');
		$code = preg_replace('/[^a-zA-Z0-9_\-]/', '', $code);
		$file = 'extension/module/product_3d.php';
		$candidates = array();

		if ($code !== '') {
			$candidates[] = DIR_LANGUAGE . $code . '/' . $file;
			$candidates[] = DIR_LANGUAGE . strtolower($code) . '/' . $file;

			$prefix = strtolower(substr($code, 0, 2));

			foreach ((array)glob(DIR_LANGUAGE . $prefix . '*/' . $file) as $match) {
				$candidates[] = $match;
			}
		}

		$candidates[] = DIR_LANGUAGE . 'en-gb/' . $file;

		foreach ($candidates as $path) {
			if ($path && is_file($path)) {
				$_ = array();
				require($path);

				return array_merge($defaults, array_intersect_key($_, $defaults));
			}
		}

		return $defaults;
	}

	private function encodePath($path) {
		return implode('/', array_map('rawurlencode', explode('/', $path)));
	}
}
