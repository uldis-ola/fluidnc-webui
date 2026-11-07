<?php
/**
 * Builds the bars from message rows — port of the OpenCart catalog controller (buildItem, variant, goal) and its Twig
 * template, so the markup the shared storefront script and styles expect is the same. Every step can be extended by an
 * add-on through filters (see README.md, "Hooks for add-ons").
 *
 * @package AnnouncementBarPro
 */

namespace AnnouncementBarPro;

defined( 'ABSPATH' ) || exit;

/**
 * Message rows → items → HTML.
 */
class Renderer {
	/**
	 * Sanitizer.
	 *
	 * @var Sanitizer
	 */
	private $abp;

	/**
	 * Effective settings.
	 *
	 * @var array
	 */
	private $s;

	/**
	 * Admin preview (sample cart, no endpoints, no visitor data).
	 *
	 * @var bool
	 */
	private $preview;

	/**
	 * Sample cart subtotal of the preview.
	 *
	 * @var float
	 */
	private $sample = 0.0;

	/**
	 * Constructor.
	 *
	 * @param array $settings Effective settings.
	 * @param bool  $preview  Admin preview.
	 */
	public function __construct( array $settings, $preview = false ) {
		$this->abp     = new Sanitizer();
		$this->s       = $settings;
		$this->preview = (bool) $preview;
	}

	/**
	 * Effective settings of this renderer.
	 *
	 * @return array
	 */
	public function settings() {
		return $this->s;
	}

	/**
	 * True in the admin preview (sample cart, no visitor data, no endpoints).
	 *
	 * @return bool
	 */
	public function is_preview() {
		return $this->preview;
	}

	/**
	 * Sample cart subtotal for the preview.
	 *
	 * @param float $amount Amount.
	 */
	public function set_sample_subtotal( $amount ) {
		$this->sample = (float) $amount;
	}

	/**
	 * Cart subtotal for goals: the sample of the preview, else the visitor's cart (already in the visitor's currency).
	 *
	 * @return float
	 */
	public function subtotal() {
		return $this->preview ? $this->sample : Commerce::subtotal();
	}

	/**
	 * Amount as the HTML of {remaining}.
	 *
	 * @param float  $amount Amount.
	 * @param string $code   Currency code ('' = shop currency).
	 * @return string
	 */
	public static function money_html( $amount, $code = '' ) {
		return '<span class="abp-remaining">' . esc_html( Commerce::money( $amount, $code ) ) . '</span>';
	}

	/**
	 * Placements a message may have: the two bars (filter "abpro_placements" adds the ones of an add-on).
	 *
	 * @return string[]
	 */
	public static function placements() {
		$list = apply_filters( 'abpro_placements', array( 'top', 'bottom' ) );

		return is_array( $list ) ? array_values( array_map( 'strval', $list ) ) : array( 'top', 'bottom' );
	}

	/**
	 * True for placements that are boxes inside the page (not bars).
	 *
	 * @param string $place Placement.
	 * @return bool
	 */
	public static function is_inline( $place ) {
		return ! in_array( (string) $place, array( '', 'top', 'bottom' ), true );
	}

	/**
	 * Items of every row that may show now, grouped by placement.
	 *
	 * @param array $rows Message rows with texts.
	 * @return array (groups, needs)
	 */
	public function groups( array $rows ) {
		$groups   = array();
		$need     = array( 'cart' => false );
		$places   = self::placements();
		$position = in_array( (string) $this->s['position'], $places, true ) && ! self::is_inline( $this->s['position'] ) ? (string) $this->s['position'] : 'top';

		foreach ( $rows as $row ) {
			$item = $this->item( $row );

			if ( null === $item ) {
				continue;
			}

			$place = '' !== $item['placement'] && in_array( $item['placement'], $places, true ) ? $item['placement'] : $position;

			$groups[ $place ][] = $item;
			$need['cart']       = $need['cart'] || $item['fs'] || $item['cc'];
			$more               = apply_filters( 'abpro_need', $need, $item );
			$need               = is_array( $more ) ? $more : $need;
		}

		return array( $groups, $need );
	}

