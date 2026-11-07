<?php
namespace Opencart\Catalog\Controller\Extension\AnnouncementBarPro\Module;
\defined('VERSION') || exit; // not callable directly
/**
 * Announcement Bar Pro — catalog controller (OpenCart 4.0.x / 4.1.x)
 * Injected via events: catalog/view/common/header/after (footer/after as automatic fallback)
 *
 * @version 2.3.0
 */
class AnnouncementBarPro extends \Opencart\System\Engine\Controller {
	public const VERSION = '2.3.0';
	public const CODE    = 'module_announcement_bar_pro';
	public const ROUTE   = 'extension/announcement_bar_pro/module/announcement_bar_pro';

	/** @var \Opencart\System\Library\Extension\AnnouncementBarPro\Sanitizer */
	private $abp;

	/** Placeholder the footer leaves for resolveFallback(); never shown (HTML comment). */
	public const MARKER = '<!--abp-fallback-->';

	/** True while rendering from the footer fallback: the script then moves a top bar to the top of <body>. */
	private $fallback = false;

	/** Set once the bar is in this page, so the footer fallback never adds a second one. */
	private static bool $injected = false;

	/** Categories of the product on this page (loaded once, only when a message targets categories). */
	private $product_categories = null;

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
		$rows = $this->model_extension_announcement_bar_pro_module_announcement_bar_pro->getActiveMessages($language_id, $this->defaultLanguageId(), $schema);

		if (!$rows) {
			return '';
		}

		$store_id          = (int)$this->config->get('config_store_id');
		$customer_group_id = $this->customer->isLogged() ? (int)$this->customer->getGroupId() : (int)$this->config->get('config_customer_group_id');
		$current_route     = $this->currentRoute();
		$module_style      = $this->styleValue($this->config->get(self::CODE . '_style'), 'solid');

		// English first, then the visitor's language on top (stores in other languages still get labels).
		$this->load->language(self::ROUTE, '', 'en-gb');
		$this->load->language(self::ROUTE);

		$items = array();
		$has_fs = false;

		foreach ($rows as $row) {
			if (!$this->inList($row['stores'], $store_id) || !$this->inList($row['customer_groups'], $customer_group_id) || !$this->pageMatches($row['page_mode'], $row['page_routes'], $current_route) || !$this->targetMatches($row, $current_route)) {
				continue;
			}

			$item = $this->buildItem($row, $language_id, $allow_html, $module_style);

			if ($item) {
				$items[] = $item;
				$has_fs  = $has_fs || $item['fs'];
			}
		}

		if (!$items) {
			return '';
		}

		$position = $this->config->get(self::CODE . '_position') === 'bottom' ? 'bottom' : 'top';
		$mode     = $this->config->get(self::CODE . '_mode');
		$mode     = in_array($mode, array('rotate', 'marquee', 'stack'), true) ? $mode : 'rotate';

		$data = array(
			'items'        => $items,
			'position'     => $position,
			'sticky'       => $position === 'top' && $this->config->get(self::CODE . '_sticky'),
			'mode'         => $mode,
			'transition'   => $this->config->get(self::CODE . '_transition') === 'slide' ? 'slide' : 'fade',
			'arrows'       => $mode === 'rotate' && count($items) > 1 && $this->config->get(self::CODE . '_arrows'),
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
			// Free-shipping refresh after AJAX cart changes (read-only JSON); only when such a message is on the page.
			'cart'         => $schema >= 7 && $has_fs ? str_replace('&amp;', '&', $this->url->link(self::ROUTE . $this->sep() . 'cart')) : '',
			'now'          => time() * 1000, // server clock: countdown/expiry/schedule must not trust the visitor's clock
			'tz'           => (int)date('Z'), // store time zone offset for the weekly schedule
			'label'        => $this->language->get('abp_text_label'),
			'close'        => $this->language->get('abp_text_close'),
			'days'         => $this->language->get('abp_text_days'),
			'copy'         => $this->language->get('abp_text_copy'),
			'copied'       => $this->language->get('abp_text_copied'),
			'prev'         => $this->language->get('abp_text_prev'),
			'next'         => $this->language->get('abp_text_next')
		);

