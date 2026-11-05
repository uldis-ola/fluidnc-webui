<?php
/**
 * OpenCart 3D Product Viewer v1.0.0 — admin controller
 *
 * - Iestatījumu lapa (Extensions > Modules > 3D Product Viewer)
 * - Notikumi (bez kodola failu labošanas):
 *     admin/view/catalog/product_form/before        -> cilne "3D modelis" produkta formā
 *     admin/model/catalog/product/addProduct/after  -> saglabā 3D datus
 *     admin/model/catalog/product/editProduct/after -> saglabā 3D datus
 *     admin/model/catalog/product/deleteProduct/after -> dzēš 3D ierakstu (failus atstāj)
 *     admin/view/common/column_left/before          -> izvēlnes ieraksts sadaļā Catalog
 *     catalog/view/product/product/before           -> skatītājs produkta lapā
 * - AJAX: upload (augšupielāde ar validāciju), files (esošo failu saraksts)
 */
class ControllerExtensionModuleProduct3d extends Controller {
	const VERSION = '1.0.1';
	const EVENT_CODE = 'product_3d';

	private $error = array();

	/** Atļautie failu tipi pēc lauka */
	private $types = array(
		'model'  => array('glb', 'gltf'),
		'ios'    => array('usdz'),
		'poster' => array('jpg', 'jpeg', 'png', 'webp')
	);

	private $placements = array('replace', 'before', 'after', 'manual');

	/* ================================================================ iestatījumi */

	public function index() {
		$this->load->language('extension/module/product_3d');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_product_3d', $this->sanitizeSettings($this->request->post));

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/product_3d', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/product_3d', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		$source = ($this->request->server['REQUEST_METHOD'] == 'POST') ? $this->sanitizeSettings($this->request->post) : array();

		foreach ($this->getDefaults() as $key => $default) {
			$full = 'module_product_3d_' . $key;

			if (isset($source[$full])) {
				$data[$full] = $source[$full];
			} elseif ($this->config->has($full)) {
				$data[$full] = $this->config->get($full);
			} else {
				$data[$full] = $default;
			}
		}

		$this->load->model('extension/module/product_3d');

		$data['total_products'] = $this->model_extension_module_product_3d->getTotalProducts3d();
		$data['version'] = self::VERSION;
		$data['upload_limit'] = $this->getServerUploadLimitMb();
		$data['local_assets'] = is_file(DIR_CATALOG . 'view/javascript/product_3d/model-viewer.min.js');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/product_3d', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/product_3d')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	private function getDefaults() {
		return array(
			'status'          => 1,
			'placement'       => 'replace',
			'selector'        => '.thumbnails',
			'height'          => 480,
			'bg_color'        => '#f5f6f8',
			'ar'              => 1,
			'auto_rotate'     => 1,
			'camera_controls' => 1,
			'shadow'          => '1',
			'exposure'        => '1',
			'camera_orbit'    => '',
			'poster_fallback' => 1,
			'source'          => 'local',
			'folder'          => 'catalog/3d/',
			'max_size'        => 30,
			'delete_data'     => 0
		);
	}

