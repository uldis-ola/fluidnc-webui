<?php
namespace Opencart\Catalog\Controller\Extension\AnnouncementBarPro\Module;
/**
 * Announcement Bar Pro — catalog controller (OpenCart 4.0.x / 4.1.x)
 * Injected via event: catalog/view/common/header/after
 *
 * @version 2.0.3
 */
class AnnouncementBarPro extends \Opencart\System\Engine\Controller {
	public const VERSION = '2.0.3';
	public const CODE    = 'module_announcement_bar_pro';
	public const ROUTE   = 'extension/announcement_bar_pro/module/announcement_bar_pro';

	/** @var \Opencart\System\Library\Extension\AnnouncementBarPro\Sanitizer */
	private $abp;

	/**
	 * Event handler. Inserts the bar right after the opening <body> tag of the header output.
	 */
	public function injectBar(string &$route = '', array &$args = [], &$output = ''): void {
		if (!$this->config->get(self::CODE . '_status') || !is_string($output) || stripos($output, '<body') === false) {
			return;
		}

		try {
			$html = $this->render();
		} catch (\Throwable $e) {
			// Never break the storefront because of this module.
			$this->log->write('Announcement Bar Pro: ' . $e->getMessage());
			return;
		}

		if ($html === '') {
			return;
		}

		// First real <body> tag — skip any "<body" inside HTML comments or <script> blocks.
		$result = preg_replace_callback('#<!--.*?-->(*SKIP)(*FAIL)|<script\b.*?</script>(*SKIP)(*FAIL)|<body\b[^>]*>#is', function ($m) use ($html) {
			return $m[0] . "\n" . $html;
		}, $output, 1);

		// preg_* returns null on failure (e.g. backtrack limit): keep the original header.
		if (is_string($result)) {
			$output = $result;
		}
	}

	/**
	 * Layout-module entry point: not used, the bar is global.
	 */
	public function index(): string {
		return '';
	}

	private function render() {
		$this->abp = new \Opencart\System\Library\Extension\AnnouncementBarPro\Sanitizer();

		$language_id = (int)$this->config->get('config_language_id');
		$allow_html  = (bool)$this->config->get(self::CODE . '_allow_html');

		if ((int)$this->config->get(self::CODE . '_schema') < 3) {
			return '';
		}

		$this->load->model(self::ROUTE);
		$model = $this->model_extension_announcement_bar_pro_module_announcement_bar_pro;
		$rows  = $model->getActiveMessages($language_id, $model->getDefaultLanguageId((int)$this->config->get('config_store_id')));

		if (!$rows) {
			return '';
		}

		$store_id          = (int)$this->config->get('config_store_id');
		$customer_group_id = $this->customer->isLogged() ? (int)$this->customer->getGroupId() : (int)$this->config->get('config_customer_group_id');
		$current_route     = isset($this->request->get['route']) && is_string($this->request->get['route']) ? strtolower($this->request->get['route']) : 'common/home';

		$items = array();


		foreach ($rows as $row) {
			if (!$this->inList($row['stores'], $store_id) || !$this->inList($row['customer_groups'], $customer_group_id) || !$this->pageMatches($row['page_mode'], $row['page_routes'], $current_route)) {
				continue;
			}

			$html = $this->abp->toSafeHtml($row['text'], $allow_html);

			$countdown = '';
			if (!empty($row['countdown']) && !empty($row['date_end'])) {
				$end = strtotime($row['date_end']);
				if ($end) {
					$countdown = '<span class="abp-countdown" data-end="' . ($end * 1000) . '"></span>';
				}
			}

			// Replace {countdown} only in text, never inside a tag/attribute.
			list($html, $replaced) = $this->abp->replaceToken($html, '{countdown}', $countdown);

			if ($replaced) {
				$countdown = '';
			}

			if (trim(strip_tags($html)) === '' && $countdown === '') {
				continue;
			}

			$link      = $this->abp->url($row['link']);
			$link_text = $this->abp->plain($this->abp->decode($row['link_text']));

			$items[] = array(
				'id'         => (int)$row['message_id'],
				// Per-message dismiss key: changes when the message is edited, so edited/new messages reappear.
				'key'        => substr(md5($row['message_id'] . ':' . $row['date_modified'] . ':' . $language_id), 0, 12),
				'html'       => $html,           // already safe HTML
				'countdown'  => $countdown,      // built above, safe
				'bg_color'   => $this->abp->color($row['bg_color'], '#222222'),
				'text_color' => $this->abp->color($row['text_color'], '#ffffff'),
				'icon'       => $this->abp->icon($row['icon']),
				'link'       => $link,
				'link_text'  => $link_text,
				'new_tab'    => !empty($row['link_new_tab']),
				'wrap_link'  => $link !== '' && $link_text === '' && stripos($html, '<a ') === false,
				// Lets the script hide a message when its end time passes while the page is open.
				'end'        => !empty($row['date_end']) && strtotime($row['date_end']) ? strtotime($row['date_end']) * 1000 : 0
			);
		}

		if (!$items) {
			return '';
		}

		$this->load->language(self::ROUTE);

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
			'version'      => self::VERSION,
			'label'        => $this->language->get('abp_text_label'),
			'close'        => $this->language->get('abp_text_close'),
			'days'         => $this->language->get('abp_text_days')
		);

		return $this->load->view(self::ROUTE, $data);
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
