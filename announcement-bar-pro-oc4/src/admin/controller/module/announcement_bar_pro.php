<?php
namespace Opencart\Admin\Controller\Extension\AnnouncementBarPro\Module;
/**
 * Announcement Bar Pro — admin controller (OpenCart 4.0.x / 4.1.x)
 *
 * @version 2.0.5
 */
class AnnouncementBarPro extends \Opencart\System\Engine\Controller {
	public const VERSION       = '2.0.5';
	public const CODE          = 'module_announcement_bar_pro';
	public const EVENT_CODE    = 'announcement_bar_pro';
	public const ROUTE         = 'extension/announcement_bar_pro/module/announcement_bar_pro';
	public const MAX_TEXT      = 1000;
	public const MAX_RAW_TEXT  = 20000;
	public const MAX_LINK_TEXT = 64;
	public const MAX_ROUTES    = 50;

	/** @var \Opencart\System\Library\Extension\AnnouncementBarPro\Sanitizer */
	private $abp;

	private array $orphaned = ['stores' => false, 'customer_groups' => false];

	/* ------------------------------------------------------------------ *
	 * Page: message list + settings
	 * ------------------------------------------------------------------ */
	public function index(): void {
		$this->bootstrap();

		$data = $this->commonData();

		$data['save']     = $this->link(self::ROUTE . $this->sep() . 'save');
		$data['add']      = $this->link(self::ROUTE . $this->sep() . 'form');
		$data['delete']   = $this->link(self::ROUTE . $this->sep() . 'delete');
		$data['back']     = $this->link('marketplace/extension', '&type=module');
		$data['reload']   = $this->link(self::ROUTE, '&tab=messages');

		$stored = array_merge($this->model_extension_announcement_bar_pro_module_announcement_bar_pro->getDefaultSettings(), $this->model_setting_setting->getSetting(self::CODE));

		$data['setting'] = [];
		foreach ($this->settingKeys() as $key) {
			$data['setting'][$key] = $stored[self::CODE . '_' . $key];
		}

		$tab = isset($this->request->get['tab']) && is_string($this->request->get['tab']) ? $this->request->get['tab'] : '';
		$data['active_tab'] = $tab === 'settings' ? 'settings' : 'messages';

		$language_id = (int)$this->config->get('config_language_id');
		$languages   = $this->model_localisation_language->getLanguages();
		$now         = date('Y-m-d H:i:s');

		$data['messages'] = [];

		foreach ($this->model_extension_announcement_bar_pro_module_announcement_bar_pro->getMessages($language_id) as $message) {
			if (!$message['status']) {
				$state = 'disabled';
			} elseif ($message['date_end'] && $message['date_end'] <= $now) {
				$state = 'expired';
			} elseif ($message['date_start'] && $message['date_start'] > $now) {
				$state = 'scheduled';
			} else {
				$state = 'active';
			}

			$with_text = array_map('intval', array_filter(explode(',', (string)$message['languages_with_text'])));
			$missing   = [];
			foreach ($languages as $language) {
				if (!in_array((int)$language['language_id'], $with_text, true)) {
					$missing[] = $language['name'];
				}
			}

			$text = $this->abp->plain($this->abp->decode((string)$message['text']));

			$data['messages'][] = [
				'message_id' => (int)$message['message_id'],
				'text'       => mb_strimwidth($text, 0, 90, '…', 'UTF-8'),
				'bg_color'   => $this->abp->color($message['bg_color'], '#222222'),
				'text_color' => $this->abp->color($message['text_color'], '#ffffff'),
				'date_start' => $message['date_start'] ? substr($message['date_start'], 0, 16) : '',
				'date_end'   => $message['date_end'] ? substr($message['date_end'], 0, 16) : '',
				'sort_order' => (int)$message['sort_order'],
				'state'      => $state,
				'state_text' => $this->language->get('text_state_' . $state),
				'missing'    => $missing ? sprintf($this->language->get('text_missing_language'), implode(', ', $missing)) : '',
				'edit'       => $this->link(self::ROUTE . $this->sep() . 'form', '&message_id=' . (int)$message['message_id'])
			];
		}

		$this->response->setOutput($this->load->view(self::ROUTE, $data));
	}