	private function sanitizeSettings($post) {
		$d = $this->getDefaults();
		$get = function ($key) use ($post, $d) {
			return isset($post['module_product_3d_' . $key]) ? $post['module_product_3d_' . $key] : $d[$key];
		};

		$placement = (string)$get('placement');
		$bg = (string)$get('bg_color');
		$folder = trim(str_replace('\\', '/', (string)$get('folder')), '/') . '/';
		$orbit = (string)$get('camera_orbit');
		// OpenCart Request klase POST datus apstrādā ar htmlspecialchars ('>' -> '&gt;'); selektoram vajag oriģinālu
		$selector = trim(html_entity_decode((string)$get('selector'), ENT_QUOTES, 'UTF-8'));

		return array(
			'module_product_3d_status'          => (int)!empty($post['module_product_3d_status']),
			'module_product_3d_placement'       => in_array($placement, $this->placements) ? $placement : $d['placement'],
			'module_product_3d_selector'        => ($selector !== '' && strlen($selector) <= 200 && strpos($selector, '<') === false) ? $selector : $d['selector'],
			'module_product_3d_height'          => max(200, min(1200, (int)$get('height'))),
			'module_product_3d_bg_color'        => preg_match('/^(#[0-9a-fA-F]{3,8}|transparent)$/', $bg) ? $bg : $d['bg_color'],
			'module_product_3d_ar'              => (int)!empty($post['module_product_3d_ar']),
			'module_product_3d_auto_rotate'     => (int)!empty($post['module_product_3d_auto_rotate']),
			'module_product_3d_camera_controls' => (int)!empty($post['module_product_3d_camera_controls']),
			'module_product_3d_shadow'          => (string)max(0, min(2, round((float)$get('shadow'), 2))),
			'module_product_3d_exposure'        => (string)max(0.1, min(3, round((float)$get('exposure'), 2))),
			'module_product_3d_camera_orbit'    => $this->cleanOrbit($orbit),
			'module_product_3d_poster_fallback' => (int)!empty($post['module_product_3d_poster_fallback']),
			'module_product_3d_source'          => ($get('source') === 'cdn') ? 'cdn' : 'local',
			'module_product_3d_folder'          => preg_match('#^catalog/[A-Za-z0-9_\-/]+/$#', $folder) && strpos($folder, '..') === false ? $folder : $d['folder'],
			'module_product_3d_max_size'        => max(1, min(500, (int)$get('max_size'))),
			'module_product_3d_delete_data'     => (int)!empty($post['module_product_3d_delete_data'])
		);
	}

	/* ================================================================ install / uninstall */

	/**
	 * Izsauc core Extensions > Modules (tas pārbauda tiesības). Tieša izsaukšana pa maršrutu
	 * ir pieejama ar `access` tiesībām, tāpēc pārbaudām `modify` uz extension/extension/module
	 * (moduļa paša tiesības pirmās instalēšanas pieprasījumā vēl nav ielādētas).
	 */
	private function canManageExtensions() {
		return $this->user->hasPermission('modify', 'extension/extension/module');
	}

	public function install() {
		if (!$this->canManageExtensions()) {
			return;
		}

		$this->load->model('extension/module/product_3d');
		$this->load->model('setting/event');
		$this->load->model('setting/setting');

		$this->model_extension_module_product_3d->createTable();

		// idempotenti: vispirms noņem vecos notikumus ar šo kodu
		$this->model_setting_event->deleteEventByCode(self::EVENT_CODE);

		$events = array(
			'admin/view/catalog/product_form/before'          => 'extension/module/product_3d/eventAdminForm',
			'admin/model/catalog/product/addProduct/after'    => 'extension/module/product_3d/eventAdminAdd',
			'admin/model/catalog/product/editProduct/after'   => 'extension/module/product_3d/eventAdminEdit',
			'admin/model/catalog/product/deleteProduct/after' => 'extension/module/product_3d/eventAdminDelete',
			'admin/model/catalog/product/copyProduct/after'   => 'extension/module/product_3d/eventAdminCopy',
			'admin/view/common/column_left/before'            => 'extension/module/product_3d/eventAdminMenu',
			'catalog/view/product/product/before'             => 'extension/module/product_3d/eventProductView'
		);

		foreach ($events as $trigger => $action) {
			$this->model_setting_event->addEvent(self::EVENT_CODE, $trigger, $action, 1, 0);
		}

		// noklusējuma iestatījumi (ja vēl nav saglabāti)
		if (!$this->model_setting_setting->getSetting('module_product_3d')) {
			$settings = array();

			foreach ($this->getDefaults() as $key => $value) {
				$settings['module_product_3d_' . $key] = $value;
			}

			$this->model_setting_setting->editSetting('module_product_3d', $settings);
		}

		$this->ensureFolder($this->config->get('module_product_3d_folder') ? $this->config->get('module_product_3d_folder') : 'catalog/3d/');
	}