	/**
	 * One row → item (with its placement), or null when it must not show for this visitor.
	 *
	 * Without an add-on that handles them (filter "abpro_handles_extras"), a message that narrows its audience with
	 * rules this plugin does not check stays hidden, and columns / options that only change its look are ignored.
	 *
	 * @param array $row Message row with texts.
	 * @return array|null
	 */
	public function item( array $row ) {
		$row['placement'] = isset( $row['placement'] ) ? (string) $row['placement'] : '';
		$opt              = Options::decode( isset( $row['options'] ) ? $row['options'] : '' );

		if ( ! Options::handled() ) {
			if ( Options::narrows( $row, $opt ) ) {
				return null;
			}

			$row['placement'] = '';
			$row['priority']  = 0;
			$row['ab_test']   = 0;
			$row['coupon']    = '';
		}

		// An add-on may hide the message (null) or change the row (country, code, amounts …).
		$row = apply_filters( 'abpro_item_row', $row, $this );

		if ( ! is_array( $row ) ) {
			return null;
		}

		if ( isset( $row['type'] ) && 'free_shipping' === $row['type'] && Options::fs_wc( $opt ) ) {
			$min = $this->preview && ! Commerce::active() ? 0.0 : Commerce::free_shipping_minimum();

			if ( $min <= 0 ) {
				return null; // No "Free shipping" method with a minimum amount for this visitor: nothing to announce.
			}

			$row['threshold'] = $min;
		}

		$item = $this->build_item( $row );

		if ( ! $item ) {
			return null;
		}

		$item['placement'] = $row['placement'];

		return $item;
	}

