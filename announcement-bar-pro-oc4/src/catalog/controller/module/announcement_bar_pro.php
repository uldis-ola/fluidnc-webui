<?php
namespace Opencart\Catalog\Controller\Extension\AnnouncementBarPro\Module;
\defined('VERSION') || exit; // not callable directly
/**
 * Announcement Bar Pro — catalog controller (OpenCart 4.0.x / 4.1.x)
 * Injected via events: catalog/view/common/header/after (footer/after as automatic fallback)
 *
 * @version 2.2.0
 */
class AnnouncementBarPro extends \Opencart\System\Engine\Controller {
	public const VERSION = '2.2.0';
	public const CODE    = 'module_announcement_bar_pro';
	public const ROUTE   = 'extension/announcement_bar_pro/module/announcement_bar_pro';

	/** @var \Opencart\System\Library\Extension\AnnouncementBarPro\Sanitizer */
	private $abp;

	/** Placeholder the footer leaves for resolveFallback(); never shown (HTML comment). */
	public const MARKER = '<!--abp-fallback-->';

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
	 * Event handler (footer). OpenCart renders the footer BEFORE the header, so the bar is never placed
	 * here: the footer only gets an invisible marker before </body>. resolveFallback() later removes it
	 * (header worked) or replaces it with the bar (theme header without <body>).
	 */
	public function injectBarFooter(string &$route = '', array &$args = [], &$output = ''): void {
		if (!$this->config->get(self::CODE . '_status') || !is_string($output) || $output === '' || strpos($output, self::MARKER) !== false) {
			return;
		}

		$result = preg_replace_callback('#<!--.*?-->(*SKIP)(*FAIL)|<script\b.*?</script>(*SKIP)(*FAIL)|</body\s*>#is', function ($m) {
			return self::MARKER . $m[0];
		}, $output, 1, $count);

		$output = (is_string($result) && $count) ? $result : $output . self::MARKER;
	}

	/**
	 * Event handler (catalog/view/[any]/after, i.e. every view). Runs on the finished page template, which contains both the
	 * header and the footer output: drop the marker, or use it when the header could not take the bar.
	 */
	public function resolveFallback(string &$route = '', array &$args = [], &$output = ''): void {
		if (!is_string($output) || strpos($output, self::MARKER) === false || preg_match('#(^|/)common/(footer|header)$#', (string)$route)) {
			return;
		}

		if (self::$injected || !$this->config->get(self::CODE . '_status')) {
			$output = str_replace(self::MARKER, '', $output);
			return;
		}

		$this->fallback = true;

		$output = str_replace(self::MARKER, '', $output);
		$this->inject($output, '#<!--.*?-->(*SKIP)(*FAIL)|<script\b.*?</script>(*SKIP)(*FAIL)|</body\s*>#is', false) || $this->inject($output, '', false);
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

		$schema = (int)$this->config->get(self::CODE . '_schema');

		if ($schema < 3) {
			return '';
		}

		$this->load->model(self::ROUTE);
		$model = $this->model_extension_announcement_bar_pro_module_announcement_bar_pro;
		$rows  = $model->getActiveMessages($language_id, $model->getDefaultLanguageId((int)$this->config->get('config_store_id')), $schema);

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

			$html   = $this->abp->toSafeHtml($row['text'], $allow_html);
			$html_m = isset($row['mobile_text']) && trim((string)$row['mobile_text']) !== '' ? $this->abp->toSafeHtml($row['mobile_text'], $allow_html) : '';
			if (trim(strip_tags($html_m)) === '') {
				$html_m = '';
			}
			// What the visitor sees (before the timer is inserted) — basis of the dismiss key. Without a phone text the
			// key stays exactly as in 2.1.x, so messages closed before the upgrade stay closed.
			$seen = $html_m === '' ? $html : $html . '|m|' . $html_m;

			$countdown = '';
			if (!empty($row['countdown']) && !empty($row['date_end'])) {
				$end = strtotime($row['date_end']);
				if ($end) {
					$countdown = '<span class="abp-countdown" data-end="' . ($end * 1000) . '"></span>';
				}
			}

			// Replace {countdown} only in text, never inside a tag/attribute.
			list($html, $replaced) = $this->abp->replaceToken($html, '{countdown}', $countdown);

			if ($html_m !== '') {
				// Phone text: its own {countdown}, or the timer after it — like the normal text.
				list($html_m, $replaced_m) = $this->abp->replaceToken($html_m, '{countdown}', $countdown);

				if (!$replaced && $countdown !== '') {
					$html .= ' ' . $countdown;
				}

				if (!$replaced_m && $countdown !== '') {
					$html_m .= ' ' . $countdown;
				}

				$countdown = '';
			} elseif ($replaced) {
				$countdown = '';
			}

			if (trim(strip_tags($html)) === '' && trim(strip_tags($html_m)) === '' && $countdown === '') {
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
				'html_m'     => $html_m,         // optional phone text (below 768 px), already safe HTML
				'device'     => isset($row['device']) && in_array($row['device'], array('desktop', 'mobile'), true) ? $row['device'] : 'all',
				'countdown'  => $countdown,      // built above, safe
				'bg_color'   => $this->abp->color($row['bg_color'], '#222222'),
				'text_color' => $this->abp->color($row['text_color'], '#ffffff'),
				'icon'       => $this->abp->icon($row['icon']),
				'link'       => $link,
				'link_text'  => $link_text,
				'new_tab'    => !empty($row['link_new_tab']),
				'wrap_link'  => $link !== '' && $link_text === '' && stripos($html . $html_m, '<a ') === false,
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
			// Statistics endpoint (counters only); empty until the 2.2.0 columns exist.
			'track'        => $schema >= 6 ? str_replace('&amp;', '&', $this->url->link(self::ROUTE . $this->sep() . 'track')) : '',
			'now'          => time() * 1000, // server clock: countdown/expiry must not trust the visitor's clock
			'label'        => $this->language->get('abp_text_label'),
			'close'        => $this->language->get('abp_text_close'),
			'days'         => $this->language->get('abp_text_days')
		);

		return $this->load->view(self::ROUTE, $data);
	}