	public function uninstall() {
		if (!$this->canManageExtensions()) {
			return;
		}

		$this->load->model('setting/event');

		$this->model_setting_event->deleteEventByCode(self::EVENT_CODE);

		// Iestatījumus OpenCart jau izdzēsis; $this->config vēl satur šī pieprasījuma vērtības.
		// Dati (produktu saites uz modeļiem) paliek, ja vien iestatījumos nav ieslēgts "Dzēst datus".
		// Augšupielādētie faili image/ mapē netiek dzēsti nekad.
		if ($this->config->get('module_product_3d_delete_data')) {
			$this->load->model('extension/module/product_3d');
			$this->model_extension_module_product_3d->dropTable();
		}
	}

	/* ================================================================ notikumi */

	/** Cilne "3D modelis" produkta rediģēšanas formā */
	public function eventAdminForm(&$route, &$data, &$code) {
		if (!$this->config->get('module_product_3d_status') || !isset($data['footer'])) {
			return null;
		}

		$lang = $this->isolatedLanguage();

		$this->load->model('extension/module/product_3d');

		$product_id = isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0;

		$row = array();

		if (isset($this->request->post['product_3d']) && is_array($this->request->post['product_3d'])) {
			$row = $this->cleanProductInput($this->request->post['product_3d']);
		} elseif ($product_id) {
			$row = $this->model_extension_module_product_3d->getProduct3d($product_id);
		}

		$view = array();

		foreach (array('model_3d', 'ios_3d', 'poster_3d', 'placement', 'camera_orbit', 'auto_rotate') as $key) {
			$view[$key] = isset($row[$key]) ? (string)$row[$key] : '';
		}

		foreach ($lang as $key => $value) {
			if (strpos($key, 'p3d_') === 0) {
				$view[$key] = $value;
			}
		}

		$catalog = $this->catalogPath();

		$view['image_base'] = $catalog . 'image/';
		$view['js_base'] = $catalog . 'catalog/view/javascript/product_3d/';
		$view['version'] = self::VERSION;
		$view['source'] = $this->config->get('module_product_3d_source');
		$view['max_size'] = min((int)$this->config->get('module_product_3d_max_size'), $this->getServerUploadLimitMb());
		$view['upload_url'] = str_replace('&amp;', '&', $this->url->link('extension/module/product_3d/upload', 'user_token=' . $this->session->data['user_token'], true));
		$view['files_url'] = str_replace('&amp;', '&', $this->url->link('extension/module/product_3d/files', 'user_token=' . $this->session->data['user_token'], true));
		$view['settings_url'] = $this->url->link('extension/module/product_3d', 'user_token=' . $this->session->data['user_token'], true);
		$view['can_modify'] = $this->user->hasPermission('modify', 'extension/module/product_3d');
		$view['placements'] = $this->placements;

		$data['footer'] = $this->load->view('extension/module/product_3d_tab', $view) . $data['footer'];

		return null;
	}

	public function eventAdminAdd(&$route, &$args, &$output) {
		if (isset($args[0]['product_3d']) && is_array($args[0]['product_3d'])) {
			$this->saveFromForm((int)$output, $args[0]['product_3d']);
		}
	}

	public function eventAdminEdit(&$route, &$args, &$output) {
		if (isset($args[1]['product_3d']) && is_array($args[1]['product_3d'])) {
			$this->saveFromForm((int)$args[0], $args[1]['product_3d']);
		}
	}

	public function eventAdminDelete(&$route, &$args, &$output) {
		$this->load->model('extension/module/product_3d');
		$this->model_extension_module_product_3d->deleteProduct3d((int)$args[0]);
	}

	/** Produkta kopēšana: core copyProduct() izsauc addProduct() iekšēji (bez notikuma), tāpēc kopējam šeit */
	public function eventAdminCopy(&$route, &$args, &$output) {
		$source_id = isset($args[0]) ? (int)$args[0] : 0;

		if ($source_id <= 0) {
			return;
		}

		$this->load->model('extension/module/product_3d');
		$this->model_extension_module_product_3d->copyProduct3d($source_id);
	}