	/**
	 * One message as the template needs it, or null when there is nothing to show.
	 *
	 * @param array $row Message row (with texts of one language).
	 * @return array|null
	 */
	public function build_item( array $row ) {
		$goal = apply_filters( 'abpro_goal', $this->goal( $row ), $row, $this );
		$goal = is_array( $goal ) ? $goal : null;

		$variants = array();
		$seen     = '';
		$names    = apply_filters( 'abpro_variant_names', array( 'a' ), $row );

		foreach ( is_array( $names ) ? $names : array( 'a' ) as $name ) {
			$v = $this->variant( $row, 'b' === $name ? 'b' : 'a', $goal );

			if ( null === $v ) {
				continue;
			}

			$variants[] = $v;
			$seen      .= ( 'a' === $v['name'] ? '' : '|b|' . $v['link_text'] . '|' ) . $v['seen'];
		}

		if ( ! $variants ) {
			return null;
		}

		$link  = $this->abp->url( isset( $row['link'] ) ? $row['link'] : '' );
		$allow = (bool) $this->s['allow_html'];

		foreach ( $variants as $i => $v ) {
			$variants[ $i ]['wrap_link'] = '' !== $link && '' === $v['link_text'] && false === stripos( $v['txt'], '<a ' );
		}

		$weekdays = $this->abp->weekdays( isset( $row['weekdays'] ) ? $row['weekdays'] : '' );
		$t1       = $this->abp->clock_time( isset( $row['time_start'] ) ? $row['time_start'] : '' );
		$t2       = $this->abp->clock_time( isset( $row['time_end'] ) ? $row['time_end'] : '' );

		if ( '' === $t1 || '' === $t2 || $t1 === $t2 ) {
			$t1 = '';
			$t2 = '';
		}

		$icon  = $this->abp->icon( isset( $row['icon'] ) ? $row['icon'] : '' );
		$bg    = $this->abp->color( isset( $row['bg_color'] ) ? $row['bg_color'] : '', '#222222' );
		$style = isset( $row['style'] ) && isset( Settings::choices( 'style' )[ (string) $row['style'] ] ) ? (string) $row['style'] : $this->s['style'];

		$item = array(
			'id'         => (int) $row['message_id'],
			// Parts of the dismiss key, in this order (an add-on fills c = code, t = tiers, e = form, i = image).
			'seen'       => array(
				'v'  => $seen,
				'c'  => '',
				'fs' => $goal ? '|fs|' . $goal['threshold'] . '|' . $this->abp->to_safe_html( isset( $row['success_text'] ) ? $row['success_text'] : '', $allow ) : '',
				't'  => '',
				'e'  => '',
				'i'  => '',
			),
			'variants'   => $variants,
			'ab'         => count( $variants ) > 1,
			'device'     => isset( $row['device'] ) && in_array( $row['device'], array( 'desktop', 'mobile' ), true ) ? $row['device'] : 'all',
			'bg_color'   => $bg,
			'text_color' => $this->abp->color( isset( $row['text_color'] ) ? $row['text_color'] : '', '#ffffff' ),
			'style'      => $style,
			'btn_style'  => isset( $row['button_style'] ) && in_array( $row['button_style'], array( 'filled', 'outline', 'link' ), true ) ? $row['button_style'] : 'outline',
			'icon'       => $icon,
			'link'       => $link,
			'link_text'  => $variants[0]['link_text'],
			'locale'     => isset( $row['locale'] ) ? (string) $row['locale'] : '',
			'new_tab'    => ! empty( $row['link_new_tab'] ),
			'fs'         => (bool) $goal,
			'done'       => $goal && $goal['done'],
			'pct'        => $goal ? $goal['pct'] : 0,
			'progress'   => $goal && ! empty( $row['progress_bar'] ),
			'steps'      => $goal ? $goal['steps'] : array(),
			'k'          => $goal ? $goal['k'] : 0,
			'wd'         => $weekdays,
			't1'         => $t1,
			't2'         => $t2,
			'off'        => ! $this->preview && ! $this->abp->schedule_open( $weekdays, $t1, $t2, time(), wp_timezone() ),
			'end'        => ! empty( $row['date_end'] ) ? self::local_ts( $row['date_end'] ) * 1000 : 0,
			'style_attr' => '', // More inline CSS of the message (an add-on: a background image).
			'cc'         => false, // Cart conditions of an add-on, refreshed by the cart endpoint.
			'coff'       => false,
		);

		$item = apply_filters( 'abpro_item', $item, $row, $this );

		if ( ! is_array( $item ) ) {
			return null;
		}

		// Dismiss key from what the visitor sees: a visible change shows a closed message again.
		$seen = '';
		foreach ( array( 'v', 'c', 'fs', 't', 'e', 'i' ) as $part ) {
			$seen .= isset( $item['seen'][ $part ] ) ? (string) $item['seen'][ $part ] : '';
		}

		$item['key'] = substr( md5( $row['message_id'] . '|' . $seen . '|' . $item['link_text'] . '|' . $item['link'] . '|' . $item['icon'] . '|' . $item['locale'] ), 0, 12 );

		return $item;
	}

