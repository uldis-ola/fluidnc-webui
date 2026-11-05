<?php
/**
 * Announcement Bar Pro — admin controller (OpenCart 3.0.x)
 * @version 2.0.1
 */
class ControllerExtensionModuleAnnouncementBarPro extends Controller {
	const VERSION    = '2.0.1';
	const CODE       = 'module_announcement_bar_pro';
	const EVENT_CODE = 'announcement_bar_pro';
	const ROUTE      = 'extension/module/announcement_bar_pro';
	const MAX_TEXT   = 1000;
	const MAX_LINK_TEXT = 64;

	private $error = array();

	/* ------------------------------------------------------------------ *
	 * Settings + message list
	 * ------------------------------------------------------------------ */
	public function index() {
		$this->bootstrap();

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validateSettings()) {
			$this->model_extension_module_announcement_bar_pro->saveSettings($this->sanitizeSettings($this->request->post));

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->link(self::ROUTE));
		}

		$data = $this->commonData();

		$data['action']     = $this->link(self::ROUTE);
		$data['cancel']     = $this->link('marketplace/extension', '&type=module');
		$data['add']        = $this->link(self::ROUTE . '/form');
		$data['delete']     = $this->link(self::ROUTE . '/delete');
		$data['error_warning']  = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['error_settings'] = $this->error;

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$post   = $this->request->server['REQUEST_METHOD'] == 'POST';
		$stored = array_merge($this->model_extension_module_announcement_bar_pro->getDefaultSettings(), $this->model_setting_setting->getSetting(self::CODE));

		foreach ($this->settingKeys() as $key) {
			$full = self::CODE . '_' . $key;
			$data['setting'][$key] = $post ? (isset($this->request->post[$full]) && is_string($this->request->post[$full]) ? $this->request->post[$full] : '') : $stored[$full];
		}

		$data['active_tab'] = (isset($this->request->get['tab']) && $this->request->get['tab'] === 'messages') ? 'messages' : 'settings';

		// Message list
		$language_id = (int)$this->config->get('config_language_id');
		$now         = date('Y-m-d H:i:s');

		$data['messages'] = array();

		foreach ($this->model_extension_module_announcement_bar_pro->getMessages($language_id) as $message) {
			if (!$message['status']) {
				$state = 'disabled';
			} elseif ($message['date_end'] && $message['date_end'] <= $now) {
				$state = 'expired';
			} elseif ($message['date_start'] && $message['date_start'] > $now) {
				$state = 'scheduled';
			} else {
				$state = 'active';
			}

			$text = $this->sanitizer->plain($this->sanitizer->decode((string)$message['text']));

			$data['messages'][] = array(
				'message_id' => (int)$message['message_id'],
				'text'       => function_exists('mb_strimwidth') ? mb_strimwidth($text, 0, 90, '…', 'UTF-8') : substr($text, 0, 90),
				'bg_color'   => $this->sanitizer->color($message['bg_color'], '#222222'),
				'text_color' => $this->sanitizer->color($message['text_color'], '#ffffff'),
				'date_start' => $message['date_start'] ? substr($message['date_start'], 0, 16) : '',
				'date_end'   => $message['date_end'] ? substr($message['date_end'], 0, 16) : '',
				'sort_order' => (int)$message['sort_order'],
				'state'      => $state,
				'state_text' => $this->language->get('text_state_' . $state),
				'edit'       => $this->link(self::ROUTE . '/form', '&message_id=' . (int)$message['message_id'])
			);
		}

		$this->response->setOutput($this->load->view('extension/module/announcement_bar_pro', $data));
	}

	/* ------------------------------------------------------------------ *
	 * Add / edit message
	 * ------------------------------------------------------------------ */
	public function form() {
		$this->bootstrap();

		$message_id = isset($this->request->get['message_id']) ? (int)$this->request->get['message_id'] : 0;
		$message    = $message_id ? $this->model_extension_module_announcement_bar_pro->getMessage($message_id) : array();

		if ($message_id && !$message) {
			$this->response->redirect($this->link(self::ROUTE, '&tab=messages'));
		}

		$languages = $this->model_localisation_language->getLanguages();
		$post_data = $this->normalizeMessagePost($this->request->post);

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validateMessage($post_data, $languages)) {
			$clean = $this->sanitizeMessage($post_data, $languages);

			if ($message_id) {
				$this->model_extension_module_announcement_bar_pro->editMessage($message_id, $clean);
			} else {
				$this->model_extension_module_announcement_bar_pro->addMessage($clean);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->link(self::ROUTE, '&tab=messages'));
		}

		$data = $this->commonData();

		$data['text_form']     = $message_id ? $this->language->get('text_edit_message') : $this->language->get('text_add_message');
		$data['action']        = $this->link(self::ROUTE . '/form', $message_id ? '&message_id=' . $message_id : '');
		$data['cancel']        = $this->link(self::ROUTE, '&tab=messages');
		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['error']         = $this->error;
		$data['languages']     = $languages;
		$data['max_text']      = self::MAX_TEXT;
		$data['max_link_text'] = self::MAX_LINK_TEXT;
		$data['allow_html']    = (bool)$this->config->get(self::CODE . '_allow_html');

		// Stores (0 = default)
		$this->load->model('setting/store');
		$data['store_list'] = array(array('store_id' => 0, 'name' => $this->config->get('config_name') . $this->language->get('text_default')));
		foreach ($this->model_setting_store->getStores() as $store) {
			$data['store_list'][] = array('store_id' => (int)$store['store_id'], 'name' => $store['name']);
		}

		$this->load->model('customer/customer_group');
		$data['customer_group_list'] = $this->model_customer_customer_group->getCustomerGroups();

		$post = $this->request->server['REQUEST_METHOD'] == 'POST';
		$src  = $post ? $post_data : $message;

		$defaults = array(
			'status'          => 1,
			'sort_order'      => 0,
			'date_start'      => '',
			'date_end'        => '',
			'bg_color'        => $this->sanitizer->color($this->config->get(self::CODE . '_default_bg_color'), '#222222'),
			'text_color'      => $this->sanitizer->color($this->config->get(self::CODE . '_default_text_color'), '#ffffff'),
			'link'            => '',
			'link_new_tab'    => 0,
			'icon'            => '',
			'countdown'       => 0,
			'stores'          => array(),
			'customer_groups' => array(),
			'page_mode'       => 'all',
			'page_routes'     => ''
		);

		foreach ($defaults as $key => $default) {
			if (isset($src[$key])) {
				$value = $src[$key];
				if (is_array($default)) {
					$value = is_array($value) ? array_map('intval', $value) : array();
				} elseif (is_string($value)) {
					$value = $this->sanitizer->decode($value);
				}
				$data[$key] = $value;
			} else {
				$data[$key] = ($post && !is_array($default) && in_array($key, array('status', 'link_new_tab', 'countdown'), true)) ? 0 : $default;
			}
		}

		$data['date_start'] = $data['date_start'] ? substr(str_replace('T', ' ', $data['date_start']), 0, 16) : '';
		$data['date_end']   = $data['date_end'] ? substr(str_replace('T', ' ', $data['date_end']), 0, 16) : '';

		$descriptions = isset($src['message_description']) && is_array($src['message_description']) ? $src['message_description'] : array();

		$data['message_description'] = array();
		foreach ($languages as $language) {
			$id  = (int)$language['language_id'];
			$row = isset($descriptions[$id]) && is_array($descriptions[$id]) ? $descriptions[$id] : array();

			$data['message_description'][$id] = array(
				'text'      => isset($row['text']) && is_string($row['text']) ? $this->sanitizer->decode($row['text']) : '',
				'link_text' => isset($row['link_text']) && is_string($row['link_text']) ? $this->sanitizer->decode($row['link_text']) : ''
			);
		}

		$this->response->setOutput($this->load->view('extension/module/announcement_bar_pro_form', $data));
	}

	/* ------------------------------------------------------------------ *
	 * Delete selected messages (POST only)
	 * ------------------------------------------------------------------ */
	public function delete() {
		$this->bootstrap();

		if ($this->request->server['REQUEST_METHOD'] != 'POST') {
			$this->response->redirect($this->link(self::ROUTE, '&tab=messages'));
		}

		if (!$this->user->hasPermission('modify', self::ROUTE)) {
			$this->session->data['abp_error'] = $this->language->get('error_permission');
		} elseif (isset($this->request->post['selected']) && is_array($this->request->post['selected'])) {
			foreach ($this->request->post['selected'] as $message_id) {
				$this->model_extension_module_announcement_bar_pro->deleteMessage((int)$message_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');
		}

		$this->response->redirect($this->link(self::ROUTE, '&tab=messages'));
	}

	/* ------------------------------------------------------------------ *
	 * Install / uninstall (called by extension/extension/module, which
	 * already validated "modify" permission on that route)
	 * ------------------------------------------------------------------ */
	public function install() {
		if (!$this->user->hasPermission('modify', 'extension/extension/module')) {
			return;
		}

		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode(self::EVENT_CODE);
		$this->model_setting_event->addEvent(self::EVENT_CODE, 'catalog/view/common/header/after', self::ROUTE . '/injectBar');

		$this->load->model(self::ROUTE);
		$this->model_extension_module_announcement_bar_pro->ensureSchema();
	}

	public function uninstall() {
		if (!$this->user->hasPermission('modify', 'extension/extension/module')) {
			return;
		}

		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode(self::EVENT_CODE);

		$this->load->model('setting/setting');
		$this->load->model(self::ROUTE);

		// Messages are kept unless the admin explicitly asked to delete them.
		if ($this->config->get(self::CODE . '_delete_data')) {
			$this->model_extension_module_announcement_bar_pro->dropTables();
		}

		$this->model_setting_setting->deleteSetting(self::CODE);
	}

	/* ------------------------------------------------------------------ *
	 * Validation
	 * ------------------------------------------------------------------ */
	protected function validateSettings() {
		if (!$this->user->hasPermission('modify', self::ROUTE)) {
			$this->error['warning'] = $this->language->get('error_permission');
			return false;
		}

		$p = $this->request->post;

		if (!in_array($this->postValue('position'), array('top', 'bottom'), true))            $this->error['position'] = $this->language->get('error_value');
		if (!in_array($this->postValue('mode'), array('rotate', 'marquee', 'stack'), true))    $this->error['mode'] = $this->language->get('error_value');
		if (!$this->intInRange($this->postValue('interval'), 2, 60))                           $this->error['interval'] = sprintf($this->language->get('error_range'), 2, 60);
		if (!$this->intInRange($this->postValue('speed'), 20, 300))                            $this->error['speed'] = sprintf($this->language->get('error_range'), 20, 300);
		if (!$this->intInRange($this->postValue('font_size'), 10, 30))                         $this->error['font_size'] = sprintf($this->language->get('error_range'), 10, 30);
		if (!$this->intInRange($this->postValue('padding'), 0, 30))                            $this->error['padding'] = sprintf($this->language->get('error_range'), 0, 30);
		if (!$this->intInRange($this->postValue('dismiss_days'), 0, 365))                      $this->error['dismiss_days'] = sprintf($this->language->get('error_range'), 0, 365);
		if (!$this->sanitizer->color($this->postValue('default_bg_color')))                    $this->error['default_bg_color'] = $this->language->get('error_color');
		if (!$this->sanitizer->color($this->postValue('default_text_color')))                  $this->error['default_text_color'] = $this->language->get('error_color');

		if ($this->error) {
			$this->error['warning'] = $this->language->get('error_form');
		}

		return !$this->error;
	}

	protected function validateMessage(array $p, array $languages) {
		if (!$this->user->hasPermission('modify', self::ROUTE)) {
			$this->error['warning'] = $this->language->get('error_permission');
			return false;
		}

		$allow_html = (bool)$this->config->get(self::CODE . '_allow_html');
		$descs      = isset($p['message_description']) && is_array($p['message_description']) ? $p['message_description'] : array();
		$has_text   = false;

		foreach ($languages as $language) {
			$id   = (int)$language['language_id'];
			$row  = isset($descs[$id]) && is_array($descs[$id]) ? $descs[$id] : array();
			$text = isset($row['text']) && is_string($row['text']) ? $this->sanitizer->decode($row['text']) : '';
			$link = isset($row['link_text']) && is_string($row['link_text']) ? $this->sanitizer->plain($this->sanitizer->decode($row['link_text'])) : '';

			$clean = $allow_html ? $this->sanitizer->html($text) : $this->sanitizer->plain($text);

			if ($this->strlen($clean) > self::MAX_TEXT) {
				$this->error['text'][$id] = sprintf($this->language->get('error_text_length'), self::MAX_TEXT);
			}

			if ($this->strlen($link) > self::MAX_LINK_TEXT) {
				$this->error['link_text'][$id] = sprintf($this->language->get('error_text_length'), self::MAX_LINK_TEXT);
			}

			if (trim(strip_tags($clean)) !== '') {
				$has_text = true;
			}
		}

		if (!$has_text) {
			$first = $languages ? (int)reset($languages)['language_id'] : 0;
			$this->error['text'][$first] = $this->language->get('error_text_required');
		}

		foreach (array('bg_color', 'text_color') as $key) {
			if (!$this->sanitizer->color(isset($p[$key]) ? $p[$key] : '')) {
				$this->error[$key] = $this->language->get('error_color');
			}
		}

		$link = isset($p['link']) ? $this->sanitizer->decode($p['link']) : '';
		if ($link !== '' && $this->sanitizer->url($link) === '') {
			$this->error['link'] = $this->language->get('error_link');
		}

		$icon = isset($p['icon']) ? trim((string)$p['icon']) : '';
		if ($icon !== '' && $this->sanitizer->icon($icon) === '') {
			$this->error['icon'] = $this->language->get('error_icon');
		}

		$start = $this->parseDate(isset($p['date_start']) ? $p['date_start'] : '');
		$end   = $this->parseDate(isset($p['date_end']) ? $p['date_end'] : '');

		if ($start === false) $this->error['date_start'] = $this->language->get('error_date');
		if ($end === false)   $this->error['date_end'] = $this->language->get('error_date');

		if ($start && $end && $end <= $start) {
			$this->error['date_end'] = $this->language->get('error_date_order');
		}

		if (!empty($p['countdown']) && !$end) {
			$this->error['countdown'] = $this->language->get('error_countdown');
		}

		if (!isset($p['sort_order']) || !preg_match('/^-?\d{1,6}$/', trim((string)$p['sort_order']))) {
			$this->error['sort_order'] = $this->language->get('error_value');
		}

		$mode = isset($p['page_mode']) ? $p['page_mode'] : '';
		if (!in_array($mode, array('all', 'home', 'include', 'exclude'), true)) {
			$this->error['page_mode'] = $this->language->get('error_value');
		} elseif (in_array($mode, array('include', 'exclude'), true)) {
			$routes = $this->parseRoutes(isset($p['page_routes']) ? $p['page_routes'] : '');
			if ($routes === false) {
				$this->error['page_routes'] = $this->language->get('error_routes');
			} elseif (!$routes) {
				$this->error['page_routes'] = $this->language->get('error_routes_required');
			}
		}

		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('error_form');
		}

		return !$this->error;
	}

	/* ------------------------------------------------------------------ *
	 * Sanitizing (whitelists only known keys)
	 * ------------------------------------------------------------------ */
	protected function sanitizeSettings(array $p) {
		$out = array(
			'status'             => !empty($p[self::CODE . '_status']) ? 1 : 0,
			'position'           => $this->postValue('position'),
			'sticky'             => !empty($p[self::CODE . '_sticky']) ? 1 : 0,
			'mode'               => $this->postValue('mode'),
			'interval'           => (int)$this->postValue('interval'),
			'speed'              => (int)$this->postValue('speed'),
			'font_size'          => (int)$this->postValue('font_size'),
			'padding'            => (int)$this->postValue('padding'),
			'dismissible'        => !empty($p[self::CODE . '_dismissible']) ? 1 : 0,
			'dismiss_days'       => (int)$this->postValue('dismiss_days'),
			'allow_html'         => !empty($p[self::CODE . '_allow_html']) ? 1 : 0,
			'default_bg_color'   => $this->sanitizer->color($this->postValue('default_bg_color')),
			'default_text_color' => $this->sanitizer->color($this->postValue('default_text_color')),
			'delete_data'        => !empty($p[self::CODE . '_delete_data']) ? 1 : 0
		);

		$data = array();
		foreach ($out as $key => $value) {
			$data[self::CODE . '_' . $key] = $value;
		}

		return $data;
	}

	protected function sanitizeMessage(array $p, array $languages) {
		$allow_html = (bool)$this->config->get(self::CODE . '_allow_html');
		$descs      = isset($p['message_description']) && is_array($p['message_description']) ? $p['message_description'] : array();

		$description = array();
		foreach ($languages as $language) {
			$id   = (int)$language['language_id'];
			$row  = isset($descs[$id]) && is_array($descs[$id]) ? $descs[$id] : array();
			$text = isset($row['text']) && is_string($row['text']) ? $this->sanitizer->decode($row['text']) : '';
			$link = isset($row['link_text']) && is_string($row['link_text']) ? $this->sanitizer->decode($row['link_text']) : '';

			// encode4byte: OpenCart's DB link is 3-byte utf8, emoji would be stored as "?".
			$description[$id] = array(
				'text'      => $this->sanitizer->encode4byte($allow_html ? $this->sanitizer->html($text) : $this->sanitizer->plain($text)),
				'link_text' => $this->sanitizer->encode4byte($this->substr($this->sanitizer->plain($link), self::MAX_LINK_TEXT))
			);
		}

		$mode   = isset($p['page_mode']) ? (string)$p['page_mode'] : 'all';
		$routes = in_array($mode, array('include', 'exclude'), true) ? implode("\n", (array)$this->parseRoutes(isset($p['page_routes']) ? $p['page_routes'] : '')) : '';
		$start  = $this->parseDate(isset($p['date_start']) ? $p['date_start'] : '');
		$end    = $this->parseDate(isset($p['date_end']) ? $p['date_end'] : '');

		return array(
			'status'              => !empty($p['status']) ? 1 : 0,
			'sort_order'          => isset($p['sort_order']) ? (int)$p['sort_order'] : 0,
			'date_start'          => $start ? $start : '',
			'date_end'            => $end ? $end : '',
			'bg_color'            => $this->sanitizer->color(isset($p['bg_color']) ? $p['bg_color'] : '', '#222222'),
			'text_color'          => $this->sanitizer->color(isset($p['text_color']) ? $p['text_color'] : '', '#ffffff'),
			'link'                => $this->sanitizer->url($this->sanitizer->decode(isset($p['link']) ? $p['link'] : '')),
			'link_new_tab'        => !empty($p['link_new_tab']) ? 1 : 0,
			'icon'                => $this->sanitizer->icon(isset($p['icon']) ? $p['icon'] : ''),
			'countdown'           => !empty($p['countdown']) ? 1 : 0,
			'stores'              => isset($p['stores']) && is_array($p['stores']) ? array_unique(array_map('intval', $p['stores'])) : array(),
			'customer_groups'     => isset($p['customer_groups']) && is_array($p['customer_groups']) ? array_unique(array_map('intval', $p['customer_groups'])) : array(),
			'page_mode'           => $mode,
			'page_routes'         => $routes,
			'message_description' => $description
		);
	}

	/* ------------------------------------------------------------------ *
	 * Helpers
	 * ------------------------------------------------------------------ */
	private function bootstrap() {
		$this->load->language(self::ROUTE);
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('localisation/language');
		$this->load->model(self::ROUTE);
		$this->load->library('announcementbarpro/sanitizer');

		// Creating/migrating tables writes to the DB: only for users with "modify" permission.
		if ($this->user->hasPermission('modify', self::ROUTE)) {
			$this->model_extension_module_announcement_bar_pro->ensureSchema();
		} elseif (!$this->model_extension_module_announcement_bar_pro->isSchemaCurrent()) {
			$this->session->data['abp_error'] = $this->language->get('error_permission');
			$this->response->redirect($this->link('marketplace/extension', '&type=module'));
		}
	}

	/**
	 * Scalars must be strings, lists must be arrays — crafted array input is neutralised here,
	 * so later casts never raise "Array to string conversion".
	 */
	private function normalizeMessagePost(array $post) {
		$out = array();

		foreach (array('status', 'sort_order', 'date_start', 'date_end', 'bg_color', 'text_color', 'link', 'link_new_tab', 'icon', 'countdown', 'page_mode', 'page_routes') as $key) {
			if (isset($post[$key])) {
				$out[$key] = is_string($post[$key]) ? $post[$key] : '';
			}
		}

		foreach (array('stores', 'customer_groups') as $key) {
			if (isset($post[$key])) {
				$out[$key] = is_array($post[$key]) ? array_filter($post[$key], 'is_scalar') : array();
			}
		}

		$out['message_description'] = array();
		if (isset($post['message_description']) && is_array($post['message_description'])) {
			foreach ($post['message_description'] as $language_id => $row) {
				if (is_array($row)) {
					$out['message_description'][(int)$language_id] = array(
						'text'      => isset($row['text']) && is_string($row['text']) ? $row['text'] : '',
						'link_text' => isset($row['link_text']) && is_string($row['link_text']) ? $row['link_text'] : ''
					);
				}
			}
		}

		return $out;
	}

	private function commonData() {
		$data['breadcrumbs'] = array(
			array('text' => $this->language->get('text_home'),      'href' => $this->link('common/dashboard')),
			array('text' => $this->language->get('text_extension'), 'href' => $this->link('marketplace/extension', '&type=module')),
			array('text' => $this->language->get('heading_title'),  'href' => $this->link(self::ROUTE))
		);

		$data['version'] = self::VERSION;

		if (isset($this->session->data['abp_error'])) {
			$data['error_session'] = $this->session->data['abp_error'];
			unset($this->session->data['abp_error']);
		} else {
			$data['error_session'] = '';
		}

		$data['header']      = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer']      = $this->load->controller('common/footer');

		return $data;
	}

	private function link($route, $args = '') {
		return $this->url->link($route, 'user_token=' . $this->session->data['user_token'] . $args, true);
	}

	private function settingKeys() {
		return array('status', 'position', 'sticky', 'mode', 'interval', 'speed', 'font_size', 'padding', 'dismissible', 'dismiss_days', 'allow_html', 'default_bg_color', 'default_text_color', 'delete_data');
	}

	private function postValue($key) {
		$full = self::CODE . '_' . $key;

		return isset($this->request->post[$full]) && is_string($this->request->post[$full]) ? trim($this->request->post[$full]) : '';
	}

	private function intInRange($value, $min, $max) {
		return is_string($value) && preg_match('/^\d{1,4}$/', $value) && (int)$value >= $min && (int)$value <= $max;
	}

	/**
	 * '' → '' (no limit); valid → 'Y-m-d H:i:00'; invalid → false.
	 */
	private function parseDate($value) {
		$value = trim(str_replace('T', ' ', (string)$value));

		if ($value === '') {
			return '';
		}

		foreach (array('Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d') as $format) {
			$date = DateTime::createFromFormat('!' . $format, $value);
			if ($date && $date->format($format) === $value) {
				return $date->format('Y-m-d H:i:00');
			}
		}

		return false;
	}

	/**
	 * One route per line, e.g. "product/product", "product/*". Returns list, or false on invalid line.
	 */
	private function parseRoutes($value) {
		$routes = array();

		foreach (preg_split('/[\r\n,]+/', $this->sanitizer->decode((string)$value)) as $line) {
			$line = trim($line);

			if ($line === '') {
				continue;
			}

			if (!preg_match('#^[a-z0-9_]+(/[a-z0-9_]+)*(/\*)?$|^\*$#i', $line) || strlen($line) > 64) {
				return false;
			}

			$routes[] = strtolower($line);
		}

		return array_slice(array_values(array_unique($routes)), 0, 50);
	}

	private function strlen($value) {
		return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
	}

	private function substr($value, $length) {
		return function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length);
	}
}
