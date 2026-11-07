<?php
defined('VERSION') || exit; // not callable directly (in the universal package these files also sit, unused, inside OpenCart 4)
/**
 * Announcement Bar Pro — admin controller (OpenCart 3.0.x)
 * @version 2.4.1
 */
class ControllerExtensionModuleAnnouncementBarPro extends Controller {
	const VERSION    = '2.4.1';
	const CODE       = 'module_announcement_bar_pro';
	const EVENT_CODE = 'announcement_bar_pro';
	const ROUTE      = 'extension/module/announcement_bar_pro';
	const MAX_TEXT   = 1000;
	const MAX_LINK_TEXT = 64;

	const MAX_RAW_TEXT = 20000;  // hard cap on submitted text before it is parsed/sanitized
	const MAX_ROUTES   = 50;
	const MAX_TARGETS  = 200;    // categories / products per message
	const MAX_IMPORT   = 1048576; // 1 MB
	const MAX_IMPORT_MESSAGES = 500;
	const STYLES       = 'solid,gradient,soft,minimal,glass';
	const AB_MIN_VIEWS = 100;
	const MAX_CSS      = 10000;

	/** Per-language text fields of a message. */
	const TEXT_FIELDS = 'text,link_text,mobile_text,success_text,text_b,mobile_text_b,link_text_b';

	private $error = array();
	private $orphaned = array('stores' => false, 'customer_groups' => false);

	/** @var \Announcementbarpro\Sanitizer  Declared property: kept out of the shared registry. */
	private $abp;

	/** @var \Announcementbarpro\Features  2.4.0 options, targeting and templates (shared with the catalog). */
	private $fx;

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
		$data['duplicate']  = $this->link(self::ROUTE . '/duplicate');
		$data['reset']      = $this->link(self::ROUTE . '/resetStats');
		$data['use_b']      = $this->link(self::ROUTE . '/useB');
		$data['export']     = $this->link(self::ROUTE . '/export');
		$data['import']     = $this->link(self::ROUTE . '/import');
		$data['reload']     = $this->link(self::ROUTE, '&tab=messages');
		$data['export_subscribers'] = $this->link(self::ROUTE . '/exportSubscribers');
		$data['erase_subscriber']   = $this->link(self::ROUTE . '/eraseSubscriber');
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

		$tab = isset($this->request->get['tab']) && is_string($this->request->get['tab']) ? $this->request->get['tab'] : '';
		$data['active_tab'] = ($tab === 'settings' || $post) ? 'settings' : 'messages';
		$data['styles']     = $this->styleList();
		$data = array_merge($data, $this->settingsExtra());

		$data['messages'] = $this->listRows();

		$data['admin_css'] = 'view/javascript/announcement_bar_pro/abp_admin.css?v=' . self::VERSION;
		$data['admin_js']  = 'view/javascript/announcement_bar_pro/abp_admin.js?v=' . self::VERSION;
		$data['config_json'] = $this->jsonForHtml(array(
			'page'    => 'list',
			'oc'      => 3,
			'confirm' => array(
				'reset'  => $this->language->get('text_confirm_reset'),
				'use_b'  => $this->language->get('text_confirm_use_b'),
				'delete' => $this->language->get('text_confirm'),
				'erase'  => $this->language->get('text_confirm_erase')
			),
			'text'    => array('importing' => $this->language->get('text_importing'), 'error_import_file' => $this->language->get('error_import_file'))
		));