	/* ------------------------------------------------------------------ *
	 * AJAX: save settings
	 * ------------------------------------------------------------------ */
	public function save(): void {
		$this->bootstrap();

		$json = [];

		if (!$this->user->hasPermission('modify', self::ROUTE)) {
			$json['error']['warning'] = $this->language->get('error_permission');
		} else {
			$checks = [
				'interval'     => [2, 60],
				'speed'        => [20, 300],
				'font_size'    => [10, 30],
				'padding'      => [0, 30],
				'dismiss_days' => [0, 365]
			];

			foreach ($checks as $key => $range) {
				if (!$this->intInRange($this->postValue($key), $range[0], $range[1])) {
					$json['error'][$key] = sprintf($this->language->get('error_range'), $range[0], $range[1]);
				}
			}

			if (!in_array($this->postValue('position'), ['top', 'bottom'], true)) {
				$json['error']['position'] = $this->language->get('error_value');
			}

			if (!in_array($this->postValue('mode'), ['rotate', 'marquee', 'stack'], true)) {
				$json['error']['mode'] = $this->language->get('error_value');
			}

			foreach (['default_bg_color', 'default_text_color'] as $key) {
				if (!$this->abp->color($this->postValue($key))) {
					$json['error'][$key] = $this->language->get('error_color');
				}
			}

			if (isset($json['error'])) {
				$json['error']['warning'] = $this->language->get('error_form');
			}
		}

		if (!$json) {
			$out = [];

			foreach (['status', 'sticky', 'dismissible', 'allow_html', 'delete_data'] as $key) {
				$out[$key] = $this->postValue($key) !== '' && $this->postValue($key) !== '0' ? 1 : 0;
			}

			foreach (['interval', 'speed', 'font_size', 'padding', 'dismiss_days'] as $key) {
				$out[$key] = (int)$this->postValue($key);
			}

			$out['position']           = $this->postValue('position');
			$out['mode']               = $this->postValue('mode');
			$out['default_bg_color']   = $this->abp->color($this->postValue('default_bg_color'));
			$out['default_text_color'] = $this->abp->color($this->postValue('default_text_color'));

			$data = [];
			foreach ($out as $key => $value) {
				$data[self::CODE . '_' . $key] = $value;
			}

			$this->model_extension_announcement_bar_pro_module_announcement_bar_pro->saveSettings($data);

			$json['success'] = $this->language->get('text_success');
		}

		$this->json($json);
	}

