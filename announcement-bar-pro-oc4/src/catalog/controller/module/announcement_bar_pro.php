<?php
namespace Opencart\Catalog\Controller\Extension\AnnouncementBarPro\Module;
\defined('VERSION') || exit; // not callable directly
/**
 * Announcement Bar Pro — catalog controller (OpenCart 4.0.x / 4.1.x)
 * Bars: event catalog/view/common/header/after (footer/after + view/[any]/after as automatic fallback).
 * Inline boxes (2.4.0): checkout/cart, product/product and common/cart views; each fails silently without its hook.
 * Public endpoints: track, cart, subscribe (POST, JSON), confirm / unsubscribe (pages). None writes the session.
 * @version 2.4.0
 */
class AnnouncementBarPro extends \Opencart\System\Engine\Controller {
	public const VERSION = '2.4.0';
	public const CODE    = 'module_announcement_bar_pro';
	public const ROUTE   = 'extension/announcement_bar_pro/module/announcement_bar_pro';

	/** @var \Opencart\System\Library\Extension\AnnouncementBarPro\Sanitizer */
	private $abp;

	/** @var \Opencart\System\Library\Extension\AnnouncementBarPro\Features */
	private $fx;

	/** Placeholder the footer leaves for resolveFallback(); never shown (HTML comment). */
	public const MARKER = '<!--abp-fallback-->';

	/** True while rendering from the footer fallback: the script then moves a top bar to the top of <body>. */
	private $fallback = false;

	/** Set once the bars are in this page, so the footer fallback never adds them twice. */
	private static bool $injected = false;

	/** Styles and scripts are printed once per page (the first bar or box that is rendered). */
	private static bool $assets = false;

	/** Messages of this request by placement, worked out once (every event handler is a new controller instance). */
	private static $prepared = null;

	/** Categories of the product on this page (loaded once, only when a message targets categories). */
	private $product_categories = null;

	/** Visitor's country and zone: array(country_id, zone_id). */
	private $place = null;

	/** Cart subtotal, item count, product and category IDs (loaded only when a message has cart conditions). */
	private $cart_state = null;

	/* ------------------------------------------------------------------ *
	 * Bars (top / bottom)
	 * ------------------------------------------------------------------ */

