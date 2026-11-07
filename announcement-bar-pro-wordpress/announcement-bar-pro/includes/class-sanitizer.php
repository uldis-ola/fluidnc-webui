<?php
/**
 * Text / HTML / URL sanitizer — port of the OpenCart 2.4.0 library (system/library/announcementbarpro/sanitizer.php).
 *
 * Differences to the OpenCart file (on purpose):
 * - WordPress does not HTML-escape the request, so input is never entity-decoded on the way in;
 * - the database is utf8mb4, so 4-byte characters (emoji) are stored as they are (no encode4byte());
 * - URLs: http(s) and site paths ("/shop/"), no OpenCart "index.php?route=" form;
 * - icons are names of the built-in SVG set (Icons::NAMES), not Font Awesome classes;
 * - the schedule is checked in the site's time zone (wp_timezone()), WordPress keeps PHP on UTC.
 *
 * @package AnnouncementBarPro
 */

namespace AnnouncementBarPro;

defined( 'ABSPATH' ) || exit;

/**
 * Whitelist sanitizer for message texts, links, colours and schedule values. No WordPress calls: unit-testable.
 */
class Sanitizer {
	/** Allowed inline tags. Everything else is unwrapped (content kept) or dropped. */
	const ALLOWED_TAGS = array( 'b', 'strong', 'i', 'em', 'u', 's', 'br', 'span', 'a' );

	/** Tags removed together with their content (svg, math, form, button are unwrapped instead). */
	const DROP_TAGS = array( 'script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'input', 'textarea', 'select', 'link', 'meta', 'base', 'head', 'title' );

	/**
	 * Entities decoded, invalid UTF-8 dropped.
	 *
	 * @param mixed $value Text.
	 * @return string
	 */
	public function decode( $value ) {
		return $this->utf8( html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Drops invalid UTF-8 byte sequences (otherwise /u regexes return null and text silently vanishes).
	 *
	 * @param mixed $value Text.
	 * @return string
	 */
	public function utf8( $value ) {
		$value = (string) $value;

		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $value, 'UTF-8' ) ) {
			$value = mb_convert_encoding( $value, 'UTF-8', 'UTF-8' );
		}

		return $value;
	}

	/**
	 * Plain text: no tags, no control characters, collapsed whitespace. "x < 5" stays text.
	 *
	 * @param mixed $value Text.
	 * @return string
	 */
	public function plain( $value ) {
		$value = $this->utf8( $value );
		$value = (string) preg_replace( '/<!--.*?-->/s', ' ', $value );
		$value = (string) preg_replace( '#<(br|p|div|li|tr|h[1-6])\b[^<>]*>|</(p|div|li|tr|h[1-6])\s*>#i', ' ', $value );
		$value = (string) preg_replace( '#</?[a-z][a-z0-9-]*(\s[^<>]*)?/?>#i', '', $value );
		$value = (string) preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $value );

		return trim( (string) preg_replace( '/\s+/u', ' ', $value ) );
	}

