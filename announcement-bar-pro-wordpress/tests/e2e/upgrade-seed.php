<?php
// phpcs:disable -- test seed (wp eval-file), runs with the single-plugin build 1.0.0 active: Pro licence, Pro settings,
// free and Pro messages, two subscribers — written through that build's own classes.
use AnnouncementBarPro\DB;
use AnnouncementBarPro\Features;
use AnnouncementBarPro\Local_Licence_Provider;
use AnnouncementBarPro\Settings;

$body = 'HJKLMNPQRSTUVWX';
$key  = 'ABP1-' . implode( '-', str_split( $body . Local_Licence_Provider::checksum( $body ), 4 ) );
update_option( 'abpro_licence', array( 'key' => $key, 'status' => 'valid', 'activated' => time() ), false );

$s                 = Settings::get();
$s['pause_button'] = 1;
$s['ga4']          = 1;
$s['sticky']       = 1;
$s['custom_css']   = '.abp .abp-code{letter-spacing:.1em}';
Settings::save( $s );

$fx  = new Features();
$add = static function ( $text, array $cols, array $opt = array(), array $extra = array() ) use ( $fx ) {
	$o   = array_merge( $fx->defaults(), $opt );
	$row = array_merge(
		array(
			'status' => 1, 'sort_order' => 0, 'priority' => 0, 'bg_color' => '#1f2937', 'text_color' => '#ffffff', 'link' => '', 'link_new_tab' => 0,
			'icon' => '', 'countdown' => 0, 'roles' => '[]', 'page_mode' => 'all', 'page_rules' => '', 'device' => 'all', 'type' => 'standard',
			'threshold' => '0', 'progress_bar' => 1, 'coupon' => '', 'ab_test' => 0, 'weekdays' => '', 'time_start' => '', 'time_end' => '',
			'categories' => '[]', 'products' => '[]', 'style' => '', 'button_style' => 'filled', 'placement' => '', 'options' => $fx->encode( $o ),
		),
		$cols
	);
	return DB::save_message( 0, $row, array( 'en_US' => array( 'text' => $text, 'extra' => wp_json_encode( $extra ) ) ) );
};

$ids = array(
	'free'    => $add( 'UPG free message', array() ),
	'bottom'  => $add( 'UPG bottom coupon', array( 'placement' => 'bottom', 'coupon' => 'SAVE5', 'style' => 'gradient', 'priority' => 10 ) ),
	'page'    => $add( 'UPG sale page only', array( 'page_mode' => 'include', 'page_rules' => '/sale/' ) ),
	'country' => $add( 'UPG not for Latvia', array(), array( 'countries_mode' => 'exclude', 'countries' => array( 'LV' ) ) ),
	'email'   => $add( 'UPG news sign-up', array( 'priority' => 5 ), array( 'email' => 1 ), array( 'email_consent' => 'UPG consent text' ) ),
);

global $wpdb;
$t = $wpdb->prefix . 'abpro_subscribers';
$wpdb->query( $wpdb->prepare( "INSERT INTO $t (email, locale, message_id, status, token, unsubscribe, consent, date_added, date_confirmed) VALUES (%s, 'en_US', %d, 1, '', %s, 'UPG consent text', UTC_TIMESTAMP(), UTC_TIMESTAMP())", 'kept@example.test', $ids['email'], hash( 'sha256', 'a' ) ) );
$wpdb->query( $wpdb->prepare( "INSERT INTO $t (email, locale, message_id, status, token, unsubscribe, consent, date_added, date_confirmed) VALUES (%s, 'en_US', %d, 2, '', %s, 'UPG consent text', UTC_TIMESTAMP(), NULL)", 'gone@example.test', $ids['email'], hash( 'sha256', 'b' ) ) );

echo wp_json_encode( $ids );