	/* ------------------------------------------------------------------ *
	 * Page: add / edit message
	 * ------------------------------------------------------------------ */
	public function form(): void {
		$this->bootstrap();

		$raw_id     = isset($this->request->get['message_id']) && is_string($this->request->get['message_id']) ? $this->request->get['message_id'] : '';
		$message_id = preg_match('/^\d{1,10}$/', $raw_id) ? (int)$raw_id : 0;
		$message    = $message_id ? $this->model_extension_announcement_bar_pro_module_announcement_bar_pro->getMessage($message_id) : [];

		if (($raw_id !== '' && !$message_id) || ($message_id && !$message)) {
			$this->session->data['abp_error'] = $this->language->get('error_not_found');
			$this->response->redirect($this->link(self::ROUTE, '&tab=messages'));
		}

		$data = $this->commonData();

		$data['text_form']     = $message_id ? $this->language->get('text_edit_message') : $this->language->get('text_add_message');
		$data['save']          = $this->link(self::ROUTE . $this->sep() . 'saveMessage', $message_id ? '&message_id=' . $message_id : '');
		$data['back']          = $this->link(self::ROUTE, '&tab=messages');
		$data['languages']     = $this->model_localisation_language->getLanguages();
		$data['max_text']      = self::MAX_TEXT;
		$data['max_link_text'] = self::MAX_LINK_TEXT;
		$data['allow_html']    = (bool)$this->config->get(self::CODE . '_allow_html');
		$data['orphaned']      = $this->orphanedTargets($message);

		$data['store_list'] = [['store_id' => 0, 'name' => $this->config->get('config_name') . $this->language->get('text_default')]];
		$this->load->model('setting/store');
		foreach ($this->model_setting_store->getStores() as $store) {
			$data['store_list'][] = ['store_id' => (int)$store['store_id'], 'name' => $store['name']];
		}

		$this->load->model('customer/customer_group');
		$data['customer_group_list'] = $this->model_customer_customer_group->getCustomerGroups();

		$defaults = [
			'status'          => 1,
			'sort_order'      => 0,
			'date_start'      => '',
			'date_end'        => '',
			'bg_color'        => $this->abp->color($this->config->get(self::CODE . '_default_bg_color'), '#222222'),
			'text_color'      => $this->abp->color($this->config->get(self::CODE . '_default_text_color'), '#ffffff'),
			'link'            => '',
			'link_new_tab'    => 0,
			'icon'            => '',
			'countdown'       => 0,
			'stores'          => [],
			'customer_groups' => [],
			'page_mode'       => 'all',
			'page_routes'     => ''
		];

		foreach ($defaults as $key => $default) {
			$data[$key] = array_key_exists($key, $message) ? $message[$key] : $default;
		}

		$data['end_all_day'] = !empty($message['date_end']) && substr($message['date_end'], 11, 8) === '23:59:59';
		// <input type="datetime-local"> wants "YYYY-MM-DDTHH:MM"
		$data['date_start']  = $data['date_start'] ? str_replace(' ', 'T', substr($data['date_start'], 0, 16)) : '';
		$data['date_end']    = $data['date_end'] ? str_replace(' ', 'T', substr($data['date_end'], 0, 16)) : '';
		$data['link']        = $this->abp->decode($data['link']);

		$data['message_description'] = [];
		foreach ($data['languages'] as $language) {
			$id  = (int)$language['language_id'];
			$row = $message['message_description'][$id] ?? [];

			$data['message_description'][$id] = [
				'text'      => isset($row['text']) ? $this->abp->decode($row['text']) : '',
				'link_text' => isset($row['link_text']) ? $this->abp->decode($row['link_text']) : ''
			];
		}

		$this->response->setOutput($this->load->view(self::ROUTE . '_form', $data));
	}

	/* ------------------------------------------------------------------ *
	 * AJAX: save message
	 * ------------------------------------------------------------------ */
	public function saveMessage(): void {
		$this->bootstrap();

		$json = [];

		$raw_id     = isset($this->request->get['message_id']) && is_string($this->request->get['message_id']) ? $this->request->get['message_id'] : '';
		$message_id = preg_match('/^\d{1,10}$/', $raw_id) ? (int)$raw_id : 0;
		$languages  = $this->model_localisation_language->getLanguages();
		$p          = $this->normalizeMessagePost($this->request->post);
		$message    = $message_id ? $this->model_extension_announcement_bar_pro_module_announcement_bar_pro->getMessage($message_id) : [];

		$this->orphaned = $this->orphanedTargets($message);

		if (!$this->user->hasPermission('modify', self::ROUTE)) {
			$json['error']['warning'] = $this->language->get('error_permission');
		} elseif (($raw_id !== '' && !$message_id) || ($message_id && !$message)) {
			$json['error']['warning'] = $this->language->get('error_not_found');
		} else {
			$errors = $this->validateMessage($p, $languages);

			if ($errors) {
				$json['error'] = $errors;
				$json['error']['warning'] = $this->language->get('error_form');
			}
		}

		if (!$json) {
			$clean = $this->sanitizeMessage($p, $languages);

			if ($message_id) {
				$this->model_extension_announcement_bar_pro_module_announcement_bar_pro->editMessage($message_id, $clean);
			} else {
				$this->model_extension_announcement_bar_pro_module_announcement_bar_pro->addMessage($clean);
			}

			$this->session->data['abp_success'] = $this->language->get('text_success');

			$json['redirect'] = str_replace('&amp;', '&', $this->link(self::ROUTE, '&tab=messages'));
		}

		$this->json($json);
	}