	/**
	 * Only requests from the shop's own pages count (the browser's Sec-Fetch-Site, or Origin/Referer host).
	 * Scripts and other sites cannot inflate the statistics with cookie-less requests.
	 */
	private function sameOrigin(): bool {
		$server = $this->request->server;
		$host   = isset($server['HTTP_HOST']) && is_string($server['HTTP_HOST']) ? strtolower($server['HTTP_HOST']) : '';

		if (isset($server['HTTP_SEC_FETCH_SITE']) && is_string($server['HTTP_SEC_FETCH_SITE'])) {
			return $server['HTTP_SEC_FETCH_SITE'] === 'same-origin';
		}

		foreach (array('HTTP_ORIGIN', 'HTTP_REFERER') as $key) {
			if (!empty($server[$key]) && is_string($server[$key])) {
				$from = parse_url($server[$key], PHP_URL_HOST);
				$port = parse_url($server[$key], PHP_URL_PORT);

				return $host !== '' && is_string($from) && strtolower($from . ($port ? ':' . $port : '')) === $host;
			}
		}

		return false;
	}

	/**
	 * Statistics endpoint (POST id + type). Counters only: no personal data is read or stored, the session
	 * is not touched. Only same-origin requests for messages that are on and inside their date window count.
	 * Always answers with an empty 204 response and never outputs errors.
	 */
	public function track(): void {
		try {
			if (($this->request->server['REQUEST_METHOD'] ?? '') === 'POST' && $this->config->get(self::CODE . '_status') && (int)$this->config->get(self::CODE . '_schema') >= 6) {
				$id   = isset($this->request->post['id']) && is_string($this->request->post['id']) && preg_match('/^\d{1,10}$/', $this->request->post['id']) ? (int)$this->request->post['id'] : 0;
				$type = isset($this->request->post['type']) && is_string($this->request->post['type']) ? $this->request->post['type'] : '';

				if ($id > 0 && in_array($type, array('view', 'click'), true) && $this->sameOrigin()) {
					// No session write here: a parallel request (cart, login) must never lose its session data.
					// The browser sends each message's view/click at most once per tab session (sessionStorage).
					$this->load->model(self::ROUTE);
					$this->model_extension_announcement_bar_pro_module_announcement_bar_pro->track($id, $type);
				}
			}
		} catch (\Throwable $e) {
			// Statistics must never produce an error page.
		}

		if (!headers_sent()) {
			http_response_code(204);
			header('Cache-Control: no-store');
		}

		$this->response->setOutput('');
	}

	/** Method separator: "." from OpenCart 4.0.2.0, "|" in 4.0.0.x–4.0.1.x. */
	private function sep(): string {
		return version_compare(VERSION, '4.0.2.0', '>=') ? '.' : '|';
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