		return $this->load->view(self::ROUTE, $data);
	}

	/**
	 * One message as the template needs it, or null when there is nothing to show.
	 */
	private function buildItem(array $row, $language_id, $allow_html, $module_style) {
		$fs = $this->freeShipping($row);

		$variants = array();
		$seen     = '';

		foreach ($this->variantNames($row) as $name) {
			$v = $this->variant($row, $name, $allow_html, $fs);

			if ($v === null) {
				continue;
			}

			$variants[] = $v;
			// What the visitor sees (before timer / amounts are inserted) — basis of the dismiss key. A message
			// that uses none of the 2.3.0 features keeps exactly its 2.1.x / 2.2.0 key (closed stays closed).
			$seen .= ($name === 'a' ? '' : '|b|' . $v['link_text'] . '|') . $v['seen'];
		}

		if (!$variants) {
			return null;
		}

		$link      = $this->abp->url($row['link']);
		$link_text = $variants[0]['link_text'];
		$coupon    = $this->abp->coupon(isset($row['coupon']) ? $row['coupon'] : '');

		if ($coupon !== '') {
			$seen .= '|c|' . $coupon;
		}

		if ($fs) {
			$seen .= '|fs|' . $fs['threshold'] . '|' . $this->abp->toSafeHtml(isset($row['success_text']) ? $row['success_text'] : '', $allow_html);
		}

		foreach ($variants as $i => $v) {
			$variants[$i]['wrap_link'] = $link !== '' && $v['link_text'] === '' && stripos($v['txt'], '<a ') === false;
		}

		$weekdays = $this->abp->weekdays(isset($row['weekdays']) ? $row['weekdays'] : '');
		$t1       = $this->abp->clockTime(isset($row['time_start']) ? $row['time_start'] : '');
		$t2       = $this->abp->clockTime(isset($row['time_end']) ? $row['time_end'] : '');

		if ($t1 === '' || $t2 === '' || $t1 === $t2) {
			$t1 = $t2 = '';
		}

		$icon = $this->abp->icon($row['icon']);

		return array(
			'id'         => (int)$row['message_id'],
			// Per-message dismiss key from what the visitor sees (rendered text, button, link, icon): a re-save,
			// an HTML-mode switch or a visibility/date edit keeps it closed; a visible change shows it again.
			'key'        => substr(md5($row['message_id'] . '|' . $seen . '|' . $link_text . '|' . $link . '|' . $row['icon'] . '|' . $language_id), 0, 12),
			'variants'   => $variants,
			'ab'         => count($variants) > 1,
			'device'     => isset($row['device']) && in_array($row['device'], array('desktop', 'mobile'), true) ? $row['device'] : 'all',
			'bg_color'   => $this->abp->color($row['bg_color'], '#222222'),
			'text_color' => $this->abp->color($row['text_color'], '#ffffff'),
			'style'      => $this->styleValue(isset($row['style']) ? $row['style'] : '', $module_style),
			'btn_style'  => isset($row['button_style']) && in_array($row['button_style'], array('filled', 'outline', 'link'), true) ? $row['button_style'] : 'outline',
			'icon'       => $icon !== '' && strpos($icon, 'fa-solid ') === 0 ? 'fa ' . $icon : $icon, // "fa" too: FA4/5 theme fonts
			'link'       => $link,
			'new_tab'    => !empty($row['link_new_tab']),
			'coupon'     => $coupon,
			'copy_aria'  => sprintf($this->language->get('abp_text_copy_code'), $coupon),
			'fs'         => (bool)$fs,
			'done'       => $fs && $fs['done'],
			'pct'        => $fs ? $fs['pct'] : 0,
			'progress'   => $fs && !empty($row['progress_bar']),
			'wd'         => $weekdays,
			't1'         => $t1,
			't2'         => $t2,
			// Outside its weekly schedule right now: rendered hidden, the script shows it when the time comes.
			'off'        => !$this->abp->scheduleOpen($weekdays, $t1, $t2),
			// Lets the script hide a message when its end time passes while the page is open.
			'end'        => !empty($row['date_end']) && strtotime($row['date_end']) ? strtotime($row['date_end']) * 1000 : 0
		);
	}

	/** 'a', plus 'b' while the A/B test is on and variant B has a text. */
	private function variantNames(array $row) {
		$names = array('a');

		if (!empty($row['ab_test']) && isset($row['text_b']) && trim(strip_tags($this->abp->decode($row['text_b']))) !== '') {
			$names[] = 'b';
		}

		return $names;
	}

	/**
	 * Text of one variant: safe HTML with the phone text, {countdown} and (free shipping) {remaining} or the
	 * success text. Returns null when the variant has nothing to show.
	 */
	private function variant(array $row, $name, $allow_html, $fs) {
		$sfx    = $name === 'b' ? '_b' : '';
		$html   = $this->abp->toSafeHtml(isset($row['text' . $sfx]) ? $row['text' . $sfx] : '', $allow_html);
		$mobile = isset($row['mobile_text' . $sfx]) ? (string)$row['mobile_text' . $sfx] : '';
		$html_m = trim($mobile) !== '' ? $this->abp->toSafeHtml($mobile, $allow_html) : '';

		if (trim(strip_tags($html_m)) === '') {
			$html_m = '';
		}

		$seen = $html_m === '' ? $html : $html . '|m|' . $html_m;

		if ($fs) {
			$success = $fs['done'] ? $this->abp->toSafeHtml(isset($row['success_text']) ? $row['success_text'] : '', $allow_html) : '';

			if (trim(strip_tags($success)) !== '') {
				$html   = $success;
				$html_m = '';
			} else {
				list($html) = $this->abp->replaceToken($html, '{remaining}', $fs['remaining']);
				list($html_m) = $this->abp->replaceToken($html_m, '{remaining}', $fs['remaining']);
			}
		}

		$countdown = '';
		if (!empty($row['countdown']) && !empty($row['date_end'])) {
			$end = strtotime($row['date_end']);
			if ($end) {
				$countdown = '<span class="abp-countdown" data-end="' . ($end * 1000) . '"></span>';
			}
		}

		// Replace {countdown} only in text, never inside a tag/attribute; without the token the timer follows the text.
		list($html, $replaced) = $this->abp->replaceToken($html, '{countdown}', $countdown);
		$replaced_m = false;

		if ($html_m !== '') {
			list($html_m, $replaced_m) = $this->abp->replaceToken($html_m, '{countdown}', $countdown);
		}

		if (trim(strip_tags($html)) === '' && trim(strip_tags($html_m)) === '' && $countdown === '') {
			return null;
		}

		if ($html_m !== '' && $countdown !== '') {
			$html   .= $replaced ? '' : ' ' . $countdown;
			$html_m .= $replaced_m ? '' : ' ' . $countdown;
			$txt     = $this->abp->textHtml($html, $html_m);
		} else {
			$txt = $this->abp->textHtml($html, $html_m) . (!$replaced && $countdown !== '' ? ' ' . $countdown : '');
		}

		return array(
			'name'      => $name,
			'txt'       => $txt,  // already safe HTML
			'seen'      => $seen,
			'link_text' => $this->abp->plain($this->abp->decode(isset($row['link_text' . $sfx]) && ($name === 'a' || trim((string)$row['link_text' . $sfx]) !== '') ? $row['link_text' . $sfx] : $row['link_text']))
		);
	}

	/**
	 * Free-shipping state from the cart subtotal (store's default currency), formatted in the visitor's currency.
	 * Null for standard messages.
	 */
	private function freeShipping(array $row) {
		if (!isset($row['type']) || $row['type'] !== 'free_shipping' || (float)$row['threshold'] <= 0) {
			return null;
		}

		$threshold = (float)$row['threshold'];
		$subtotal  = (float)$this->cart->getSubTotal();
		$remaining = max(0, $threshold - $subtotal);
		$currency  = isset($this->session->data['currency']) && is_string($this->session->data['currency']) ? $this->session->data['currency'] : $this->config->get('config_currency');
		$amount    = html_entity_decode($this->currency->format($remaining, $currency), ENT_QUOTES, 'UTF-8');

		return array(
			'threshold' => number_format($threshold, 4, '.', ''),
			'done'      => $subtotal >= $threshold,
			'pct'       => (int)min(100, floor($subtotal * 100 / $threshold)),
			'remaining' => '<span class="abp-remaining">' . htmlspecialchars($amount, ENT_QUOTES, 'UTF-8') . '</span>'
		);
	}

	/**
	 * Free-shipping refresh (GET ids=1,2): read-only JSON with the new text of each variant and the progress.
	 * Same-origin only; changes nothing (OpenCart itself keeps the session as on any page view); never outputs errors.
	 */
	public function cart(): void {
		$json = array('m' => new \stdClass());

		try {
			if (isset($this->request->server['REQUEST_METHOD']) && $this->request->server['REQUEST_METHOD'] === 'GET' && $this->config->get(self::CODE . '_status') && (int)$this->config->get(self::CODE . '_schema') >= 7 && $this->sameOrigin()) {
				$this->abp = $this->sanitizer();
				$ids       = $this->abp->idList(isset($this->request->get['ids']) && is_string($this->request->get['ids']) ? $this->request->get['ids'] : '', 20);

				if ($ids) {
					$language_id = (int)$this->config->get('config_language_id');
					$allow_html  = (bool)$this->config->get(self::CODE . '_allow_html');
					$out         = array();

					$this->load->model(self::ROUTE);

					foreach ($this->model_extension_announcement_bar_pro_module_announcement_bar_pro->getActiveMessages($language_id, $this->defaultLanguageId(), 7) as $row) {
						if (!in_array((int)$row['message_id'], $ids, true)) {
							continue;
						}

						$fs = $this->freeShipping($row);

						if (!$fs) {
							continue;
						}

						$entry = array('p' => $fs['pct'], 'done' => $fs['done']);

						foreach ($this->variantNames($row) as $name) {
							$v = $this->variant($row, $name, $allow_html, $fs);
							$entry[$name] = $v ? $v['txt'] : '';
						}

						$out[(string)(int)$row['message_id']] = $entry;
					}

					$json['m'] = $out ? $out : new \stdClass();
				}
			}
		} catch (\Throwable $e) {
			// Never output errors.
		}

		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		$this->response->addHeader('Cache-Control: no-store');
		$this->response->addHeader('X-Content-Type-Options: nosniff');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Only requests from the shop's own pages count (the browser's Sec-Fetch-Site, or Origin/Referer host).
	 * Scripts and other sites cannot inflate the statistics with cookie-less requests.
	 */
	private function sameOrigin() {
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
	 * Statistics endpoint (POST id + type [+ v]). Counters only: no personal data is read or stored, the session
	 * is not touched. Only same-origin requests for messages that are on and inside their date window count.
	 * Always answers with an empty 204 response and never outputs errors.
	 */
	public function track(): void {
		try {
			$schema = (int)$this->config->get(self::CODE . '_schema');

			if (isset($this->request->server['REQUEST_METHOD']) && $this->request->server['REQUEST_METHOD'] === 'POST' && $this->config->get(self::CODE . '_status') && $schema >= 6) {
				$id      = isset($this->request->post['id']) && is_string($this->request->post['id']) && preg_match('/^\d{1,10}$/', $this->request->post['id']) ? (int)$this->request->post['id'] : 0;
				$type    = isset($this->request->post['type']) && is_string($this->request->post['type']) ? $this->request->post['type'] : '';
				$variant = isset($this->request->post['v']) && $this->request->post['v'] === 'b' ? 'b' : 'a';

				if ($id > 0 && in_array($type, array('view', 'click', 'copy'), true) && $this->sameOrigin()) {
					// No session write here: a parallel request (cart, login) must never lose its session data.
					// The browser sends each message's view/click/copy at most once per tab session (sessionStorage).
					$this->load->model(self::ROUTE);
					$this->model_extension_announcement_bar_pro_module_announcement_bar_pro->track($id, $type, $variant, $schema);
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
	 * Sanitizer: autoloaded on 4.0.1+; 4.0.0.x registers no Library\Extension namespace, so require it.
	 */
	private function sanitizer(): \Opencart\System\Library\Extension\AnnouncementBarPro\Sanitizer {
		if (!class_exists('Opencart\System\Library\Extension\AnnouncementBarPro\Sanitizer')) {
			require_once(DIR_EXTENSION . 'announcement_bar_pro/system/library/sanitizer.php');
		}

		return new \Opencart\System\Library\Extension\AnnouncementBarPro\Sanitizer();
	}

	/**
	 * Store's default language (4.1: config_language_catalog, 4.0: config_language), used when a message has no
	 * text in the visitor's language.
	 */
	private function defaultLanguageId(): int {
		$this->load->model(self::ROUTE);

		return $this->model_extension_announcement_bar_pro_module_announcement_bar_pro->getDefaultLanguageId((int)$this->config->get('config_store_id'));
	}

	private function currentRoute() {
		return isset($this->request->get['route']) && is_string($this->request->get['route']) ? strtolower($this->request->get['route']) : 'common/home';
	}

	private function styleValue($value, $default) {
		return in_array($value, array('solid', 'gradient', 'soft', 'minimal', 'glass'), true) ? $value : $default;
	}

	private function inList($json, $id) {
		$list = json_decode((string)$json, true);

		return !is_array($list) || !$list || in_array((int)$id, array_map('intval', $list), true);
	}

	/**
	 * Category / product targeting: with lists set, only category pages of those categories, product pages of
	 * those products and product pages whose product is in one of those categories.
	 */
	private function targetMatches(array $row, $route) {
		$categories = $this->abp->idList(isset($row['categories']) ? $row['categories'] : '');
		$products   = $this->abp->idList(isset($row['products']) ? $row['products'] : '');

		if (!$categories && !$products) {
			return true;
		}

		$get = $this->request->get;

		if ($route === 'product/category' && $categories && isset($get['path']) && is_string($get['path'])) {
			$path = explode('_', $get['path']);

			return in_array((int)end($path), $categories, true);
		}

		if ($route === 'product/product' && isset($get['product_id']) && is_scalar($get['product_id']) && (int)$get['product_id'] > 0) {
			$product_id = (int)$get['product_id'];

			if (in_array($product_id, $products, true)) {
				return true;
			}

			if ($categories) {
				if ($this->product_categories === null) {
					$this->product_categories = $this->model_extension_announcement_bar_pro_module_announcement_bar_pro->getProductCategories($product_id);
				}

				return (bool)array_intersect($this->product_categories, $categories);
			}
		}

		return false;
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