	/**
	 * Text of one variant: safe HTML with the phone text, {countdown}, {remaining}, {goal} or the success text.
	 *
	 * @param array      $row  Message row.
	 * @param string     $name a | b (b: the *_b texts of the row).
	 * @param array|null $goal Goal state.
	 * @return array|null
	 */
	private function variant( array $row, $name, $goal ) {
		$allow  = (bool) $this->s['allow_html'];
		$sfx    = 'b' === $name ? '_b' : '';
		$html   = $this->abp->to_safe_html( isset( $row[ 'text' . $sfx ] ) ? $row[ 'text' . $sfx ] : '', $allow );
		$mobile = isset( $row[ 'mobile_text' . $sfx ] ) ? (string) $row[ 'mobile_text' . $sfx ] : '';
		$html_m = '' !== trim( $mobile ) ? $this->abp->to_safe_html( $mobile, $allow ) : '';

		if ( '' === trim( wp_strip_all_tags( $html_m ) ) ) {
			$html_m = '';
		}

		$seen = '' === $html_m ? $html : $html . '|m|' . $html_m;

		if ( $goal ) {
			$success = $goal['done'] ? $this->abp->to_safe_html( isset( $row['success_text'] ) ? $row['success_text'] : '', $allow ) : '';

			if ( '' !== trim( wp_strip_all_tags( $success ) ) ) {
				$html   = $success;
				$html_m = '';
			} else {
				if ( '' !== $goal['text'] ) {
					// Own text of the current goal step: replaces the main (and phone) text while that step is next.
					$html   = esc_html( $goal['text'] );
					$html_m = '';
				}

				foreach ( array(
					'{remaining}' => $goal['remaining'],
					'{goal}'      => $goal['goal'],
				) as $token => $value ) {
					list( $html )   = $this->abp->replace_token( $html, $token, $value );
					list( $html_m ) = $this->abp->replace_token( $html_m, $token, $value );
				}
			}
		}

		$countdown = '';
		if ( ! empty( $row['countdown'] ) ) {
			$end = ! empty( $row['date_end'] ) ? self::local_ts( $row['date_end'] ) : 0;

			if ( $end ) {
				$countdown = '<span class="abp-countdown" data-end="' . ( $end * 1000 ) . '"></span>';
			}

			$countdown = (string) apply_filters( 'abpro_countdown', $countdown, $row );
		}

		list( $html, $replaced ) = $this->abp->replace_token( $html, '{countdown}', $countdown );
		$replaced_m              = false;

		if ( '' !== $html_m ) {
			list( $html_m, $replaced_m ) = $this->abp->replace_token( $html_m, '{countdown}', $countdown );
		}

		if ( '' === trim( wp_strip_all_tags( $html ) ) && '' === trim( wp_strip_all_tags( $html_m ) ) && '' === $countdown ) {
			return null;
		}

		if ( '' !== $html_m && '' !== $countdown ) {
			$html   .= $replaced ? '' : ' ' . $countdown;
			$html_m .= $replaced_m ? '' : ' ' . $countdown;
			$txt     = $this->abp->text_html( $html, $html_m );
		} else {
			$txt = $this->abp->text_html( $html, $html_m ) . ( ! $replaced && '' !== $countdown ? ' ' . $countdown : '' );
		}

		$link_text = isset( $row[ 'link_text' . $sfx ] ) && ( 'a' === $name || '' !== trim( (string) $row[ 'link_text' . $sfx ] ) ) ? $row[ 'link_text' . $sfx ] : ( isset( $row['link_text'] ) ? $row['link_text'] : '' );

		return array(
			'name'      => $name,
			'txt'       => $txt,
			'seen'      => $seen,
			'link_text' => $this->abp->plain( $this->abp->decode( $link_text ) ),
		);
	}

	/**
	 * Free-shipping goal from the cart subtotal (filter "abpro_goal" can change it or add other goals); null otherwise.
	 *
	 * @param array $row Message row.
	 * @return array|null
	 */
	private function goal( array $row ) {
		if ( ! isset( $row['type'] ) || 'free_shipping' !== $row['type'] || (float) $row['threshold'] <= 0 ) {
			return null;
		}

		if ( ! $this->preview && ! Commerce::active() ) {
			return null; // Commerce messages need WooCommerce.
		}

		$amount = (float) $row['threshold'];
		$have   = $this->subtotal();

		return array(
			'threshold' => number_format( $amount, 4, '.', '' ),
			'done'      => $have >= $amount,
			'pct'       => (int) min( 100, floor( $have * 100 / $amount ) ),
			'remaining' => self::money_html( max( 0, $amount - $have ) ),
			'goal'      => '',
			'text'      => '',
			'steps'     => array(),
			'k'         => 0,
		);
	}

