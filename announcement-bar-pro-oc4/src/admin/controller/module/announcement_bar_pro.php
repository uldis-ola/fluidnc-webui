<?php
namespace Opencart\Admin\Controller\Extension\AnnouncementBarPro\Module;
\defined('VERSION') || exit; // not callable directly
/**
 * Announcement Bar Pro — admin controller (OpenCart 4.0.x / 4.1.x)
 *
 * @version 2.3.0
 */
class AnnouncementBarPro extends \Opencart\System\Engine\Controller {
	public const VERSION       = '2.3.0';
	public const CODE          = 'module_announcement_bar_pro';
	public const EVENT_CODE    = 'announcement_bar_pro';
	public const ROUTE         = 'extension/announcement_bar_pro/module/announcement_bar_pro';
	public const MAX_TEXT      = 1000;
	public const MAX_RAW_TEXT  = 20000;
	public const MAX_LINK_TEXT = 64;
	public const MAX_ROUTES    = 50;
	public const MAX_TARGETS   = 200;     // categories / products per message
	public const MAX_IMPORT    = 1048576; // 1 MB
	public const MAX_IMPORT_MESSAGES = 500;
	public const STYLES        = 'solid,gradient,soft,minimal,glass';
	public const AB_MIN_VIEWS  = 100;

	/** Per-language text fields of a message. */
	public const TEXT_FIELDS = 'text,link_text,mobile_text,success_text,text_b,mobile_text_b,link_text_b';

	/** @var \Opencart\System\Library\Extension\AnnouncementBarPro\Sanitizer */
	private $abp;

	/** Validation errors (nested like the form: text[language_id]); flattened for the AJAX answer. */
	private array $error = [];

	private array $orphaned = ['stores' => false, 'customer_groups' => false];

	/* ------------------------------------------------------------------ *
	 * Page: message list + settings
	 * ------------------------------------------------------------------ */
	public function index(): void {
		$this->bootstrap();

		$data = $this->commonData();

		$data['save']      = $this->link(self::ROUTE . $this->sep() . 'save');
		$data['add']       = $this->link(self::ROUTE . $this->sep() . 'form');
		$data['delete']    = $this->link(self::ROUTE . $this->sep() . 'delete');
		$data['duplicate'] = $this->link(self::ROUTE . $this->sep() . 'duplicate');
		$data['reset']     = $this->link(self::ROUTE . $this->sep() . 'resetStats');
		$data['use_b']     = $this->link(self::ROUTE . $this->sep() . 'useB');
		$data['export']    = $this->link(self::ROUTE . $this->sep() . 'export');
		$data['import']    = $this->link(self::ROUTE . $this->sep() . 'import');
		$data['back']      = $this->link('marketplace/extension', '&type=module');
		$data['reload']    = $this->link(self::ROUTE, '&tab=messages');

		$stored = array_merge($this->model_extension_announcement_bar_pro_module_announcement_bar_pro->getDefaultSettings(), $this->model_setting_setting->getSetting(self::CODE));

		$data['setting'] = [];
		foreach ($this->settingKeys() as $key) {
			$data['setting'][$key] = $stored[self::CODE . '_' . $key];
		}

		$tab = isset($this->request->get['tab']) && is_string($this->request->get['tab']) ? $this->request->get['tab'] : '';
		$data['active_tab'] = $tab === 'settings' ? 'settings' : 'messages';
		$data['styles']     = $this->styleList();
		$data['messages']   = $this->listRows();

		$data['admin_css']   = $this->asset('admin/view/javascript/abp_admin.css');
		$data['admin_js']    = $this->asset('admin/view/javascript/abp_admin.js');
		$data['config_json'] = $this->jsonForHtml([
			'page'    => 'list',
			'oc'      => 4,
			'confirm' => [
				'reset'  => $this->language->get('text_confirm_reset'),
				'use_b'  => $this->language->get('text_confirm_use_b'),
				'delete' => $this->language->get('text_confirm')
			],
			'text'    => ['importing' => $this->language->get('text_importing'), 'error_import_file' => $this->language->get('error_import_file')]
		]);

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

			$lists = [
				'position'   => ['top', 'bottom'],
				'color_mode' => ['auto', 'custom'],
				'mode'       => ['rotate', 'marquee', 'stack'],
				'style'      => explode(',', self::STYLES),
				'transition' => ['fade', 'slide']
			];

			foreach ($lists as $key => $allowed) {
				if (!in_array($this->postValue($key), $allowed, true)) {
					$json['error'][$key] = $this->language->get('error_value');
				}
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

			foreach (['status', 'sticky', 'dismissible', 'allow_html', 'delete_data', 'arrows'] as $key) {
				$out[$key] = $this->postValue($key) !== '' && $this->postValue($key) !== '0' ? 1 : 0;
			}

			foreach (['interval', 'speed', 'font_size', 'padding', 'dismiss_days'] as $key) {
				$out[$key] = (int)$this->postValue($key);
			}

			foreach (['position', 'color_mode', 'mode', 'style', 'transition'] as $key) {
				$out[$key] = $this->postValue($key);
			}

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
		$data['color_auto']    = $this->config->get(self::CODE . '_color_mode') === 'auto';
		$data['orphaned']      = $this->orphanedTargets($message);
		$data['styles']        = $this->styleList();
		$data['module_style']  = $this->language->get('text_style_' . $this->styleValue($this->config->get(self::CODE . '_style'), 'solid'));
		$data['currency_code'] = $this->config->get('config_currency');

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
			'page_routes'     => '',
			'device'          => 'all',
			'type'            => 'standard',
			'threshold'       => '',
			'progress_bar'    => 1,
			'coupon'          => '',
			'ab_test'         => 0,
			'weekdays'        => '',
			'time_start'      => '',
			'time_end'        => '',
			'categories'      => [],
			'products'        => [],
			'style'           => '',
			'button_style'    => 'filled'
		];

		foreach ($defaults as $key => $default) {
			$data[$key] = array_key_exists($key, $message) ? $message[$key] : $default;
		}

		$data['end_all_day'] = !empty($message['date_end']) && substr($message['date_end'], 11, 8) === '23:59:59';
		// <input type="datetime-local"> wants "YYYY-MM-DDTHH:MM"
		$data['date_start']  = $data['date_start'] ? str_replace(' ', 'T', substr($data['date_start'], 0, 16)) : '';
		$data['date_end']    = $data['date_end'] ? str_replace(' ', 'T', substr($data['date_end'], 0, 16)) : '';
		$data['link']        = $this->abp->decode($data['link']);
		$data['threshold']   = $data['threshold'] !== '' && is_numeric($data['threshold']) ? rtrim(rtrim(number_format((float)$data['threshold'], 4, '.', ''), '0'), '.') : '';

		$data['weekday_list']   = $this->weekdayList((string)$data['weekdays']);
		$data['category_chips'] = $this->model_extension_announcement_bar_pro_module_announcement_bar_pro->getCategoryNames($this->abp->idList($data['categories']), (int)$this->config->get('config_language_id'));
		$data['product_chips']  = $this->model_extension_announcement_bar_pro_module_announcement_bar_pro->getProductNames($this->abp->idList($data['products']), (int)$this->config->get('config_language_id'));

		$data['message_description'] = [];
		foreach ($data['languages'] as $language) {
			$id  = (int)$language['language_id'];
			$row = $message['message_description'][$id] ?? [];

			foreach (explode(',', self::TEXT_FIELDS) as $field) {
				$data['message_description'][$id][$field] = isset($row[$field]) ? $this->abp->decode($row[$field]) : '';
			}
		}

		$data['admin_css']   = $this->asset('admin/view/javascript/abp_admin.css');
		$data['admin_js']    = $this->asset('admin/view/javascript/abp_admin.js');
		$data['config_json'] = $this->jsonForHtml($this->formConfig());

		$this->response->setOutput($this->load->view(self::ROUTE . '_form', $data));
	}