	/**
	 * Whitelist HTML: only ALLOWED_TAGS, no attributes except a[href,target]; href checked by url().
	 *
	 * @param mixed $value HTML.
	 * @return string Safe HTML.
	 */
	public function html( $value ) {
		$value = trim( (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $this->utf8( $value ) ) );

		if ( '' === $value ) {
			return '';
		}

		if ( false === strpos( $value, '<' ) ) {
			return htmlspecialchars( $this->decode( $value ), ENT_QUOTES, 'UTF-8' );
		}

		if ( ! class_exists( 'DOMDocument' ) ) {
			return htmlspecialchars( $this->plain( $value ), ENT_QUOTES, 'UTF-8' );
		}

		// A "<" that cannot start a tag ("x < 5") is text: escape it so the parser does not swallow the rest.
		$value = (string) preg_replace( '/<(?![a-zA-Z\/!?])/', '&lt;', $value );

		$doc  = new \DOMDocument( '1.0', 'UTF-8' );
		$prev = libxml_use_internal_errors( true );
		// <body> as root: stray closing tags in the input cannot close it early.
		$doc->loadHTML( '<?xml encoding="utf-8"?><html><body>' . $value . '</body></html>', LIBXML_HTML_NODEFDTD | LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		$root = $doc->getElementsByTagName( 'body' )->item( 0 );

		if ( ! $root ) {
			return htmlspecialchars( $this->plain( $value ), ENT_QUOTES, 'UTF-8' );
		}

		$this->clean_node( $root );

		$html = '';
		foreach ( $root->childNodes as $child ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
			$html .= $doc->saveHTML( $child );
		}

		return trim( $html );
	}

	/**
	 * Safe HTML for output; $allow_html = false gives escaped plain text.
	 *
	 * @param mixed $value      Stored text.
	 * @param bool  $allow_html HTML mode of the settings.
	 * @return string
	 */
	public function to_safe_html( $value, $allow_html ) {
		if ( $allow_html ) {
			return $this->html( $value );
		}

		return htmlspecialchars( $this->plain( $this->decode( $value ) ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Replaces the first {token} found in text (never inside a tag) with $replacement; other copies are removed.
	 *
	 * @param string $html        Safe HTML.
	 * @param string $token       Token such as {countdown}.
	 * @param string $replacement Safe HTML.
	 * @return array (html, replaced?)
	 */
	public function replace_token( $html, $token, $replacement ) {
		$done = false;

		$html = preg_replace_callback(
			'/(<[^>]*>)|' . preg_quote( $token, '/' ) . '/',
			static function ( $m ) use ( &$done, $replacement ) {
				if ( ! empty( $m[1] ) ) {
					return $m[1];
				}

				if ( ! $done && '' !== $replacement ) {
					$done = true;
					return $replacement;
				}

				return '';
			},
			(string) $html
		);

		return array( (string) $html, $done );
	}

	/**
	 * Accepts absolute http(s) URLs and site paths ("/shop/"). Returns '' when invalid.
	 *
	 * @param mixed $value URL.
	 * @return string
	 */
	public function url( $value ) {
		$value = trim( str_replace( array( "\r", "\n", "\t", "\0", ' ' ), array( '', '', '', '', '%20' ), $this->utf8( $value ) ) );

		// Internationalised domain (https://ēkas.lv) → punycode, when the intl extension is available.
		if ( preg_match( '#^(https?://)([^/?\#:]+)(.*)$#iu', $value, $m ) && preg_match( '/[^\x00-\x7F]/', $m[2] ) && function_exists( 'idn_to_ascii' ) && defined( 'INTL_IDNA_VARIANT_UTS46' ) ) {
			$host = idn_to_ascii( $m[2], 0, INTL_IDNA_VARIANT_UTS46 );
			if ( false !== $host ) {
				$value = $m[1] . $host . $m[3];
			}
		}

		// Non-ASCII paths (/akcija-šodien) → percent-encoded.
		$value = (string) preg_replace_callback(
			'/[^\x00-\x7F]+/u',
			static function ( $m ) {
				return rawurlencode( $m[0] );
			},
			$value
		);

		if ( '' === $value || strlen( $value ) > 255 ) {
			return '';
		}

		// Only RFC 3986 characters, minus the apostrophe (no quotes, <, >, {, }, backslash).
		if ( ! preg_match( '/^[A-Za-z0-9\-._~:\/?#\[\]@!$&()*+,;=%]+$/', $value ) ) {
			return '';
		}

		if ( preg_match( '#^https?://#i', $value ) ) {
			return false !== filter_var( $value, FILTER_VALIDATE_URL ) ? $value : '';
		}

		return preg_match( '#^/(?!/)#', $value ) ? $value : '';
	}

	/**
	 * Colour #rrggbb (lower case) or $fallback.
	 *
	 * @param mixed  $value    Colour.
	 * @param string $fallback Value for invalid input.
	 * @return string
	 */
	public function color( $value, $fallback = '' ) {
		return ( is_string( $value ) && preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ) ? strtolower( $value ) : $fallback;
	}

	/**
	 * Icon name of the built-in SVG set, or ''.
	 *
	 * @param mixed $value Icon name.
	 * @return string
	 */
	public function icon( $value ) {
		$value = trim( (string) $value );

		return in_array( $value, Icons::NAMES, true ) ? $value : '';
	}

	/**
	 * Static coupon code: A–Z, 0–9, "-" and "_", at most 32, upper case. '' when empty or invalid.
	 *
	 * @param mixed $value Code.
	 * @return string
	 */
	public function coupon( $value ) {
		$value = strtoupper( trim( (string) $value ) );

		return preg_match( '/^[A-Z0-9_-]{1,32}$/', $value ) ? $value : '';
	}

	/**
	 * Daily time "HH:MM" (00:00–23:59), or ''.
	 *
	 * @param mixed $value Time.
	 * @return string
	 */
	public function clock_time( $value ) {
		$value = trim( (string) $value );

		return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : '';
	}

	/**
	 * Weekdays as ISO numbers (1 = Monday … 7 = Sunday), sorted ("135"). All seven or none = '' (every day).
	 *
	 * @param mixed $value Array or string.
	 * @return string
	 */
	public function weekdays( $value ) {
		$list = is_array( $value ) ? $value : str_split( (string) preg_replace( '/[^1-7]/', '', (string) $value ) );
		$days = array();

		foreach ( $list as $day ) {
			if ( is_scalar( $day ) && preg_match( '/^[1-7]$/', (string) $day ) ) {
				$days[ (int) $day ] = (int) $day;
			}
		}

		ksort( $days );

		return 7 === count( $days ) ? '' : implode( '', $days );
	}

	/**
	 * Weekly schedule in the site's time zone. A window that crosses midnight (22:00–02:00) belongs to the day it
	 * starts on. Same rules as the storefront script.
	 *
	 * @param string             $weekdays   ISO weekday digits ('' = every day).
	 * @param string             $time_start HH:MM.
	 * @param string             $time_end   HH:MM.
	 * @param int|null           $timestamp  Unix time (default now).
	 * @param \DateTimeZone|null $tz         Site time zone (default UTC).
	 * @return bool
	 */
	public function schedule_open( $weekdays, $time_start, $time_end, $timestamp = null, $tz = null ) {
		$timestamp = null === $timestamp ? time() : (int) $timestamp;
		$weekdays  = (string) $weekdays;
		$t1        = $this->clock_time( $time_start );
		$t2        = $this->clock_time( $time_end );

		if ( '' === $weekdays && '' === $t1 ) {
			return true;
		}

		$at   = ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $tz instanceof \DateTimeZone ? $tz : new \DateTimeZone( 'UTC' ) );
		$day  = (int) $at->format( 'N' );
		$prev = 1 === $day ? 7 : $day - 1;
		$min  = (int) $at->format( 'G' ) * 60 + (int) $at->format( 'i' );
		$has  = static function ( $d ) use ( $weekdays ) {
			return '' === $weekdays || false !== strpos( $weekdays, (string) $d );
		};

		if ( '' === $t1 || '' === $t2 || $t1 === $t2 ) {
			return $has( $day );
		}

		$a = (int) substr( $t1, 0, 2 ) * 60 + (int) substr( $t1, 3, 2 );
		$b = (int) substr( $t2, 0, 2 ) * 60 + (int) substr( $t2, 3, 2 );

		if ( $a < $b ) {
			return $has( $day ) && $min >= $a && $min < $b;
		}

		return ( $min >= $a && $has( $day ) ) || ( $min < $b && $has( $prev ) );
	}

	/**
	 * Positive integer IDs from an array, a JSON list or "1,2,3": unique, at most $max.
	 *
	 * @param mixed $value List.
	 * @param int   $max   Maximum.
	 * @return int[]
	 */
	public function id_list( $value, $max = 200 ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : explode( ',', $value );
		}

		$out = array();

		foreach ( (array) $value as $id ) {
			if ( is_scalar( $id ) && preg_match( '/^\s*\d{1,10}\s*$/', (string) $id ) && (int) $id > 0 ) {
				$out[ (int) $id ] = (int) $id;
			}
		}

		return array_slice( array_values( $out ), 0, (int) $max );
	}

	/**
	 * Money amount ("50", "49.99", "49,99") → '49.9900'; false when invalid.
	 *
	 * @param mixed $value Amount.
	 * @return string|false
	 */
	public function amount( $value ) {
		$value = str_replace( array( ' ', "\xc2\xa0", ',' ), array( '', '', '.' ), trim( (string) $value ) );

		if ( ! preg_match( '/^\d{1,9}(\.\d{1,4})?$/', $value ) ) {
			return false;
		}

		return number_format( (float) $value, 4, '.', '' );
	}

	/**
	 * Text markup of one variant: normal text, plus the phone text when there is one. Input is safe HTML.
	 *
	 * @param string $html   Safe HTML.
	 * @param string $html_m Safe HTML for phones ('' = same).
	 * @return string
	 */
	public function text_html( $html, $html_m ) {
		if ( '' === (string) $html_m ) {
			return '<span class="abp-text">' . $html . '</span>';
		}

		return '<span class="abp-text abp-text-d">' . $html . '</span><span class="abp-text abp-text-m">' . $html_m . '</span>';
	}

	/**
	 * Removes everything that is not on the whitelist below $node.
	 *
	 * @param \DOMNode $node    Parent.
	 * @param bool     $in_link Inside a link (nested links are unwrapped).
	 */
	private function clean_node( \DOMNode $node, $in_link = false ) {
		$children = array();
		foreach ( $node->childNodes as $child ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
			$children[] = $child;
		}

		foreach ( $children as $child ) {
			if ( $child instanceof \DOMElement ) {
				$tag = strtolower( $child->nodeName ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.

				if ( in_array( $tag, self::DROP_TAGS, true ) ) {
					$node->removeChild( $child );
					continue;
				}

				$this->clean_node( $child, $in_link || 'a' === $tag );

				$href   = 'a' === $tag ? $this->url( $child->getAttribute( 'href' ) ) : '';
				$target = 'a' === $tag && '_blank' === $child->getAttribute( 'target' );

				if ( ! in_array( $tag, self::ALLOWED_TAGS, true ) || ( 'a' === $tag && ( '' === $href || $in_link ) ) ) {
					$this->unwrap( $node, $child );
					continue;
				}

				// Rebuild the element instead of removing attributes one by one (namespace-like names never loop).
				$clean = $node->ownerDocument->createElement( $tag ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.

				while ( $child->firstChild ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
					$clean->appendChild( $child->firstChild ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
				}

				if ( 'a' === $tag ) {
					$clean->setAttribute( 'href', $href );

					if ( $target ) {
						$clean->setAttribute( 'target', '_blank' );
						$clean->setAttribute( 'rel', 'noopener noreferrer' );
					}
				}

				$node->replaceChild( $clean, $child );
			} elseif ( $child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction || $child instanceof \DOMCdataSection ) {
				$node->removeChild( $child );
			}
		}
	}

	/**
	 * Replaces $child by its children.
	 *
	 * @param \DOMNode $parent_node Parent.
	 * @param \DOMNode $child       Child.
	 */
	private function unwrap( \DOMNode $parent_node, \DOMNode $child ) {
		while ( $child->firstChild ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
			$parent_node->insertBefore( $child->firstChild, $child ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
		}

		$parent_node->removeChild( $child );
	}
}