	/**
	 * Unix time of a date stored in site time ('' / invalid → 0).
	 *
	 * @param string $mysql Y-m-d H:i:s in the site's time zone.
	 * @return int
	 */
	public static function local_ts( $mysql ) {
		if ( ! is_string( $mysql ) || ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $mysql ) || 0 === strpos( $mysql, '0000' ) ) {
			return 0;
		}

		$date = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $mysql, wp_timezone() );

		return $date ? $date->getTimestamp() : 0;
	}

	/*
	 * ------------------------------------------------------------------
	 * Containers and markup
	 * ------------------------------------------------------------------
	 */

	/**
	 * One bar or box. Inline boxes show every message (stacked), without close / arrows.
	 *
	 * @param string $place  Placement.
	 * @param array  $items  Items.
	 * @param bool   $inline Inline box.
	 * @return array
	 */
	public function container( $place, array $items, $inline ) {
		$mode = $inline ? 'stack' : $this->s['mode'];
		$box  = array(
			'id'          => '',
			'inline'      => $inline,
			'placement'   => $place,
			'mode'        => $mode,
			'items'       => $items,
			'sticky'      => ! $inline && 'top' === $place && ! empty( $this->s['sticky'] ),
			'arrows'      => ! $inline && 'rotate' === $mode && count( $items ) > 1 && ! empty( $this->s['arrows'] ),
			'dismissible' => ! $inline && ! empty( $this->s['dismissible'] ),
		);
		$box  = apply_filters( 'abpro_container', $box, $this );

		return is_array( $box ) ? $box : array();
	}

	/**
	 * Values every container shares (labels, endpoints, timing).
	 *
	 * @param array $need     What the items need (cart refresh …).
	 * @param bool  $fallback Bars printed in the footer (theme without wp_body_open).
	 * @return array
	 */
	public function common( array $need, $fallback = false ) {
		$s      = $this->s;
		$tz     = wp_timezone();
		$offset = $tz->getOffset( new \DateTimeImmutable( 'now', $tz ) );

		$c = array(
			'mode'         => $s['mode'],
			'transition'   => 'slide' === $s['transition'] ? 'slide' : 'fade',
			'interval'     => (int) $s['interval'],
			'speed'        => (int) $s['speed'],
			'font_size'    => (int) $s['font_size'],
			'padding'      => (int) $s['padding'],
			'color_mode'   => 'auto' === $s['color_mode'] ? 'auto' : 'custom',
			'dismiss_days' => (int) $s['dismiss_days'],
			'fallback'     => (bool) $fallback,
			'cart'         => ! $this->preview && ! empty( $need['cart'] ) && Commerce::active() ? \WC_AJAX::get_endpoint( 'abpro_cart' ) : '',
			'now'          => time() * 1000,
			'tz'           => (int) $offset,
			'label'        => __( 'Announcement', 'announcement-bar-pro' ),
			'close'        => __( 'Close', 'announcement-bar-pro' ),
			/* translators: abbreviation of "days" in a countdown such as "2d 04:10:00" */
			'days'         => _x( 'd', 'countdown days', 'announcement-bar-pro' ),
			'prev'         => __( 'Previous message', 'announcement-bar-pro' ),
			'next'         => __( 'Next message', 'announcement-bar-pro' ),
		);
		$c = apply_filters( 'abpro_common', $c, $need, $this );

		return is_array( $c ) ? $c : array();
	}

	/**
	 * Allowed markup of a message text (already sanitized; wp_kses is a second line of defence).
	 *
	 * @return array
	 */
	public static function kses_text() {
		return array(
			'b'      => array(),
			'strong' => array(),
			'i'      => array(),
			'em'     => array(),
			'u'      => array(),
			's'      => array(),
			'br'     => array(),
			'span'   => array(
				'class'      => true,
				'data-end'   => true,
				'data-recur' => true,
			),
			'a'      => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
		);
	}

	/**
	 * HTML of the containers (same markup as the OpenCart template).
	 *
	 * @param array $containers Containers.
	 * @param array $c          Common values.
	 * @return string
	 */
	public function html( array $containers, array $c ) {
		$out  = '';
		$kses = self::kses_text();

		foreach ( $containers as $box ) {
			$first   = $box['items'][0];
			$classes = 'abp ' . ( $box['inline'] ? 'abp-inline abp-inline-' . $box['placement'] : 'abp-bar abp-pos-' . $box['placement'] )
				. ( $box['sticky'] ? ' abp-sticky' : '' ) . ' abp-mode-' . $box['mode'] . ' abp-tr-' . $c['transition']
				. ( $box['dismissible'] ? ' abp-has-close' : '' ) . ( $box['arrows'] ? ' abp-has-nav' : '' );

			$attrs = array(
				'id'               => $box['id'],
				'class'            => (string) apply_filters( 'abpro_container_class', $classes, $box, $c ),
				'data-abp'         => $box['inline'] ? 'inline' : 'bar',
				'data-place'       => $box['placement'],
				'role'             => 'region',
				'aria-label'       => $c['label'],
				'data-days'        => $c['dismiss_days'],
				'data-interval'    => $c['interval'],
				'data-speed'       => $c['speed'],
				'data-mode'        => $box['mode'],
				'data-now'         => $c['now'],
				'data-tz'          => $c['tz'],
				'data-color'       => $c['color_mode'],
				'data-dismissible' => $box['dismissible'] ? 1 : 0,
				'data-fallback'    => $c['fallback'] && ! $box['inline'] ? 1 : 0,
				'data-days-label'  => $c['days'],
				'data-cart'        => $c['cart'],
				'style'            => 'font-size:' . (int) $c['font_size'] . 'px;background-color:' . $first['bg_color'] . ';color:' . $first['text_color'] . ';',
			);
			$attrs = apply_filters( 'abpro_container_attrs', $attrs, $box, $c );

			$out .= '<div' . self::attrs( is_array( $attrs ) ? $attrs : array() ) . '>' . "\n" . '<div class="abp-viewport"><div class="abp-track">' . "\n";

			foreach ( $box['items'] as $n => $item ) {
				$out .= $this->item_html( $item, 0 === $n, $box, $c, $kses );
			}

			$out .= '</div></div>' . "\n";

			// Extra controls of an add-on (already escaped HTML), before the arrows.
			$out .= (string) apply_filters( 'abpro_container_controls', '', $box, $c );

			if ( $box['arrows'] ) {
				$out .= '<button type="button" class="abp-nav abp-prev" aria-label="' . esc_attr( $c['prev'] ) . '" title="' . esc_attr( $c['prev'] ) . '"><svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M10 3 5 8l5 5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button>'
					. '<button type="button" class="abp-nav abp-next" aria-label="' . esc_attr( $c['next'] ) . '" title="' . esc_attr( $c['next'] ) . '"><svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="m6 3 5 5-5 5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button>';
			}

			if ( $box['dismissible'] ) {
				$out .= '<button type="button" class="abp-close" aria-label="' . esc_attr( $c['close'] ) . '" title="' . esc_attr( $c['close'] ) . '"><svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M3.5 3.5l9 9m0-9-9 9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button>';
			}

			$out .= '</div>' . "\n";
		}

		return $out;
	}

	/**
	 * HTML of one message.
	 *
	 * @param array $item  Item.
	 * @param bool  $first First item of the container.
	 * @param array $box   Container.
	 * @param array $c     Common values.
	 * @param array $kses  Allowed text markup.
	 * @return string
	 */
	private function item_html( array $item, $first, array $box, array $c, array $kses ) {
		$attrs = array(
			'class'       => 'abp-item' . ( $first ? ' abp-active' : '' ),
			'data-id'     => $item['id'],
			'data-key'    => $item['key'],
			'data-style'  => $item['style'],
			'data-c'      => $item['bg_color'],
			'data-t'      => $item['text_color'],
			'data-device' => 'all' !== $item['device'] ? $item['device'] : '',
			'data-end'    => $item['end'] ? $item['end'] : '',
			'data-ab'     => $item['ab'] ? 1 : '',
			'data-fs'     => $item['fs'] ? 1 : '',
			'data-done'   => $item['fs'] && $item['done'] ? 1 : '',
			'data-wd'     => $item['wd'],
			'data-t1'     => $item['t1'],
			'data-t2'     => $item['t1'] ? $item['t2'] : '',
			'data-off'    => $item['off'] ? 1 : '',
			'style'       => 'background-color:' . $item['bg_color'] . ';color:' . $item['text_color'] . ';--abp-btn-bg:' . $item['text_color'] . ';--abp-btn-fg:' . $item['bg_color'] . ';--abp-accent:' . $item['text_color'] . ';padding-top:' . (int) $c['padding'] . 'px;padding-bottom:' . (int) $c['padding'] . 'px;' . $item['style_attr'],
		);
		$attrs = apply_filters( 'abpro_item_attrs', $attrs, $item, $box );

		$target = $item['new_tab'] ? ' target="_blank" rel="noopener noreferrer"' : '';
		$icon   = '' !== $item['icon'] ? Icons::svg( $item['icon'] ) . ' ' : '';
		$out    = '<div' . self::attrs( is_array( $attrs ) ? $attrs : array() ) . '>' . "\n" . '<div class="abp-inner">';

		foreach ( $item['variants'] as $v ) {
			$out .= '<span class="abp-v abp-v-' . esc_attr( $v['name'] ) . '">';
			$out .= $v['wrap_link'] ? '<a class="abp-link abp-body" href="' . esc_url( $item['link'] ) . '"' . $target . '>' : '<span class="abp-body">';
			$out .= $icon . '<span class="abp-txt">' . wp_kses( $v['txt'], $kses ) . '</span>';
			$out .= $v['wrap_link'] ? '</a>' : '</span>';

			if ( $item['link'] && '' !== $v['link_text'] ) {
				$out .= '<a class="abp-btn abp-btn-' . esc_attr( $item['btn_style'] ) . '" href="' . esc_url( $item['link'] ) . '"' . $target . '>' . esc_html( $v['link_text'] ) . '</a>';
			}

			$out .= '</span>';
		}

		// More parts of the message from an add-on (a code with a copy button, a form …), already escaped HTML.
		$out .= (string) apply_filters( 'abpro_item_html', '', $item, $box, $c );
		$out .= '</div>' . "\n";

		if ( $item['fs'] && $item['progress'] ) {
			$out  .= '<span class="abp-progress' . ( $item['steps'] ? ' abp-steps' : '' ) . '" aria-hidden="true" data-p="' . (int) $item['pct'] . '"' . ( $item['steps'] ? ' data-k="' . (int) $item['k'] . '"' : '' ) . '><span class="abp-progress-fill" style="width:' . (int) $item['pct'] . '%;"></span>';
			$count = count( $item['steps'] );

			foreach ( $item['steps'] as $i => $step ) {
				$out .= '<span class="abp-step' . ( $step['done'] ? ' abp-step-done' : '' ) . ( $i === $count - 1 ? ' abp-step-last' : '' ) . '" style="left:' . esc_attr( (string) $step['at'] ) . '%;"><span class="abp-step-label">' . esc_html( $step['label'] ) . '</span></span>';
			}

			$out .= '</span>';
		}

		return $out . '</div>' . "\n";
	}

	/**
	 * Attribute string; empty values ('' / null) are left out (0 stays).
	 *
	 * @param array $attrs Attributes.
	 * @return string
	 */
	public static function attrs( array $attrs ) {
		$out = '';

		foreach ( $attrs as $name => $value ) {
			if ( null === $value || '' === $value || ! preg_match( '/^[a-z][a-z0-9\-]*$/', (string) $name ) ) {
				continue;
			}

			$out .= ' ' . $name . '="' . esc_attr( (string) $value ) . '"';
		}

		return $out;
	}
}