	/* ------------------------------------------------------------------ *
	 * AJAX: delete selected messages
	 * ------------------------------------------------------------------ */
	public function delete(): void {
		$this->bootstrap();

		$json = [];

		if ($this->request->server['REQUEST_METHOD'] != 'POST') {
			$json['error'] = $this->language->get('error_value');
		} elseif (!$this->user->hasPermission('modify', self::ROUTE)) {
			$json['error'] = $this->language->get('error_permission');
		}

		$selected = isset($this->request->post['selected']) && is_array($this->request->post['selected']) ? array_filter($this->request->post['selected'], function ($v) { return is_scalar($v) && preg_match('/^\d{1,10}$/', (string)$v); }) : [];

		if (!$json && !$selected) {
			$json['error'] = $this->language->get('error_nothing_selected');
		}

		if (!$json) {
			foreach ($selected as $message_id) {
				if (is_scalar($message_id)) {
					$this->model_extension_announcement_bar_pro_module_announcement_bar_pro->deleteMessage((int)$message_id);
				}
			}

			$this->session->data['abp_success'] = $this->language->get('text_success');
			$json['success'] = $this->language->get('text_success');
		}

		$this->json($json);
	}

	/* ------------------------------------------------------------------ *
	 * Install / uninstall (called by extension/module, which checks
	 * "modify" on extension/module and adds/removes our permissions)
	 * ------------------------------------------------------------------ */
	public function install(): void {
		if (!$this->user->hasPermission('modify', 'extension/module')) {
			return;
		}

		$this->load->model('setting/event');

		$this->model_setting_event->deleteEventByCode(self::EVENT_CODE);
		$this->model_setting_event->addEvent([
			'code'        => self::EVENT_CODE,
			'description' => 'Announcement Bar Pro: inject the bar after <body>',
			'trigger'     => 'catalog/view/common/header/after',
			'action'      => self::ROUTE . $this->sep() . 'injectBar',
			'status'      => 1,
			'sort_order'  => 0
		]);

		$this->load->model(self::ROUTE);
		$this->model_extension_announcement_bar_pro_module_announcement_bar_pro->ensureSchema();
	}

	public function uninstall(): void {
		if (!$this->user->hasPermission('modify', 'extension/module')) {
			return;
		}

		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode(self::EVENT_CODE);

		$this->load->model('setting/setting');
		$this->load->model(self::ROUTE);

		if ($this->config->get(self::CODE . '_delete_data')) {
			$this->model_extension_announcement_bar_pro_module_announcement_bar_pro->dropTables();
		}

		$this->model_setting_setting->deleteSetting(self::CODE);
	}