	/** Ieraksts kreisajā izvēlnē: Catalog > 3D Product Viewer */
	public function eventAdminMenu(&$route, &$data, &$code) {
		if (!isset($data['menus']) || !$this->user->hasPermission('access', 'extension/module/product_3d')) {
			return null;
		}

		$lang = $this->isolatedLanguage();

		foreach ($data['menus'] as &$menu) {
			if (isset($menu['id']) && $menu['id'] == 'menu-catalog') {
				$menu['children'][] = array(
					'name'     => $lang['p3d_menu'],
					'href'     => $this->url->link('extension/module/product_3d', 'user_token=' . $this->session->data['user_token'], true),
					'children' => array()
				);
				break;
			}
		}
		unset($menu);

		return null;
	}

	private function saveFromForm($product_id, array $input) {
		if ($product_id <= 0 || !$this->user->hasPermission('modify', 'extension/module/product_3d')) {
			return;
		}

		$this->load->model('extension/module/product_3d');

		$clean = $this->cleanProductInput($input);
		$current = $this->model_extension_module_product_3d->getProduct3d($product_id);

		// Ja lauks bija aizpildīts, bet ceļš nav derīgs (fails uz brīdi nav, pilns URL u.c.),
		// neko neizdzēšam klusām: paturam iepriekšējo vērtību un ierakstām žurnālā.
		foreach (array('model_3d', 'ios_3d', 'poster_3d') as $key) {
			$submitted = isset($input[$key]) ? trim((string)$input[$key]) : '';

			if ($submitted !== '' && $clean[$key] === '') {
				$clean[$key] = isset($current[$key]) ? $current[$key] : '';
				$this->log->write('[product_3d] product_id=' . $product_id . ': ignored invalid ' . $key . ' path "' . substr($submitted, 0, 200) . '"');
			}
		}

		$this->model_extension_module_product_3d->saveProduct3d($product_id, $clean);
	}

	private function cleanProductInput(array $input) {
		$placement = isset($input['placement']) ? (string)$input['placement'] : '';
		$auto = isset($input['auto_rotate']) ? (string)$input['auto_rotate'] : '';

		return array(
			'model_3d'     => $this->cleanPath(isset($input['model_3d']) ? $input['model_3d'] : '', 'model'),
			'ios_3d'       => $this->cleanPath(isset($input['ios_3d']) ? $input['ios_3d'] : '', 'ios'),
			'poster_3d'    => $this->cleanPath(isset($input['poster_3d']) ? $input['poster_3d'] : '', 'poster'),
			'placement'    => in_array($placement, array_merge($this->placements, array('disabled'))) ? $placement : '',
			'camera_orbit' => $this->cleanOrbit(isset($input['camera_orbit']) ? $input['camera_orbit'] : ''),
			'auto_rotate'  => in_array($auto, array('0', '1'), true) ? $auto : ''
		);
	}

	/** Ceļš relatīvi pret image/; tikai esoši faili ar atļautu paplašinājumu image/ mapē */
	private function cleanPath($path, $type) {
		$path = trim(str_replace('\\', '/', html_entity_decode((string)$path, ENT_QUOTES, 'UTF-8')));
		// pieņem arī pilnu URL vai ceļu ar image/ (arī veikalam apakšmapē: .../shop/image/...)
		$path = preg_replace('#^https?://[^/]+#i', '', $path);
		$pos = strpos($path, '/image/');

		if ($pos !== false) {
			$path = substr($path, $pos + 7);
		}

		$path = preg_replace('#^image/#', '', ltrim($path, '/'));

		if ($path === '' || strpos($path, '..') !== false || strpos($path, "\0") !== false || strlen($path) > 255) {
			return '';
		}

		$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

		if (!in_array($ext, $this->types[$type])) {
			return '';
		}

		$real = realpath(DIR_IMAGE . $path);
		$root = realpath(DIR_IMAGE);

		if (!$real || !$root || strpos(str_replace('\\', '/', $real), str_replace('\\', '/', $root) . '/') !== 0 || !is_file($real)) {
			return '';
		}

		return $path;
	}

	private function cleanOrbit($orbit) {
		$orbit = trim((string)$orbit);

		return preg_match('/^[0-9a-z.%\- ]{0,64}$/i', $orbit) ? $orbit : '';
	}

	/* ================================================================ AJAX */

