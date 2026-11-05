<?php
/**
 * Announcement Bar Pro — admin controller (OpenCart 3.0.x)
 * @version 1.0.0
 */
class ControllerExtensionModuleAnnouncementBarPro extends Controller {
	const VERSION    = '1.0.0';
	const CODE       = 'module_announcement_bar_pro';
	const EVENT_CODE = 'announcement_bar_pro';
	const MAX_TEXT   = 500;

	private $error = array();

	public function index() {
		$this->load->language('extension/module/announcement_bar_pro');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('localisation/language');

		$languages = $this->model_localisation_language->getLanguages();
		$token     = $this->session->data['user_token'];

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate($languages)) {
			$this->model_setting_setting->editSetting(self::CODE, $this->sanitize($this->request->post, $languages));

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $token . '&type=module', true));
		}

		$data['error_warning']    = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['error_text']       = isset($this->error['text']) ? $this->error['text'] : array();
		$data['error_bg_color']   = isset($this->error['bg_color']) ? $this->error['bg_color'] : '';
		$data['error_text_color'] = isset($this->error['text_color']) ? $this->error['text_color'] : '';

		$data['breadcrumbs'] = array(
			array('text' => $this->language->get('text_home'),      'href' => $this->url->link('common/dashboard', 'user_token=' . $token, true)),
			array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', 'user_token=' . $token . '&type=module', true)),
			array('text' => $this->language->get('heading_title'),  'href' => $this->url->link('extension/module/announcement_bar_pro', 'user_token=' . $token, true))
		);

		$data['action']    = $this->url->link('extension/module/announcement_bar_pro', 'user_token=' . $token, true);
		$data['cancel']    = $this->url->link('marketplace/extension', 'user_token=' . $token . '&type=module', true);
		$data['languages'] = $languages;
		$data['max_text']  = self::MAX_TEXT;
		$data['version']   = self::VERSION;

		// POST (after a failed validation) wins over saved config.
		$post = $this->request->server['REQUEST_METHOD'] == 'POST';

		$data['status']     = $post ? !empty($this->request->post[self::CODE . '_status']) : (bool)$this->config->get(self::CODE . '_status');
		$data['bg_color']   = $post ? $this->postString(self::CODE . '_bg_color')   : (string)$this->config->get(self::CODE . '_bg_color');
		$data['text_color'] = $post ? $this->postString(self::CODE . '_text_color') : (string)$this->config->get(self::CODE . '_text_color');

		if (!$this->isHexColor($data['bg_color']) && !$post)   $data['bg_color']   = '#222222';
		if (!$this->isHexColor($data['text_color']) && !$post) $data['text_color'] = '#ffffff';

		$texts = $post
			? (isset($this->request->post[self::CODE . '_text']) && is_array($this->request->post[self::CODE . '_text']) ? $this->request->post[self::CODE . '_text'] : array())
			: (array)$this->config->get(self::CODE . '_text');

		$data['text'] = array();
		foreach ($languages as $language) {
			$id = (int)$language['language_id'];
			// Request class already HTML-encodes POST; saved values are plain text. Decode both, Twig escapes on output.
			$data['text'][$id] = isset($texts[$id]) && is_string($texts[$id]) ? html_entity_decode($texts[$id], ENT_QUOTES, 'UTF-8') : '';
		}

		$data['header']      = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer']      = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/announcement_bar_pro', $data));
	}

	/**
	 * Called by admin/controller/extension/extension/module.php on install
	 * (that controller already checks the "modify" permission and grants access).
	 * Idempotent: safe to run more than once.
	 */
	public function install() {
		if (!$this->user->hasPermission('modify', 'extension/extension/module')) {
			return;
		}

		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode(self::EVENT_CODE);
		$this->model_setting_event->addEvent(self::EVENT_CODE, 'catalog/view/common/header/after', 'extension/module/announcement_bar_pro/injectBar');

		$this->load->model('setting/setting');
		if (!$this->model_setting_setting->getSetting(self::CODE)) {
			$this->model_setting_setting->editSetting(self::CODE, array(
				self::CODE . '_status'     => 0,
				self::CODE . '_bg_color'   => '#222222',
				self::CODE . '_text_color' => '#ffffff',
				self::CODE . '_text'       => array()
			));
		}
	}

	/**
	 * Removes only this module's event and settings. No customer/order data touched.
	 */
	public function uninstall() {
		if (!$this->user->hasPermission('modify', 'extension/extension/module')) {
			return;
		}

		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode(self::EVENT_CODE);

		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting(self::CODE);
	}

	protected function validate(array $languages) {
		if (!$this->user->hasPermission('modify', 'extension/module/announcement_bar_pro')) {
			$this->error['warning'] = $this->language->get('error_permission');
			return false;
		}

		if (!$this->isHexColor($this->postString(self::CODE . '_bg_color'))) {
			$this->error['bg_color'] = $this->language->get('error_color');
		}

		if (!$this->isHexColor($this->postString(self::CODE . '_text_color'))) {
			$this->error['text_color'] = $this->language->get('error_color');
		}

		$texts = isset($this->request->post[self::CODE . '_text']) ? $this->request->post[self::CODE . '_text'] : array();
		if (!is_array($texts)) {
			$texts = array();
		}

		$enabled = !empty($this->request->post[self::CODE . '_status']);

		foreach ($languages as $language) {
			$id    = (int)$language['language_id'];
			$value = isset($texts[$id]) && is_string($texts[$id]) ? $this->cleanText($texts[$id], false) : '';
			$len   = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);

			if ($len > self::MAX_TEXT) {
				$this->error['text'][$id] = sprintf($this->language->get('error_text_length'), self::MAX_TEXT);
			} elseif ($enabled && $len === 0) {
				$this->error['text'][$id] = $this->language->get('error_text_required');
			}
		}

		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('error_form');
		}

		return !$this->error;
	}

	/**
	 * Whitelist: only the 4 known keys are stored; everything else in POST is ignored.
	 */
	protected function sanitize(array $post, array $languages) {
		$texts = isset($post[self::CODE . '_text']) && is_array($post[self::CODE . '_text']) ? $post[self::CODE . '_text'] : array();
		$clean = array();

		foreach ($languages as $language) {
			$id = (int)$language['language_id'];
			$clean[$id] = isset($texts[$id]) && is_string($texts[$id]) ? $this->cleanText($texts[$id], true) : '';
		}

		return array(
			self::CODE . '_status'     => !empty($post[self::CODE . '_status']) ? 1 : 0,
			self::CODE . '_bg_color'   => strtolower($this->postString(self::CODE . '_bg_color')),
			self::CODE . '_text_color' => strtolower($this->postString(self::CODE . '_text_color')),
			self::CODE . '_text'       => $clean
		);
	}

	/**
	 * Plain text only: decode OC's request encoding, strip tags and control chars, collapse whitespace.
	 */
	protected function cleanText($value, $truncate) {
		$value = html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8');
		$value = strip_tags($value);
		$value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
		$value = trim(preg_replace('/\s+/u', ' ', (string)$value));

		if ($truncate && function_exists('mb_substr')) {
			$value = mb_substr($value, 0, self::MAX_TEXT, 'UTF-8');
		}

		return $value;
	}

	protected function postString($key) {
		return isset($this->request->post[$key]) && is_string($this->request->post[$key]) ? trim($this->request->post[$key]) : '';
	}

	protected function isHexColor($value) {
		return is_string($value) && (bool)preg_match('/^#[0-9a-fA-F]{6}$/', $value);
	}
}