	/* ------------------------------------------------------------------ *
	 * Validation / sanitizing
	 * ------------------------------------------------------------------ */
	private function validateMessage(array $p, array $languages): array {
		$error      = [];
		$allow_html = (bool)$this->config->get(self::CODE . '_allow_html');
		$has_text   = false;

		foreach ($languages as $language) {
			$id   = (int)$language['language_id'];
			$row  = $p['message_description'][$id] ?? ['text' => '', 'link_text' => ''];
			$text = $this->abp->decode($row['text']);
			$link = $this->abp->plain($this->abp->decode($row['link_text']));

			if (strlen($text) > self::MAX_RAW_TEXT) {
				$error['text_' . $id] = sprintf($this->language->get('error_text_length'), self::MAX_TEXT);
				continue;
			}

			$clean = $allow_html ? $this->abp->html($text) : $this->abp->plain($text);

			if (mb_strlen($clean, 'UTF-8') > self::MAX_TEXT) {
				$error['text_' . $id] = sprintf($this->language->get('error_text_length'), self::MAX_TEXT);
			}

			if (mb_strlen($link, 'UTF-8') > self::MAX_LINK_TEXT) {
				$error['link_text_' . $id] = sprintf($this->language->get('error_text_length'), self::MAX_LINK_TEXT);
			}

			if (trim(strip_tags($clean)) !== '') {
				$has_text = true;
			}
		}

		if (!$has_text && $languages) {
			$first = reset($languages);
			$error['text_' . (int)$first['language_id']] = $this->language->get('error_text_required');
		}

		foreach (['bg_color', 'text_color'] as $key) {
			if (!$this->abp->color($p[$key] ?? '')) {
				$error[$key] = $this->language->get('error_color');
			}
		}

		$link = $this->abp->decode($p['link'] ?? '');
		if ($link !== '' && $this->abp->url($link) === '') {
			$error['link'] = $this->language->get('error_link');
		}

		$icon = trim($p['icon'] ?? '');
		if ($icon !== '' && $this->abp->icon($icon) === '') {
			$error['icon'] = $this->language->get('error_icon');
		}

		$start = $this->parseDate($p['date_start'] ?? '');
		$end   = $this->parseEndDate($p);

		if ($start === false) {
			$error['date_start'] = $this->language->get('error_date');
		}

		if ($end === false) {
			$error['date_end'] = $this->language->get('error_date');
		}

		if ($start && $end && $end <= $start) {
			$error['date_end'] = $this->language->get('error_date_order');
		}

		if (!empty($p['countdown']) && !$end) {
			$error['countdown'] = $this->language->get('error_countdown');
		}

		if (!preg_match('/^-?\d{1,6}$/', trim($p['sort_order'] ?? ''))) {
			$error['sort_order'] = $this->language->get('error_value');
		}

		foreach (['stores' => $this->storeIds(), 'customer_groups' => $this->customerGroupIds()] as $key => $valid) {
			$raw = $p[$key] ?? [];

			if (array_diff(array_map('intval', $raw), $valid) || preg_grep('/^\d{1,9}$/', array_map('strval', $raw), PREG_GREP_INVERT)) {
				$error[$key] = $this->language->get('error_value');
			}
		}

		// A message limited to stores/groups that were all deleted must not silently become "all".
		foreach ($this->orphaned as $key => $is_orphaned) {
			if ($is_orphaned && empty($p[$key])) {
				$error['orphan_' . $key] = $this->language->get('error_orphan_' . $key);
				$error[$key] = $this->language->get('error_orphan_' . $key);
			}
		}

		$mode = $p['page_mode'] ?? '';
		if (!in_array($mode, ['all', 'home', 'include', 'exclude'], true)) {
			$error['page_mode'] = $this->language->get('error_value');
		} elseif (in_array($mode, ['include', 'exclude'], true)) {
			$routes = $this->parseRoutes($p['page_routes'] ?? '');

			if ($routes === false) {
				$error['page_routes'] = $this->language->get('error_routes');
			} elseif (!$routes) {
				$error['page_routes'] = $this->language->get('error_routes_required');
			}
		}

		return $error;
	}

	private function sanitizeMessage(array $p, array $languages): array {
		$allow_html  = (bool)$this->config->get(self::CODE . '_allow_html');
		$description = [];

		foreach ($languages as $language) {
			$id  = (int)$language['language_id'];
			$row = $p['message_description'][$id] ?? ['text' => '', 'link_text' => ''];

			$text = $this->abp->decode($row['text']);

			$description[$id] = [
				// OpenCart 4 talks utf8mb4 to MySQL, so emoji are stored as they are (no entity encoding needed).
				'text'      => $allow_html ? $this->abp->html($text) : $this->abp->plain($text),
				'link_text' => mb_substr($this->abp->plain($this->abp->decode($row['link_text'])), 0, self::MAX_LINK_TEXT, 'UTF-8')
			];
		}

		$mode  = $p['page_mode'] ?? 'all';
		$start = $this->parseDate($p['date_start'] ?? '');
		$end   = $this->parseEndDate($p);

		return [
			'status'              => !empty($p['status']) ? 1 : 0,
			'sort_order'          => (int)($p['sort_order'] ?? 0),
			'date_start'          => $start ?: '',
			'date_end'            => $end ?: '',
			'bg_color'            => $this->abp->color($p['bg_color'] ?? '', '#222222'),
			'text_color'          => $this->abp->color($p['text_color'] ?? '', '#ffffff'),
			'link'                => $this->abp->url($this->abp->decode($p['link'] ?? '')),
			'link_new_tab'        => !empty($p['link_new_tab']) ? 1 : 0,
			'icon'                => $this->abp->icon($p['icon'] ?? ''),
			'countdown'           => !empty($p['countdown']) ? 1 : 0,
			'stores'              => array_values(array_unique(array_intersect(array_map('intval', $p['stores'] ?? []), $this->storeIds()))),
			'customer_groups'     => array_values(array_unique(array_intersect(array_map('intval', $p['customer_groups'] ?? []), $this->customerGroupIds()))),
			'page_mode'           => $mode,
			'page_routes'         => in_array($mode, ['include', 'exclude'], true) ? implode("\n", (array)$this->parseRoutes($p['page_routes'] ?? '')) : '',
			'message_description' => $description
		];
	}