	/** Augšupielāde: POST file + type (model|ios|poster) */
	public function upload() {
		$this->load->language('extension/module/product_3d');

		$json = array();

		if (!$this->user->hasPermission('modify', 'extension/module/product_3d')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$type = isset($this->request->get['type']) ? (string)$this->request->get['type'] : '';

		if (!$json && !isset($this->types[$type])) {
			$json['error'] = $this->language->get('error_type');
		}

		if (!$json && ($this->request->server['REQUEST_METHOD'] != 'POST' || empty($this->request->files['file']['name']) || !is_file($this->request->files['file']['tmp_name']))) {
			$code = isset($this->request->files['file']['error']) ? (int)$this->request->files['file']['error'] : 0;
			$json['error'] = ($code == UPLOAD_ERR_INI_SIZE || $code == UPLOAD_ERR_FORM_SIZE)
				? sprintf($this->language->get('error_size'), $this->getServerUploadLimitMb())
				: $this->language->get('error_upload');
		}

		if (!$json) {
			$file = $this->request->files['file'];
			$name = html_entity_decode($file['name'], ENT_QUOTES, 'UTF-8');
			$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
			$max_mb = min((int)$this->config->get('module_product_3d_max_size'), $this->getServerUploadLimitMb());

			if (!in_array($ext, $this->types[$type])) {
				$json['error'] = sprintf($this->language->get('error_extension'), implode(', ', $this->types[$type]));
			} elseif ($file['error'] != UPLOAD_ERR_OK) {
				$json['error'] = $this->language->get('error_upload');
			} elseif ($file['size'] > $max_mb * 1024 * 1024) {
				$json['error'] = sprintf($this->language->get('error_size'), $max_mb);
			} else {
				$problem = $this->validateContent($file['tmp_name'], $ext);

				if ($problem) {
					$json['error'] = $this->language->get($problem);
				}
			}
		}

		if (!$json) {
			$folder = $this->config->get('module_product_3d_folder') ? $this->config->get('module_product_3d_folder') : 'catalog/3d/';

			if (!$this->ensureFolder($folder)) {
				$json['error'] = sprintf($this->language->get('error_folder'), 'image/' . $folder);
			}
		}

		if (!$json) {
			$base = $this->safeName(pathinfo($name, PATHINFO_FILENAME));
			$target = $folder . $base . '.' . $ext;
			$i = 1;

			// rezervē nosaukumu atomāri (fopen 'x'), lai paralēlas augšupielādes nepārrakstītu viena otru
			$handle = false;

			while ($i < 1000 && !($handle = @fopen(DIR_IMAGE . $target, 'x'))) {
				$target = $folder . $base . '-' . $i++ . '.' . $ext;
			}

			if ($handle) {
				fclose($handle);
			}

			if ($handle && move_uploaded_file($file['tmp_name'], DIR_IMAGE . $target)) {
				@chmod(DIR_IMAGE . $target, 0644);

				$catalog = $this->catalogPath();

				$json['path'] = $target;
				$json['url'] = $catalog . 'image/' . $this->encodePath($target);
				$json['size'] = (int)$file['size'];
				$json['success'] = $this->language->get('text_uploaded');
			} else {
				if ($handle && is_file(DIR_IMAGE . $target) && !filesize(DIR_IMAGE . $target)) {
					@unlink(DIR_IMAGE . $target); // tikai mūsu rezervētais tukšais fails
				}

				$json['error'] = $this->language->get('error_upload');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/** Esošo failu saraksts mapē (lai izvēlētos FTP augšupielādētos failus) */
	public function files() {
		$this->load->language('extension/module/product_3d');

		$json = array('files' => array());

		$type = isset($this->request->get['type']) ? (string)$this->request->get['type'] : '';

		if (!$this->user->hasPermission('access', 'extension/module/product_3d') || !isset($this->types[$type])) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$folder = $this->config->get('module_product_3d_folder') ? $this->config->get('module_product_3d_folder') : 'catalog/3d/';
			$dir = DIR_IMAGE . $folder;

			if (is_dir($dir)) {
				$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

				foreach ($iterator as $item) {
					if ($item->isFile() && in_array(strtolower($item->getExtension()), $this->types[$type])) {
						$json['files'][] = $folder . str_replace('\\', '/', substr($item->getPathname(), strlen($dir)));
					}

					if (count($json['files']) >= 1000) {
						break;
					}
				}

				sort($json['files']);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/* ================================================================ palīgfunkcijas */

	/**
	 * Moduļa teksti atsevišķā Language objektā, lai notikumos nepārrakstītu
	 * lapas globālos tekstus (heading_title, text_success u.c.).
	 */
	private function isolatedLanguage() {
		$this->language->load('extension/module/product_3d', 'product_3d_lang');

		return $this->language->get('product_3d_lang')->all();
	}

	/** Pārbauda faila saturu, ne tikai paplašinājumu. Atgriež valodas atslēgu vai ''. */
	private function validateContent($tmp, $ext) {
		$head = (string)file_get_contents($tmp, false, null, 0, 16);

		// skriptu marķieri visā failā (izņemot bināro .glb, kur tie var būt nejauši datos)
		if ($ext != 'glb' && $this->containsScript($tmp)) {
			return 'error_content';
		}

		switch ($ext) {
			case 'glb':
				if (strlen($head) < 12 || substr($head, 0, 4) !== 'glTF') {
					return 'error_glb';
				}

				$header = unpack('Vversion/Vlength', substr($head, 4, 8));

				if ($header['version'] != 2 || $header['length'] != filesize($tmp)) {
					return 'error_glb';
				}

				// pirmais chunk ir JSON - pārbaudām ārējās atsauces
				$chunk = (string)file_get_contents($tmp, false, null, 12, 8);
				$info = strlen($chunk) == 8 ? unpack('Vlength/Vtype', $chunk) : null;

				if (!$info || $info['type'] != 0x4E4F534A || $info['length'] > 64 * 1048576) {
					return 'error_glb';
				}

				$json = json_decode((string)file_get_contents($tmp, false, null, 20, $info['length']), true);

				if (!is_array($json)) {
					return 'error_glb';
				}

				return $this->hasExternalUri($json) ? 'error_gltf_external' : '';

			case 'gltf':
				$json = json_decode((string)file_get_contents($tmp), true);

				if (!is_array($json) || !isset($json['asset']['version']) || substr((string)$json['asset']['version'], 0, 1) !== '2') {
					return 'error_gltf';
				}

				// .gltf ar ārējiem .bin/.png failiem pārlūkā nestrādās - prasām pašpietiekamu failu
				return $this->hasExternalUri($json) ? 'error_gltf_external' : '';

			case 'usdz':
				if (substr($head, 0, 4) !== "PK\x03\x04") {
					return 'error_usdz';
				}

				// USDZ: pirmajam ZIP ierakstam jābūt USD failam
				$name_len = unpack('v', (string)file_get_contents($tmp, false, null, 26, 2));
				$first = $name_len ? (string)file_get_contents($tmp, false, null, 30, min(255, $name_len[1])) : '';

				return preg_match('/\.(usd|usda|usdc)$/i', $first) ? '' : 'error_usdz';

			default:
				$info = @getimagesize($tmp);
				$expected = array('jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp');

				// attēla tipam jāsakrīt ar paplašinājumu
				if (!$info || !isset($expected[$ext]) || $info['mime'] !== $expected[$ext]) {
					return 'error_image';
				}

				return '';
		}
	}

	private function containsScript($file) {
		$handle = @fopen($file, 'rb');

		if (!$handle) {
			return true;
		}

		$tail = '';
		$found = false;

		while (!feof($handle)) {
			$block = $tail . (string)fread($handle, 1048576);

			if (preg_match('/<\?php|<script/i', $block)) {
				$found = true;
				break;
			}

			$tail = substr($block, -16);
		}

		fclose($handle);

		return $found;
	}

	/** true, ja glTF JSON atsaucas uz ārējiem failiem/URL (atļauts tikai data: un bufferView) */
	private function hasExternalUri(array $json) {
		foreach (array('buffers', 'images') as $key) {
			if (isset($json[$key]) && is_array($json[$key])) {
				foreach ($json[$key] as $index => $item) {
					if (!isset($item['uri'])) {
						continue;
					}

					// GLB: buffers[0] bez uri ir BIN chunk; jebkurš cits uri, kas nav data:, ir ārējs
					if (strpos((string)$item['uri'], 'data:') !== 0) {
						return true;
					}
				}
			}
		}

		return false;
	}

	private function safeName($name) {
		$map = array(
			'ā' => 'a', 'č' => 'c', 'ē' => 'e', 'ģ' => 'g', 'ī' => 'i', 'ķ' => 'k', 'ļ' => 'l', 'ņ' => 'n',
			'š' => 's', 'ū' => 'u', 'ž' => 'z', 'Ā' => 'a', 'Č' => 'c', 'Ē' => 'e', 'Ģ' => 'g', 'Ī' => 'i',
			'Ķ' => 'k', 'Ļ' => 'l', 'Ņ' => 'n', 'Š' => 's', 'Ū' => 'u', 'Ž' => 'z'
		);
		$name = strtr($name, $map);

		if (function_exists('iconv')) {
			$converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);

			if ($converted !== false) {
				$name = $converted;
			}
		}

		$name = strtolower(preg_replace('/[^A-Za-z0-9_\-]+/', '-', $name));
		$name = trim(preg_replace('/-+/', '-', $name), '-_');

		return $name !== '' ? substr($name, 0, 80) : 'model-' . date('YmdHis');
	}

	/** Veikala saknes ceļš bez protokola un domēna (piem. "/" vai "/shop/") - nav CORS problēmu */
	private function catalogPath() {
		$url = $this->request->server['HTTPS'] ? HTTPS_CATALOG : HTTP_CATALOG;
		$path = (string)parse_url($url, PHP_URL_PATH);

		return '/' . trim($path, '/') . ($path !== '' && trim($path, '/') !== '' ? '/' : '');
	}

	private function encodePath($path) {
		return implode('/', array_map('rawurlencode', explode('/', $path)));
	}

	private function ensureFolder($folder) {
		$dir = DIR_IMAGE . $folder;

		if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
			return false;
		}

		// MIME tipi un PHP bloķēšana (Apache). Nginx gadījumā skat. README.
		$htaccess = $dir . '.htaccess';

		// raksta, ja nav vai ja to izveidojis šis modulis (atjaunināšana); svešu .htaccess neaiztiek
		if (!is_file($htaccess) || strpos((string)@file_get_contents($htaccess, false, null, 0, 64), '# OpenCart 3D Product Viewer') === 0) {
			@file_put_contents($htaccess, "# OpenCart 3D Product Viewer\n"
				. "<IfModule mod_mime.c>\n"
				. "  AddType model/gltf-binary .glb\n"
				. "  AddType model/gltf+json .gltf\n"
				. "  AddType model/vnd.usdz+zip .usdz\n"
				. "</IfModule>\n"
				. "Options -Indexes\n"
				. "<IfModule mod_headers.c>\n"
				. "  Header set X-Content-Type-Options \"nosniff\"\n"
				. "</IfModule>\n"
				. "<FilesMatch \"\\.(php[0-9]?|phtml|phar|pl|py|cgi|sh)$\">\n"
				. "  <IfModule mod_authz_core.c>\n"
				. "    Require all denied\n"
				. "  </IfModule>\n"
				. "  <IfModule !mod_authz_core.c>\n"
				. "    Order Deny,Allow\n"
				. "    Deny from all\n"
				. "  </IfModule>\n"
				. "</FilesMatch>\n");
		}

		return is_writable($dir);
	}

	private function getServerUploadLimitMb() {
		$toBytes = function ($value) {
			$value = trim((string)$value);

			if ($value === '') {
				return 0;
			}

			$unit = strtolower(substr($value, -1));
			$number = (float)$value;

			switch ($unit) {
				case 'g': $number *= 1024;
				// no break
				case 'm': $number *= 1024;
				// no break
				case 'k': $number *= 1024;
			}

			return (int)$number;
		};

		$limits = array_filter(array($toBytes(ini_get('upload_max_filesize')), $toBytes(ini_get('post_max_size'))));

		return $limits ? max(1, (int)floor(min($limits) / 1048576)) : 2;
	}
}
