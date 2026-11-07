<?php
/**
 * Builds the bars and inline boxes from message rows — port of the OpenCart 2.4.0 catalog controller (buildItem,
 * variant, goal) and its Twig template, so the markup the shared storefront script and styles expect is the same.
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
	 * Feature helpers.
	 *
	 * @var Features
	 */
	private $fx;

	/**
	 * Effective settings.
	 *
	 * @var array
	 */
	private $s;

	/**
	 * Pro licence active.
	 *
	 * @var bool
	 */
	private $pro;

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
	 * @param bool  $pro      Pro licence.
	 * @param bool  $preview  Admin preview.
	 */
	public function __construct( array $settings, $pro, $preview = false ) {
		$this->abp     = new Sanitizer();
		$this->fx      = new Features();
		$this->s       = $settings;
		$this->pro     = (bool) $pro;
		$this->preview = (bool) $preview;
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
	 * Items of every row that may show now, grouped by placement (top, bottom, cart, product, minicart).
	 *
	 * @param array $rows Message rows with texts.
	 * @return array (groups, needs)
	 */
	public function groups( array $rows ) {
		$groups   = array();
		$need     = array(
			'cart'  => false,
			'email' => false,
		);
		$position = 'bottom' === $this->s['position'] ? 'bottom' : 'top';

		foreach ( $rows as $row ) {
			$item = $this->item( $row );

			if ( null === $item ) {
				continue;
			}

			$place = in_array( $item['placement'], array( 'top', 'bottom', 'cart', 'product', 'minicart' ), true ) ? $item['placement'] : $position;

			$groups[ $place ][] = $item;
			$need['cart']       = $need['cart'] || $item['fs'] || $item['cc'];
			$need['email']      = $need['email'] || (bool) $item['email'];
		}

		return array( $groups, $need );
	}

	/**
	 * One row → item (with its placement), or null when it must not show for this visitor.
	 *
	 * @param array $row Message row with texts.
	 * @return array|null
	 */
	public function item( array $row ) {
		$opt               = $this->fx->options( isset( $row['options'] ) ? $row['options'] : '' );
		list( $row, $opt ) = $this->gate( $row, $opt );

		if ( null === $row ) {
			return null;
		}

		$row = $this->apply_options( $row, $opt );

		if ( null === $row ) {
			return null;
		}

		$item = $this->build_item( $row, $opt );

		if ( ! $item ) {
			return null;
		}

		$item['placement'] = $row['placement'];

		return $item;
	}

	/**
	 * Free edition: rules that narrow the audience make a Pro message stay hidden (it never shows to more people
	 * than intended); Pro-only looks and extras are dropped. Returns array( row or null, options ).
	 *
	 * @param array $row Message row.
	 * @param array $opt Options.
	 * @return array
	 */
	public function gate( array $row, array $opt ) {
		$row['placement'] = isset( $row['placement'] ) ? (string) $row['placement'] : '';

		if ( $this->pro ) {
			return array( $row, $opt );
		}

		$narrow = 'all' !== $opt['countries_mode'] || $this->fx->has_cart_rules( $opt ) || $this->fx->has_client_rules( $opt ) || 'none' !== $opt['trigger']
			|| ( isset( $row['type'] ) && 'tiers' === $row['type'] ) || in_array( $row['placement'], array( 'cart', 'product', 'minicart' ), true );

		if ( $narrow ) {
			return array( null, $opt );
		}

		$keep             = $this->fx->defaults();
		$keep['fs_wc']    = $opt['fs_wc'];
		$row['placement'] = '';
		$row['ab_test']   = 0;
		$row['coupon']    = '';
		$row['style']     = in_array( (string) $row['style'], Settings::FREE_STYLES, true ) ? $row['style'] : '';
		$row['priority']  = 0;

		return array( $row, $keep );
	}

	/**
	 * Rules decided on the server for one message: visitor's country, WooCommerce coupon (code + validity) and the
	 * free-shipping amount taken from WooCommerce. Returns the row (with the coupon code) or null.
	 *
	 * @param array $row Message row.
	 * @param array $opt Options.
	 * @return array|null
	 */
	private function apply_options( array $row, array $opt ) {
		if ( 'all' !== $opt['countries_mode'] && ! $this->preview && ! $this->fx->country_matches( $opt, Commerce::place( $this->s )[0] ) ) {
			return null;
		}

		if ( $opt['coupon_id'] ) {
			$code = Commerce::usable_coupon( $opt['coupon_id'] );

			if ( '' === $code ) {
				return null; // Not published, expired or used up: the message hides itself, like WooCommerce would refuse it.
			}

			$row['coupon'] = $code;
		}

		if ( isset( $row['type'] ) && 'free_shipping' === $row['type'] && $opt['fs_wc'] ) {
			$min = $this->preview && ! Commerce::active() ? 0.0 : Commerce::free_shipping_minimum( $this->s );

			if ( $min <= 0 ) {
				return null; // No "Free shipping" method with a minimum amount for this visitor: nothing to announce.
			}

			$row['threshold'] = $min;
		}

		return $row;
	}

	/**
	 * One message as the template needs it, or null when there is nothing to show.
	 *
	 * @param array $row Message row (with texts of one language).
	 * @param array $opt Options.
	 * @return array|null
	 */
	public function build_item( array $row, array $opt ) {
		$extra = $this->fx->extra_of( isset( $row['extra'] ) ? $row['extra'] : '' );
		$goal  = $this->goal( $row, $opt, $extra );

		$variants = array();
		$seen     = '';

		foreach ( $this->variant_names( $row ) as $name ) {
			$v = $this->variant( $row, $name, $goal, $opt );

			if ( null === $v ) {
				continue;
			}

			$variants[] = $v;
			$seen      .= ( 'a' === $name ? '' : '|b|' . $v['link_text'] . '|' ) . $v['seen'];
		}

		if ( ! $variants ) {
			return null;
		}

		$link      = $this->abp->url( isset( $row['link'] ) ? $row['link'] : '' );
		$link_text = $variants[0]['link_text'];
		$coupon    = $this->abp->coupon( isset( $row['coupon'] ) ? $row['coupon'] : '' );
		$allow     = (bool) $this->s['allow_html'];

		if ( '' !== $coupon ) {
			$seen .= '|c|' . $coupon;
		}

		if ( $goal ) {
			$seen .= '|fs|' . $goal['threshold'] . '|' . $this->abp->to_safe_html( isset( $row['success_text'] ) ? $row['success_text'] : '', $allow );
		}

		if ( $goal && $goal['steps'] ) {
			$seen .= '|t|' . implode( '|', $extra['tier_label'] );
		}

		$email = $opt['email'] ? $this->email_texts( $extra ) : null;

		if ( $email ) {
			$seen .= '|e';
		}

		if ( $opt['image'] ) {
			$seen .= '|i|' . $opt['image'];
		}

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

		$icon     = $this->abp->icon( isset( $row['icon'] ) ? $row['icon'] : '' );
		$bg       = $this->abp->color( isset( $row['bg_color'] ) ? $row['bg_color'] : '', '#222222' );
		$cart_off = false;

		if ( $this->fx->has_cart_rules( $opt ) && ! $this->preview ) {
			$cart     = Commerce::cart( (bool) $opt['cart_categories'] );
			$cart_off = ! $this->fx->cart_matches( $opt, $cart['subtotal'], $cart['qty'], $cart['products'], $cart['categories'] );
		}

		$locale = isset( $row['locale'] ) ? (string) $row['locale'] : '';
		$style  = isset( $row['style'] ) && in_array( $row['style'], Settings::STYLES, true ) ? $row['style'] : $this->s['style'];

		return array(
			'id'         => (int) $row['message_id'],
			// Dismiss key from what the visitor sees: a visible change shows a closed message again.
			'key'        => substr( md5( $row['message_id'] . '|' . $seen . '|' . $link_text . '|' . $link . '|' . $icon . '|' . $locale ), 0, 12 ),
			'variants'   => $variants,
			'ab'         => count( $variants ) > 1,
			'device'     => isset( $row['device'] ) && in_array( $row['device'], array( 'desktop', 'mobile' ), true ) ? $row['device'] : 'all',
			'bg_color'   => $bg,
			'text_color' => $this->abp->color( isset( $row['text_color'] ) ? $row['text_color'] : '', '#ffffff' ),
			'style'      => $style,
			'btn_style'  => isset( $row['button_style'] ) && in_array( $row['button_style'], array( 'filled', 'outline', 'link' ), true ) ? $row['button_style'] : 'outline',
			'icon'       => $icon,
			'link'       => $link,
			'new_tab'    => ! empty( $row['link_new_tab'] ),
			'coupon'     => $coupon,
			/* translators: %s: coupon code */
			'copy_aria'  => sprintf( __( 'Copy code %s', 'announcement-bar-pro' ), $coupon ),
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
			'font'       => $opt['font'],
			'image'      => $opt['image'] ? $this->image_style( (int) $opt['image'], $bg, $opt['overlay'] ) : '',
			'utm'        => ( '' !== $opt['utm_source'] || '' !== $opt['utm_medium'] || '' !== $opt['utm_campaign'] || '' !== $opt['referrer'] ) && ! $this->preview ? wp_json_encode( $this->fx->client_rules( $opt ) ) : '',
			'vis'        => 'all' !== $opt['visitor'] && ! $this->preview ? $opt['visitor'] : '',
			'trig'       => $this->preview ? '' : $this->fx->trigger_attr( $opt ),
			'cc'         => $this->fx->has_cart_rules( $opt ) && ! $this->preview,
			'coff'       => $cart_off,
			'email'      => $email,
		);
	}

	/**
	 * 'a', plus 'b' while the A/B test is on and variant B has a text.
	 *
	 * @param array $row Message row.
	 * @return string[]
	 */
	private function variant_names( array $row ) {
		$names = array( 'a' );

		if ( ! empty( $row['ab_test'] ) && isset( $row['text_b'] ) && '' !== trim( wp_strip_all_tags( $this->abp->decode( $row['text_b'] ) ) ) ) {
			$names[] = 'b';
		}

		return $names;
	}

	/**
	 * Text of one variant: safe HTML with the phone text, {countdown}, {remaining}, {goal} or the success text.
	 *
	 * @param array      $row  Message row.
	 * @param string     $name a | b.
	 * @param array|null $goal Goal state.
	 * @param array      $opt  Options.
	 * @return array|null
	 */
	private function variant( array $row, $name, $goal, array $opt ) {
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
					// Own text of the next tier: replaces the main (and phone) text while that tier is next.
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
			if ( '' !== $opt['recur'] ) {
				$end = $this->fx->next_cutoff( $opt, time(), wp_timezone() );

				if ( $end ) {
					$countdown = '<span class="abp-countdown" data-end="' . ( $end * 1000 ) . '" data-recur="' . esc_attr( $this->fx->recur_attr( $opt ) ) . '"></span>';
				}
			} elseif ( ! empty( $row['date_end'] ) ) {
				$end = self::local_ts( $row['date_end'] );

				if ( $end ) {
					$countdown = '<span class="abp-countdown" data-end="' . ( $end * 1000 ) . '"></span>';
				}
			}
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
	 * Free shipping / tiered goals from the cart subtotal. Free shipping uses the country / state / currency amounts of
	 * the message when set. Null otherwise.
	 *
	 * @param array $row   Message row.
	 * @param array $opt   Options.
	 * @param array $extra Extra texts.
	 * @return array|null
	 */
	private function goal( array $row, array $opt, array $extra ) {
		$type = isset( $row['type'] ) ? $row['type'] : 'standard';

		if ( 'free_shipping' !== $type && 'tiers' !== $type ) {
			return null;
		}

		if ( ! $this->preview && ! Commerce::active() ) {
			return null; // Commerce messages need WooCommerce.
		}

		$subtotal = $this->preview ? $this->sample : Commerce::subtotal();
		$currency = Commerce::currency();
		$money    = static function ( $amount, $code = '' ) {
			return '<span class="abp-remaining">' . esc_html( Commerce::money( $amount, $code ) ) . '</span>';
		};

		if ( 'free_shipping' === $type ) {
			if ( (float) $row['threshold'] <= 0 ) {
				return null;
			}

			$place                 = $opt['fs_zones'] && ! $this->preview ? Commerce::place( $this->s ) : array( '', '' );
			list( $amount, $code ) = $this->fx->threshold( $opt, (float) $row['threshold'], $place[0], $place[1], $currency );

			if ( $amount <= 0 ) {
				return null;
			}

			// The subtotal is already in the visitor's currency (WooCommerce and multi-currency plugins convert it).
			$have = $subtotal;

			return array(
				'threshold' => number_format( (float) $row['threshold'], 4, '.', '' ),
				'done'      => $have >= $amount,
				'pct'       => (int) min( 100, floor( $have * 100 / $amount ) ),
				'remaining' => $money( max( 0, $amount - $have ), $code ),
				'goal'      => '',
				'text'      => '',
				'steps'     => array(),
				'k'         => 0,
			);
		}

		if ( count( $opt['tiers'] ) < 2 ) {
			return null;
		}

		$state = $this->fx->tier_state( $opt['tiers'], $subtotal );
		$next  = $state['next'];
		$steps = array();

		foreach ( $state['marks'] as $i => $mark ) {
			$steps[] = array(
				'at'    => $mark,
				'label' => isset( $extra['tier_label'][ $i ] ) ? $extra['tier_label'][ $i ] : '',
				'done'  => $i < $state['done'],
			);
		}

		return array(
			'threshold' => implode( ',', $opt['tiers'] ),
			'done'      => $next < 0,
			'pct'       => $state['pct'],
			'remaining' => $next >= 0 ? $money( max( 0, (float) $opt['tiers'][ $next ] - $subtotal ) ) : '',
			'goal'      => $next >= 0 && isset( $extra['tier_label'][ $next ] ) ? '<span class="abp-goal">' . esc_html( $extra['tier_label'][ $next ] ) . '</span>' : '',
			'text'      => $next >= 0 && isset( $extra['tier_text'][ $next ] ) ? $extra['tier_text'][ $next ] : '',
			'steps'     => $steps,
			'k'         => $state['done'],
		);
	}

	/**
	 * E-mail form texts of the visitor's language; empty fields use the plugin's texts.
	 *
	 * @param array $extra Extra texts.
	 * @return array
	 */
	private function email_texts( array $extra ) {
		$defaults = self::email_defaults();
		$out      = array();

		foreach ( array( 'placeholder', 'button', 'consent', 'success' ) as $key ) {
			$value       = isset( $extra[ 'email_' . $key ] ) ? $extra[ 'email_' . $key ] : '';
			$out[ $key ] = '' !== $value ? $value : $defaults[ $key ];
		}

		return $out;
	}

	/**
	 * Default texts of the e-mail form.
	 *
	 * @return array
	 */
	public static function email_defaults() {
		return array(
			'placeholder' => __( 'Your e-mail address', 'announcement-bar-pro' ),
			'button'      => __( 'Subscribe', 'announcement-bar-pro' ),
			'consent'     => __( 'I agree to receive news and offers by e-mail. I can unsubscribe at any time.', 'announcement-bar-pro' ),
			'success'     => __( 'Almost done — please confirm with the link we sent to your inbox.', 'announcement-bar-pro' ),
		);
	}

	/**
	 * Background image with the message colour laid over it (overlay %), for a style attribute.
	 *
	 * @param int    $id      Attachment ID.
	 * @param string $hex     Message colour.
	 * @param int    $overlay Overlay %.
	 * @return string
	 */
	private function image_style( $id, $hex, $overlay ) {
		$url = wp_get_attachment_image_url( $id, 'full' );

		if ( ! is_string( $url ) || '' === $url || preg_match( '/[\'"()\s\\\\]/', $url ) ) {
			return '';
		}

		$rgb  = array( hexdec( substr( $hex, 1, 2 ) ), hexdec( substr( $hex, 3, 2 ) ), hexdec( substr( $hex, 5, 2 ) ) );
		$tint = 'rgba(' . implode( ',', $rgb ) . ',' . number_format( max( 0, min( 90, (int) $overlay ) ) / 100, 2, '.', '' ) . ')';

		return 'background-image:linear-gradient(' . $tint . ',' . $tint . '),url("' . esc_url_raw( $url ) . '");background-size:cover;background-position:center;';
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
	 * One bar or box. Inline boxes show every message (stacked), without close / arrows / auto-hide.
	 *
	 * @param string $place  Placement.
	 * @param array  $items  Items.
	 * @param bool   $inline Inline box.
	 * @return array
	 */
	public function container( $place, array $items, $inline ) {
		$mode = $inline ? 'stack' : $this->s['mode'];

		if ( 'marquee' === $mode ) {
			foreach ( $items as $i => $item ) {
				$items[ $i ]['email'] = null; // A form cannot scroll by; the text is still shown.
			}
		}

		return array(
			'id'          => '',
			'inline'      => $inline,
			'placement'   => $place,
			'mode'        => $mode,
			'items'       => $items,
			'sticky'      => ! $inline && 'top' === $place && ! empty( $this->s['sticky'] ),
			'arrows'      => ! $inline && 'rotate' === $mode && count( $items ) > 1 && ! empty( $this->s['arrows'] ),
			'dismissible' => ! $inline && ! empty( $this->s['dismissible'] ),
			'pause'       => ! $inline && ! empty( $this->s['pause_button'] ) && ( ( 'rotate' === $mode && count( $items ) > 1 ) || 'marquee' === $mode ),
			'autohide'    => $inline ? 0 : (int) $this->s['autohide'],
		);
	}

	/**
	 * Values every container shares (labels, endpoints, timing).
	 *
	 * @param array $need What the items need (cart refresh, e-mail form).
	 * @param bool  $fallback Bars printed in the footer (theme without wp_body_open).
	 * @return array
	 */
	public function common( array $need, $fallback = false ) {
		$s      = $this->s;
		$live   = ! $this->preview;
		$tz     = wp_timezone();
		$offset = $tz->getOffset( new \DateTimeImmutable( 'now', $tz ) );

		return array(
			'mode'         => $s['mode'],
			'font'         => $s['font'],
			'ga4'          => $live && ! empty( $s['ga4'] ),
			'consent'      => $live && ! empty( $s['consent_wait'] ),
			'max'          => (int) $s['max_messages'],
			'transition'   => 'slide' === $s['transition'] ? 'slide' : 'fade',
			'interval'     => (int) $s['interval'],
			'speed'        => (int) $s['speed'],
			'font_size'    => (int) $s['font_size'],
			'padding'      => (int) $s['padding'],
			'color_mode'   => 'auto' === $s['color_mode'] ? 'auto' : 'custom',
			'dismiss_days' => (int) $s['dismiss_days'],
			'fallback'     => (bool) $fallback,
			'track'        => $live && $this->pro ? rest_url( 'announcement-bar-pro/v1/track' ) : '',
			'cart'         => $live && $need['cart'] && Commerce::active() ? \WC_AJAX::get_endpoint( 'abpro_cart' ) : '',
			'subscribe'    => $need['email'] ? ( $live ? rest_url( 'announcement-bar-pro/v1/subscribe' ) : '#' ) : '',
			'token'        => $live && $need['email'] ? $this->fx->token( Settings::secret(), 'abp-signup' ) : '',
			'privacy'      => $need['email'] ? self::privacy_url( (int) $s['privacy_id'] ) : '',
			'now'          => time() * 1000,
			'tz'           => (int) $offset,
			'label'        => __( 'Announcement', 'announcement-bar-pro' ),
			'close'        => __( 'Close', 'announcement-bar-pro' ),
			/* translators: abbreviation of "days" in a countdown such as "2d 04:10:00" */
			'days'         => _x( 'd', 'countdown days', 'announcement-bar-pro' ),
			'copy'         => __( 'Copy', 'announcement-bar-pro' ),
			'copied'       => __( 'Copied!', 'announcement-bar-pro' ),
			'prev'         => __( 'Previous message', 'announcement-bar-pro' ),
			'next'         => __( 'Next message', 'announcement-bar-pro' ),
			'show'         => __( 'Show announcements', 'announcement-bar-pro' ),
			'pause_label'  => __( 'Pause messages', 'announcement-bar-pro' ),
			'play_label'   => __( 'Play messages', 'announcement-bar-pro' ),
			'privacy_text' => __( 'Privacy policy', 'announcement-bar-pro' ),
			'sending'      => __( 'Sending…', 'announcement-bar-pro' ),
			'err_email'    => __( 'Please enter a valid e-mail address.', 'announcement-bar-pro' ),
			'err_consent'  => __( 'Please tick the box to agree.', 'announcement-bar-pro' ),
			'err_failed'   => __( 'Sorry, the sign-up did not work. Please try again later.', 'announcement-bar-pro' ),
		);
	}

	/**
	 * Privacy page URL: the page of the settings, else WordPress's privacy policy page.
	 *
	 * @param int $page_id Page ID.
	 * @return string
	 */
	public static function privacy_url( $page_id ) {
		if ( $page_id > 0 && 'publish' === get_post_status( $page_id ) ) {
			$url = get_permalink( $page_id );

			return is_string( $url ) ? $url : '';
		}

		return get_privacy_policy_url();
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
				. ( $box['dismissible'] ? ' abp-has-close' : '' ) . ( $box['arrows'] ? ' abp-has-nav' : '' ) . ( $box['pause'] ? ' abp-has-pause' : '' )
				. ( $c['font'] ? ' abp-font-' . $c['font'] : '' );

			$attrs = array(
				'id'               => $box['id'],
				'class'            => $classes,
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
				'data-track'       => $c['track'],
				'data-cart'        => $c['cart'],
				'data-max'         => $c['max'] ? $c['max'] : '',
				'data-autohide'    => $box['autohide'] ? $box['autohide'] : '',
				'data-l-show'      => $box['autohide'] ? $c['show'] : '',
				'data-l-pause'     => $box['pause'] ? $c['pause_label'] : '',
				'data-l-play'      => $box['pause'] ? $c['play_label'] : '',
				'data-ga'          => $c['ga4'] ? 1 : '',
				'data-consent'     => $c['consent'] ? 1 : '',
				'style'            => 'font-size:' . (int) $c['font_size'] . 'px;background-color:' . $first['bg_color'] . ';color:' . $first['text_color'] . ';',
			);

			$out .= '<div' . self::attrs( $attrs ) . '>' . "\n" . '<div class="abp-viewport"><div class="abp-track">' . "\n";

			foreach ( $box['items'] as $n => $item ) {
				$out .= $this->item_html( $item, 0 === $n, $box, $c, $kses );
			}

			$out .= '</div></div>' . "\n";

			if ( $box['pause'] ) {
				$out .= '<button type="button" class="abp-pause" aria-pressed="false" aria-label="' . esc_attr( $c['pause_label'] ) . '" title="' . esc_attr( $c['pause_label'] ) . '"><svg class="abp-i-pause" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M5.5 3.5v9m5-9v9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><svg class="abp-i-play" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M5 3.2v9.6L12.6 8z" fill="currentColor"/></svg></button>';
			}

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
			'data-font'   => $item['font'],
			'data-img'    => $item['image'] ? 1 : '',
			'data-utm'    => $item['utm'],
			'data-vis'    => $item['vis'],
			'data-uoff'   => $item['utm'] || $item['vis'] ? 1 : '',
			'data-trig'   => $item['trig'],
			'data-toff'   => $item['trig'] ? 1 : '',
			'data-cc'     => $item['cc'] ? 1 : '',
			'data-coff'   => $item['cc'] && $item['coff'] ? 1 : '',
			'style'       => 'background-color:' . $item['bg_color'] . ';color:' . $item['text_color'] . ';--abp-btn-bg:' . $item['text_color'] . ';--abp-btn-fg:' . $item['bg_color'] . ';--abp-accent:' . $item['text_color'] . ';padding-top:' . (int) $c['padding'] . 'px;padding-bottom:' . (int) $c['padding'] . 'px;' . $item['image'],
		);

		$target = $item['new_tab'] ? ' target="_blank" rel="noopener noreferrer"' : '';
		$icon   = '' !== $item['icon'] ? Icons::svg( $item['icon'] ) . ' ' : '';
		$out    = '<div' . self::attrs( $attrs ) . '>' . "\n" . '<div class="abp-inner">';

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

		if ( '' !== $item['coupon'] ) {
			$out .= '<span class="abp-coupon"><span class="abp-code">' . esc_html( $item['coupon'] ) . '</span><button type="button" class="abp-copy" data-code="' . esc_attr( $item['coupon'] ) . '" data-label="' . esc_attr( $c['copy'] ) . '" data-copied="' . esc_attr( $c['copied'] ) . '" aria-label="' . esc_attr( $item['copy_aria'] ) . '">'
				. '<svg class="abp-cp" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><rect x="5.2" y="5.2" width="8.6" height="8.6" rx="1.8" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M10.6 3.1V3A1.5 1.5 0 0 0 9.1 1.5H3A1.5 1.5 0 0 0 1.5 3v6.1a1.5 1.5 0 0 0 1.5 1.5h.1" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>'
				. '<svg class="abp-ok" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M3 8.6l3.3 3.1L13 4.6" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>'
				. '<span class="abp-copy-label">' . esc_html( $c['copy'] ) . '</span></button><span class="abp-sr" role="status" aria-live="polite"></span></span>';
		}

		if ( $item['email'] && '' !== $c['subscribe'] ) {
			$fid  = 'abp-fm-' . $box['placement'] . '-' . $item['id'];
			$out .= '<form class="abp-form" action="' . esc_url( $c['subscribe'] ) . '" method="post" novalidate data-tok="' . esc_attr( $c['token'] ) . '" data-success="' . esc_attr( $item['email']['success'] ) . '" data-sending="' . esc_attr( $c['sending'] ) . '" data-err-email="' . esc_attr( $c['err_email'] ) . '" data-err-consent="' . esc_attr( $c['err_consent'] ) . '" data-err-failed="' . esc_attr( $c['err_failed'] ) . '">'
				. '<span class="abp-hp" aria-hidden="true"><label>Website <input type="text" name="website" value="" tabindex="-1" autocomplete="off" /></label></span>'
				. '<span class="abp-fields"><input type="email" name="email" class="abp-email" required="required" maxlength="96" autocomplete="email" inputmode="email" placeholder="' . esc_attr( $item['email']['placeholder'] ) . '" aria-label="' . esc_attr( $item['email']['placeholder'] ) . '" aria-describedby="' . esc_attr( $fid ) . '" /><button type="submit" class="abp-submit">' . esc_html( $item['email']['button'] ) . '</button></span>'
				. '<label class="abp-consent"><input type="checkbox" name="consent" value="1" required="required" aria-describedby="' . esc_attr( $fid ) . '" /> <span>' . esc_html( $item['email']['consent'] )
				. ( '' !== $c['privacy'] ? ' <a href="' . esc_url( $c['privacy'] ) . '" target="_blank" rel="noopener">' . esc_html( $c['privacy_text'] ) . '</a>' : '' ) . '</span></label>'
				. '<span class="abp-form-msg" id="' . esc_attr( $fid ) . '" role="status" aria-live="polite"></span></form>';
		}

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
	private static function attrs( array $attrs ) {
		$out = '';

		foreach ( $attrs as $name => $value ) {
			if ( null === $value || '' === $value ) {
				continue;
			}

			$out .= ' ' . $name . '="' . esc_attr( (string) $value ) . '"';
		}

		return $out;
	}
}