	/**
	 * Event handler (header). Inserts the bars right after the opening <body> tag of the header output.
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
	 * Renders the bars and places them after (header) or before (footer) the matched tag.
	 * Returns false only when the pattern was not found; never throws.
	 */
	private function inject(&$output, $pattern, $after) {
		try {
			$html = $this->renderBars();
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

	/* ------------------------------------------------------------------ *
	 * Inline boxes (2.4.0)
	 * ------------------------------------------------------------------ */

	/** Cart page: under the heading (default theme), else above the cart list, else at the top of the content. */
	public function injectCart(string &$route = '', array &$args = [], &$output = ''): void {
		$this->inline('cart', $output, array(
			array('#<!--.*?-->(*SKIP)(*FAIL)|</h1\s*>#is', true, 0),
			array('#<div\b[^>]*\bid=["\']shopping-cart["\']#i', false, 0),
			array('#<div\b[^>]*\bid=["\']content["\'][^>]*>#i', true, 0)
		));
	}

	/** Product page: right under the price block, else above the options / add-to-cart form. */
	public function injectProduct(string &$route = '', array &$args = [], &$output = ''): void {
		$h1 = is_string($output) ? stripos($output, '<h1') : false;

		$this->inline('product', $output, array(
			array('#<h2\b[^>]*>.*?</h2>.*?</ul>#is', true, $h1 === false ? 0 : $h1),
			array('#<div\b[^>]*\bid=["\']product["\']#i', false, 0),
			array('#<form\b[^>]*\bid=["\']form-product["\']#i', false, 0)
		));
	}

	/** Mini-cart drop-down: first entry of the list (an <li>, so AJAX reloads of "ul li" keep it). */
	public function injectMiniCart(string &$route = '', array &$args = [], &$output = ''): void {
		$this->inline('minicart', $output, array(
			array('#<ul\b[^>]*\bclass=["\'][^"\']*\bdropdown-menu\b[^"\']*["\'][^>]*>#i', true, 0)
		), true);
	}

	/**
	 * Places the box of $placement at the first pattern that matches: array(regex, after?, start offset).
	 * Theme without the spot → nothing happens. Never throws.
	 */
	private function inline($placement, &$output, array $patterns, $li = false) {
		if (!is_string($output) || $output === '' || !$this->config->get(self::CODE . '_status') || (int)$this->config->get(self::CODE . '_schema') < 8) {
			return;
		}

		try {
			$html = $this->renderInline($placement);

			if ($html === '') {
				return;
			}

			if ($li) {
				$html = '<li class="abp-mc-li">' . $html . '</li>';
			}

			foreach ($patterns as $p) {
				if (preg_match($p[0], $output, $m, PREG_OFFSET_CAPTURE, (int)$p[2])) {
					$at     = $p[1] ? $m[0][1] + strlen($m[0][0]) : $m[0][1];
					$output = substr($output, 0, $at) . "\n" . $html . "\n" . substr($output, $at);
					return;
				}
			}
		} catch (\Throwable $e) {
			$this->log->write('Announcement Bar Pro: ' . $e->getMessage());
		}
	}

	/**
	 * Layout-module entry point: not used, the bar is global.
	 */
	public function index(): string {
		return '';
	}

	/* ------------------------------------------------------------------ *
	 * Rendering
	 * ------------------------------------------------------------------ */

	private function renderBars() {
		$prepared = $this->prepare();
		$bars     = array();

		foreach (array('top', 'bottom') as $place) {
			if (!empty($prepared['groups'][$place])) {
				$bars[] = $this->container($place, $prepared['groups'][$place], false, $prepared['common']);
			}
		}

		if (!$bars) {
			return '';
		}

		// One bar keeps the id of 2.3.0 ("abp-bar") wherever it is; with two, the bottom one is "abp-bar-bottom".
		$bars[0]['id'] = 'abp-bar';

		if (count($bars) === 2) {
			$bars[1]['id'] = 'abp-bar-bottom';
		}

		return $this->view($bars, true, $prepared['common']);
	}

	private function renderInline($placement) {
		$prepared = $this->prepare();

		if (empty($prepared['groups'][$placement])) {
			return '';
		}

		return $this->view(array($this->container($placement, $prepared['groups'][$placement], true, $prepared['common'])), false, $prepared['common']);
	}

	/** One bar or box. Inline boxes show every message (stacked), without close / arrows / auto-hide. */
	private function container($place, array $items, $inline, array $common) {
		$mode = $inline ? 'stack' : $common['mode'];

		if ($mode === 'marquee') {
			foreach ($items as $i => $item) {
				$items[$i]['email'] = null; // a form cannot scroll by; the text is still shown
			}
		}

		return array(
			'id'          => '',
			'inline'      => $inline,
			'placement'   => $place,
			'mode'        => $mode,
			'items'       => $items,
			'sticky'      => !$inline && $place === 'top' && $common['sticky'],
			'arrows'      => !$inline && $mode === 'rotate' && count($items) > 1 && $common['arrows'],
			'dismissible' => !$inline && $common['dismissible'],
			'pause'       => !$inline && $common['pause'] && (($mode === 'rotate' && count($items) > 1) || $mode === 'marquee'),
			'autohide'    => $inline ? 0 : $common['autohide']
		);
	}

	private function view(array $containers, $is_bar, array $common) {
		$assets = !self::$assets && !$this->isPartial();

		if ($assets) {
			self::$assets = true;
		}

		$data = $common;
		$data['containers'] = $containers;
		$data['fallback']   = $is_bar && $this->fallback;
		$data['assets']     = $assets;
		$data['pre']        = $is_bar || $assets;
		$data['custom_css'] = $assets ? $common['custom_css'] : '';

		return $this->load->view(self::ROUTE, $data);
	}

	/** AJAX fragments (mini-cart refresh): no styles / scripts, the page already has them. */
	private function isPartial() {
		$route = isset($this->request->get['route']) && is_string($this->request->get['route']) ? $this->request->get['route'] : '';

		return (bool)preg_match('#^common/cart[/.|]info$#', $route);
	}

	/**
	 * Every message for this page, filtered and built once per request, grouped by placement
	 * (top, bottom, cart, product, minicart), plus the data every bar / box shares.
	 */
	private function prepare() {
		if (self::$prepared !== null) {
			return self::$prepared;
		}

		self::$prepared = array('groups' => array(), 'common' => array()); // a failure below leaves "nothing to show"

		$this->abp = $this->sanitizer();
		$this->fx  = $this->features();

		$language_id = (int)$this->config->get('config_language_id');
		$allow_html  = (bool)$this->config->get(self::CODE . '_allow_html');
		$schema      = (int)$this->config->get(self::CODE . '_schema');

		if ($schema < 3) {
			return self::$prepared;
		}

		$this->load->model(self::ROUTE);
		$rows = $this->model_extension_announcement_bar_pro_module_announcement_bar_pro->getActiveMessages($language_id, $this->defaultLanguageId(), $schema);

		if (!$rows) {
			return self::$prepared;
		}

		$store_id          = (int)$this->config->get('config_store_id');
		$customer_group_id = $this->customer->isLogged() ? (int)$this->customer->getGroupId() : (int)$this->config->get('config_customer_group_id');
		$current_route     = $this->currentRoute();
		$module_style      = $this->styleValue($this->config->get(self::CODE . '_style'), 'solid');
		$position          = $this->config->get(self::CODE . '_position') === 'bottom' ? 'bottom' : 'top';

		$this->load->language(self::ROUTE, '', 'en-gb');
		$this->load->language(self::ROUTE);

		$groups = array();
		$need   = array('cart' => false, 'email' => false);

		foreach ($rows as $row) {
			if (!$this->inList($row['stores'], $store_id) || !$this->inList($row['customer_groups'], $customer_group_id) || !$this->pageMatches($row['page_mode'], $row['page_routes'], $current_route) || !$this->targetMatches($row, $current_route)) {
				continue;
			}

			$opt = $schema >= 8 ? $this->fx->options(isset($row['options']) ? $row['options'] : '') : $this->fx->defaults();
			$row = $this->applyOptions($row, $opt);

			if ($row === null) {
				continue;
			}

			$item = $this->buildItem($row, $opt, $language_id, $allow_html, $module_style);

			if (!$item) {
				continue;
			}

			$place = in_array($row['placement'], array('top', 'bottom', 'cart', 'product', 'minicart'), true) ? $row['placement'] : $position;

			$groups[$place][] = $item;
			$need['cart']  = $need['cart'] || $item['fs'] || $item['cc'];
			$need['email'] = $need['email'] || $item['email'];
		}

		if (!$groups) {
			return self::$prepared;
		}

		$mode  = $this->config->get(self::CODE . '_mode');
		$mode  = in_array($mode, array('rotate', 'marquee', 'stack'), true) ? $mode : 'rotate';
		$font  = (string)$this->config->get(self::CODE . '_font');
		$link  = function ($method) {
			return str_replace('&amp;', '&', $this->url->link(self::ROUTE . $this->sep() . $method, 'language=' . $this->config->get('config_language')));
		};

		self::$prepared = array('groups' => $groups, 'common' => array(
			'mode'         => $mode,
			'sticky'       => (bool)$this->config->get(self::CODE . '_sticky'),
			'arrows'       => (bool)$this->config->get(self::CODE . '_arrows'),
			'dismissible'  => (bool)$this->config->get(self::CODE . '_dismissible'),
			'pause'        => $schema >= 8 && (bool)$this->config->get(self::CODE . '_pause_button'),
			'autohide'     => $schema >= 8 ? $this->clamp($this->config->get(self::CODE . '_autohide'), 0, 600, 0) : 0,
			'max'          => $schema >= 8 ? $this->clamp($this->config->get(self::CODE . '_max_messages'), 0, 20, 0) : 0,
			'font'         => in_array($font, \Opencart\System\Library\Extension\AnnouncementBarPro\Features::FONTS, true) ? $font : '',
			'ga4'          => $schema >= 8 && (bool)$this->config->get(self::CODE . '_ga4'),
			// "Wait for cookie consent": A/B, visitor memory, statistics and GA4 only once window.abpConsent === true.
			'consent'      => $schema >= 8 && (bool)$this->config->get(self::CODE . '_consent_wait'),
			'custom_css'   => $schema >= 8 ? $this->fx->css((string)$this->config->get(self::CODE . '_custom_css')) : '',
			'transition'   => $this->config->get(self::CODE . '_transition') === 'slide' ? 'slide' : 'fade',
			'interval'     => $this->clamp($this->config->get(self::CODE . '_interval'), 2, 60, 5),
			'speed'        => $this->clamp($this->config->get(self::CODE . '_speed'), 20, 300, 60),
			'font_size'    => $this->clamp($this->config->get(self::CODE . '_font_size'), 10, 30, 14),
			'padding'      => $this->clamp($this->config->get(self::CODE . '_padding'), 0, 30, 8),
			'color_mode'   => $this->config->get(self::CODE . '_color_mode') === 'auto' ? 'auto' : 'custom',
			'dismiss_days' => $this->clamp($this->config->get(self::CODE . '_dismiss_days'), 0, 365, 1),
			'version'      => self::VERSION,
			// Statistics endpoint (counters only); empty until the 2.2.0 columns exist.
			'track'        => $schema >= 6 ? $link('track') : '',
			// Free-shipping / goal / cart-condition refresh after AJAX cart changes (read-only JSON); only when needed.
			'cart'         => $schema >= 7 && $need['cart'] ? $link('cart') : '',
			'subscribe'    => $schema >= 8 && $need['email'] ? $link('subscribe') : '',
			'token'        => $schema >= 8 && $need['email'] ? $this->fx->token($this->config->get(self::CODE . '_secret'), $this->session->getId()) : '',
			'privacy'      => $schema >= 8 && $need['email'] && (int)$this->config->get(self::CODE . '_privacy_id') > 0 ? str_replace('&amp;', '&', $this->url->link('information/information', 'language=' . $this->config->get('config_language') . '&information_id=' . (int)$this->config->get(self::CODE . '_privacy_id'))) : '',
			'now'          => time() * 1000, // server clock: countdown/expiry/schedule must not trust the visitor's clock
			'tz'           => (int)date('Z'), // store time zone offset for the weekly schedule and cut-off times
			'label'        => $this->language->get('abp_text_label'),
			'close'        => $this->language->get('abp_text_close'),
			'days'         => $this->language->get('abp_text_days'),
			'copy'         => $this->language->get('abp_text_copy'),
			'copied'       => $this->language->get('abp_text_copied'),
			'prev'         => $this->language->get('abp_text_prev'),
			'next'         => $this->language->get('abp_text_next'),
			'show'         => $this->language->get('abp_text_show'),
			'pause_label'  => $this->language->get('abp_text_pause'),
			'play_label'   => $this->language->get('abp_text_play'),
			'privacy_text' => $this->language->get('abp_text_privacy'),
			'sending'      => $this->language->get('abp_text_sending'),
			'err_email'    => $this->language->get('abp_error_email'),
			'err_consent'  => $this->language->get('abp_error_consent'),
			'err_failed'   => $this->language->get('abp_error_failed'),
			'base'         => 'extension/announcement_bar_pro/catalog/view/javascript/'
		));

		return self::$prepared;
	}

	/**
	 * 2.4.0 rules decided on the server for one message: visitor's country, OpenCart coupon (code + validity) and the
	 * free-shipping amount taken from OpenCart. Returns the row (with the coupon code) or null when it must not show.
	 */
	private function applyOptions(array $row, array $opt) {
		$row['placement'] = isset($row['placement']) ? (string)$row['placement'] : '';

		if ($opt['countries_mode'] !== 'all' && !$this->fx->countryMatches($opt, $this->place()[0])) {
			return null;
		}

		if ($opt['coupon_id']) {
			$code = $this->model_extension_announcement_bar_pro_module_announcement_bar_pro->getUsableCoupon($opt['coupon_id'], $this->customer->isLogged() ? (int)$this->customer->getId() : 0);

			if ($code === '') {
				return null; // disabled, not started, expired or used up: the message hides itself
			}

			$row['coupon'] = $code;
		}

		if (isset($row['type']) && $row['type'] === 'free_shipping' && $opt['fs_oc']) {
			if (!$this->config->get('shipping_free_status') || (float)$this->config->get('shipping_free_total') <= 0) {
				return null; // OpenCart's Free Shipping is off (or free for everyone): nothing to announce
			}

			$row['threshold'] = (float)$this->config->get('shipping_free_total');
		}

		return $row;
	}

	/**
	 * One message as the template needs it, or null when there is nothing to show.
	 */
	private function buildItem(array $row, array $opt, $language_id, $allow_html, $module_style) {
		$extra = $this->fx->extraOf(isset($row['extra']) ? $row['extra'] : '');
		$goal  = $this->goal($row, $opt, $extra);

		$variants = array();
		$seen     = '';

		foreach ($this->variantNames($row) as $name) {
			$v = $this->variant($row, $name, $allow_html, $goal, $opt);

			if ($v === null) {
				continue;
			}

			$variants[] = $v;
			// What the visitor sees (before timer / amounts are inserted) — basis of the dismiss key. A message
			// that uses none of the 2.3.0 / 2.4.0 features keeps exactly its 2.1.x / 2.2.0 key (closed stays closed).
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

		if ($goal) {
			$seen .= '|fs|' . $goal['threshold'] . '|' . $this->abp->toSafeHtml(isset($row['success_text']) ? $row['success_text'] : '', $allow_html);
		}

		if ($goal && $goal['steps']) {
			$seen .= '|t|' . implode('|', $extra['tier_label']);
		}

		$email = $opt['email'] ? $this->emailTexts($extra) : null;

		if ($email) {
			$seen .= '|e';
		}

		if ($opt['image'] !== '') {
			$seen .= '|i|' . $opt['image'];
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

		$icon     = $this->abp->icon($row['icon']);
		$bg       = $this->abp->color($row['bg_color'], '#222222');
		$cart_off = false;

		if ($this->fx->hasCartRules($opt)) {
			$cart     = $this->cartState($opt);
			$cart_off = !$this->fx->cartMatches($opt, $cart['subtotal'], $cart['qty'], $cart['products'], $cart['categories']);
		}

		return array(
			'id'         => (int)$row['message_id'],
			// Per-message dismiss key from what the visitor sees (rendered text, button, link, icon): a re-save,
			// an HTML-mode switch or a visibility/date edit keeps it closed; a visible change shows it again.
			'key'        => substr(md5($row['message_id'] . '|' . $seen . '|' . $link_text . '|' . $link . '|' . $row['icon'] . '|' . $language_id), 0, 12),
			'variants'   => $variants,
			'ab'         => count($variants) > 1,
			'device'     => isset($row['device']) && in_array($row['device'], array('desktop', 'mobile'), true) ? $row['device'] : 'all',
			'bg_color'   => $bg,
			'text_color' => $this->abp->color($row['text_color'], '#ffffff'),
			'style'      => $this->styleValue(isset($row['style']) ? $row['style'] : '', $module_style),
			'btn_style'  => isset($row['button_style']) && in_array($row['button_style'], array('filled', 'outline', 'link'), true) ? $row['button_style'] : 'outline',
			'icon'       => $icon !== '' && strpos($icon, 'fa-solid ') === 0 ? 'fa ' . $icon : $icon, // "fa" too: FA4/5 theme fonts
			'link'       => $link,
			'new_tab'    => !empty($row['link_new_tab']),
			'coupon'     => $coupon,
			'copy_aria'  => sprintf($this->language->get('abp_text_copy_code'), $coupon),
			'fs'         => (bool)$goal,
			'done'       => $goal && $goal['done'],
			'pct'        => $goal ? $goal['pct'] : 0,
			'progress'   => $goal && !empty($row['progress_bar']),
			'steps'      => $goal ? $goal['steps'] : array(),
			'k'          => $goal ? $goal['k'] : 0,
			'wd'         => $weekdays,
			't1'         => $t1,
			't2'         => $t2,
			// Outside its weekly schedule right now: rendered hidden, the script shows it when the time comes.
			'off'        => !$this->abp->scheduleOpen($weekdays, $t1, $t2),
			// Lets the script hide a message when its end time passes while the page is open.
			'end'        => !empty($row['date_end']) && strtotime($row['date_end']) ? strtotime($row['date_end']) * 1000 : 0,
			// 2.4.0
			'font'       => $opt['font'],
			'image'      => $opt['image'] !== '' ? $this->imageStyle($opt['image'], $bg, $opt['overlay']) : '',
			'utm'        => $this->fx->hasClientRules($opt) && ($opt['utm_source'] !== '' || $opt['utm_medium'] !== '' || $opt['utm_campaign'] !== '' || $opt['referrer'] !== '') ? json_encode($this->fx->clientRules($opt)) : '',
			'vis'        => $opt['visitor'] !== 'all' ? $opt['visitor'] : '',
			'trig'       => $this->fx->triggerAttr($opt),
			'cc'         => $this->fx->hasCartRules($opt),
			'coff'       => $cart_off,
			'email'      => $email
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
	 * Text of one variant: safe HTML with the phone text, {countdown} and (free shipping / goals) {remaining}, {goal}
	 * or the success text. Returns null when the variant has nothing to show.
	 */
	private function variant(array $row, $name, $allow_html, $goal, array $opt) {
		$sfx    = $name === 'b' ? '_b' : '';
		$html   = $this->abp->toSafeHtml(isset($row['text' . $sfx]) ? $row['text' . $sfx] : '', $allow_html);
		$mobile = isset($row['mobile_text' . $sfx]) ? (string)$row['mobile_text' . $sfx] : '';
		$html_m = trim($mobile) !== '' ? $this->abp->toSafeHtml($mobile, $allow_html) : '';

		if (trim(strip_tags($html_m)) === '') {
			$html_m = '';
		}

		$seen = $html_m === '' ? $html : $html . '|m|' . $html_m;

		if ($goal) {
			$success = $goal['done'] ? $this->abp->toSafeHtml(isset($row['success_text']) ? $row['success_text'] : '', $allow_html) : '';

			if (trim(strip_tags($success)) !== '') {
				$html   = $success;
				$html_m = '';
			} else {
				if ($goal['text'] !== '') {
					// Own text of the next tier: replaces the main (and phone) text while that tier is next.
					$html   = htmlspecialchars($goal['text'], ENT_QUOTES, 'UTF-8');
					$html_m = '';
				}

				foreach (array('{remaining}' => $goal['remaining'], '{goal}' => $goal['goal']) as $token => $value) {
					list($html) = $this->abp->replaceToken($html, $token, $value);
					list($html_m) = $this->abp->replaceToken($html_m, $token, $value);
				}
			}
		}

		$countdown = '';
		if (!empty($row['countdown'])) {
			if ($opt['recur'] !== '') {
				// Daily / weekly cut-off: the script starts again for the next one when the time is up.
				$end = $this->fx->nextCutoff($opt);

				if ($end) {
					$countdown = '<span class="abp-countdown" data-end="' . ($end * 1000) . '" data-recur="' . htmlspecialchars($this->fx->recurAttr($opt), ENT_QUOTES, 'UTF-8') . '"></span>';
				}
			} elseif (!empty($row['date_end'])) {
				$end = strtotime($row['date_end']);
				if ($end) {
					$countdown = '<span class="abp-countdown" data-end="' . ($end * 1000) . '"></span>';
				}
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
	 * Free shipping / tiered goals from the cart subtotal (default currency, without tax), shown in the visitor's
	 * currency. Free shipping uses the country / zone / currency amounts of the message when set. Null otherwise.
	 */
	private function goal(array $row, array $opt, array $extra) {
		$type = isset($row['type']) ? $row['type'] : 'standard';

		if ($type !== 'free_shipping' && $type !== 'tiers') {
			return null;
		}

		$subtotal = (float)$this->cart->getSubTotal();
		$currency = isset($this->session->data['currency']) && is_string($this->session->data['currency']) ? $this->session->data['currency'] : $this->config->get('config_currency');
		$money    = function ($amount, $code = '') use ($currency) {
			$text = $code !== '' ? $this->currency->format($amount, $code, 1.0) : $this->currency->format($amount, $currency);

			return '<span class="abp-remaining">' . htmlspecialchars(html_entity_decode($text, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '</span>';
		};

		if ($type === 'free_shipping') {
			if ((float)$row['threshold'] <= 0) {
				return null;
			}

			$place = $opt['fs_zones'] ? $this->place() : array(0, 0);
			list($amount, $code) = $this->fx->threshold($opt, (float)$row['threshold'], $place[0], $place[1], $currency);

			if ($amount <= 0) {
				return null;
			}

			// A fixed amount in the visitor's currency is compared with the subtotal converted to that currency.
			$have = $code !== '' ? $subtotal * (float)$this->currency->getValue($code) : $subtotal;

			return array(
				'threshold' => number_format((float)$row['threshold'], 4, '.', ''),
				'done'      => $have >= $amount,
				'pct'       => (int)min(100, floor($have * 100 / $amount)),
				'remaining' => $money(max(0, $amount - $have), $code),
				'goal'      => '',
				'text'      => '',
				'steps'     => array(),
				'k'         => 0
			);
		}

		if (count($opt['tiers']) < 2) {
			return null;
		}

		$state = $this->fx->tierState($opt['tiers'], $subtotal);
		$next  = $state['next'];
		$steps = array();

		foreach ($state['marks'] as $i => $mark) {
			$steps[] = array('at' => $mark, 'label' => isset($extra['tier_label'][$i]) ? $extra['tier_label'][$i] : '', 'done' => $i < $state['done']);
		}

		return array(
			'threshold' => implode(',', $opt['tiers']),
			'done'      => $next < 0,
			'pct'       => $state['pct'],
			'remaining' => $next >= 0 ? $money(max(0, (float)$opt['tiers'][$next] - $subtotal)) : '',
			'goal'      => $next >= 0 && isset($extra['tier_label'][$next]) ? '<span class="abp-goal">' . htmlspecialchars($extra['tier_label'][$next], ENT_QUOTES, 'UTF-8') . '</span>' : '',
			'text'      => $next >= 0 && isset($extra['tier_text'][$next]) ? $extra['tier_text'][$next] : '',
			'steps'     => $steps,
			'k'         => $state['done']
		);
	}

	/** E-mail form texts of the visitor's language; empty fields use the module's defaults. */
	private function emailTexts(array $extra) {
		$out = array();

		foreach (array('placeholder', 'button', 'consent', 'success') as $key) {
			$value     = isset($extra['email_' . $key]) ? $extra['email_' . $key] : '';
			$out[$key] = $value !== '' ? $value : $this->language->get('abp_text_email_' . $key);
		}

		return $out;
	}

	/** Background image with the message colour laid over it (overlay %), for a style attribute. */
	private function imageStyle($path, $hex, $overlay) {
		if (!$this->fx->imagePath($path)) {
			return '';
		}

		$base  = (string)$this->config->get('config_url');
		$url   = $base . 'image/' . str_replace('%2F', '/', rawurlencode($path));
		$rgb   = array(hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)));
		$tint  = 'rgba(' . implode(',', $rgb) . ',' . number_format(max(0, min(90, (int)$overlay)) / 100, 2, '.', '') . ')';

		return 'background-image:linear-gradient(' . $tint . ',' . $tint . '),url(&quot;' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '&quot;);background-size:cover;background-position:center;';
	}

	/* ------------------------------------------------------------------ *
	 * Visitor context
	 * ------------------------------------------------------------------ */

	/**
	 * Country and zone: address chosen in this visit (shipping, then payment), the customer's own address,
	 * CloudFlare's CF-IPCountry (only when switched on and the request comes from a CloudFlare address), else the store
	 * country (or unknown). No external service.
	 */
	private function place() {
		if ($this->place !== null) {
			return $this->place;
		}

		$country = 0;
		$zone    = 0;

		foreach (array('shipping_address', 'payment_address') as $key) {
			if (isset($this->session->data[$key]['country_id']) && is_scalar($this->session->data[$key]['country_id']) && (int)$this->session->data[$key]['country_id'] > 0) {
				$country = (int)$this->session->data[$key]['country_id'];
				$zone    = isset($this->session->data[$key]['zone_id']) && is_scalar($this->session->data[$key]['zone_id']) ? (int)$this->session->data[$key]['zone_id'] : 0;
				break;
			}
		}

		if (!$country && $this->customer->isLogged() && method_exists($this->customer, 'getAddressId') && (int)$this->customer->getAddressId() > 0) {
			$this->load->model(self::ROUTE);
			list($country, $zone) = $this->model_extension_announcement_bar_pro_module_announcement_bar_pro->getAddressCountry((int)$this->customer->getAddressId(), (int)$this->customer->getId());
		}

		if (!$country && $this->config->get(self::CODE . '_geo_cf')) {
			// Only on requests that come from CloudFlare's own addresses: anyone else could send the header.
			$iso = $this->features()->cfCountry(is_array($this->request->server) ? $this->request->server : array(), true);

			if ($iso !== '') {
				$this->load->model(self::ROUTE);
				$country = $this->model_extension_announcement_bar_pro_module_announcement_bar_pro->getCountryIdByIso($iso);
			}
		}

		if (!$country && $this->config->get(self::CODE . '_geo_fallback') !== 'none') {
			$country = (int)$this->config->get('config_country_id');
			$zone    = (int)$this->config->get('config_zone_id');
		}

		return $this->place = array($country, $zone);
	}

	/** Cart numbers for the cart conditions (categories only when a message asks for them). */
	private function cartState(array $opt) {
		if ($this->cart_state === null) {
			$ids = array();

			foreach ($this->cart->getProducts() as $product) {
				$ids[(int)$product['product_id']] = (int)$product['product_id'];
			}

			$this->cart_state = array('subtotal' => (float)$this->cart->getSubTotal(), 'qty' => (int)$this->cart->countProducts(), 'products' => array_values($ids), 'categories' => null);
		}

		if ($opt['cart_categories'] && $this->cart_state['categories'] === null) {
			$this->load->model(self::ROUTE);
			$this->cart_state['categories'] = $this->model_extension_announcement_bar_pro_module_announcement_bar_pro->getCategoriesOfProducts($this->cart_state['products']);
		}

		$state = $this->cart_state;
		$state['categories'] = (array)$state['categories'];

		return $state;
	}

	/* ------------------------------------------------------------------ *
	 * Public endpoints
	 * ------------------------------------------------------------------ */

	/**
	 * Cart refresh (GET ids=1,2): read-only JSON with the new text of each variant, the progress, the reached tiers
	 * and whether the cart conditions hold. Same-origin only; changes nothing; never outputs errors.
	 */
	public function cart(): void {
		$json = array('m' => new \stdClass());

		try {
			$schema = (int)$this->config->get(self::CODE . '_schema');

			$this->abp = $this->sanitizer();
			$ids       = $this->abp->idList(isset($this->request->get['ids']) && is_string($this->request->get['ids']) ? $this->request->get['ids'] : '', 20);

			// Cheap checks first: without valid ids there is nothing to do (no rate-limit write either). The limit
			// (1200 per 10 minutes per visitor) only stops scripted floods: shoppers change the cart far less often.
			if ($ids && isset($this->request->server['REQUEST_METHOD']) && $this->request->server['REQUEST_METHOD'] === 'GET' && $this->config->get(self::CODE . '_status') && $schema >= 7 && $this->sameOrigin(true) && !$this->limited('cart', 1200, 600)) {
				$this->fx  = $this->features();

				if ($ids) {
					$language_id = (int)$this->config->get('config_language_id');
					$allow_html  = (bool)$this->config->get(self::CODE . '_allow_html');
					$module      = $this->styleValue($this->config->get(self::CODE . '_style'), 'solid');
					$out         = array();
					$store_id    = (int)$this->config->get('config_store_id');
					$group_id    = $this->customer->isLogged() ? (int)$this->customer->getGroupId() : (int)$this->config->get('config_customer_group_id');

					$this->load->model(self::ROUTE);
					$this->load->language(self::ROUTE, '', 'en-gb');
		$this->load->language(self::ROUTE);

					foreach ($this->model_extension_announcement_bar_pro_module_announcement_bar_pro->getActiveMessages($language_id, $this->defaultLanguageId(), $schema) as $row) {
						if (!in_array((int)$row['message_id'], $ids, true) || !$this->inList($row['stores'], $store_id) || !$this->inList($row['customer_groups'], $group_id)) {
							continue;
						}

						$opt = $schema >= 8 ? $this->fx->options(isset($row['options']) ? $row['options'] : '') : $this->fx->defaults();
						$row = $this->applyOptions($row, $opt);

						if ($row === null) {
							continue;
						}

						$item = $this->buildItem($row, $opt, $language_id, $allow_html, $module);

						if (!$item || (!$item['fs'] && !$item['cc'])) {
							continue;
						}

						$entry = array('p' => $item['pct'], 'done' => $item['done'], 'k' => $item['k'], 'off' => $item['coff']);

						foreach ($item['variants'] as $v) {
							$entry[$v['name']] = $v['txt'];
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
	private function sameOrigin($allow_unknown = false) {
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

		// Read-only requests (the cart endpoint) may come without any origin header: older Safari does not send
		// Sec-Fetch-Site and a no-referrer policy drops the Referer. Statistics stay strict.
		return $allow_unknown;
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

				if ($id > 0 && in_array($type, array('view', 'click', 'copy'), true) && $this->sameOrigin() && !$this->limited('track', 300, 600)) {
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

	/**
	 * Newsletter sign-up (POST email, consent, tok, id, honeypot "website"), JSON answer {ok, msg} or {error, field}.
	 * Same-origin only, session-bound form token (HMAC, no session write), honeypot, rate limit per address and in total,
	 * double opt-in by e-mail. The answer never tells whether an address was already subscribed.
	 */
	public function subscribe(): void {
		$json = array();

		try {
			$this->load->language(self::ROUTE, '', 'en-gb');
		$this->load->language(self::ROUTE);
			$this->fx = $this->features();

			$post   = $this->request->post;
			$schema = (int)$this->config->get(self::CODE . '_schema');
			$text   = function ($key) {
				return $this->language->get($key);
			};

			if (!isset($this->request->server['REQUEST_METHOD']) || $this->request->server['REQUEST_METHOD'] !== 'POST' || !$this->config->get(self::CODE . '_status') || $schema < 8 || !$this->sameOrigin()) {
				$json['error'] = $text('abp_error_failed');
			} elseif (!$this->fx->tokenValid(isset($post['tok']) && is_string($post['tok']) ? $post['tok'] : '', (string)$this->config->get(self::CODE . '_secret'), $this->session->getId())) {
				$json['error'] = $text('abp_error_token');
			} elseif (!empty($post['website'])) {
				$json['ok'] = 1; // honeypot filled: a bot — pretend it worked, do nothing
			} elseif ($this->limited('subscribe', $this->clamp($this->config->get(self::CODE . '_signup_limit_ip'), 1, 1000, 10), 3600) || $this->limited('subscribe-all', $this->clamp($this->config->get(self::CODE . '_signup_limit_total'), 1, 100000, 200), 3600, false)) {
				$json['error'] = $text('abp_error_rate');
			} else {
				$email = $this->fx->email(isset($post['email']) && is_string($post['email']) ? html_entity_decode($post['email'], ENT_QUOTES, 'UTF-8') : '');
				$id    = isset($post['id']) && is_string($post['id']) && preg_match('/^\d{1,10}$/', $post['id']) ? (int)$post['id'] : 0;

				$this->load->model(self::ROUTE);
				$message = $id ? $this->model_extension_announcement_bar_pro_module_announcement_bar_pro->getSignupMessage($id) : array();
				$opt     = $message ? $this->fx->options($message['options']) : array();

				if (!$message || empty($opt['email']) || !$this->inList($message['stores'], (int)$this->config->get('config_store_id'))) {
					$json['error'] = $text('abp_error_failed');
				} elseif ($email === '') {
					$json['error'] = $text('abp_error_email');
					$json['field'] = 'email';
				} elseif (!isset($post['consent']) || $post['consent'] !== '1') {
					$json['error'] = $text('abp_error_consent');
					$json['field'] = 'consent';
				} else {
					$json = $this->signUp($email, $id);
				}
			}
		} catch (\Throwable $e) {
			$this->log->write('Announcement Bar Pro sign-up: ' . $e->getMessage());
			$json = array('error' => $this->language->get('abp_error_failed'));
		}

		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		$this->response->addHeader('Cache-Control: no-store');
		$this->response->addHeader('X-Content-Type-Options: nosniff');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Stores the sign-up and sends the confirmation e-mail (double opt-in), or subscribes at once (opt-in off).
	 * The answer is the same whether or not the address was known. Without double opt-in nobody proves that the
	 * address is theirs, so customer accounts' newsletter flags are never changed then and an unsubscribed address is
	 * never subscribed again (only its owner can, through the confirmation e-mail of double opt-in).
	 */
	private function signUp($email, $message_id) {
		$model    = $this->model_extension_announcement_bar_pro_module_announcement_bar_pro;
		$store_id = (int)$this->config->get('config_store_id');
		$double   = (bool)$this->config->get(self::CODE . '_double_optin');
		$existing = $model->getSubscriber($email, $store_id);
		$done     = array('ok' => 1, 'msg' => $double ? '' : $this->language->get('abp_text_email_done'));

		// Already confirmed, or a confirmation e-mail went out less than 10 minutes ago: same answer, nothing sent.
		if ($existing && ((int)$existing['status'] === 1 || ((int)$existing['status'] === 0 && strtotime($existing['date_added']) > time() - 600))) {
			return $done;
		}

		// Opt-in off: an unsubscribed address stays unsubscribed (same answer, nothing changes).
		if (!$double && $existing && (int)$existing['status'] === 2) {
			return $done;
		}

		// At most 3 confirmation e-mails per address in 24 hours (keyed hash of the address; same answer, nothing sent).
		if ($double && $this->limited('subscribe-mail', 3, 86400, strtolower($email))) {
			return $done;
		}

		$token = bin2hex(random_bytes(32));
		$unsub = bin2hex(random_bytes(32));

		// Proof of consent: the consent text this shop showed (never a text sent by the browser).
		$extra   = $model->getActiveMessages((int)$this->config->get('config_language_id'), $this->defaultLanguageId(), 8);
		$consent = $this->language->get('abp_text_email_consent');

		foreach ($extra as $row) {
			if ((int)$row['message_id'] === (int)$message_id) {
				$texts   = $this->fx->extraOf(isset($row['extra']) ? $row['extra'] : '');
				$consent = $texts['email_consent'] !== '' ? $texts['email_consent'] : $consent;
				break;
			}
		}

		$model->saveSubscriber(array(
			'email'       => $email,
			'store_id'    => $store_id,
			'language_id' => (int)$this->config->get('config_language_id'),
			'message_id'  => $message_id,
			'status'      => $double ? 0 : 1,
			'token'       => $double ? hash('sha256', $token) : '',
			'unsubscribe' => hash('sha256', $unsub),
			'consent'     => function_exists('mb_substr') ? mb_substr($consent, 0, 400, 'UTF-8') : substr($consent, 0, 400)
		));

		if (!$double) {
			// The module's own list only: a customer account's newsletter flag changes only after a confirmed opt-in.
			$model->countSubscribe($message_id);

			return $done;
		}

		if (!$this->sendConfirmation($email, $token, $unsub)) {
			$model->unsubscribe((int)$model->getSubscriber($email, $store_id)['subscriber_id']);

			return array('error' => $this->language->get('abp_error_failed'));
		}

		return $done;
	}

	/** Double opt-in e-mail through the shop's own mail settings. False when it could not be sent. */
	private function sendConfirmation($email, $token, $unsub) {
		if ($this->fx->email($email) === '') {
			return false;
		}

		if ((string)$this->config->get('config_mail_engine') === '' || (string)$this->config->get('config_email') === '') {
			$this->log->write('Announcement Bar Pro: sign-up confirmation not sent — the shop\'s mail settings are incomplete.');
			return false;
		}

		$store   = $this->fx->headerText(html_entity_decode((string)$this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
		$lang    = 'language=' . $this->config->get('config_language');
		$confirm = str_replace('&amp;', '&', $this->url->link(self::ROUTE . $this->sep() . 'confirm', $lang . '&t=' . $token));
		$leave   = str_replace('&amp;', '&', $this->url->link(self::ROUTE . $this->sep() . 'unsubscribe', $lang . '&t=' . $unsub));
		$l       = function ($key) {
			return $this->language->get($key);
		};
		$e       = function ($value) {
			return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
		};

		$text = $l('abp_mail_hello') . "\n\n" . sprintf($l('abp_mail_body'), $store) . "\n" . $confirm . "\n\n" . $l('abp_mail_ignore') . "\n\n" . $l('abp_mail_unsubscribe') . "\n" . $leave . "\n";
		$html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="font-family:Arial,Helvetica,sans-serif;color:#1f2937;line-height:1.5;">'
			. '<p>' . $e($l('abp_mail_hello')) . '</p><p>' . $e(sprintf($l('abp_mail_body'), $store)) . '</p>'
			. '<p><a href="' . $e($confirm) . '" style="display:inline-block;padding:10px 18px;background:#1f2937;color:#ffffff;text-decoration:none;border-radius:6px;">' . $e($l('abp_button_confirm')) . '</a></p>'
			. '<p style="font-size:13px;color:#4b5563;">' . $e($l('abp_mail_ignore')) . '</p>'
			. '<p style="font-size:12px;color:#4b5563;">' . $e($l('abp_mail_unsubscribe')) . ' <a href="' . $e($leave) . '">' . $e($leave) . '</a></p></body></html>';

		try {
			$mail = new \Opencart\System\Library\Mail((string)$this->config->get('config_mail_engine'), [
				'parameter'     => $this->config->get('config_mail_parameter'),
				'smtp_hostname' => $this->config->get('config_mail_smtp_hostname'),
				'smtp_username' => $this->config->get('config_mail_smtp_username'),
				'smtp_password' => html_entity_decode((string)$this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8'),
				'smtp_port'     => $this->config->get('config_mail_smtp_port'),
				'smtp_timeout'  => $this->config->get('config_mail_smtp_timeout')
			]);

			// No CR / LF can reach a header (the address was validated already; this is a second line of defence).
			$mail->setTo($this->fx->headerText($email));
			$mail->setFrom($this->fx->headerText($this->config->get('config_email')));
			$mail->setSender($store);
			$mail->setSubject($this->fx->headerText(sprintf($l('abp_mail_subject'), $store)));
			$mail->setText($text);
			$mail->setHtml($html);
			$mail->send();
		} catch (\Throwable $ex) {
			$this->log->write('Announcement Bar Pro: confirmation e-mail failed — ' . $ex->getMessage());
			return false;
		}

		return true;
	}

	/**
	 * Double opt-in page. GET shows a button (mail scanners that open links must not confirm anything);
	 * POST (same origin) confirms: status confirmed, customer accounts with this address get the newsletter flag.
	 */
	public function confirm(): void {
		$this->tokenPage('confirm');
	}

	/** Unsubscribe page: GET shows a button, POST (same origin) unsubscribes (module list + customer newsletter flag). */
	public function unsubscribe(): void {
		$this->tokenPage('unsubscribe');
	}

	private function tokenPage($action) {
		$this->load->language(self::ROUTE, '', 'en-gb');
		$this->load->language(self::ROUTE);
		$this->fx = $this->features();

		$token  = isset($this->request->get['t']) && is_string($this->request->get['t']) ? $this->request->get['t'] : (isset($this->request->post['t']) && is_string($this->request->post['t']) ? $this->request->post['t'] : '');
		$data   = array('title' => $this->language->get($action === 'confirm' ? 'abp_heading_confirm' : 'abp_heading_unsubscribe'), 'text' => '', 'button' => '', 'action' => '', 'token' => '', 'home' => $this->url->link('common/home', 'language=' . $this->config->get('config_language')), 'continue' => $this->language->get('abp_text_continue'));
		$row    = array();

		try {
			if ((int)$this->config->get(self::CODE . '_schema') >= 8 && preg_match('/^[0-9a-f]{64}$/', $token)) {
				$this->load->model(self::ROUTE);
				$row = $this->model_extension_announcement_bar_pro_module_announcement_bar_pro->getSubscriberByToken($action === 'confirm' ? 'token' : 'unsubscribe', hash('sha256', $token));
			}

			$valid = $row && ($action === 'unsubscribe' ? (int)$row['status'] !== 2 : (int)$row['status'] === 0 && strtotime($row['date_added']) > time() - 7 * 86400);
			$store = html_entity_decode((string)$this->config->get('config_name'), ENT_QUOTES, 'UTF-8');

			if (!$valid) {
				$data['text'] = $this->language->get($row && $action === 'unsubscribe' ? 'abp_text_unsubscribed' : 'abp_text_invalid_link');
			} elseif ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->sameOrigin()) {
				$model = $this->model_extension_announcement_bar_pro_module_announcement_bar_pro;

				if ($action === 'confirm') {
					$model->confirmSubscriber((int)$row['subscriber_id']);
					$model->setCustomerNewsletter($row['email'], true);
					$model->countSubscribe((int)$row['message_id']);
					$data['text'] = $this->language->get('abp_text_confirmed');
				} else {
					$model->unsubscribe((int)$row['subscriber_id']);
					$model->setCustomerNewsletter($row['email'], false);
					$data['text'] = $this->language->get('abp_text_unsubscribed');
				}
			} else {
				$data['text']   = sprintf($this->language->get($action === 'confirm' ? 'abp_text_confirm_intro' : 'abp_text_unsubscribe_intro'), $store, $row['email']);
				$data['button'] = $this->language->get($action === 'confirm' ? 'abp_button_confirm' : 'abp_button_unsubscribe');
				$data['action'] = $this->url->link(self::ROUTE . $this->sep() . $action, 'language=' . $this->config->get('config_language'));
				$data['token']  = $token;
			}
		} catch (\Throwable $e) {
			$this->log->write('Announcement Bar Pro: ' . $e->getMessage());
			$data['text'] = $this->language->get('abp_error_failed');
		}

		$this->document->setTitle($data['title']);
		$this->response->addHeader('X-Robots-Tag: noindex, nofollow');
		$this->response->addHeader('Referrer-Policy: no-referrer');

		$data['column_left']    = $this->load->controller('common/column_left');
		$data['column_right']   = $this->load->controller('common/column_right');
		$data['content_top']    = '';
		$data['content_bottom'] = '';
		$data['footer']         = $this->load->controller('common/footer');
		$data['header']         = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view(self::ROUTE . '_page', $data));
	}

	/**
	 * Rate limit of a public endpoint: true when this visitor (IP, never stored — only a keyed hash) made more than $max
	 * requests in the current $window seconds. $per_ip = false counts every visitor together; a string counts that key
	 * (an e-mail address — also kept only as a keyed hash). Needs schema 8.
	 */
	private function limited($action, $max, $window, $per_ip = true) {
		if ((int)$this->config->get(self::CODE . '_schema') < 8) {
			return false;
		}

		$this->load->model(self::ROUTE);

		$who    = is_string($per_ip) ? 'k:' . $per_ip : ($per_ip ? $this->clientIp() : '*');
		$bucket = hash_hmac('sha1', $action . '|' . $who, (string)$this->config->get(self::CODE . '_secret'));

		return $this->model_extension_announcement_bar_pro_module_announcement_bar_pro->hit($bucket, (int)$window) > (int)$max;
	}

	/**
	 * Visitor IP for the rate limit: REMOTE_ADDR; behind CloudFlare (only when switched on, and only when REMOTE_ADDR is
	 * one of CloudFlare's published addresses) its CF-Connecting-IP header.
	 */
	private function clientIp() {
		return $this->features()->clientIp(is_array($this->request->server) ? $this->request->server : array(), (bool)$this->config->get(self::CODE . '_geo_cf'));
	}

	/* ------------------------------------------------------------------ *
	 * Helpers
	 * ------------------------------------------------------------------ */

	/** Method separator: "." from OpenCart 4.0.2.0, "|" in 4.0.0.x–4.0.1.x. */
	private function sep(): string {
		return version_compare(VERSION, '4.0.2.0', '>=') ? '.' : '|';
	}

	/**
	 * Library classes: autoloaded on 4.0.1+; 4.0.0.x registers no Library\Extension namespace, so require them.
	 */
	private function sanitizer(): \Opencart\System\Library\Extension\AnnouncementBarPro\Sanitizer {
		if (!class_exists('Opencart\System\Library\Extension\AnnouncementBarPro\Sanitizer')) {
			require_once(DIR_EXTENSION . 'announcement_bar_pro/system/library/sanitizer.php');
		}

		return new \Opencart\System\Library\Extension\AnnouncementBarPro\Sanitizer();
	}

	private function features(): \Opencart\System\Library\Extension\AnnouncementBarPro\Features {
		if (!class_exists('Opencart\System\Library\Extension\AnnouncementBarPro\Features')) {
			require_once(DIR_EXTENSION . 'announcement_bar_pro/system/library/features.php');
		}

		return new \Opencart\System\Library\Extension\AnnouncementBarPro\Features();
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
