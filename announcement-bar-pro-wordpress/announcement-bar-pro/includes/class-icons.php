<?php
/**
 * Built-in icon set: small line icons drawn for this plugin (24 × 24, stroke = currentColor), printed as inline SVG.
 * No icon font and nothing loaded from elsewhere.
 *
 * @package AnnouncementBarPro
 */

namespace AnnouncementBarPro;

defined( 'ABSPATH' ) || exit;

/**
 * Inline SVG icons.
 */
class Icons {
	const NAMES = array( 'truck', 'gift', 'bolt', 'clock', 'ticket', 'mail', 'star', 'box', 'snowflake', 'percent', 'exit', 'briefcase', 'shield', 'info', 'megaphone', 'tag', 'heart', 'check', 'leaf', 'globe' );

	/**
	 * Path data of every icon.
	 *
	 * @return array
	 */
	public static function paths() {
		$c = static function ( $x, $y, $r ) {
			return 'M' . ( $x - $r ) . ' ' . $y . 'a' . $r . ' ' . $r . ' 0 1 0 ' . ( 2 * $r ) . ' 0a' . $r . ' ' . $r . ' 0 1 0 ' . ( -2 * $r ) . ' 0';
		};

		return array(
			'truck'     => 'M2 6h11v10H2zM13 9h4l3.5 3.5V16H13' . $c( 6, 17.5, 1.8 ) . $c( 16.5, 17.5, 1.8 ),
			'gift'      => 'M3 9h18v4H3zM5 13h14v8H5zM12 9v12M12 9c-1.6-3.6-6-4-5.2-1.2C7.3 9.3 12 9 12 9zm0 0c1.6-3.6 6-4 5.2-1.2C16.7 9.3 12 9 12 9z',
			'bolt'      => 'M13 2 4.5 13.5H11L10 22l8.5-11.5H12z',
			'clock'     => $c( 12, 12, 9 ) . 'M12 7v5l3.2 2',
			'ticket'    => 'M3 7h18v3a2 2 0 0 0 0 4v3H3v-3a2 2 0 0 0 0-4zM14.5 7.5v1.5M14.5 11.25v1.5M14.5 15v1.5',
			'mail'      => 'M3 5.5h18v13H3zM3.5 6.5 12 13l8.5-6.5',
			'star'      => 'M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z',
			'box'       => 'M12 2.8 3.5 7.2v9.6L12 21.2l8.5-4.4V7.2zM3.5 7.2 12 11.6l8.5-4.4M12 11.6v9.6',
			'snowflake' => 'M12 2v20M3.3 7l17.4 10M3.3 17 20.7 7M9.5 3.5 12 6l2.5-2.5M9.5 20.5 12 18l2.5 2.5',
			'percent'   => 'M19 5 5 19' . $c( 7, 7, 2.5 ) . $c( 17, 17, 2.5 ),
			'exit'      => 'M14 3H5v18h9M10 12h11M17 8l4 4-4 4',
			'briefcase' => 'M3 7.5h18v12H3zM8.5 7.5V4.5h7v3M3 12.5h18',
			'shield'    => 'M12 2.8 19.5 6v6c0 4.6-3.2 8-7.5 9.2C7.7 20 4.5 16.6 4.5 12V6zM8.8 12l2.2 2.2 4.2-4.4',
			'info'      => $c( 12, 12, 9 ) . 'M12 11v6M12 7.6v.4',
			'megaphone' => 'M3 10v4h3.5l7.5 4.5v-13L6.5 10zM17 9a4 4 0 0 1 0 6M6.5 14l1.2 5.5h2.6',
			'tag'       => 'M3 3h8.2L21 12.8 12.8 21 3 11.2z' . $c( 7.8, 7.8, 1.4 ),
			'heart'     => 'M12 20.5S3.5 15.3 3.5 9.2A4.3 4.3 0 0 1 12 7.4a4.3 4.3 0 0 1 8.5 1.8c0 6.1-8.5 11.3-8.5 11.3z',
			'check'     => 'M4.5 12.5 9.5 17.5 19.5 6.5',
			'leaf'      => 'M5 19c0-8 5-14 15-14 0 10-6 15-14 15M5 19l7-7',
			'globe'     => $c( 12, 12, 9 ) . 'M3 12h18M12 3c3 3.2 3 14.8 0 18M12 3c-3 3.2-3 14.8 0 18',
		);
	}

	/**
	 * SVG markup of one icon ('' for an unknown name). Decorative: hidden from screen readers.
	 *
	 * @param string $name  Icon name.
	 * @param string $css_class CSS class.
	 * @return string
	 */
	public static function svg( $name, $css_class = 'abp-ico' ) {
		$paths = self::paths();

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		return '<svg class="' . esc_attr( $css_class ) . '" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="' . esc_attr( $paths[ $name ] ) . '"/></svg>';
	}

	/**
	 * Tags and attributes of the icon markup, for wp_kses().
	 *
	 * @return array
	 */
	public static function kses() {
		return array(
			'svg'  => array(
				'class'           => true,
				'viewbox'         => true,
				'width'           => true,
				'height'          => true,
				'aria-hidden'     => true,
				'focusable'       => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
			),
			'path' => array(
				'd'               => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
			),
			'rect' => array(
				'x'            => true,
				'y'            => true,
				'width'        => true,
				'height'       => true,
				'rx'           => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
		);
	}
}