	/**
	 * Settings for the admin script: live preview (the shop's real bar script and styles), autocomplete URLs, texts.
	 */
	private function formConfig(): array {
		$catalog = defined('HTTP_CATALOG') ? HTTP_CATALOG : '../';
		$assets  = $catalog . 'extension/announcement_bar_pro/catalog/view/javascript/';

		$this->load->model('localisation/currency');
		$currency = $this->model_localisation_currency->getCurrencyByCode((string)$this->config->get('config_currency'));

		return [
			'page'     => 'form',
			'oc'       => 4,
			'assets'   => [
				'css' => $assets . 'announcement_bar_pro.css?v=' . self::VERSION,
				'pre' => $assets . 'announcement_bar_pro_pre.js?v=' . self::VERSION,
				'js'  => $assets . 'announcement_bar_pro.js?v=' . self::VERSION,
				'fa'  => $catalog . 'catalog/view/stylesheet/fonts/fontawesome/css/all.min.css'
			],
			'bar'      => $this->previewSettings(),
			'currency' => [
				'left'     => $currency ? html_entity_decode($currency['symbol_left'], ENT_QUOTES, 'UTF-8') : '',
				'right'    => $currency ? html_entity_decode($currency['symbol_right'], ENT_QUOTES, 'UTF-8') : '',
				'decimals' => $currency ? (int)$currency['decimal_place'] : 2,
				'point'    => $this->language->get('decimal_point') !== 'decimal_point' ? $this->language->get('decimal_point') : '.',
				'thousand' => $this->language->get('thousand_point') !== 'thousand_point' ? $this->language->get('thousand_point') : ''
			],
			'autocomplete' => [
				'category' => str_replace('&amp;', '&', $this->link('catalog/category' . $this->sep() . 'autocomplete')),
				'product'  => str_replace('&amp;', '&', $this->link('catalog/product' . $this->sep() . 'autocomplete'))
			],
			'text'     => [
				'copy'       => $this->language->get('text_copy'),
				'close'      => $this->language->get('text_close'),
				'remove'     => $this->language->get('button_remove'),
				'no_results' => $this->language->get('text_no_results'),
				'sample'     => $this->language->get('text_preview_sample')
			]
		];
	}

	/** URL of a file inside this extension's folder (served by the shop like its catalog files). */
	private function asset(string $path): string {
		return (defined('HTTP_CATALOG') ? HTTP_CATALOG : '../') . 'extension/announcement_bar_pro/' . $path . '?v=' . self::VERSION;
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
		} elseif (!$this->validateMessage($p, $languages)) {
			$json['error'] = $this->flatErrors();
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

	/**
	 * Errors for OpenCart 4's AJAX form: text[2] → "text_2" (its script shows it in #error-text-2).
	 */
	private function flatErrors(): array {
		$out = [];

		foreach ($this->error as $key => $value) {
			if (is_array($value)) {
				foreach ($value as $id => $text) {
					$out[$key . '_' . $id] = $text;
				}
			} else {
				$out[$key] = $value;
			}
		}

		return $out;
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
				$this->model_extension_announcement_bar_pro_module_announcement_bar_pro->deleteMessage((int)$message_id);
			}

			$this->session->data['abp_success'] = $this->language->get('text_success');
			$json['success'] = $this->language->get('text_success');
		}

