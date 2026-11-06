<?php
namespace Opencart\Catalog\Controller\Extension\AnnouncementBarPro\Module;
/**
 * Announcement Bar Pro — catalog controller (OpenCart 4.0.x / 4.1.x)
 * Injected via events: catalog/view/common/header/after (footer/after as automatic fallback)
 *
 * @version 2.1.0
 */
class AnnouncementBarPro extends \Opencart\System\Engine\Controller {
	public const VERSION = '2.1.0';
	public const CODE    = 'module_announcement_bar_pro';
	public const ROUTE   = 'extension/announcement_bar_pro/module/announcement_bar_pro';

	/** @var \Opencart\System\Library\Extension\AnnouncementBarPro\Sanitizer */
	private $abp;

	/** Set once the bar is in this page, so the footer fallback never adds a second one. */
	/** True while rendering from the footer fallback: the script then moves a top bar to the top of <body>. */
	private $fallback = false;

	private static bool $injected = false;

	/**
	 * Event handler (header). Inserts the bar right after the opening <body> tag of the header output.
	 */
	public function injectBar(string &$route = '', array &$args = [], &$output = ''): void {
		if (self::$injected || !$this->config->get(self::CODE . '_status') || !is_string($output) || stripos($output, '<body') === false) {
			return;
		}

		// First real <body> tag — skip any "<body" inside HTML comments or <script> blocks.
		$this->inject($output, '#<!--.*?-->(*SKIP)(*FAIL)|<script\b.*?</script>(*SKIP)(*FAIL)|<body\b[^>]*>#is', true);
	}

	/**
	 * Event handler (footer) — automatic fallback for themes whose header template has no <body> tag
	 * (or that render the header through another route). The bar goes before </body>; the bar's own
	 * script then moves it to the top of the page before the first paint.
	 */
	public function injectBarFooter(string &$route = '', array &$args = [], &$output = ''): void {
		if (self::$injected || !$this->config->get(self::CODE . '_status') || !is_string($output) || $output === '') {
			return;
		}

		$this->fallback = true;

		if (!$this->inject($output, '#<!--.*?-->(*SKIP)(*FAIL)|<script\b.*?</script>(*SKIP)(*FAIL)|</body\s*>#is', false)) {
			// No </body> in the footer either: append; the browser still puts it inside <body>.
			$this->inject($output, '', false);
		}
	}

	/**
	 * Renders the bar and places it after (header) or before (footer) the matched tag.
	 * Returns false only when the pattern was not found; never throws.
	 */
	private function inject(&$output, string $pattern, bool $after): bool {
		try {
			$html = $this->render();
		} catch (\Throwable $e) {
			// Never break the storefront because of this module.
			$this->log->write('Announcement Bar Pro: ' . $e->getMessage());
			self::$injected = true;
			return true;
		}

		if ($html === '') {
			self::$injected = true; // nothing to show for this page; do not render again in the footer
			return true;
		}

		if ($pattern === '') {
			$output .= "\n" . $html;
			self::$injected = true;
			return true;
		}

		$found  = false;
		$result = preg_replace_callback($pattern, function ($m) use ($html, $after, &$found) {
			$found = true;
			return $after ? $m[0] . "\n" . $html : $html . "\n" . $m[0];
		}, $output, 1);

		// preg_* returns null on failure (e.g. backtrack limit): keep the original output.
		if (is_string($result) && $found) {
			$output = $result;
			self::$injected = true;
		}

		return $found;
	}

	/**
	 * Layout-module entry point: not used, the bar is global.
	 */
	public function index(): string {
		return '';
	}

	private function render() {
		$this->abp = $this->sanitizer();

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
			$seen = $html; // what the visitor sees (before the timer is inserted) — basis of the dismiss key

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
				// Per-message dismiss key from what the visitor sees (rendered text, button, link, icon): a re-save,
				// an HTML-mode switch or a visibility/date edit keeps it closed; a visible change shows it again.
				'key'        => substr(md5($row['message_id'] . '|' . $seen . '|' . $link_text . '|' . $link . '|' . $row['icon'] . '|' . $language_id), 0, 12),
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

		// English first, then the visitor's language on top (stores in other languages still get labels).
		$this->load->language(self::ROUTE, '', 'en-gb');
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
			'color_mode'   => $this->config->get(self::CODE . '_color_mode') === 'auto' ? 'auto' : 'custom',
			'fallback'     => $this->fallback,
			'dismiss_days' => $this->clamp($this->config->get(self::CODE . '_dismiss_days'), 0, 365, 1),
			'version'      => self::VERSION,
			'now'          => time() * 1000, // server clock: countdown/expiry must not trust the visitor's clock
			'label'        => $this->language->get('abp_text_label'),
			'close'        => $this->language->get('abp_text_close'),
			'days'         => $this->language->get('abp_text_days')
		);

		return $this->load->view(self::ROUTE, $data);
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