	/**
	 * Scalars must be strings, lists arrays — crafted array input is neutralised here.
	 */
	private function normalizeMessagePost(array $post): array {
		$out = [];

		foreach (['status', 'sort_order', 'date_start', 'date_end', 'bg_color', 'text_color', 'link', 'link_new_tab', 'icon', 'countdown', 'page_mode', 'page_routes', 'end_all_day'] as $key) {
			if (isset($post[$key])) {
				$out[$key] = is_string($post[$key]) ? $post[$key] : '';
			}
		}

		foreach (['stores', 'customer_groups'] as $key) {
			$out[$key] = isset($post[$key]) && is_array($post[$key]) ? array_filter($post[$key], 'is_scalar') : [];
		}

		$out['message_description'] = [];
		if (isset($post['message_description']) && is_array($post['message_description'])) {
			foreach ($post['message_description'] as $language_id => $row) {
				if (is_array($row)) {
					$out['message_description'][(int)$language_id] = [
						'text'      => isset($row['text']) && is_string($row['text']) ? $row['text'] : '',
						'link_text' => isset($row['link_text']) && is_string($row['link_text']) ? $row['link_text'] : ''
					];
				}
			}
		}

		return $out;
	}

	/* ------------------------------------------------------------------ *
	 * Helpers
	 * ------------------------------------------------------------------ */
	private function bootstrap(): void {
		// English first, then the admin's language on top: a language without our file still gets labels.
		$this->load->language(self::ROUTE, '', 'en-gb');
		$this->load->language(self::ROUTE);
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('setting/extension');
		$this->load->model('localisation/language');
		$this->load->model(self::ROUTE);

		$this->abp = $this->sanitizer();

		// After "Uninstall" the files and the group permission stay; do not silently recreate data then.
		if (!$this->model_setting_extension->getExtensionByCode('module', 'announcement_bar_pro')) {
			$this->response->redirect($this->link('marketplace/extension', '&type=module'));
		}

		if ($this->user->hasPermission('modify', self::ROUTE)) {
			$this->model_extension_announcement_bar_pro_module_announcement_bar_pro->ensureSchema();
		} elseif (!$this->model_extension_announcement_bar_pro_module_announcement_bar_pro->isSchemaCurrent()) {
			$this->response->redirect($this->link('error/permission'));
		}
	}

	/**
	 * Sanitizer: autoloaded on 4.0.1+; 4.0.0.x registers no Library\\Extension namespace, so require it.
	 */
	private function sanitizer(): \Opencart\System\Library\Extension\AnnouncementBarPro\Sanitizer {
		if (!class_exists('Opencart\\System\\Library\\Extension\\AnnouncementBarPro\\Sanitizer')) {
			require_once(DIR_EXTENSION . 'announcement_bar_pro/system/library/sanitizer.php');
		}

		return new \Opencart\System\Library\Extension\AnnouncementBarPro\Sanitizer();
	}

	private function commonData(): array {
		$data['breadcrumbs'] = [
			['text' => $this->language->get('text_home'),      'href' => $this->link('common/dashboard')],
			['text' => $this->language->get('text_extension'), 'href' => $this->link('marketplace/extension', '&type=module')],
			['text' => $this->language->get('heading_title'),  'href' => $this->link(self::ROUTE)]
		];

		$data['version'] = self::VERSION;

		$data['error_session'] = $this->session->data['abp_error'] ?? '';
		$data['success']       = $this->session->data['abp_success'] ?? '';
		unset($this->session->data['abp_error'], $this->session->data['abp_success']);

		$data['header']      = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer']      = $this->load->controller('common/footer');

		return $data;
	}

