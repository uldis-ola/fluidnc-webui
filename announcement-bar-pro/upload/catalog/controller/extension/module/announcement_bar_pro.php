<?php
/**
 * Announcement Bar Pro — catalog controller (OpenCart 3.0.x)
 * Injected via event: catalog/view/common/header/after
 * @version 1.0.0
 */
class ControllerExtensionModuleAnnouncementBarPro extends Controller {
	const CODE = 'module_announcement_bar_pro';

	/**
	 * Event handler. Inserts the bar right after the opening <body> tag of the header output.
	 */
	public function injectBar(&$route, &$args, &$output) {
		if (!$this->config->get(self::CODE . '_status') || !is_string($output) || $output === '') {
			return;
		}

		$texts = $this->config->get(self::CODE . '_text');
		$id    = (int)$this->config->get('config_language_id');
		$text  = (is_array($texts) && isset($texts[$id]) && is_string($texts[$id])) ? trim($texts[$id]) : '';

		if ($text === '') {
			return;
		}

		$data['text']       = $text; // escaped in Twig with |e
		$data['bg_color']   = $this->color($this->config->get(self::CODE . '_bg_color'), '#222222');
		$data['text_color'] = $this->color($this->config->get(self::CODE . '_text_color'), '#ffffff');

		$html = $this->load->view('extension/module/announcement_bar_pro', $data);

		$output = preg_replace_callback('/<body\b[^>]*>/i', function ($m) use ($html) {
			return $m[0] . "\n" . $html;
		}, $output, 1);
	}

	/**
	 * Layout-module entry point: not used, bar is global.
	 */
	public function index() {
		return '';
	}

	private function color($value, $default) {
		return (is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value)) ? $value : $default;
	}
}