		$this->response->setOutput($this->load->view('extension/module/announcement_bar_pro', $data));
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
			$opt      = $this->fx->options(isset($message['options']) ? $message['options'] : '');

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
				'fs'         => $message['type'] === 'free_shipping' ? ($opt['fs_oc'] ? $this->language->get('text_badge_fs_oc') : sprintf($this->language->get('text_badge_fs'), $this->currency->format((float)$message['threshold'], $currency))) : '',
				'coupon'     => $message['coupon'] !== '' && !$opt['coupon_id'] ? sprintf($this->language->get('text_badge_coupon'), $message['coupon']) : '',
				'schedule'   => $schedule !== '' ? sprintf($this->language->get('text_badge_schedule'), $schedule) : '',
				'targeting'  => ($message['categories'] || $message['products']) ? $this->language->get('text_badge_targeting') : '',
				'badges'     => $this->badges($message, $opt),
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
				'edit'       => $this->link(self::ROUTE . '/form', '&message_id=' . (int)$message['message_id'])
			);
		}

		return $rows;
	}

	/* ------------------------------------------------------------------ *
	 * Add / edit message
	 * ------------------------------------------------------------------ */
	public function form() {
		$this->bootstrap();

		$raw_id     = isset($this->request->get['message_id']) && is_string($this->request->get['message_id']) ? $this->request->get['message_id'] : '';
		$message_id = preg_match('/^\d{1,10}$/', $raw_id) ? (int)$raw_id : 0;
		$message    = $message_id ? $this->model_extension_module_announcement_bar_pro->getMessage($message_id) : array();

		if (($raw_id !== '' && !$message_id) || ($message_id && !$message)) {
			$this->load->language(self::ROUTE);
			$this->session->data['abp_error'] = $this->language->get('error_not_found');
			$this->response->redirect($this->link(self::ROUTE, '&tab=messages'));
		}

		$languages = $this->model_localisation_language->getLanguages();
		$post_data = $this->normalizeMessagePost($this->request->post);
		$this->orphaned = $this->orphanedTargets($message);

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
		$data['color_auto']    = $this->config->get(self::CODE . '_color_mode') === 'auto';
		$data['datepicker']    = $this->language->get('datepicker');
		$data['orphaned']      = $this->orphaned;
		$data['styles']        = $this->styleList();
		$data['module_style']  = $this->language->get('text_style_' . $this->styleValue($this->config->get(self::CODE . '_style'), 'solid'));
		$data['currency_code'] = $this->config->get('config_currency');

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
			'bg_color'        => $this->abp->color($this->config->get(self::CODE . '_default_bg_color'), '#222222'),
			'text_color'      => $this->abp->color($this->config->get(self::CODE . '_default_text_color'), '#ffffff'),
			'link'            => '',
			'link_new_tab'    => 0,
			'icon'            => '',
			'countdown'       => 0,
			'stores'          => array(),
			'customer_groups' => array(),
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
			'categories'      => array(),
			'products'        => array(),
			'style'           => '',
			'button_style'    => 'filled',
			'placement'       => '',
			'priority'        => 0
		);

		foreach ($defaults as $key => $default) {
			if (isset($src[$key])) {
				$value = $src[$key];
				if (is_array($default)) {
					$value = is_array($value) ? array_map('intval', $value) : array();
				} elseif (is_string($value)) {
					$value = $this->abp->decode($value);
				}
				$data[$key] = $value;
			} else {
				$data[$key] = ($post && !is_array($default) && in_array($key, array('status', 'link_new_tab', 'countdown', 'progress_bar', 'ab_test'), true)) ? 0 : $default;
			}
		}

		$data['threshold']   = $data['threshold'] !== '' && is_numeric($data['threshold']) && !$post ? rtrim(rtrim(number_format((float)$data['threshold'], 4, '.', ''), '0'), '.') : $data['threshold'];
		if (!in_array($data['type'], \Announcementbarpro\Features::TYPES, true)) {
			$data['type'] = 'standard';
		}

		// 2.4.0 options: posted values after a failed save, else the stored (clean) options.
		$opt = $post ? $this->postedOptions($post_data) : (isset($message['options']) && is_array($message['options']) ? $message['options'] : $this->fx->defaults());
		$data = array_merge($data, $this->optionData($opt));
		$data['weekday_list'] = $this->weekdayList(is_array($data['weekdays']) ? $this->abp->weekdays($data['weekdays']) : (string)$data['weekdays']);
		$data['category_chips'] = $this->model_extension_module_announcement_bar_pro->getCategoryNames($this->abp->idList($data['categories']), (int)$this->config->get('config_language_id'));
		$data['product_chips']  = $this->model_extension_module_announcement_bar_pro->getProductNames($this->abp->idList($data['products']), (int)$this->config->get('config_language_id'));

		$data['end_all_day'] = $post ? !empty($post_data['end_all_day']) : (!empty($message['date_end']) && substr($message['date_end'], 11, 8) === '23:59:59');
		$data['date_start'] = $data['date_start'] ? substr(str_replace('T', ' ', $data['date_start']), 0, 16) : '';
		$data['date_end']   = $data['date_end'] ? substr(str_replace('T', ' ', $data['date_end']), 0, 16) : '';

		$descriptions = isset($src['message_description']) && is_array($src['message_description']) ? $src['message_description'] : array();

		$data['message_description'] = array();
		foreach ($languages as $language) {
			$id  = (int)$language['language_id'];
			$row = isset($descriptions[$id]) && is_array($descriptions[$id]) ? $descriptions[$id] : array();

			foreach (explode(',', self::TEXT_FIELDS) as $field) {
				$data['message_description'][$id][$field] = isset($row[$field]) && is_string($row[$field]) ? $this->abp->decode($row[$field]) : '';
			}

			// Tier labels / texts and e-mail form texts (5 tier rows, empty ones left blank).
			$extra = $post ? $row : (isset($row['extra']) && is_array($row['extra']) ? $row['extra'] : array());
			foreach (array('tier_label', 'tier_text') as $field) {
				$list = isset($extra[$field]) && is_array($extra[$field]) ? array_values($extra[$field]) : array();
				for ($i = 0; $i < 5; $i++) {
					$data['message_description'][$id][$field][$i] = isset($list[$i]) && is_scalar($list[$i]) ? $this->fx->plainText((string)$list[$i]) : '';
				}
			}
			foreach (explode(',', \Announcementbarpro\Features::EXTRA_TEXTS) as $field) {
				$data['message_description'][$id][$field] = isset($extra[$field]) && is_scalar($extra[$field]) ? $this->fx->plainText((string)$extra[$field]) : '';
			}
		}

		$data['is_new'] = !$message_id;
		$data['oc_free_shipping'] = $this->ocFreeShipping();

		$data['admin_css']   = 'view/javascript/announcement_bar_pro/abp_admin.css?v=' . self::VERSION;
		$data['admin_js']    = 'view/javascript/announcement_bar_pro/abp_admin.js?v=' . self::VERSION;
		$data['config_json'] = $this->jsonForHtml($this->formConfig($languages));

		$this->response->setOutput($this->load->view('extension/module/announcement_bar_pro_form', $data));
	}

	/**
	 * Settings for the admin script: live preview (the shop's real bar script and styles), autocomplete URLs, texts.
	 */
	private function formConfig(array $languages) {
		$catalog = !empty($this->request->server['HTTPS']) && defined('HTTPS_CATALOG') ? HTTPS_CATALOG : (defined('HTTP_CATALOG') ? HTTP_CATALOG : '../');
		$assets  = $catalog . 'catalog/view/javascript/announcement_bar_pro/';

		$this->load->model('localisation/currency');
		$currency = $this->model_localisation_currency->getCurrencyByCode($this->config->get('config_currency'));

		return array(
			'page'     => 'form',
			'oc'       => 3,
			'assets'   => array(
				'css' => $assets . 'announcement_bar_pro.css?v=' . self::VERSION,
				'pre' => $assets . 'announcement_bar_pro_pre.js?v=' . self::VERSION,
				'js'  => $assets . 'announcement_bar_pro.js?v=' . self::VERSION,
				'fa'  => $catalog . 'catalog/view/javascript/font-awesome/css/font-awesome.min.css'
			),
			'bar'      => $this->previewSettings(),
			'currency' => array(
				'left'     => $currency ? html_entity_decode($currency['symbol_left'], ENT_QUOTES, 'UTF-8') : '',
				'right'    => $currency ? html_entity_decode($currency['symbol_right'], ENT_QUOTES, 'UTF-8') : '',
				'decimals' => $currency ? (int)$currency['decimal_place'] : 2,
				'point'    => $this->language->get('decimal_point') !== 'decimal_point' ? $this->language->get('decimal_point') : '.',
				'thousand' => $this->language->get('thousand_point') !== 'thousand_point' ? $this->language->get('thousand_point') : ''
			),
			'autocomplete' => array(
				'category' => str_replace('&amp;', '&', $this->link('catalog/category/autocomplete')),
				'product'  => str_replace('&amp;', '&', $this->link('catalog/product/autocomplete')),
				'coupon'   => str_replace('&amp;', '&', $this->link(self::ROUTE . '/coupons'))
			),
			'zones'     => str_replace('&amp;', '&', $this->link(self::ROUTE . '/zones')),
			'lists'     => array('country' => $this->model_extension_module_announcement_bar_pro->getCountries()),
			'image_url' => $catalog . 'image/',
			'filemanager' => str_replace('&amp;', '&', $this->link('common/filemanager')),
			'templates' => $this->templatesFor($languages),
			'oc_free'   => (float)$this->config->get('shipping_free_total'),
			'fonts'     => $this->fontStacks(),
			'text'     => array(
				'copy'       => $this->language->get('text_copy'),
				'close'      => $this->language->get('text_close'),
				'remove'     => $this->language->get('button_remove'),
				'no_results' => $this->language->get('text_no_results'),
				'sample'     => $this->language->get('text_preview_sample'),
				'all_zones'  => $this->language->get('text_all_zones'),
				'confirm_template' => $this->language->get('text_confirm_template'),
				'tier'       => $this->language->get('text_tier'),
				'email_placeholder' => $this->language->get('text_email_placeholder_default'),
				'email_button'      => $this->language->get('text_email_button_default'),
				'email_consent'     => $this->language->get('text_email_consent_default'),
				'privacy'    => $this->language->get('text_privacy_policy'),
				'placement_inline' => $this->language->get('text_preview_inline'),
				'trigger_note' => $this->language->get('text_preview_trigger')
			)
		);
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
			'dismissible' => (bool)$get('dismissible', 1),
			'font'        => in_array($get('font', ''), \Announcementbarpro\Features::FONTS, true) ? $get('font', '') : '',
			'pause'       => (bool)$get('pause_button', 1),
			'privacy'     => (int)$get('privacy_id', 0) > 0
		);
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
				if (is_scalar($message_id)) {
					$this->model_extension_module_announcement_bar_pro->deleteMessage((int)$message_id);
				}
			}

			$this->session->data['success'] = $this->language->get('text_success');
		}

		$this->response->redirect($this->link(self::ROUTE, '&tab=messages'));
	}

	/* ------------------------------------------------------------------ *
	 * Duplicate one message (POST message_id): the copy is switched off
	 * and starts with zero statistics.
	 * ------------------------------------------------------------------ */
	public function duplicate() {
		$this->bootstrap();

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			$message_id = $this->postedMessageId();

			if (!$this->user->hasPermission('modify', self::ROUTE)) {
				$this->session->data['abp_error'] = $this->language->get('error_permission');
			} elseif (!$message_id || !$this->model_extension_module_announcement_bar_pro->duplicateMessage($message_id)) {
				$this->session->data['abp_error'] = $this->language->get('error_not_found');
			} else {
				$this->session->data['success'] = $this->language->get('text_duplicated');
			}
		}

		$this->response->redirect($this->link(self::ROUTE, '&tab=messages'));
	}

	/* ------------------------------------------------------------------ *
	 * Reset all counters of one message (POST message_id)
	 * ------------------------------------------------------------------ */
	public function resetStats() {
		$this->bootstrap();

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			$message_id = $this->postedMessageId();

			if (!$this->user->hasPermission('modify', self::ROUTE)) {
				$this->session->data['abp_error'] = $this->language->get('error_permission');
			} elseif (!$message_id || !$this->model_extension_module_announcement_bar_pro->getMessage($message_id)) {
				$this->session->data['abp_error'] = $this->language->get('error_not_found');
			} else {
				$this->model_extension_module_announcement_bar_pro->resetStatistics($message_id);
				$this->session->data['success'] = $this->language->get('text_stats_reset');
			}
		}

		$this->response->redirect($this->link(self::ROUTE, '&tab=messages'));
	}

	/* ------------------------------------------------------------------ *
	 * A/B test: use variant B as the main text (POST message_id, JSON)
	 * ------------------------------------------------------------------ */
	public function useB() {
		$this->bootstrap();

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] != 'POST') {
			$json['error'] = $this->language->get('error_value');
		} elseif (!$this->user->hasPermission('modify', self::ROUTE)) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$message_id = $this->postedMessageId();

			if (!$message_id || !$this->model_extension_module_announcement_bar_pro->getMessage($message_id)) {
				$json['error'] = $this->language->get('error_not_found');
			} elseif (!$this->model_extension_module_announcement_bar_pro->useVariantB($message_id)) {
				$json['error'] = $this->language->get('error_ab_off');
			} else {
				$this->session->data['success'] = $this->language->get('text_used_b');
				$json['success'] = $this->language->get('text_used_b');
			}
		}

		$this->json($json);
	}

	/* ------------------------------------------------------------------ *
	 * Export all messages as JSON (texts keyed by language code)
	 * ------------------------------------------------------------------ */
	public function export() {
		$this->bootstrap();

		$messages = array();

		foreach ($this->model_extension_module_announcement_bar_pro->getMessagesForExport() as $message) {
			$messages[] = $this->exportMessage($message);
		}

		$out = array(
			'format'   => 'announcement_bar_pro',
			'version'  => self::VERSION,
			'schema'   => ModelExtensionModuleAnnouncementBarPro::SCHEMA_VERSION,
			'exported' => date('c'),
			'messages' => $messages
		);

		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		$this->response->addHeader('Content-Disposition: attachment; filename="announcement-bar-pro-' . date('Ymd-Hi') . '.json"');
		$this->response->addHeader('Cache-Control: no-store');
		$this->response->addHeader('X-Content-Type-Options: nosniff');
		$this->response->setOutput(json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	/* ------------------------------------------------------------------ *
	 * Import (POST file, JSON answer). Every message goes through the same
	 * validation and sanitizing as the form and is created switched off.
	 * ------------------------------------------------------------------ */
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

				$this->session->data['success'] = $text;
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
				$this->model_extension_module_announcement_bar_pro->addMessage($clean);
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

		foreach (array('sort_order', 'date_start', 'date_end', 'bg_color', 'text_color', 'link', 'icon', 'page_mode', 'page_routes', 'device', 'type', 'threshold', 'coupon', 'time_start', 'time_end', 'style', 'button_style', 'placement', 'priority') as $key) {
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

		// 2.4.0 options (same shape as the form's "opt"; validated like the form).
		if (isset($m['options']) && is_array($m['options'])) {
			$post['opt'] = $m['options'];
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

				foreach (explode(',', self::TEXT_FIELDS . ',' . \Announcementbarpro\Features::EXTRA_TEXTS) as $field) {
					$post['message_description'][$by_code[$code]][$field] = isset($row[$field]) && is_scalar($row[$field]) ? (string)$row[$field] : '';
				}

				foreach (array('tier_label', 'tier_text') as $field) {
					$post['message_description'][$by_code[$code]][$field] = isset($row[$field]) && is_array($row[$field]) ? array_values(array_filter($row[$field], 'is_scalar')) : array();
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

		foreach (array('bg_color', 'text_color', 'link', 'icon', 'page_mode', 'page_routes', 'device', 'type', 'coupon', 'weekdays', 'time_start', 'time_end', 'style', 'button_style', 'placement') as $key) {
			$out[$key] = isset($m[$key]) ? (string)$m[$key] : '';
		}

		$out['priority'] = isset($m['priority']) ? (int)$m['priority'] : 0;
		$out['options']  = isset($m['options']) && is_array($m['options']) ? $m['options'] : $this->fx->defaults();

		$out['threshold'] = rtrim(rtrim(number_format((float)$m['threshold'], 4, '.', ''), '0'), '.');

		foreach (array('stores', 'customer_groups', 'categories', 'products') as $key) {
			$out[$key] = array_values(array_map('intval', (array)$m[$key]));
		}

		$out['texts'] = array();

		foreach ($m['texts'] as $code => $row) {
			foreach (explode(',', self::TEXT_FIELDS) as $field) {
				$out['texts'][$code][$field] = $this->abp->decode(isset($row[$field]) ? $row[$field] : '');
			}

			$extra = $this->fx->extraOf(isset($row['extra']) ? $row['extra'] : array());

			foreach ($extra as $field => $value) {
				$out['texts'][$code][$field] = $value;
			}
		}

		return $out;
	}

	/* ------------------------------------------------------------------ *
	 * Install / uninstall (called by extension/extension/module, which
	 * already validated "modify" permission on that route)
	 * ------------------------------------------------------------------ */
	public function install() {
		if (!$this->user->hasPermission('modify', 'extension/extension/module')) {
			return;
		}

		$this->load->model(self::ROUTE);
		$this->model_extension_module_announcement_bar_pro->ensureSchema();
		$this->model_extension_module_announcement_bar_pro->registerEvents(true);

		// Works out of the box: a fresh install (no messages yet) is switched on; adding the first
		// message is all that is left. Kept messages from an earlier install stay switched off.
		if (!$this->model_extension_module_announcement_bar_pro->getTotalMessages()) {
			$this->load->model('setting/setting');
			$this->model_setting_setting->editSettingValue(self::CODE, self::CODE . '_status', 1);
		}
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

		if (!in_array($this->postValue('position'), array('top', 'bottom'), true))            $this->error['position'] = $this->language->get('error_value');
		if (!in_array($this->postValue('color_mode'), array('auto', 'custom'), true))         $this->error['color_mode'] = $this->language->get('error_value');
		if (!in_array($this->postValue('mode'), array('rotate', 'marquee', 'stack'), true))    $this->error['mode'] = $this->language->get('error_value');
		if (!in_array($this->postValue('style'), explode(',', self::STYLES), true))           $this->error['style'] = $this->language->get('error_value');
		if (!in_array($this->postValue('transition'), array('fade', 'slide'), true))          $this->error['transition'] = $this->language->get('error_value');
		if (!$this->intInRange($this->postValue('interval'), 2, 60))                           $this->error['interval'] = sprintf($this->language->get('error_range'), 2, 60);
		if (!$this->intInRange($this->postValue('speed'), 20, 300))                            $this->error['speed'] = sprintf($this->language->get('error_range'), 20, 300);
		if (!$this->intInRange($this->postValue('font_size'), 10, 30))                         $this->error['font_size'] = sprintf($this->language->get('error_range'), 10, 30);
		if (!$this->intInRange($this->postValue('padding'), 0, 30))                            $this->error['padding'] = sprintf($this->language->get('error_range'), 0, 30);
		if (!$this->intInRange($this->postValue('dismiss_days'), 0, 365))                      $this->error['dismiss_days'] = sprintf($this->language->get('error_range'), 0, 365);
		if (!$this->abp->color($this->postValue('default_bg_color')))                    $this->error['default_bg_color'] = $this->language->get('error_color');
		if (!$this->abp->color($this->postValue('default_text_color')))                  $this->error['default_text_color'] = $this->language->get('error_color');
		if (!$this->intInRange($this->postValue('max_messages'), 0, 20))                       $this->error['max_messages'] = sprintf($this->language->get('error_range'), 0, 20);
		if (!$this->intInRange($this->postValue('autohide'), 0, 600))                          $this->error['autohide'] = sprintf($this->language->get('error_range'), 0, 600);
		if (!in_array($this->postValue('font'), \Announcementbarpro\Features::FONTS, true))     $this->error['font'] = $this->language->get('error_value');
		if (!in_array($this->postValue('geo_fallback'), array('store', 'none'), true))         $this->error['geo_fallback'] = $this->language->get('error_value');
		if (!$this->validPrivacyId($this->postValue('privacy_id')))                            $this->error['privacy_id'] = $this->language->get('error_value');
		if (!$this->intInRange($this->postValue('signup_limit_ip'), 1, 1000))                  $this->error['signup_limit_ip'] = sprintf($this->language->get('error_range'), 1, 1000);
		if (!$this->intInRange($this->postValue('signup_limit_total'), 1, 100000))             $this->error['signup_limit_total'] = sprintf($this->language->get('error_range'), 1, 100000);
		if (strlen($this->postValue('custom_css')) > self::MAX_CSS * 2)                         $this->error['custom_css'] = sprintf($this->language->get('error_text_length'), self::MAX_CSS);
		$this->error = array_merge($this->error, $this->tsErrors());

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
		$has_text_b = false;
		$free_ship  = isset($p['type']) && in_array($p['type'], array('free_shipping', 'tiers'), true); // both need the "reached" text
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

		if (!empty($p['countdown']) && !$end && (empty($p['opt']['recur']) || !is_string($p['opt']['recur']))) {
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
		$this->validateMessage240($p, $languages);

		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('error_form');
		}

		return !$this->error;
	}

	/** 2.3.0 fields: type, threshold, coupon, button / style, weekly schedule, category / product targeting. */
	private function validateMessage230(array $p) {
		$type = isset($p['type']) ? $p['type'] : 'standard';

		if (!in_array($type, \Announcementbarpro\Features::TYPES, true)) {
			$this->error['type'] = $this->language->get('error_value');
		} elseif ($type === 'free_shipping' && empty($p['opt']['fs_oc'])) {
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
				|| count($this->model_extension_module_announcement_bar_pro->$method($ids, $language_id)) !== count($ids)) {
				$this->error[$key] = $this->language->get('error_' . $key);
			}
		}
	}

	/**
	 * 2.4.0 fields: placement, priority and the options (countries, free-shipping overrides, OpenCart coupon, tiers,
	 * cart conditions, campaign / visitor, trigger, recurring countdown, e-mail capture, image, font) + extra texts.
	 */
	private function validateMessage240(array $p, array $languages) {
		$type = isset($p['type']) && in_array($p['type'], \Announcementbarpro\Features::TYPES, true) ? $p['type'] : 'standard';

		list($opt, $errors) = $this->fx->validate(isset($p['opt']) ? $p['opt'] : array(), $this->lookup(), $this->currencyCodes(), $type);

		foreach ($errors as $field => $key) {
			$this->error['opt_' . $field] = $this->language->get($key);
		}

		if (!in_array(isset($p['placement']) ? $p['placement'] : '', \Announcementbarpro\Features::PLACEMENTS, true)) {
			$this->error['placement'] = $this->language->get('error_value');
		}

		if (isset($p['priority']) && trim($p['priority']) !== '' && (!preg_match('/^\d{1,3}$/', trim($p['priority'])) || (int)$p['priority'] > 100)) {
			$this->error['priority'] = sprintf($this->language->get('error_range'), 0, 100);
		}

		$descs = isset($p['message_description']) && is_array($p['message_description']) ? $p['message_description'] : array();
		$index = $this->fx->tierIndexes(isset($p['opt']['tiers']) ? $p['opt']['tiers'] : array());
		$allow = (bool)$this->config->get(self::CODE . '_allow_html');

		foreach ($languages as $language) {
			$id     = (int)$language['language_id'];
			$row    = isset($descs[$id]) && is_array($descs[$id]) ? $descs[$id] : array();
			$errors = array();
			$extra  = $this->fx->extra($row, $index, $errors);

			foreach ($errors as $field => $key) {
				$this->error[$field][$id] = sprintf($this->language->get($key), $field === 'tier_text' || $field === 'email_consent' ? \Announcementbarpro\Features::MAX_TIER_TEXT : \Announcementbarpro\Features::MAX_EXTRA);
			}

			// Tiers: every language that has a text needs a label per tier (shown for {goal} and under the bar).
			if ($type === 'tiers' && $this->filled(isset($row['text']) ? $row['text'] : '', $allow) && in_array('', $extra['tier_label'], true) && !isset($this->error['tier_label'][$id])) {
				$this->error['tier_label'][$id] = $this->language->get('error_tier_label');
			}
		}
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
			'default_bg_color'   => $this->abp->color($this->postValue('default_bg_color')),
			'default_text_color' => $this->abp->color($this->postValue('default_text_color')),
			'delete_data'        => !empty($p[self::CODE . '_delete_data']) ? 1 : 0,
			'color_mode'         => $this->postValue('color_mode'),
			'style'              => $this->postValue('style'),
			'transition'         => $this->postValue('transition'),
			'arrows'             => !empty($p[self::CODE . '_arrows']) ? 1 : 0,
			// 2.4.0
			'max_messages'       => (int)$this->postValue('max_messages'),
			'autohide'           => (int)$this->postValue('autohide'),
			'pause_button'       => !empty($p[self::CODE . '_pause_button']) ? 1 : 0,
			'font'               => $this->postValue('font'),
			'custom_css'         => $this->fx->css(html_entity_decode($this->postValue('custom_css'), ENT_QUOTES, 'UTF-8')),
			'ga4'                => !empty($p[self::CODE . '_ga4']) ? 1 : 0,
			'geo_cf'             => !empty($p[self::CODE . '_geo_cf']) ? 1 : 0,
			'geo_fallback'       => $this->postValue('geo_fallback'),
			'privacy_id'         => (int)$this->postValue('privacy_id'),
			'double_optin'       => !empty($p[self::CODE . '_double_optin']) ? 1 : 0,
			'signup_limit_ip'    => (int)$this->postValue('signup_limit_ip'),
			'signup_limit_total' => (int)$this->postValue('signup_limit_total'),
			'consent_wait'       => !empty($p[self::CODE . '_consent_wait']) ? 1 : 0,
			// 2.4.1 Cloudflare Turnstile (the secret only when it changes; see tsSecretToSave())
			'ts_site'            => $this->fx->tsSiteKey($this->postValue('ts_site'))
		);

		if (($secret = $this->tsSecretToSave()) !== null) {
			$out['ts_secret'] = $secret;
		}

		$data = array();
		foreach ($out as $key => $value) {
			$data[self::CODE . '_' . $key] = $value;
		}

		return $data;
	}

	protected function sanitizeMessage(array $p, array $languages) {
		$allow_html = (bool)$this->config->get(self::CODE . '_allow_html');
		$descs      = isset($p['message_description']) && is_array($p['message_description']) ? $p['message_description'] : array();
		$ab_test    = !empty($p['ab_test']);
		$type       = isset($p['type']) && in_array($p['type'], \Announcementbarpro\Features::TYPES, true) ? $p['type'] : 'standard';
		$free_ship  = $type === 'free_shipping';
		$goal_text  = $type !== 'standard';
		$index      = $this->fx->tierIndexes(isset($p['opt']['tiers']) ? $p['opt']['tiers'] : array());

		list($options) = $this->fx->validate(isset($p['opt']) ? $p['opt'] : array(), null, $this->currencyCodes(), $type);

		$description = array();
		$ignored     = array();
		foreach ($languages as $language) {
			$id  = (int)$language['language_id'];
			$row = isset($descs[$id]) && is_array($descs[$id]) ? $descs[$id] : array();
			$get = function ($field) use ($row) {
				return isset($row[$field]) && is_string($row[$field]) ? $this->abp->decode($row[$field]) : '';
			};
			$html = function ($value) use ($allow_html) {
				$value = $allow_html ? $this->abp->html($value) : $this->abp->plain($value);

				// encode4byte: OpenCart's DB link is 3-byte utf8, emoji would be stored as "?".
				return trim(strip_tags($value)) === '' ? '' : $this->abp->encode4byte($value);
			};
			$button = function ($value) {
				return $this->abp->encode4byte($this->substr($this->abp->plain($value), self::MAX_LINK_TEXT));
			};

			$description[$id] = array(
				'text'          => $this->abp->encode4byte($allow_html ? $this->abp->html($get('text')) : $this->abp->plain($get('text'))),
				'link_text'     => $button($get('link_text')),
				'mobile_text'   => $html($get('mobile_text')),
				'success_text'  => $goal_text ? $html($get('success_text')) : '',
				'text_b'        => $ab_test ? $html($get('text_b')) : '',
				'mobile_text_b' => $ab_test ? $html($get('mobile_text_b')) : '',
				'link_text_b'   => $ab_test ? $button($get('link_text_b')) : '',
				'extra'         => $this->fx->extra($row, $type === 'tiers' ? $index : array(), $ignored)
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
			'type'                => $type,
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
			'placement'           => isset($p['placement']) && in_array($p['placement'], \Announcementbarpro\Features::PLACEMENTS, true) ? $p['placement'] : '',
			'priority'            => isset($p['priority']) ? max(0, min(100, (int)$p['priority'])) : 0,
			'options'             => $options,
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
		require_once(DIR_SYSTEM . 'library/announcementbarpro/sanitizer.php');
		require_once(DIR_SYSTEM . 'library/announcementbarpro/features.php');
		$this->abp = new \Announcementbarpro\Sanitizer();
		$this->fx  = new \Announcementbarpro\Features();

		// Creating/migrating tables writes to the DB: only for users with "modify" permission.
		if ($this->user->hasPermission('modify', self::ROUTE)) {
			$this->model_extension_module_announcement_bar_pro->ensureSchema();
		} elseif (!$this->model_extension_module_announcement_bar_pro->isSchemaCurrent()) {
			$this->session->data['abp_error'] = $this->language->get('error_permission');
			$this->response->redirect($this->link('marketplace/extension', '&type=module'));
		}
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

		foreach (array('status', 'sort_order', 'date_start', 'date_end', 'bg_color', 'text_color', 'link', 'link_new_tab', 'icon', 'countdown', 'page_mode', 'page_routes', 'end_all_day', 'device', 'type', 'threshold', 'progress_bar', 'coupon', 'ab_test', 'time_start', 'time_end', 'style', 'button_style', 'placement', 'priority') as $key) {
			if (isset($post[$key])) {
				$out[$key] = is_string($post[$key]) ? $post[$key] : '';
			}
		}

		foreach (array('stores', 'customer_groups', 'weekdays', 'categories', 'products') as $key) {
			if (isset($post[$key])) {
				$out[$key] = is_array($post[$key]) ? array_values(array_filter($post[$key], 'is_scalar')) : array();
			}
		}

		// 2.4.0 options: strings and lists of strings only (2 levels, e.g. fs_zones[0][amount]); checked in Features::validate().
		$out['opt'] = isset($post['opt']) && is_array($post['opt']) ? $this->tree($post['opt'], 2) : array();

		$out['message_description'] = array();
		if (isset($post['message_description']) && is_array($post['message_description'])) {
			foreach ($post['message_description'] as $language_id => $row) {
				if (is_array($row)) {
					foreach (explode(',', self::TEXT_FIELDS . ',' . \Announcementbarpro\Features::EXTRA_TEXTS) as $field) {
						$out['message_description'][(int)$language_id][$field] = isset($row[$field]) && is_string($row[$field]) ? $row[$field] : '';
					}

					foreach (array('tier_label', 'tier_text') as $field) {
						$out['message_description'][(int)$language_id][$field] = isset($row[$field]) && is_array($row[$field]) ? array_map('strval', array_values(array_filter($row[$field], 'is_string'))) : array();
					}
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

	private function json(array $json) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/** JSON that is safe inside <script type="application/json"> (no "<", "&", quotes as raw characters). */
	private function jsonForHtml(array $data) {
		return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
	}

	private function settingKeys() {
		return array('status', 'position', 'sticky', 'mode', 'interval', 'speed', 'font_size', 'padding', 'dismissible', 'dismiss_days', 'allow_html', 'default_bg_color', 'default_text_color', 'delete_data', 'color_mode', 'style', 'transition', 'arrows',
			'max_messages', 'autohide', 'pause_button', 'font', 'custom_css', 'ga4', 'geo_cf', 'geo_fallback', 'privacy_id', 'double_optin',
			'signup_limit_ip', 'signup_limit_total', 'consent_wait', 'ts_site'); // never the Turnstile secret
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

	private function intInRange($value, $min, $max) {
		return is_string($value) && preg_match('/^\d{1,4}$/', $value) && (int)$value >= $min && (int)$value <= $max;
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
	 * One route per line, e.g. "product/product", "product/*". Returns list, or false on invalid line.
	 */
	private function parseRoutes($value) {
		$routes = array();

		foreach (preg_split('/[\r\n,]+/', $this->abp->decode((string)$value)) as $line) {
			$line = trim($line);

			if ($line === '') {
				continue;
			}

			if (!preg_match('#^[a-z0-9_]+(/[a-z0-9_]+)*(/\*)?$|^\*$#i', $line) || strlen($line) > 64) {
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

	/* ------------------------------------------------------------------ *
	 * 2.4.0 AJAX helpers for the form (read-only, GET)
	 * ------------------------------------------------------------------ */

	/** Zones of a country (free-shipping amount per region). */
	public function zones() {
		$this->bootstrap();

		$raw = isset($this->request->get['country_id']) && is_string($this->request->get['country_id']) ? $this->request->get['country_id'] : '';

		$this->json(preg_match('/^\d{1,10}$/', $raw) ? $this->model_extension_module_announcement_bar_pro->getZones((int)$raw) : array());
	}

	/** Coupon autocomplete: "CODE — name (disabled / expired)". */
	public function coupons() {
		$this->bootstrap();

		$filter = isset($this->request->get['filter_name']) && is_string($this->request->get['filter_name']) ? $this->abp->plain($this->abp->decode($this->request->get['filter_name'])) : '';
		$out    = array();

		if ($filter !== '') {
			foreach ($this->model_extension_module_announcement_bar_pro->searchCoupons($this->substr($filter, 64)) as $row) {
				$out[] = array('coupon_id' => (int)$row['coupon_id'], 'name' => $this->couponLabel($row));
			}
		}

		$this->json($out);
	}

	/* ------------------------------------------------------------------ *
	 * 2.4.0 newsletter sign-ups: CSV export (GET) and erasure (POST)
	 * Personal data: both need the "modify" permission.
	 * ------------------------------------------------------------------ */
	public function exportSubscribers() {
		$this->bootstrap();

		if (!$this->user->hasPermission('modify', self::ROUTE)) {
			$this->session->data['abp_error'] = $this->language->get('error_permission');
			$this->response->redirect($this->link(self::ROUTE, '&tab=settings'));
		}

		$states = array(0 => 'pending', 1 => 'confirmed', 2 => 'unsubscribed');
		$lines  = array(implode(',', array_map(array($this->fx, 'csvCell'), array('email', 'status', 'store_id', 'language', 'message_id', 'consent_text', 'date_added', 'date_confirmed'))));

		foreach ($this->model_extension_module_announcement_bar_pro->getSubscribers() as $row) {
			$lines[] = implode(',', array_map(array($this->fx, 'csvCell'), array(
				$row['email'],
				isset($states[(int)$row['status']]) ? $states[(int)$row['status']] : '',
				(int)$row['store_id'],
				(string)$row['language'],
				(int)$row['message_id'],
				(string)$row['consent'],
				(string)$row['date_added'],
				(string)$row['date_confirmed']
			)));
		}

		$this->response->addHeader('Content-Type: text/csv; charset=utf-8');
		$this->response->addHeader('Content-Disposition: attachment; filename="announcement-bar-pro-subscribers-' . date('Ymd-Hi') . '.csv"');
		$this->response->addHeader('Cache-Control: no-store');
		$this->response->addHeader('X-Content-Type-Options: nosniff');
		$this->response->setOutput("\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n");
	}

	public function eraseSubscriber() {
		$this->bootstrap();

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] != 'POST') {
			$json['error'] = $this->language->get('error_value');
		} elseif (!$this->user->hasPermission('modify', self::ROUTE)) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$email = $this->fx->email(isset($this->request->post['email']) && is_string($this->request->post['email']) ? html_entity_decode($this->request->post['email'], ENT_QUOTES, 'UTF-8') : '');

			if ($email === '') {
				$json['error'] = $this->language->get('error_email');
			} else {
				$count = $this->model_extension_module_announcement_bar_pro->deleteSubscriber($email);
				$json['success'] = $count ? sprintf($this->language->get('text_erased'), $count) : $this->language->get('text_erase_none');
			}
		}

		$this->json($json);
	}

	/* ------------------------------------------------------------------ *
	 * 2.4.0 helpers
	 * ------------------------------------------------------------------ */

	/** Existence checks for Features::validate() (countries, zones, coupons, products, categories, images). */
	private function lookup() {
		return function ($kind, $values) {
			return $this->model_extension_module_announcement_bar_pro->lookup($kind, $values);
		};
	}

	/** Currency codes of the shop. */
	private function currencyCodes() {
		$this->load->model('localisation/currency');

		return array_values(array_map('strval', array_keys($this->model_localisation_currency->getCurrencies())));
	}

	/** Posted tree → strings and arrays only, at most $depth levels (crafted input is neutralised). */
	private function tree(array $value, $depth) {
		$out = array();

		foreach ($value as $key => $item) {
			if (is_array($item)) {
				if ($depth > 0) {
					$out[$key] = $this->tree($item, $depth - 1);
				}
			} elseif (is_scalar($item)) {
				$out[$key] = (string)$item;
			}
		}

		return $out;
	}

	/** Options for re-showing a form that did not save: clean values, with the raw text where a field had an error. */
	private function postedOptions(array $p) {
		$raw = isset($p['opt']) && is_array($p['opt']) ? $p['opt'] : array();

		list($opt) = $this->fx->validate($raw, null, $this->currencyCodes(), null);

		foreach (array('utm_source', 'utm_medium', 'utm_campaign', 'referrer', 'cart_subtotal_min', 'cart_subtotal_max', 'cart_qty_min', 'cart_qty_max', 'trigger_value', 'recur_time', 'overlay') as $key) {
			if (isset($raw[$key]) && is_string($raw[$key])) {
				$opt[$key] = $this->abp->decode($raw[$key]);
			}
		}

		$opt['tiers'] = array();
		foreach (isset($raw['tiers']) && is_array($raw['tiers']) ? array_slice(array_values($raw['tiers']), 0, 5) : array() as $value) {
			$opt['tiers'][] = is_string($value) ? $value : '';
		}

		return $opt;
	}

	/** Twig data for the 2.4.0 fields of the form. */
	private function optionData(array $opt) {
		$language_id = (int)$this->config->get('config_language_id');
		$model       = $this->model_extension_module_announcement_bar_pro;
		$short       = function ($amount) {
			return $amount === '' || !is_numeric($amount) ? (string)$amount : rtrim(rtrim(number_format((float)$amount, 4, '.', ''), '0'), '.');
		};

		$countries = array();
		foreach ($model->getCountries() as $country) {
			$countries[$country['id']] = $country['name'];
		}

		$data = array('opt' => $opt, 'country_list' => $countries);

		$data['country_chips'] = array();
		foreach ($opt['countries'] as $id) {
			if (isset($countries[$id])) {
				$data['country_chips'][$id] = $countries[$id];
			}
		}

		$data['cart_category_chips'] = $model->getCategoryNames($opt['cart_categories'], $language_id);
		$data['cart_product_chips']  = $model->getProductNames($opt['cart_products'], $language_id);

		$data['coupon_chip'] = array();
		if ($opt['coupon_id']) {
			$coupon = $model->getCoupon($opt['coupon_id']);

			if ($coupon) {
				$data['coupon_chip'][(int)$coupon['coupon_id']] = $this->couponLabel($coupon);
			}
		}

		// Free shipping per country / region: the saved rows + empty ones (at least 3 rows on screen).
		$data['fs_zone_rows'] = array();
		foreach ($opt['fs_zones'] as $row) {
			$data['fs_zone_rows'][] = array('country_id' => (int)$row['country_id'], 'zone_id' => (int)$row['zone_id'], 'amount' => $short($row['amount']), 'zones' => $row['country_id'] ? $model->getZones($row['country_id']) : array());
		}
		for ($i = count($data['fs_zone_rows']); $i < max(3, count($opt['fs_zones']) + 1); $i++) {
			$data['fs_zone_rows'][] = array('country_id' => 0, 'zone_id' => 0, 'amount' => '', 'zones' => array());
		}

		// Fixed amount per currency (all currencies except the default one).
		$this->load->model('localisation/currency');
		$data['currency_rows'] = array();
		foreach ($this->model_localisation_currency->getCurrencies() as $code => $currency) {
			if ($code !== $this->config->get('config_currency')) {
				$data['currency_rows'][] = array('code' => $code, 'title' => $currency['title'], 'amount' => isset($opt['fs_currencies'][$code]) ? $short($opt['fs_currencies'][$code]) : '');
			}
		}

		$data['tier_rows'] = array();
		for ($i = 0; $i < 5; $i++) {
			$data['tier_rows'][] = isset($opt['tiers'][$i]) ? $short($opt['tiers'][$i]) : '';
		}

		foreach (array('cart_subtotal_min', 'cart_subtotal_max') as $key) {
			$data['opt'][$key] = $short($opt[$key]);
		}

		$data['recur_skip_list'] = $this->weekdayList((string)$opt['recur_skip']);
		$this->load->model('tool/image');
		$data['placeholder']     = $this->model_tool_image->resize('no_image.png', 100, 100);
		$data['image_thumb']     = $opt['image'] !== '' && defined('DIR_IMAGE') && is_file(DIR_IMAGE . $opt['image']) ? $this->model_tool_image->resize($opt['image'], 100, 100) : $data['placeholder'];
		$data['font_list']       = $this->fontStacks();
		$data['placements']      = \Announcementbarpro\Features::PLACEMENTS;

		return $data;
	}

	/**
	 * Turnstile secret key to store (2.4.1): a new valid key replaces the saved one, "remove" clears it, an empty field
	 * keeps the saved key (null = leave it out of the saved settings). The key is never sent back to any page.
	 */
	private function tsSecretToSave() {
		$posted = $this->fx->tsSecretKey($this->postValue('ts_secret'));

		if ($posted !== '') {
			return $posted;
		}

		return $this->postValue('ts_secret_remove') === '1' ? '' : null;
	}

	/** Turnstile key errors of a settings POST: array(field => message). */
	private function tsErrors() {
		$errors = array();
		$site   = $this->postValue('ts_site');
		$secret = $this->postValue('ts_secret');

		if ($site !== '' && $this->fx->tsSiteKey($site) === '') {
			$errors['ts_site'] = $this->language->get('error_ts_site');
		}

		if ($secret !== '' && $this->fx->tsSecretKey($secret) === '') {
			$errors['ts_secret'] = $this->language->get('error_ts_secret');
		} elseif ($site !== '' && $secret === '' && ($this->postValue('ts_secret_remove') === '1' || $this->fx->tsSecretKey($this->config->get(self::CODE . '_ts_secret')) === '')) {
			$errors['ts_secret'] = $this->language->get('error_ts_secret_required');
		}

		return $errors;
	}

	/** Settings tab: privacy pages, sign-up counts, mail check, fonts, Turnstile state (never the secret itself). */
	private function settingsExtra() {
		$this->load->model('catalog/information');

		$pages = array();
		foreach ($this->model_catalog_information->getInformations() as $page) {
			$pages[] = array('id' => (int)$page['information_id'], 'title' => html_entity_decode((string)$page['title'], ENT_QUOTES, 'UTF-8'));
		}

		return array(
			'information_pages' => $pages,
			'subscriber_counts' => $this->model_extension_module_announcement_bar_pro->countSubscribers(),
			'mail_ready'        => (string)$this->config->get('config_mail_engine') !== '' && (string)$this->config->get('config_email') !== '',
			'font_list'         => $this->fontStacks(),
			'ts_secret_saved'   => $this->fx->tsSecretKey($this->config->get(self::CODE . '_ts_secret')) !== '',
			'ts_on'             => $this->fx->tsOn($this->config->get(self::CODE . '_ts_site'), $this->config->get(self::CODE . '_ts_secret'))
		);
	}

	private function validPrivacyId($value) {
		if ($value === '0' || $value === '') {
			return true;
		}

		if (!preg_match('/^\d{1,10}$/', $value)) {
			return false;
		}

		$this->load->model('catalog/information');

		return (bool)$this->model_catalog_information->getInformation((int)$value);
	}

	/** Templates with their texts for every shop language (lv / ru by language code, English for the rest). */
	private function templatesFor(array $languages) {
		$admin = substr((string)$this->config->get('config_admin_language'), 0, 2);
		$out   = array();

		foreach ($this->fx->templates() as $key => $t) {
			$pick = function (array $texts, $code) {
				$code = substr((string)$code, 0, 2);

				return isset($texts[$code]) ? $texts[$code] : $texts['en'];
			};

			$item = array('key' => $key, 'name' => $pick($t['name'], $admin), 'texts' => array());

			foreach (array('icon', 'bg', 'fg', 'type', 'threshold', 'coupon', 'countdown', 'end_days', 'recur', 'recur_time', 'recur_skip', 'email', 'style', 'weekdays', 'trigger', 'placement', 'tiers') as $field) {
				if (isset($t[$field])) {
					$item[$field] = $t[$field];
				}
			}

			foreach ($languages as $language) {
				$row = array();

				foreach (array('text', 'success_text', 'link_text') as $field) {
					$row[$field] = isset($t[$field]) ? $pick($t[$field], $language['code']) : '';
				}

				$row['tier_label'] = array();
				foreach (isset($t['tier_label']) ? $t['tier_label'] : array() as $label) {
					$row['tier_label'][] = $pick($label, $language['code']);
				}

				$item['texts'][(int)$language['language_id']] = $row;
			}

			$out[] = $item;
		}

		return $out;
	}

	/** Font choices: value, name, CSS stack. */
	private function fontStacks() {
		$out = array();

		foreach (\Announcementbarpro\Features::FONTS as $font) {
			$out[] = array('value' => $font, 'name' => $this->language->get('text_font_' . ($font === '' ? 'theme' : $font)), 'stack' => \Announcementbarpro\Features::fontStack($font));
		}

		return $out;
	}

	/** OpenCart's own Free Shipping method (status + amount) for the help text. */
	private function ocFreeShipping() {
		$total = (float)$this->config->get('shipping_free_total');

		return array(
			'status' => (bool)$this->config->get('shipping_free_status'),
			'total'  => $this->currency->format($total, $this->config->get('config_currency'))
		);
	}

	private function couponLabel(array $row) {
		$label = $row['code'] . ' — ' . html_entity_decode((string)$row['name'], ENT_QUOTES, 'UTF-8');

		if (!$row['status']) {
			$label .= ' (' . $this->language->get('text_disabled') . ')';
		} elseif (!empty($row['date_end']) && $row['date_end'] !== '0000-00-00' && $row['date_end'] < date('Y-m-d')) {
			$label .= ' (' . $this->language->get('text_state_expired') . ')';
		}

		return $label;
	}

	/** Shop URL (for image previews). */
	private function catalogUrl() {
		return !empty($this->request->server['HTTPS']) && defined('HTTPS_CATALOG') ? HTTPS_CATALOG : (defined('HTTP_CATALOG') ? HTTP_CATALOG : '../');
	}

	/**
	 * Badges of the message list: every feature in use gets an icon AND a short visible label (2.4.0: no icon-only badges).
	 * Each: icon, label, title (longer tooltip), class.
	 */
	private function badges(array $m, array $opt) {
		$out = array();
		$add = function ($icon, $label, $title = '', $class = '') use (&$out) {
			$out[] = array('icon' => $icon, 'label' => $label, 'title' => $title !== '' ? $title : $label, 'class' => $class);
		};

		if (!empty($m['placement'])) {
			$icons = array('top' => 'fa-arrow-up', 'bottom' => 'fa-arrow-down', 'cart' => 'fa-shopping-cart', 'product' => 'fa-tag', 'minicart' => 'fa-shopping-basket');
			$add(isset($icons[$m['placement']]) ? $icons[$m['placement']] : 'fa-map-marker', $this->language->get('text_placement_' . $m['placement']), $this->language->get('entry_placement'), 'abp-b-place');
		}

		if ((int)$m['priority'] > 0) {
			$add('fa-sort-amount-desc', sprintf($this->language->get('text_badge_priority'), (int)$m['priority']), $this->language->get('entry_priority'));
		}

		if ($m['type'] === 'tiers') {
			$add('fa-trophy', sprintf($this->language->get('text_badge_tiers'), count($opt['tiers'])), '', 'abp-b-fs');
		}

		if ($opt['coupon_id']) {
			$coupon = $this->model_extension_module_announcement_bar_pro->getCoupon($opt['coupon_id']);
			$add('fa-ticket', $coupon ? $coupon['code'] : '?', $this->language->get('text_badge_oc_coupon'), 'abp-b-coupon');
		}

		if ($opt['countries_mode'] !== 'all' && $opt['countries']) {
			$iso = array();
			foreach ($this->countryIso() as $country) {
				if (in_array($country['id'], $opt['countries'], true)) {
					$iso[] = $country['iso'];
				}
			}
			$label = ($opt['countries_mode'] === 'exclude' ? '≠ ' : '') . implode(', ', array_slice($iso, 0, 3)) . (count($iso) > 3 ? ' +' . (count($iso) - 3) : '');
			$add('fa-globe', $label, $this->language->get('text_countries_' . $opt['countries_mode']) . ': ' . implode(', ', $iso), 'abp-b-target');
		}

		if ($opt['fs_zones'] || $opt['fs_currencies']) {
			$add('fa-map-marker', $this->language->get('text_badge_fs_overrides'), '', 'abp-b-fs');
		}

		if ($this->fx->hasCartRules($opt)) {
			$add('fa-shopping-cart', $this->language->get('text_badge_cart'), '', 'abp-b-target');
		}

		if ($opt['utm_source'] !== '' || $opt['utm_medium'] !== '' || $opt['utm_campaign'] !== '' || $opt['referrer'] !== '') {
			$add('fa-bullseye', $this->language->get('text_badge_campaign'), '', 'abp-b-target');
		}

		if ($opt['visitor'] !== 'all') {
			$add('fa-user', $this->language->get('text_visitor_' . $opt['visitor']), $this->language->get('entry_visitor'), 'abp-b-target');
		}

		if ($opt['trigger'] !== 'none') {
			$add('fa-bolt', sprintf($this->language->get('text_badge_trigger_' . $opt['trigger']), (int)$opt['trigger_value']), $this->language->get('entry_trigger'), 'abp-b-schedule');
		}

		if ($opt['recur'] !== '') {
			$add('fa-repeat', sprintf($this->language->get('text_badge_recur_' . $opt['recur']), $opt['recur_time'], $this->language->get('text_wd_' . (int)$opt['recur_day'])), $this->language->get('entry_recur'), 'abp-b-schedule');
		}

		if ($opt['email']) {
			$add('fa-envelope', $this->language->get('text_badge_email'), '', 'abp-b-ab');
		}

		if ($opt['image'] !== '') {
			$add('fa-picture-o', $this->language->get('text_badge_image'));
		}

		return $out;
	}

	/** id + ISO code of every country (badges); loaded once. */
	private function countryIso() {
		static $list = null;

		if ($list === null) {
			$list = $this->model_extension_module_announcement_bar_pro->getCountries();
		}

		return $list;
	}

	private function strlen($value) {
		return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
	}

	private function substr($value, $length) {
		return function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length);
	}
}