	/** Method separator: "." from OpenCart 4.0.2.0, "|" in 4.0.0.x–4.0.1.x. */
	private function sep(): string {
		return version_compare(VERSION, '4.0.2.0', '>=') ? '.' : '|';
	}

	private function link(string $route, string $args = ''): string {
		return $this->url->link($route, 'user_token=' . $this->session->data['user_token'] . $args);
	}

	private function json(array $json): void {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function settingKeys(): array {
		return ['status', 'position', 'sticky', 'mode', 'interval', 'speed', 'font_size', 'padding', 'dismissible', 'dismiss_days', 'allow_html', 'default_bg_color', 'default_text_color', 'delete_data'];
	}

	private function postValue(string $key): string {
		$full = self::CODE . '_' . $key;

		return isset($this->request->post[$full]) && is_string($this->request->post[$full]) ? trim($this->request->post[$full]) : '';
	}

	private function intInRange(string $value, int $min, int $max): bool {
		return (bool)preg_match('/^\d{1,4}$/', $value) && (int)$value >= $min && (int)$value <= $max;
	}

	private function parseEndDate(array $p) {
		$value = (string)($p['date_end'] ?? '');

		if (!empty($p['end_all_day']) && preg_match('/^\s*(\d{4}-\d{2}-\d{2})/', $value, $m)) {
			$value = $m[1];
		}

		return $this->parseDate($value, true);
	}

	/**
	 * '' → '' (no limit); valid → 'Y-m-d H:i:00' (date-only end → 23:59:59); invalid → false.
	 */
	private function parseDate(string $value, bool $is_end = false) {
		$value = trim(str_replace('T', ' ', $value));

		if ($value === '') {
			return '';
		}

		foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $format) {
			$date = \DateTime::createFromFormat('!' . $format, $value);

			if ($date && $date->format($format) === $value) {
				return ($format === 'Y-m-d' && $is_end) ? $date->format('Y-m-d 23:59:59') : $date->format('Y-m-d H:i:00');
			}
		}

		return false;
	}

	/**
	 * One route per line ("product/product", "product/*"). Returns list, or false if invalid / too many.
	 */
	private function parseRoutes(string $value) {
		$routes = [];

		foreach (preg_split('/[\r\n,]+/', $this->abp->decode($value)) as $line) {
			$line = trim($line);

			if ($line === '') {
				continue;
			}

			if (!preg_match('#^[a-z0-9_]+(/[a-z0-9_]+)*([./][a-z0-9_]+)?(/\*)?$|^\*$#i', $line) || strlen($line) > 96) {
				return false;
			}

			$routes[] = strtolower($line);
		}

		$routes = array_values(array_unique($routes));

		return count($routes) > self::MAX_ROUTES ? false : $routes;
	}

	/**
	 * For each of stores / customer_groups: true when the saved message was limited to IDs that no longer exist.
	 */
	private function orphanedTargets(array $message): array {
		$out = [];

		foreach (['stores' => $this->storeIds(), 'customer_groups' => $this->customerGroupIds()] as $key => $valid) {
			$saved = isset($message[$key]) && is_array($message[$key]) ? $message[$key] : [];
			$out[$key] = $saved && !array_intersect($saved, $valid);
		}

		return $out;
	}

	private function storeIds(): array {
		$this->load->model('setting/store');

		$ids = [0];
		foreach ($this->model_setting_store->getStores() as $store) {
			$ids[] = (int)$store['store_id'];
		}

		return $ids;
	}

	private function customerGroupIds(): array {
		$this->load->model('customer/customer_group');

		$ids = [];
		foreach ($this->model_customer_customer_group->getCustomerGroups() as $group) {
			$ids[] = (int)$group['customer_group_id'];
		}

		return $ids;
	}
}