		$this->json($json);
	}

	/* ------------------------------------------------------------------ *
	 * AJAX: duplicate one message (POST message_id). The copy is switched
	 * off and starts with zero statistics.
	 * ------------------------------------------------------------------ */
	public function duplicate(): void {
		$this->bootstrap();

		$json       = [];
		$message_id = $this->postedMessageId();

		if ($this->request->server['REQUEST_METHOD'] != 'POST') {
			$json['error'] = $this->language->get('error_value');
		} elseif (!$this->user->hasPermission('modify', self::ROUTE)) {
			$json['error'] = $this->language->get('error_permission');
		} elseif (!$message_id || !$this->model_extension_announcement_bar_pro_module_announcement_bar_pro->duplicateMessage($message_id)) {
			$json['error'] = $this->language->get('error_not_found');
		} else {
			$this->session->data['abp_success'] = $this->language->get('text_duplicated');
			$json['success'] = $this->language->get('text_duplicated');
		}

		$this->json($json);
	}

	/* ------------------------------------------------------------------ *
	 * AJAX: reset all counters of one message (POST message_id)
	 * ------------------------------------------------------------------ */
	public function resetStats(): void {
		$this->bootstrap();

		$json       = [];
		$message_id = $this->postedMessageId();
		$model      = $this->model_extension_announcement_bar_pro_module_announcement_bar_pro;

		if ($this->request->server['REQUEST_METHOD'] != 'POST') {
			$json['error'] = $this->language->get('error_value');
		} elseif (!$this->user->hasPermission('modify', self::ROUTE)) {
			$json['error'] = $this->language->get('error_permission');
		} elseif (!$message_id || !$model->getMessage($message_id)) {
			$json['error'] = $this->language->get('error_not_found');
		} else {
			$model->resetStatistics($message_id);
			$this->session->data['abp_success'] = $this->language->get('text_stats_reset');
			$json['success'] = $this->language->get('text_stats_reset');
		}

		$this->json($json);
	}

	/**
	 * Rows of the message list: status, badges (type, coupon, A/B, schedule, targeting) and statistics.
	 */
	private function listRows() {
		$language_id = (int)$this->config->get('config_language_id');
		$languages   = $this->model_localisation_language->getLanguages();
		$now         = date('Y-m-d H:i:s');
		$currency    = $this->config->get('config_currency');
		$rows        = array();

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

			$text = $this->abp->plain($this->abp->decode((string)$message['text']));

			// Languages without text fall back to the store's default language in the shop.
			$with_text = array_map('intval', array_filter(explode(',', (string)$message['languages_with_text'])));
			$missing   = array();
			foreach ($languages as $language) {
				if (!in_array((int)$language['language_id'], $with_text, true)) {
					$missing[] = $language['name'];
				}
			}

			$ab       = !empty($message['ab_test']);
			$views    = (int)$message['views'];
			$clicks   = (int)$message['clicks'];
			$views_b  = (int)$message['views_b'];
			$clicks_b = (int)$message['clicks_b'];
			$ctr_a    = $this->ctr($views, $clicks);
			$ctr_b    = $this->ctr($views_b, $clicks_b);
			$better   = '';

			if ($ab && $views >= self::AB_MIN_VIEWS && $views_b >= self::AB_MIN_VIEWS && $clicks * $views_b !== $clicks_b * $views) {
				$better = $clicks * $views_b > $clicks_b * $views ? 'a' : 'b';
			}

			$schedule = $this->scheduleText($message['weekdays'], $message['time_start'], $message['time_end']);

			$rows[] = array(
				'message_id' => (int)$message['message_id'],
				'text'       => function_exists('mb_strimwidth') ? mb_strimwidth($text, 0, 90, '…', 'UTF-8') : substr($text, 0, 90),
				'bg_color'   => $this->abp->color($message['bg_color'], '#222222'),
				'text_color' => $this->abp->color($message['text_color'], '#ffffff'),
				'date_start' => $message['date_start'] ? substr($message['date_start'], 0, 16) : '',
				'date_end'   => $message['date_end'] ? substr($message['date_end'], 0, 16) : '',
				'sort_order' => (int)$message['sort_order'],
				'state'      => $state,
				'state_text' => $this->language->get('text_state_' . $state),
				'missing'    => $missing ? sprintf($this->language->get('text_missing_language'), implode(', ', $missing)) : '',
				'device'     => in_array($message['device'], array('desktop', 'mobile'), true) ? $message['device'] : 'all',
				'fs'         => $message['type'] === 'free_shipping' ? sprintf($this->language->get('text_badge_fs'), $this->currency->format((float)$message['threshold'], $currency)) : '',
				'coupon'     => $message['coupon'] !== '' ? sprintf($this->language->get('text_badge_coupon'), $message['coupon']) : '',
				'schedule'   => $schedule !== '' ? sprintf($this->language->get('text_badge_schedule'), $schedule) : '',
				'targeting'  => ($message['categories'] || $message['products']) ? $this->language->get('text_badge_targeting') : '',
				'style'      => $message['style'],
				'ab'         => $ab,
				'views'      => $views,
				'clicks'     => $clicks,
				'copies'     => (int)$message['copies'],
				'ctr'        => $ctr_a,
				'views_b'    => $views_b,
				'clicks_b'   => $clicks_b,
				'copies_b'   => (int)$message['copies_b'],
				'ctr_b'      => $ctr_b,
				'better'     => $better,
				'has_stats'  => $views || $clicks || (int)$message['copies'] || $views_b || $clicks_b || (int)$message['copies_b'],
				'edit'       => $this->link(self::ROUTE . $this->sep() . 'form', '&message_id=' . (int)$message['message_id'])
			);
		}

		return $rows;
	}

	/** Module settings the preview needs. */
	private function previewSettings() {
		$get = function ($key, $default) {
			$value = $this->config->get(self::CODE . '_' . $key);

			return $value === null ? $default : $value;
		};

		return array(
			'position'    => $get('position', 'top') === 'bottom' ? 'bottom' : 'top',
			'font_size'   => (int)$get('font_size', 14),
			'padding'     => (int)$get('padding', 8),
			'color_mode'  => $get('color_mode', 'auto') === 'auto' ? 'auto' : 'custom',
			'style'       => $this->styleValue($get('style', 'solid'), 'solid'),
			'transition'  => $get('transition', 'fade') === 'slide' ? 'slide' : 'fade',
			'arrows'      => (bool)$get('arrows', 0),
			'dismissible' => (bool)$get('dismissible', 1)
		);
	}

	public function useB() {
		$this->bootstrap();

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] != 'POST') {
			$json['error'] = $this->language->get('error_value');
		} elseif (!$this->user->hasPermission('modify', self::ROUTE)) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$message_id = $this->postedMessageId();

			if (!$message_id || !$this->model_extension_announcement_bar_pro_module_announcement_bar_pro->getMessage($message_id)) {
				$json['error'] = $this->language->get('error_not_found');
			} elseif (!$this->model_extension_announcement_bar_pro_module_announcement_bar_pro->useVariantB($message_id)) {
				$json['error'] = $this->language->get('error_ab_off');
			} else {
				$this->session->data['abp_success'] = $this->language->get('text_used_b');
				$json['success'] = $this->language->get('text_used_b');
			}
		}

		$this->json($json);
	}

	public function export() {
		$this->bootstrap();

		$messages = array();

		foreach ($this->model_extension_announcement_bar_pro_module_announcement_bar_pro->getMessagesForExport() as $message) {
			$messages[] = $this->exportMessage($message);
		}

		$out = array(
			'format'   => 'announcement_bar_pro',
			'version'  => self::VERSION,
			'schema'   => \Opencart\Admin\Model\Extension\AnnouncementBarPro\Module\AnnouncementBarPro::SCHEMA_VERSION,
			'exported' => date('c'),
			'messages' => $messages
		);

		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		$this->response->addHeader('Content-Disposition: attachment; filename="announcement-bar-pro-' . date('Ymd-Hi') . '.json"');
		$this->response->addHeader('Cache-Control: no-store');
		$this->response->addHeader('X-Content-Type-Options: nosniff');
		$this->response->setOutput(json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	public function import() {
		$this->bootstrap();

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] != 'POST') {
			$json['error'] = $this->language->get('error_value');
		} elseif (!$this->user->hasPermission('modify', self::ROUTE)) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$messages = $this->importedMessages();

			if ($messages === false) {
				$json['error'] = $this->language->get('error_import_file');
			} elseif ($messages === null) {
				$json['error'] = $this->language->get('error_import_format');
			} else {
				$result = $this->importMessages($messages);
				$text   = sprintf($this->language->get('text_import_done'), $result['created'], $result['skipped']);

				if ($result['unknown']) {
					$text .= ' ' . sprintf($this->language->get('text_import_languages'), implode(', ', $result['unknown']));
				}

				$this->session->data['abp_success'] = $text;
				$json['success'] = $text;
			}
		}

		$this->json($json);
	}

	/**
	 * Messages from the uploaded file: false = no / too big / unreadable file, null = not our format.
	 */
	private function importedMessages() {
		$file = isset($this->request->files['file']) && is_array($this->request->files['file']) ? $this->request->files['file'] : array();

		if (empty($file['tmp_name']) || !is_string($file['tmp_name']) || !empty($file['error']) || !is_uploaded_file($file['tmp_name']) || (int)$file['size'] <= 0 || (int)$file['size'] > self::MAX_IMPORT || filesize($file['tmp_name']) > self::MAX_IMPORT) {
			return false;
		}

		$content = file_get_contents($file['tmp_name'], false, null, 0, self::MAX_IMPORT + 1);

		if (!is_string($content) || strlen($content) > self::MAX_IMPORT) {
			return false;
		}

		$content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // UTF-8 BOM
		$data    = json_decode($content, true, 16);

		if (!is_array($data) || !isset($data['format']) || $data['format'] !== 'announcement_bar_pro' || !isset($data['messages']) || !is_array($data['messages']) || count($data['messages']) > self::MAX_IMPORT_MESSAGES) {
			return null;
		}

		return $data['messages'];
	}

	/**
	 * Creates the messages (switched off). Unknown languages are skipped; invalid messages are counted as skipped.
	 */
	private function importMessages(array $messages) {
		$languages = $this->model_localisation_language->getLanguages();
		$by_code   = array();

		foreach ($languages as $language) {
			$by_code[$language['code']] = (int)$language['language_id'];
		}

		$result = array('created' => 0, 'skipped' => 0, 'unknown' => array());

		foreach ($messages as $message) {
			if (!is_array($message)) {
				$result['skipped']++;
				continue;
			}

			$post = $this->importPost($message, $by_code, $result['unknown']);

			$this->error    = array();
			$this->orphaned = array('stores' => false, 'customer_groups' => false);

			$p = $this->normalizeMessagePost($post);

			if ($this->validateMessage($p, $languages)) {
				$clean = $this->sanitizeMessage($p, $languages);
				$clean['status'] = 0;
				$this->model_extension_announcement_bar_pro_module_announcement_bar_pro->addMessage($clean);
				$result['created']++;
			} else {
				$result['skipped']++;
			}
		}

		$this->error = array();
		$result['unknown'] = array_values(array_unique($result['unknown']));

		return $result;
	}

	/**
	 * One exported message → the same shape as the form POST (strings and lists only; never trusted further).
	 */
	private function importPost(array $m, array $by_code, array &$unknown) {
		$post = array();

		foreach (array('sort_order', 'date_start', 'date_end', 'bg_color', 'text_color', 'link', 'icon', 'page_mode', 'page_routes', 'device', 'type', 'threshold', 'coupon', 'time_start', 'time_end', 'style', 'button_style') as $key) {
			if (isset($m[$key]) && is_scalar($m[$key])) {
				$post[$key] = (string)$m[$key];
			}
		}

		foreach (array('link_new_tab', 'countdown', 'progress_bar', 'ab_test') as $key) {
			if (!empty($m[$key]) && is_scalar($m[$key])) {
				$post[$key] = '1';
			}
		}

		$weekdays         = isset($m['weekdays']) && is_scalar($m['weekdays']) ? preg_replace('/[^1-7]/', '', (string)$m['weekdays']) : '';
		$post['weekdays'] = $weekdays === '' ? array() : str_split($weekdays);

		foreach (array('stores', 'customer_groups', 'categories', 'products') as $key) {
			$post[$key] = array();

			if (isset($m[$key]) && is_array($m[$key])) {
				foreach ($m[$key] as $id) {
					if (is_scalar($id)) {
						$post[$key][] = (string)$id;
					}
				}
			}
		}

		$post['message_description'] = array();

		if (isset($m['texts']) && is_array($m['texts'])) {
			foreach ($m['texts'] as $code => $row) {
				if (!is_array($row)) {
					continue;
				}

				if (!isset($by_code[$code])) {
					$unknown[] = $this->abp->plain((string)$code);
					continue;
				}

				foreach (explode(',', self::TEXT_FIELDS) as $field) {
					$post['message_description'][$by_code[$code]][$field] = isset($row[$field]) && is_scalar($row[$field]) ? (string)$row[$field] : '';
				}
			}
		}

		return $post;
	}

	/** Whitelisted export fields; statistics are left out. */
	private function exportMessage(array $m) {
		$out = array();

		foreach (array('status', 'sort_order', 'link_new_tab', 'countdown', 'progress_bar', 'ab_test') as $key) {
			$out[$key] = (int)$m[$key];
		}

		foreach (array('date_start', 'date_end') as $key) {
			$out[$key] = $m[$key] ? (string)$m[$key] : '';
		}

		foreach (array('bg_color', 'text_color', 'link', 'icon', 'page_mode', 'page_routes', 'device', 'type', 'coupon', 'weekdays', 'time_start', 'time_end', 'style', 'button_style') as $key) {
			$out[$key] = (string)$m[$key];
		}

		$out['threshold'] = rtrim(rtrim(number_format((float)$m['threshold'], 4, '.', ''), '0'), '.');

		foreach (array('stores', 'customer_groups', 'categories', 'products') as $key) {
			$out[$key] = array_values(array_map('intval', (array)$m[$key]));
		}

		$out['texts'] = array();

		foreach ($m['texts'] as $code => $row) {
			foreach (explode(',', self::TEXT_FIELDS) as $field) {
				$out['texts'][$code][$field] = $this->abp->decode(isset($row[$field]) ? $row[$field] : '');
			}
		}

		return $out;
	}

	/* ------------------------------------------------------------------ *
	 * Install / uninstall (called by extension/module, which checks
	 * "modify" on extension/module and adds/removes our permissions)
	 * ------------------------------------------------------------------ */
	public function install(): void {
		if (!$this->user->hasPermission('modify', 'extension/module')) {
			return;
		}

		$this->load->model(self::ROUTE);
		$this->model_extension_announcement_bar_pro_module_announcement_bar_pro->ensureSchema();
		$this->model_extension_announcement_bar_pro_module_announcement_bar_pro->registerEvents(true);

		// Works out of the box: a fresh install (no messages yet) is switched on; kept messages stay off.
		if (!$this->model_extension_announcement_bar_pro_module_announcement_bar_pro->getTotalMessages()) {
			$this->load->model('setting/setting');
			$this->model_setting_setting->editValue(self::CODE, self::CODE . '_status', '1');
		}
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
	protected function validateMessage(array $p, array $languages) {
		if (!$this->user->hasPermission('modify', self::ROUTE)) {
			$this->error['warning'] = $this->language->get('error_permission');
			return false;
		}

		$allow_html = (bool)$this->config->get(self::CODE . '_allow_html');
		$descs      = isset($p['message_description']) && is_array($p['message_description']) ? $p['message_description'] : array();
		$has_text   = false;
		$has_text_b = false;
		$free_ship  = isset($p['type']) && $p['type'] === 'free_shipping';
		$ab_test    = !empty($p['ab_test']);

		foreach ($languages as $language) {
			$id  = (int)$language['language_id'];
			$row = isset($descs[$id]) && is_array($descs[$id]) ? $descs[$id] : array();

			// Texts (same rules for A, B, phone and success texts: sanitized length, HTML mode).
			foreach (array('text', 'mobile_text', 'success_text', 'text_b', 'mobile_text_b') as $field) {
				$value = isset($row[$field]) && is_string($row[$field]) ? $this->abp->decode($row[$field]) : '';

				if (strlen($value) > self::MAX_RAW_TEXT || $this->strlen($allow_html ? $this->abp->html($value) : $this->abp->plain($value)) > self::MAX_TEXT) {
					$this->error[$field][$id] = sprintf($this->language->get('error_text_length'), self::MAX_TEXT);
				}
			}

			foreach (array('link_text', 'link_text_b') as $field) {
				$value = isset($row[$field]) && is_string($row[$field]) ? $this->abp->plain($this->abp->decode($row[$field])) : '';

				if ($this->strlen($value) > self::MAX_LINK_TEXT) {
					$this->error[$field][$id] = sprintf($this->language->get('error_text_length'), self::MAX_LINK_TEXT);
				}
			}

			$text = $this->filled(isset($row['text']) ? $row['text'] : '', $allow_html);

			if ($text) {
				$has_text = true;

				// Free shipping: each language with a progress text needs the text shown once the amount is reached.
				if ($free_ship && !$this->filled(isset($row['success_text']) ? $row['success_text'] : '', $allow_html) && !isset($this->error['success_text'][$id])) {
					$this->error['success_text'][$id] = $this->language->get('error_success_text');
				}
			}

			if ($this->filled(isset($row['text_b']) ? $row['text_b'] : '', $allow_html)) {
				$has_text_b = true;
			}
		}

		$first = $languages ? (int)reset($languages)['language_id'] : 0;

		if (!$has_text) {
			$this->error['text'][$first] = $this->language->get('error_text_required');
		}

		if ($ab_test && !$has_text_b) {
			$this->error['text_b'][$first] = $this->language->get('error_text_b');
		}

		foreach (array('bg_color', 'text_color') as $key) {
			if (!$this->abp->color(isset($p[$key]) ? $p[$key] : '')) {
				$this->error[$key] = $this->language->get('error_color');
			}
		}

		$link = isset($p['link']) ? $this->abp->decode($p['link']) : '';
		if ($link !== '' && $this->abp->url($link) === '') {
			$this->error['link'] = $this->language->get('error_link');
		}

		$icon = isset($p['icon']) ? trim((string)$p['icon']) : '';
		if ($icon !== '' && $this->abp->icon($icon) === '') {
			$this->error['icon'] = $this->language->get('error_icon');
		}

		$start = $this->parseDate(isset($p['date_start']) ? $p['date_start'] : '');
		$end   = $this->parseEndDate($p);

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

		// Unknown store / customer-group IDs are rejected (an all-invalid list must not turn into "all").
		foreach (array('stores' => $this->storeIds(), 'customer_groups' => $this->customerGroupIds()) as $key => $valid) {
			$raw    = isset($p[$key]) && is_array($p[$key]) ? $p[$key] : array();
			$posted = array_map('intval', $raw);

			if (array_diff($posted, $valid) || preg_grep('/^\d{1,9}$/', array_map('strval', $raw), PREG_GREP_INVERT)) {
				$this->error[$key] = $this->language->get('error_value');
			}
		}

		// A message limited to stores/groups that were all deleted must not silently become "all".
		foreach ($this->orphaned as $key => $is_orphaned) {
			if ($is_orphaned && empty($p[$key])) {
				$this->error['orphan_' . $key] = $this->language->get('error_orphan_' . $key);
				$this->error[$key] = $this->language->get('error_orphan_' . $key);
			}
		}

		if (!in_array(isset($p['device']) ? $p['device'] : 'all', array('all', 'desktop', 'mobile'), true)) {
			$this->error['device'] = $this->language->get('error_value');
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

		$this->validateMessage230($p);

		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('error_form');
		}

		return !$this->error;
	}

	/** 2.3.0 fields: type, threshold, coupon, button / style, weekly schedule, category / product targeting. */
	private function validateMessage230(array $p) {
		$type = isset($p['type']) ? $p['type'] : 'standard';

		if (!in_array($type, array('standard', 'free_shipping'), true)) {
			$this->error['type'] = $this->language->get('error_value');
		} elseif ($type === 'free_shipping') {
			$amount = $this->abp->amount(isset($p['threshold']) ? $p['threshold'] : '');

			if ($amount === false || (float)$amount <= 0) {
				$this->error['threshold'] = $this->language->get('error_threshold');
			}
		}

		$coupon = isset($p['coupon']) ? trim($p['coupon']) : '';
		if ($coupon !== '' && $this->abp->coupon($coupon) === '') {
			$this->error['coupon'] = $this->language->get('error_coupon');
		}

		if (!in_array(isset($p['button_style']) ? $p['button_style'] : 'filled', array('filled', 'outline', 'link'), true)) {
			$this->error['button_style'] = $this->language->get('error_value');
		}

		if (!in_array(isset($p['style']) ? $p['style'] : '', array_merge(array(''), explode(',', self::STYLES)), true)) {
			$this->error['style'] = $this->language->get('error_value');
		}

		$days = isset($p['weekdays']) ? $p['weekdays'] : array();
		if (preg_grep('/^[1-7]$/', array_map('strval', $days), PREG_GREP_INVERT)) {
			$this->error['weekdays'] = $this->language->get('error_value');
		}

		$t1 = isset($p['time_start']) ? trim($p['time_start']) : '';
		$t2 = isset($p['time_end']) ? trim($p['time_end']) : '';
		if (($t1 !== '' || $t2 !== '') && ($this->abp->clockTime($t1) === '' || $this->abp->clockTime($t2) === '' || $t1 === $t2)) {
			$this->error['time_start'] = $this->language->get('error_time');
		}

		// Unknown category / product IDs are rejected (an all-invalid list must not turn into "everywhere").
		$language_id = (int)$this->config->get('config_language_id');
		foreach (array('categories' => 'getCategoryNames', 'products' => 'getProductNames') as $key => $method) {
			$raw = isset($p[$key]) ? $p[$key] : array();

			if (!$raw) {
				continue;
			}

			$ids = $this->abp->idList($raw, self::MAX_TARGETS + 1);

			if (count($ids) > self::MAX_TARGETS || count($ids) !== count(array_unique(array_map('intval', $raw))) || preg_grep('/^\d{1,10}$/', array_map('strval', $raw), PREG_GREP_INVERT)
				|| count($this->model_extension_announcement_bar_pro_module_announcement_bar_pro->$method($ids, $language_id)) !== count($ids)) {
				$this->error[$key] = $this->language->get('error_' . $key);
			}
		}
	}

	protected function sanitizeMessage(array $p, array $languages) {
		$allow_html = (bool)$this->config->get(self::CODE . '_allow_html');
		$descs      = isset($p['message_description']) && is_array($p['message_description']) ? $p['message_description'] : array();
		$ab_test    = !empty($p['ab_test']);
		$free_ship  = isset($p['type']) && $p['type'] === 'free_shipping';

		$description = array();
		foreach ($languages as $language) {
			$id  = (int)$language['language_id'];
			$row = isset($descs[$id]) && is_array($descs[$id]) ? $descs[$id] : array();
			$get = function ($field) use ($row) {
				return isset($row[$field]) && is_string($row[$field]) ? $this->abp->decode($row[$field]) : '';
			};
			$html = function ($value) use ($allow_html) {
				$value = $allow_html ? $this->abp->html($value) : $this->abp->plain($value);

				// store4byte: 4.0.0.x talks 3-byte utf8 to MySQL (emoji would become "?"); 4.0.1+ stores them as they are.
				return trim(strip_tags($value)) === '' ? '' : $this->store4byte($value);
			};
			$button = function ($value) {
				return $this->store4byte($this->substr($this->abp->plain($value), self::MAX_LINK_TEXT));
			};

			$description[$id] = array(
				'text'          => $this->store4byte($allow_html ? $this->abp->html($get('text')) : $this->abp->plain($get('text'))),
				'link_text'     => $button($get('link_text')),
				'mobile_text'   => $html($get('mobile_text')),
				'success_text'  => $free_ship ? $html($get('success_text')) : '',
				'text_b'        => $ab_test ? $html($get('text_b')) : '',
				'mobile_text_b' => $ab_test ? $html($get('mobile_text_b')) : '',
				'link_text_b'   => $ab_test ? $button($get('link_text_b')) : ''
			);
		}

		$mode   = isset($p['page_mode']) ? (string)$p['page_mode'] : 'all';
		$routes = in_array($mode, array('include', 'exclude'), true) ? implode("\n", (array)$this->parseRoutes(isset($p['page_routes']) ? $p['page_routes'] : '')) : '';
		$start  = $this->parseDate(isset($p['date_start']) ? $p['date_start'] : '');
		$end    = $this->parseEndDate($p);
		$t1     = $this->abp->clockTime(isset($p['time_start']) ? $p['time_start'] : '');
		$t2     = $this->abp->clockTime(isset($p['time_end']) ? $p['time_end'] : '');
		$amount = $this->abp->amount(isset($p['threshold']) ? $p['threshold'] : '');

		return array(
			'status'              => !empty($p['status']) ? 1 : 0,
			'sort_order'          => isset($p['sort_order']) ? (int)$p['sort_order'] : 0,
			'date_start'          => $start ? $start : '',
			'date_end'            => $end ? $end : '',
			'bg_color'            => $this->abp->color(isset($p['bg_color']) ? $p['bg_color'] : '', '#222222'),
			'text_color'          => $this->abp->color(isset($p['text_color']) ? $p['text_color'] : '', '#ffffff'),
			'link'                => $this->abp->url($this->abp->decode(isset($p['link']) ? $p['link'] : '')),
			'link_new_tab'        => !empty($p['link_new_tab']) ? 1 : 0,
			'icon'                => $this->abp->icon(isset($p['icon']) ? $p['icon'] : ''),
			'countdown'           => !empty($p['countdown']) ? 1 : 0,
			'stores'              => $this->existingIds(isset($p['stores']) ? $p['stores'] : array(), $this->storeIds()),
			'customer_groups'     => $this->existingIds(isset($p['customer_groups']) ? $p['customer_groups'] : array(), $this->customerGroupIds()),
			'page_mode'           => $mode,
			'page_routes'         => $routes,
			'device'              => isset($p['device']) && in_array($p['device'], array('desktop', 'mobile'), true) ? $p['device'] : 'all',
			'type'                => $free_ship ? 'free_shipping' : 'standard',
			'threshold'           => $free_ship && $amount !== false ? $amount : '0',
			'progress_bar'        => !empty($p['progress_bar']) ? 1 : 0,
			'coupon'              => $this->abp->coupon(isset($p['coupon']) ? $p['coupon'] : ''),
			'ab_test'             => $ab_test ? 1 : 0,
			'weekdays'            => $this->abp->weekdays(isset($p['weekdays']) ? $p['weekdays'] : array()),
			'time_start'          => $t1 !== '' && $t2 !== '' && $t1 !== $t2 ? $t1 : '',
			'time_end'            => $t1 !== '' && $t2 !== '' && $t1 !== $t2 ? $t2 : '',
			'categories'          => $this->abp->idList(isset($p['categories']) ? $p['categories'] : array(), self::MAX_TARGETS),
			'products'            => $this->abp->idList(isset($p['products']) ? $p['products'] : array(), self::MAX_TARGETS),
			'style'               => isset($p['style']) && in_array($p['style'], explode(',', self::STYLES), true) ? $p['style'] : '',
			'button_style'        => isset($p['button_style']) && in_array($p['button_style'], array('filled', 'outline', 'link'), true) ? $p['button_style'] : 'filled',
			'message_description' => $description
		);
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

	/** True when the (decoded) text still has visible text after sanitizing. */
	private function filled($value, $allow_html) {
		$value = is_string($value) ? $this->abp->decode($value) : '';

		return trim(strip_tags($allow_html ? $this->abp->html($value) : $this->abp->plain($value))) !== '';
	}

	/**
	 * Scalars must be strings, lists must be arrays — crafted array input is neutralised here,
	 * so later casts never raise "Array to string conversion".
	 */
	private function normalizeMessagePost(array $post) {
		$out = array();

		foreach (array('status', 'sort_order', 'date_start', 'date_end', 'bg_color', 'text_color', 'link', 'link_new_tab', 'icon', 'countdown', 'page_mode', 'page_routes', 'end_all_day', 'device', 'type', 'threshold', 'progress_bar', 'coupon', 'ab_test', 'time_start', 'time_end', 'style', 'button_style') as $key) {
			if (isset($post[$key])) {
				$out[$key] = is_string($post[$key]) ? $post[$key] : '';
			}
		}

		foreach (array('stores', 'customer_groups', 'weekdays', 'categories', 'products') as $key) {
			if (isset($post[$key])) {
				$out[$key] = is_array($post[$key]) ? array_values(array_filter($post[$key], 'is_scalar')) : array();
			}
		}

		$out['message_description'] = array();
		if (isset($post['message_description']) && is_array($post['message_description'])) {
			foreach ($post['message_description'] as $language_id => $row) {
				if (is_array($row)) {
					foreach (explode(',', self::TEXT_FIELDS) as $field) {
						$out['message_description'][(int)$language_id][$field] = isset($row[$field]) && is_string($row[$field]) ? $row[$field] : '';
					}
				}
			}
		}

		return $out;
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

	private function store4byte(string $value): string {
		return version_compare(VERSION, '4.0.1.0', '<') ? $this->abp->encode4byte($value) : $value;
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

	/** JSON that is safe inside <script type="application/json"> (no "<", "&", quotes as raw characters). */
	private function jsonForHtml(array $data) {
		return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
	}

	private function settingKeys() {
		return array('status', 'position', 'sticky', 'mode', 'interval', 'speed', 'font_size', 'padding', 'dismissible', 'dismiss_days', 'allow_html', 'default_bg_color', 'default_text_color', 'delete_data', 'color_mode', 'style', 'transition', 'arrows');
	}

	private function styleList() {
		$out = array();

		foreach (explode(',', self::STYLES) as $style) {
			$out[] = array('value' => $style, 'name' => $this->language->get('text_style_' . $style), 'help' => $this->language->get('help_style_' . $style));
		}

		return $out;
	}

	private function styleValue($value, $default) {
		return in_array($value, explode(',', self::STYLES), true) ? $value : $default;
	}

	/** Weekday checkboxes (ISO 1 = Monday) with their state. */
	private function weekdayList($weekdays) {
		$out = array();

		for ($day = 1; $day <= 7; $day++) {
			$out[] = array('value' => $day, 'name' => $this->language->get('text_wd_' . $day), 'checked' => $weekdays !== '' && strpos($weekdays, (string)$day) !== false);
		}

		return $out;
	}

	/** "Mon, Tue · 09:00–17:00" for the list; '' without a weekly schedule. */
	private function scheduleText($weekdays, $t1, $t2) {
		$weekdays = $this->abp->weekdays($weekdays);
		$t1       = $this->abp->clockTime($t1);
		$t2       = $this->abp->clockTime($t2);
		$parts    = array();

		if ($weekdays === '' && $t1 === '') {
			return '';
		}

		if ($weekdays !== '') {
			$names = array();
			foreach (str_split($weekdays) as $day) {
				$names[] = $this->language->get('text_wd_' . $day);
			}
			$parts[] = implode(', ', $names);
		} else {
			$parts[] = $this->language->get('text_every_day');
		}

		if ($t1 !== '' && $t2 !== '') {
			$parts[] = $t1 . '–' . $t2;
		}

		return implode(' · ', $parts);
	}

	private function postValue($key) {
		$full = self::CODE . '_' . $key;

		return isset($this->request->post[$full]) && is_string($this->request->post[$full]) ? trim($this->request->post[$full]) : '';
	}

	private function intInRange(string $value, int $min, int $max): bool {
		return (bool)preg_match('/^\d{1,4}$/', $value) && (int)$value >= $min && (int)$value <= $max;
	}

	/**
	 * End date; with "until end of day" ticked the time part is replaced by 23:59:59.
	 */
	private function parseEndDate(array $p) {
		$value = isset($p['date_end']) ? (string)$p['date_end'] : '';

		if (!empty($p['end_all_day']) && preg_match('/^\s*(\d{4}-\d{2}-\d{2})/', $value, $m)) {
			$value = $m[1];
		}

		return $this->parseDate($value, true);
	}

	/**
	 * '' → '' (no limit); valid → 'Y-m-d H:i:00'; invalid → false.
	 * A date without time as END date means the end of that day (23:59:59).
	 */
	private function parseDate($value, $is_end = false) {
		$value = trim(str_replace('T', ' ', (string)$value));

		if ($value === '') {
			return '';
		}

		foreach (array('Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d') as $format) {
			$date = DateTime::createFromFormat('!' . $format, $value);
			if ($date && $date->format($format) === $value) {
				if ($format === 'Y-m-d H:i:s' && $is_end && $date->format('H:i:s') === '23:59:59') {
					return $value; // exported "until the end of the day"
				}

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

	/** Keeps only IDs that really exist (crafted POSTs could send 999 / -5). */
	private function existingIds($posted, array $valid) {
		$posted = is_array($posted) ? array_map('intval', array_filter($posted, 'is_scalar')) : array();

		return array_values(array_unique(array_intersect($posted, $valid)));
	}

	/**
	 * For each of stores / customer_groups: true when the saved message was limited to IDs that no longer exist.
	 */
	private function orphanedTargets(array $message){
		$out = array();

		foreach (array('stores' => $this->storeIds(), 'customer_groups' => $this->customerGroupIds()) as $key => $valid) {
			$saved = isset($message[$key]) && is_array($message[$key]) ? $message[$key] : array();
			$out[$key] = $saved && !array_intersect($saved, $valid);
		}

		return $out;
	}

	private function storeIds() {
		$this->load->model('setting/store');

		$ids = array(0);
		foreach ($this->model_setting_store->getStores() as $store) {
			$ids[] = (int)$store['store_id'];
		}

		return $ids;
	}

	private function customerGroupIds() {
		$this->load->model('customer/customer_group');

		$ids = array();
		foreach ($this->model_customer_customer_group->getCustomerGroups() as $group) {
			$ids[] = (int)$group['customer_group_id'];
		}

		return $ids;
	}

	/** message_id from POST: digits only, else 0. */
	private function postedMessageId() {
		$raw = isset($this->request->post['message_id']) && is_string($this->request->post['message_id']) ? $this->request->post['message_id'] : '';

		return preg_match('/^\d{1,10}$/', $raw) ? (int)$raw : 0;
	}

	/** Click-through rate in % with one decimal; '' while there are no views. */
	private function ctr($views, $clicks) {
		$views = (int)$views;

		return $views > 0 ? number_format(min(100, (int)$clicks * 100 / $views), 1, '.', '') : '';
	}

	private function strlen($value) {
		return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
	}

	private function substr($value, $length) {
		return function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length);
	}
}
