<?php
/**
 * Announcement Bar Pro — catalog controller (OpenCart 3.0.x)
 * Injected via event: catalog/view/common/header/after
 * @version 2.0.0
 */
class ControllerExtensionModuleAnnouncementBarPro extends Controller {
	const VERSION = '2.0.0';
	const CODE    = 'module_announcement_bar_pro';

	/**
	 * Event handler. Inserts the bar right after the opening <body> tag of the header output.
	 */
	public function injectBar(&$route, &$args, &$output) {
		if (!$this->config->get(self::CODE . '_status') || !is_string($output) || stripos($output, '<body') === false) {
			return;
		}

		try {
			$html = $this->render();
		} catch (\Exception $e) {
			// Never break the storefront because of this module.
			$this->log->write('Announcement Bar Pro: ' . $e->getMessage());
			return;
		}

		if ($html === '') {
			return;
		}

		$output = preg_replace_callback('/<body\b[^>]*>/i', function ($m) use ($html) {
			return $m[0] . "\n" . $html;
		}, $output, 1);
	}

	/**
	 * Layout-module entry point: not used, the bar is global.
	 */
	public function index() {
		return '';
	}

	private function render() {
		$this->load->library('announcementbarpro/sanitizer');

		$language_id = (int)$this->config->get('config_language_id');
		$allow_html  = (bool)$this->config->get(self::CODE . '_allow_html');

		if ((int)$this->config->get(self::CODE . '_schema') >= 2) {
			$this->load->model('extension/module/announcement_bar_pro');
			$rows = $this->model_extension_module_announcement_bar_pro->getActiveMessages($language_id);
		} else {
			$rows = $this->legacyRows($language_id);
		}

		if (!$rows) {
			return '';
		}

		$store_id          = (int)$this->config->get('config_store_id');
		$customer_group_id = $this->customer->isLogged() ? (int)$this->customer->getGroupId() : (int)$this->config->get('config_customer_group_id');
		$current_route     = isset($this->request->get['route']) && is_string($this->request->get['route']) ? strtolower($this->request->get['route']) : 'common/home';

		$items = array();
		$hash  = '';

		foreach ($rows as $row) {
			if (!$this->inList($row['stores'], $store_id) || !$this->inList($row['customer_groups'], $customer_group_id) || !$this->pageMatches($row['page_mode'], $row['page_routes'], $current_route)) {
				continue;
			}

			$html = $this->sanitizer->toSafeHtml($row['text'], $allow_html);

			$countdown = '';
			if (!empty($row['countdown']) && !empty($row['date_end'])) {
				$end = strtotime($row['date_end']);
				if ($end) {
					$countdown = '<span class="abp-countdown" data-end="' . ($end * 1000) . '"></span>';
				}
			}

			if ($countdown !== '' && strpos($html, '{countdown}') !== false) {
				$html = preg_replace('/\{countdown\}/', $countdown, $html, 1);
				$countdown = '';
			}

			$html = str_replace('{countdown}', '', $html);

			if (trim(strip_tags($html)) === '' && $countdown === '') {
				continue;
			}

			$link      = $this->sanitizer->url($row['link']);
			$link_text = $this->sanitizer->plain($row['link_text']);

			$items[] = array(
				'id'         => (int)$row['message_id'],
				'html'       => $html,           // already safe HTML
				'countdown'  => $countdown,      // built above, safe
				'bg_color'   => $this->sanitizer->color($row['bg_color'], '#222222'),
				'text_color' => $this->sanitizer->color($row['text_color'], '#ffffff'),
				'icon'       => $this->sanitizer->icon($row['icon']),
				'link'       => $link,
				'link_text'  => $link_text,
				'new_tab'    => !empty($row['link_new_tab']),
				'wrap_link'  => $link !== '' && $link_text === '' && stripos($html, '<a ') === false
			);

			$hash .= $row['message_id'] . ':' . $row['date_modified'] . ';';
		}

		if (!$items) {
			return '';
		}

		$this->load->language('extension/module/announcement_bar_pro');

		$position = $this->config->get(self::CODE . '_position') === 'bottom' ? 'bottom' : 'top';
		$mode     = $this->config->get(self::CODE . '_mode');

		$data = array(
			'items'        => $items,
			'position'     => $position,
			'sticky'       => $position === 'top' && $this->config->get(self::CODE . '_sticky'),
			'mode'         => in_array($mode, array('rotate', 'marquee', 'stack'), true) ? $mode : 'rotate',
			'interval'     => $this->clamp($this->config->get(self::CODE . '_interval'), 2, 60, 5),
			'speed'        => $this->clamp($this->config->get(self::CODE . '_speed'), 20, 300, 60),
			'font_size'    => $this->clamp($this->config->get(self::CODE . '_font_size'), 10, 30, 14),
			'padding'      => $this->clamp($this->config->get(self::CODE . '_padding'), 0, 30, 8),
			'dismissible'  => (bool)$this->config->get(self::CODE . '_dismissible'),
			'dismiss_days' => $this->clamp($this->config->get(self::CODE . '_dismiss_days'), 0, 365, 1),
			'hash'         => substr(md5($hash . $language_id), 0, 12),
			'version'      => self::VERSION,
			'label'        => $this->language->get('text_label'),
			'close'        => $this->language->get('text_close'),
			'days'         => $this->language->get('text_days')
		);

		return $this->load->view('extension/module/announcement_bar_pro', $data);
	}

	/**
	 * v1.0.0 settings (files upgraded, admin page not opened yet).
	 */
	private function legacyRows($language_id) {
		$texts = $this->config->get(self::CODE . '_text');

		if (!is_array($texts) || empty($texts[$language_id]) || !is_string($texts[$language_id])) {
			return array();
		}

		return array(array(
			'message_id'      => 0,
			'text'            => $texts[$language_id],
			'link_text'       => '',
			'date_end'        => null,
			'bg_color'        => $this->config->get(self::CODE . '_bg_color'),
			'text_color'      => $this->config->get(self::CODE . '_text_color'),
			'link'            => '',
			'link_new_tab'    => 0,
			'icon'            => '',
			'countdown'       => 0,
			'stores'          => '[]',
			'customer_groups' => '[]',
			'page_mode'       => 'all',
			'page_routes'     => '',
			'date_modified'   => 'v1'
		));
	}

	private function inList($json, $id) {
		$list = json_decode((string)$json, true);

		return !is_array($list) || !$list || in_array((int)$id, array_map('intval', $list), true);
	}

	private function pageMatches($mode, $routes, $current) {
		if ($mode === 'home') {
			return $current === 'common/home' || $current === '';
		}

		if ($mode !== 'include' && $mode !== 'exclude') {
			return true;
		}

		$match = false;

		foreach (preg_split('/\r?\n/', (string)$routes) as $pattern) {
			$pattern = strtolower(trim($pattern));

			if ($pattern === '') {
				continue;
			}

			if ($pattern === '*' || $pattern === $current || (substr($pattern, -2) === '/*' && strpos($current . '/', substr($pattern, 0, -1)) === 0)) {
				$match = true;
				break;
			}
		}

		return $mode === 'include' ? $match : !$match;
	}

	private function clamp($value, $min, $max, $default) {
		if (!is_numeric($value)) {
			return $default;
		}

		return max($min, min($max, (int)$value));
	}
}
