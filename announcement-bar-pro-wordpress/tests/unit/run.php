<?php
/**
 * Unit tests of the WordPress edition without WordPress: sanitizer and options of the free plugin; features, e-mail
 * injection guard, licence format, Cloudflare Turnstile verification (mocked siteverify) and confirmation tokens of the
 * Premium add-on — the same cases as the OpenCart tests (announcement-bar-pro-universal/tests/unit/schema_test.php,
 * sections 13 and 14), adapted where the WordPress edition differs on purpose (ISO country codes, state codes,
 * media-library image IDs, site time zone) — and a static check that the free plugin holds no licence or premium code
 * (wordpress.org guideline 5). Run: php tests/unit/run.php
 *
 * @package AnnouncementBarPro
 */

// phpcs:disable -- test script.
define( 'ABSPATH', __DIR__ . '/' );
date_default_timezone_set( 'UTC' ); // WordPress keeps PHP on UTC; the site time zone is passed explicitly.

function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function __( $t ) { return $t; }
function apply_filters( $h, $v ) { return $v; }
$GLOBALS['abpro_opts'] = array();
function get_option( $k, $d = false ) { return isset( $GLOBALS['abpro_opts'][ $k ] ) ? $GLOBALS['abpro_opts'][ $k ] : $d; }
function update_option( $k, $v ) { $GLOBALS['abpro_opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['abpro_opts'][ $k ] ); return true; }

define( 'DAY_IN_SECONDS', 86400 );
$root = dirname( __DIR__, 2 ) . '/announcement-bar-pro/includes/';
$prem = dirname( __DIR__, 2 ) . '/announcement-bar-pro-premium/includes/';
require $root . 'class-icons.php';
require $root . 'class-sanitizer.php';
require $root . 'class-options.php';
require $root . 'class-settings.php';
require $prem . 'class-features.php';
require $prem . 'class-licence.php';
require $prem . 'class-turnstile.php';
require $prem . 'class-subscribers.php';

use AnnouncementBarPro\Sanitizer;
use AnnouncementBarPro\Options;
use AnnouncementBarPro\Premium\Features;
use AnnouncementBarPro\Premium\Licence;
use AnnouncementBarPro\Premium\Local_Licence_Provider;
use AnnouncementBarPro\Premium\Turnstile;
use AnnouncementBarPro\Premium\Subscribers;

$pass = 0;
$fail = 0;
function check( $name, $cond, $extra = '' ) {
	global $pass, $fail;
	if ( $cond ) {
		$pass++;
		echo "ok   $name\n";
	} else {
		$fail++;
		echo "FAIL $name" . ( '' !== $extra ? " — $extra" : '' ) . "\n";
	}
}

$riga = new DateTimeZone( 'Europe/Riga' );
$at   = function ( $s ) use ( $riga ) { return ( new DateTimeImmutable( $s, $riga ) )->getTimestamp(); };

/* ---------------- Sanitizer (OC section 13 + HTML / URL rules) ---------------- */
$s     = new Sanitizer();
$mon10 = $at( '2026-11-09 10:00:00' ); // a Monday, site time
$sat01 = $at( '2026-11-14 01:30:00' ); // Saturday night after Friday
check( 'scheduleOpen: no schedule = always', $s->schedule_open( '', '', '', $mon10, $riga ) );
check( 'scheduleOpen: weekdays', $s->schedule_open( '12345', '', '', $mon10, $riga ) && ! $s->schedule_open( '67', '', '', $mon10, $riga ) );
check( 'scheduleOpen: daily window', $s->schedule_open( '', '09:00', '17:00', $mon10, $riga ) && ! $s->schedule_open( '', '10:01', '17:00', $mon10, $riga ) && ! $s->schedule_open( '', '08:00', '10:00', $mon10, $riga ) );
check( 'scheduleOpen: window across midnight belongs to its start day', $s->schedule_open( '5', '22:00', '02:00', $sat01, $riga ) && ! $s->schedule_open( '6', '22:00', '02:00', $sat01, $riga ) && $s->schedule_open( '', '22:00', '02:00', $sat01, $riga ) && ! $s->schedule_open( '', '22:00', '02:00', $mon10, $riga ) );
check( 'scheduleOpen: site time zone, not UTC (10:00 Riga = 08:00 UTC)', $s->schedule_open( '', '09:30', '10:30', $mon10, $riga ) && ! $s->schedule_open( '', '09:30', '10:30', $mon10, new DateTimeZone( 'UTC' ) ) );
check( 'weekdays(): sorted digits, all 7 = every day', $s->weekdays( array( '3', '1', '1', '9', 'x' ) ) === '13' && $s->weekdays( array( 1, 2, 3, 4, 5, 6, 7 ) ) === '' && $s->weekdays( '' ) === '' && $s->weekdays( '7a5' ) === '57' );
check( 'coupon(): uppercase whitelist, max 32', $s->coupon( ' spring-10 ' ) === 'SPRING-10' && $s->coupon( 'a b' ) === '' && $s->coupon( str_repeat( 'A', 33 ) ) === '' && $s->coupon( '<b>' ) === '' );
check( 'clockTime()', $s->clock_time( '09:30' ) === '09:30' && $s->clock_time( '24:00' ) === '' && $s->clock_time( '9:30' ) === '' );
check( 'idList(): positive ints, unique, max', $s->id_list( array( '3', '3', '0', '-1', '4x', 5 ) ) === array( 3, 5 ) && $s->id_list( '[1,2,"x"]' ) === array( 1, 2 ) && count( $s->id_list( range( 1, 300 ), 200 ) ) === 200 );
check( 'amount(): 49,99 → 49.9900, invalid → false', $s->amount( '49,99' ) === '49.9900' && $s->amount( '50' ) === '50.0000' && $s->amount( '1e3' ) === false && $s->amount( '-5' ) === false && $s->amount( '' ) === false );
check( 'html(): script / style / iframe dropped with content', $s->html( 'a<script>alert(1)</script>b<style>x</style><iframe src=x></iframe>c' ) === 'abc' );
check( 'html(): attributes stripped, unknown tags unwrapped', $s->html( '<b onclick="x()" style="a">B</b><div class="x">D</div>' ) === '<b>B</b>D' );
check( 'html(): javascript: links unwrapped, safe links kept with rel', $s->html( '<a href="javascript:alert(1)">x</a>' ) === 'x' && $s->html( '<a href="https://example.com/a" target="_blank">y</a>' ) === '<a href="https://example.com/a" target="_blank" rel="noopener noreferrer">y</a>' );
check( 'html(): nested links unwrapped, "x < 5" stays text', $s->html( '<a href="/a">1<a href="/b">2</a></a>' ) === '<a href="/a">1</a><a href="/b">2</a>' || false === strpos( $s->html( '<a href="/a">1<a href="/b">2</a></a>' ), '<a href="/a">1<a' ) );
check( 'html(): "x < 5 and y > 2" kept as text', $s->html( '<b>x</b> < 5 and y > 2' ) === '<b>x</b> &lt; 5 and y &gt; 2' );
check( 'html(): svg / math / form unwrapped (content kept), comments removed', $s->html( '<svg><text>T</text></svg><!-- c --><form>F</form>' ) === 'TF' );
check( 'plain(): tags removed, words not glued, entities untouched', $s->plain( 'line1<br>line2 <b>x</b>' ) === 'line1 line2 x' && $s->plain( 'a < b' ) === 'a < b' );
check( 'to_safe_html(): plain mode escapes', $s->to_safe_html( '<b>x</b> & "y"', false ) === 'x &amp; &quot;y&quot;' );
check( 'url(): http(s) and site paths only', $s->url( 'https://example.com/a?b=1' ) === 'https://example.com/a?b=1' && $s->url( '/shop/' ) === '/shop/' && $s->url( 'javascript:alert(1)' ) === '' && $s->url( '//evil.com' ) === '' && $s->url( 'data:text/html,x' ) === '' && $s->url( "/a'onmouseover=1" ) === '' && $s->url( 'index.php?route=x' ) === '' );
check( 'url(): non-ASCII path percent-encoded', $s->url( '/akcija-šodien' ) === '/akcija-%C5%A1odien' );
check( 'color()', $s->color( '#A1B2C3' ) === '#a1b2c3' && $s->color( 'red', '#000000' ) === '#000000' && $s->color( '#abc' ) === '' );
check( 'icon(): only built-in icon names', $s->icon( 'truck' ) === 'truck' && $s->icon( 'fa-truck' ) === '' && $s->icon( '"><script>' ) === '' );
list( $h, $done ) = $s->replace_token( '<a href="/{countdown}">{countdown}</a> {countdown}', '{countdown}', '<span>T</span>' );
check( 'replace_token(): first token in text only, never inside a tag; others removed', $done && $h === '<a href="/{countdown}"><span>T</span></a> ' );

/* ---------------- Features (OC section 14, adapted) ---------------- */
$f      = new Features();
$lookup = function ( $kind, $values ) {
	if ( 'image' === $kind ) { return 77 === $values; }
	$known = array( 'country' => array( 'LV', 'LT' ), 'state' => array( 'LV:RIX' ), 'coupon' => array( 5 ), 'product' => array( 40 ), 'category' => array( 20 ) );
	return array_values( array_intersect( (array) $values, $known[ $kind ] ) );
};
list( $o, $e ) = $f->validate( array(), $lookup, array( 'EUR', 'USD' ) );
check( 'validate(empty) = defaults, no errors', $o == $f->defaults() && ! $e );
list( $o, $e ) = $f->validate( array( 'countries_mode' => 'include', 'countries' => array( 'lv' ), 'fs_zones' => array( array( 'country' => 'LV', 'state' => 'RIX', 'amount' => '40' ), array( 'country' => '', 'state' => '', 'amount' => '' ) ),
	'fs_currencies' => array( 'EUR' => '45,5', 'USD' => '' ), 'coupon_id' => '5', 'tiers' => array( '50', '100', '', '', '' ), 'cart_qty_min' => '2', 'cart_products' => array( '40' ),
	'utm_source' => 'NewsLetter, Google', 'referrer' => 'https://www.Facebook.com/x', 'visitor' => 'new', 'trigger' => 'scroll', 'trigger_value' => '40',
	'recur' => 'daily', 'recur_time' => '14:00', 'recur_skip' => array( '7', '6' ), 'email' => '1', 'image' => '77', 'overlay' => '70', 'font' => 'serif' ), $lookup, array( 'EUR', 'USD' ), 'tiers' );
check( 'validate(valid): no errors, values normalised', ! $e && $o['countries'] === array( 'LV' ) && $o['fs_zones'] === array( array( 'country' => 'LV', 'state' => 'RIX', 'amount' => '40.0000' ) ) && $o['fs_currencies'] === array( 'EUR' => '45.5000' )
	&& $o['tiers'] === array( '50.0000', '100.0000' ) && $o['utm_source'] === 'newsletter,google' && $o['referrer'] === 'facebook.com' && $o['trigger_value'] === 40 && $o['recur_skip'] === '67' && $o['coupon_id'] === 5 && $o['font'] === 'serif' && $o['image'] === 77, json_encode( $e ) . json_encode( $o ) );
list( $o, $e ) = $f->validate( array( 'countries_mode' => 'include', 'countries' => array( 'XX' ), 'fs_zones' => array( array( 'country' => 'LT', 'state' => 'RIX', 'amount' => '5' ) ), 'fs_currencies' => array( 'GBP' => '5' ),
	'coupon_id' => '6', 'tiers' => array( '100', '50' ), 'cart_subtotal_min' => '20', 'cart_subtotal_max' => '10', 'cart_products' => array( '41' ), 'utm_source' => 'a b<c', 'referrer' => 'not a host',
	'trigger' => 'delay', 'trigger_value' => '601', 'recur' => 'daily', 'recur_time' => '25:00', 'image' => '78', 'overlay' => '95', 'font' => 'comic', 'visitor' => 'robot' ), $lookup, array( 'EUR' ), 'tiers' );
$want = array( 'countries', 'fs_zones', 'fs_currencies', 'coupon_id', 'tiers', 'cart_subtotal_max', 'cart_products', 'utm_source', 'referrer', 'trigger', 'recur', 'image', 'overlay', 'font', 'visitor' );
check( 'validate(invalid): every bad field reported', ! array_diff( $want, array_keys( $e ) ), implode( ',', array_diff( $want, array_keys( $e ) ) ) );
check( 'validate(invalid): unsafe values never kept', $o['image'] === 0 && $o['font'] === '' && $o['visitor'] === 'all' && $o['coupon_id'] === 0 && $o['fs_currencies'] === array() );
list( , $e ) = $f->validate( array( 'image' => '../x.jpg', 'countries' => array( 'LVA', '<b>' ) ), null, array() );
check( 'validate(): image must be an ID, countries ISO-2 only (even without lookup)', isset( $e['image'], $e['countries'] ) );
list( , $e ) = $f->validate( array( 'tiers' => array( '50' ) ), null, array(), 'tiers' );
check( 'tiers: fewer than 2 rejected; other types drop tiers', isset( $e['tiers'] ) && $f->validate( array( 'tiers' => array( '50', '60' ) ), null, array(), 'standard' )[0]['tiers'] === array() );
check( 'options(): broken JSON → defaults; stored tiers kept', $f->options( '{x' ) == $f->defaults() && $f->options( '{"tiers":["5.0000","9.0000"]}' )['tiers'] === array( '5.0000', '9.0000' ) );
$o = $f->validate( array( 'countries_mode' => 'exclude', 'countries' => array( 'LV' ), 'fs_zones' => array( array( 'country' => 'LV', 'state' => '', 'amount' => '60' ), array( 'country' => 'LV', 'state' => 'RIX', 'amount' => '30' ) ), 'fs_currencies' => array( 'EUR' => '80' ) ), null, array( 'EUR' ) )[0];
check( 'country: exclude list; unknown country passes exclude, fails include', ! $f->country_matches( $o, 'LV' ) && $f->country_matches( $o, 'LT' ) && $f->country_matches( $o, '' ) && ! $f->country_matches( array( 'countries_mode' => 'include', 'countries' => array( 'LV' ) ), '' ) );
check( 'threshold: state > country > currency > default', $f->threshold( $o, 100, 'LV', 'RIX', 'EUR' ) === array( 30.0, '' ) && $f->threshold( $o, 100, 'LV', 'X', 'EUR' ) === array( 60.0, '' ) && $f->threshold( $o, 100, 'LT', '', 'EUR' ) === array( 80.0, 'EUR' ) && $f->threshold( $o, 100, 'LT', '', 'USD' ) === array( 100.0, '' ) );
list( , $e ) = $f->validate( array( 'fs_zones' => array( array( 'country' => 'LV', 'state' => '', 'amount' => '5' ), array( 'country' => 'lv', 'state' => '', 'amount' => '6' ) ) ), null, array() );
check( 'fs_zones: duplicate country / state refused', isset( $e['fs_zones'] ) && 'error_fs_zones_duplicate' === $e['fs_zones'] );
$t = $f->tier_state( array( '50', '100', '150' ), 120 );
check( 'tierState: 2 reached, next = 3rd, 80 %, markers', $t['done'] === 2 && $t['next'] === 2 && $t['pct'] === 80 && $t['marks'] === array( 33.33, 66.67, 100.0 ), json_encode( $t ) );
check( 'tierState: all reached → next -1', $f->tier_state( array( '50', '100' ), 100 )['next'] === -1 );
$c = array_merge( $f->defaults(), array( 'cart_subtotal_min' => '10.0000', 'cart_qty_max' => '3', 'cart_categories' => array( 20 ) ) );
check( 'cartMatches: subtotal, quantity and category rules', $f->has_cart_rules( $c ) && $f->cart_matches( $c, 15, 2, array( 1 ), array( 20 ) ) && ! $f->cart_matches( $c, 5, 2, array( 1 ), array( 20 ) ) && ! $f->cart_matches( $c, 15, 4, array( 1 ), array( 20 ) ) && ! $f->cart_matches( $c, 15, 2, array( 1 ), array( 21 ) ) && ! $f->has_cart_rules( $f->defaults() ) );
$r     = array_merge( $f->defaults(), array( 'recur' => 'daily', 'recur_time' => '14:00', 'recur_skip' => '67' ) );
$fri15 = $at( '2026-11-13 15:00:00' );
check( 'nextCutoff daily: same day before, next working day after (skips Sat+Sun)', $f->next_cutoff( $r, $mon10, $riga ) === $at( '2026-11-09 14:00:00' ) && $f->next_cutoff( $r, $fri15, $riga ) === $at( '2026-11-16 14:00:00' ) );
$w = array_merge( $f->defaults(), array( 'recur' => 'weekly', 'recur_time' => '09:30', 'recur_day' => 5 ) );
check( 'nextCutoff weekly (Friday 09:30) + attributes', $f->next_cutoff( $w, $mon10, $riga ) === $at( '2026-11-13 09:30:00' ) && $f->recur_attr( $w ) === 'w|09:30|5' && $f->recur_attr( $r ) === 'd|14:00|67' );
check( 'nextCutoff across the DST change (site time)', ( new DateTimeImmutable( '@' . $f->next_cutoff( $r, $at( '2026-10-23 15:00:00' ), $riga ) ) )->setTimezone( $riga )->format( 'H:i' ) === '14:00' );
$css = $f->css( 'a{color:red}</style><script>alert(1)</script>@import url(x.css);b{background:url(javascript:alert(1))}c{width:expression(alert(1))}d{background:url("/img/x.png")}\\3c/style\\3e{}e{-moz-binding:url(x)}' );
check( 'css(): no tags, @import, javascript:, expression, escapes, bindings; safe url kept', strpos( $css, '<' ) === false && stripos( $css, 'script>' ) === false && stripos( $css, '@import' ) === false && stripos( $css, 'javascript' ) === false && stripos( $css, 'expression' ) === false && strpos( $css, '\\' ) === false && stripos( $css, 'binding' ) === false && strpos( $css, 'url("/img/x.png")' ) !== false && strpos( $css, 'a{color:red}' ) === 0, $css );
$css2 = $f->css( 'a{background:image-set("https://x.example/a.png" 1x)}b{background:-webkit-image-set("https://x.example/b.png" 1x)}c{background-image:url(/ok.png)}' );
check( 'css(): image-set() / src() string forms neutralised, url() rules unchanged', stripos( $css2, 'image-set(' ) === false && strpos( $css2, 'background-image:url("/ok.png")' ) !== false, $css2 );
$T   = 1794000000;
$tok = $f->token( 'secret', 'sess1', $T );
check( 'token: valid for its context after 2 s; not too early, not for another context or key, not after 2 days, not forged', $f->token_valid( $tok, 'secret', 'sess1', $T + 5 ) && ! $f->token_valid( $tok, 'secret', 'sess1', $T + 1 ) && ! $f->token_valid( $tok, 'secret', 'sess2', $T + 5 ) && ! $f->token_valid( $tok, 'other', 'sess1', $T + 5 ) && ! $f->token_valid( $tok, 'secret', 'sess1', $T + 172801 ) && ! $f->token_valid( $T . '.' . str_repeat( '0', 32 ), 'secret', 'sess1', $T + 5 ) && ! $f->token_valid( $tok, '', 'sess1', $T + 5 ) );
check( 'csvCell: formula injection neutralised, quotes doubled', $f->csv_cell( '=HYPERLINK("x")' ) === '"\'=HYPERLINK(""x"")"' && $f->csv_cell( '+1' ) === '"\'+1"' && $f->csv_cell( 'a@b.lv' ) === '"a@b.lv"' );

/* ---------------- E-mail injection guard ---------------- */
check( 'email(): valid, too long, invalid', $f->email( ' a@b.lv ' ) === 'a@b.lv' && $f->email( str_repeat( 'a', 90 ) . '@b.lv' ) === '' && $f->email( 'a@b' ) === '' && $f->email( '<a@b.lv>' ) === '' );
$inj = array( "\"a\\\nX-Injected:1\"@a.co", "\"a\\\rX:1\"@a.co", "a@a.co\r\nBcc: x@evil.co", "a@a.co\nRCPT TO:<x@evil.co>", "\"x y\"@a.co", "\"a\\\\b\"@a.co", "a\x00@a.co", "a\x7f@a.co", "a@a.co\t1" );
$all = true;
foreach ( $inj as $x ) {
	if ( $f->email( $x ) !== '' ) {
		$all = false;
		echo '   accepted: ' . json_encode( $x ) . "\n";
	}
}
check( 'email(): injection strings refused (quoted local part with escaped CR / LF, raw CR / LF, quotes, backslash, NUL, DEL)', $all && filter_var( "\"a\\\nX-Injected:1\"@a.co", FILTER_VALIDATE_EMAIL ) !== false );
check( 'email(): ordinary addresses still valid', $f->email( 'first.last+tag@sub.example.co.uk' ) === 'first.last+tag@sub.example.co.uk' && $f->email( "o'brien@example.ie" ) === "o'brien@example.ie" );
check( 'headerText(): CR / LF / control characters → space', $f->header_text( "Shop\r\nBcc: x@evil.co" ) === 'Shop Bcc: x@evil.co' && $f->header_text( "a\x00b\tc" ) === 'a b c' && $f->header_text( 'Veikals Ā' ) === 'Veikals Ā' );

/* ---------------- CloudFlare ---------------- */
check( 'ipInCidr(): IPv4 / IPv6, prefix edges, other family never matches', $f->ip_in_cidr( '104.16.0.1', '104.16.0.0/13' ) && $f->ip_in_cidr( '104.23.255.255', '104.16.0.0/13' ) && ! $f->ip_in_cidr( '104.24.0.0', '104.16.0.0/13' ) && $f->ip_in_cidr( '2a06:98c7:ffff::1', '2a06:98c0::/29' ) && ! $f->ip_in_cidr( '2a06:98c8::1', '2a06:98c0::/29' ) && ! $f->ip_in_cidr( '104.16.0.1', '2400:cb00::/32' ) && ! $f->ip_in_cidr( 'x', '104.16.0.0/13' ) && ! $f->ip_in_cidr( '104.16.0.1', '104.16.0.0/33' ) && $f->ip_in_cidr( '1.2.3.4', '0.0.0.0/0' ) );
check( 'isCloudflare(): published ranges (v4, v6, v4-mapped) only', $f->is_cloudflare( '173.245.48.10' ) && $f->is_cloudflare( '131.0.75.255' ) && $f->is_cloudflare( '2606:4700:10::6816:1' ) && $f->is_cloudflare( '::ffff:162.158.1.1' ) && ! $f->is_cloudflare( '8.8.8.8' ) && ! $f->is_cloudflare( '127.0.0.1' ) && ! $f->is_cloudflare( '2001:db8::1' ) && ! $f->is_cloudflare( '' ) && count( Features::CF_IPV4 ) === 15 && count( Features::CF_IPV6 ) === 7 );
$cf   = array( 'REMOTE_ADDR' => '162.158.10.20', 'HTTP_CF_CONNECTING_IP' => '203.0.113.7', 'HTTP_CF_IPCOUNTRY' => 'lv' );
$fake = array( 'REMOTE_ADDR' => '198.51.100.9', 'HTTP_CF_CONNECTING_IP' => '203.0.113.7', 'HTTP_CF_IPCOUNTRY' => 'LV' );
check( 'clientIp(): CF-Connecting-IP only from a CloudFlare REMOTE_ADDR with the setting on', $f->client_ip( $cf, true ) === '203.0.113.7' && $f->client_ip( $cf, false ) === '162.158.10.20' && $f->client_ip( $fake, true ) === '198.51.100.9' && $f->client_ip( array( 'REMOTE_ADDR' => '162.158.10.20', 'HTTP_CF_CONNECTING_IP' => 'junk' ), true ) === '162.158.10.20' && $f->client_ip( array(), true ) === '' );
check( 'cfCountry(): CF-IPCountry only from CloudFlare with the setting on; XX / T1 / junk ignored', $f->cf_country( $cf, true ) === 'LV' && $f->cf_country( $cf, false ) === '' && $f->cf_country( $fake, true ) === '' && $f->cf_country( array( 'REMOTE_ADDR' => '162.158.10.20', 'HTTP_CF_IPCOUNTRY' => 'XX' ), true ) === '' && $f->cf_country( array( 'REMOTE_ADDR' => '162.158.10.20', 'HTTP_CF_IPCOUNTRY' => 'T1' ), true ) === '' && $f->cf_country( array( 'REMOTE_ADDR' => '162.158.10.20', 'HTTP_CF_IPCOUNTRY' => 'LVA' ), true ) === '' );

/* ---------------- Templates, extra texts ---------------- */
$tpl      = $f->templates();
$complete = true;
foreach ( $tpl as $x ) {
	foreach ( array( 'en', 'lv', 'ru' ) as $l ) {
		if ( empty( $x['name'][ $l ] ) || empty( $x['text'][ $l ] ) ) { $complete = false; }
	}
	if ( ! in_array( $x['icon'], AnnouncementBarPro\Icons::NAMES, true ) ) { $complete = false; }
}
check( 'templates: 10–15, each with name + text in EN / LV / RU and a built-in icon', count( $tpl ) >= 10 && count( $tpl ) <= 15 && $complete, (string) count( $tpl ) );
$ex   = $f->extra_of( '{"tier_label":["<b>A</b>","B"],"email_button":"Jo&amp;in"}' );
$errs = array();
$cl   = $f->extra( array( 'tier_label' => array( 'X', '', 'Z' ), 'tier_text' => array( str_repeat( 'y', 400 ) ), 'email_consent' => 'ok' ), array( 0, 2 ), $errs );
check( 'extraOf / extra: plain text, tier rows by index, length limit reported', $ex['tier_label'] === array( 'A', 'B' ) && $ex['email_button'] === 'Jo&in' && $cl['tier_label'] === array( 'X', 'Z' ) && isset( $errs['tier_text'] ) && $cl['email_consent'] === 'ok' );

/* ---------------- Icons ---------------- */
$paths = AnnouncementBarPro\Icons::paths();
check( 'icons: every name has a path, nothing but path data', count( $paths ) === count( AnnouncementBarPro\Icons::NAMES ) && ! preg_grep( '/[^MmLlHhVvCcSsQqTtAaZz0-9 .,\-]/', $paths ) );

/* ---------------- Licence (local, offline) ---------------- */
$body = 'ABCDEFGHJKLMNPQ';
$key  = 'ABP1-' . implode( '-', str_split( $body . Local_Licence_Provider::checksum( $body ), 4 ) );
check( 'licence: well-formed key with checksum accepted, also typed in lower case with spaces', Local_Licence_Provider::well_formed( $key ) && Local_Licence_Provider::well_formed( strtolower( str_replace( '-', ' ', $key ) ) ) );
$bad = substr( $key, 0, -1 ) . ( 'Z' === substr( $key, -1 ) ? 'Y' : 'Z' );
check( 'licence: wrong checksum, wrong prefix, letters O / I / 0 / 1 refused', ! Local_Licence_Provider::well_formed( $bad ) && ! Local_Licence_Provider::well_formed( 'ABP2' . substr( $key, 4 ) ) && ! Local_Licence_Provider::well_formed( 'ABP1-OOOO-IIII-0000-1111' ) && ! Local_Licence_Provider::well_formed( '' ) );
check( 'licence: not Pro without a stored key', ! Licence::is_pro() );
$res = Licence::activate( strtolower( $key ) );
check( 'licence: activation stores the normalised key → Pro', 'valid' === $res['status'] && Licence::get()['key'] === $key && Licence::is_pro() && false === strpos( Licence::masked(), substr( $key, 5, 4 ) ) );
Licence::activate( $bad );
check( 'licence: an invalid key does not replace a valid one', Licence::get()['key'] === $key && Licence::is_pro() );
Licence::deactivate();
check( 'licence: removed → free', ! Licence::is_pro() && Licence::get()['key'] === '' );
$GLOBALS['abpro_opts']['abpro_licence'] = array( 'key' => $bad, 'status' => 'valid' );
Licence::reset();
check( 'licence: a tampered stored key is not Pro', ! Licence::is_pro() );

/* ---------------- Free plugin: options it does not handle ---------------- */
$plain   = array( 'placement' => '', 'type' => 'standard', 'page_mode' => 'all', 'roles' => '[]', 'categories' => '[]', 'products' => '[]' );
$neutral = ( new Features() )->defaults();
check( 'options: neutral premium options do not narrow (also the stored defaults of the single build)', ! Options::narrows( $plain, $neutral ) && ! Options::narrows( $plain, array() ) && ! Options::narrows( $plain, array( 'fs_wc' => 1, 'overlay' => '55', 'recur_day' => 1, 'countries' => array( 'LV' ) ) ) );
check( 'options: look options never narrow (image, font, coupon, e-mail, recurring countdown)', ! Options::narrows( $plain, array_merge( $neutral, array( 'image' => 5, 'font' => 'serif', 'coupon_id' => 7, 'email' => 1, 'recur' => 'daily', 'recur_time' => '14:00' ) ) ) );
$narrow = array( array( 'countries_mode' => 'exclude', 'countries' => array( 'LV' ) ), array( 'cart_qty_min' => '1' ), array( 'utm_source' => 'news' ), array( 'referrer' => 'example.com' ), array( 'visitor' => 'new' ), array( 'trigger' => 'delay', 'trigger_value' => 2 ), array( 'cart_products' => array( 3 ) ), array( 'some_future_rule' => 'x' ) );
$all    = true;
foreach ( $narrow as $o ) {
	$all = $all && Options::narrows( $plain, array_merge( $neutral, $o ) );
}
check( 'options: every audience rule narrows (countries, cart, campaign, referrer, visitor, trigger, unknown keys)', $all );
check( 'options: columns that narrow (box placement, tiers, page rules, roles, products); top / bottom do not', Options::narrows( array_merge( $plain, array( 'placement' => 'cart' ) ), array() ) && Options::narrows( array_merge( $plain, array( 'type' => 'tiers' ) ), array() ) && Options::narrows( array_merge( $plain, array( 'page_mode' => 'home' ) ), array() ) && Options::narrows( array_merge( $plain, array( 'roles' => '["customer"]' ) ), array() ) && Options::narrows( array_merge( $plain, array( 'products' => '[4]' ) ), array() ) && ! Options::narrows( array_merge( $plain, array( 'placement' => 'bottom' ) ), array() ) );
check( 'options: decode keeps unknown keys; encode; fs_wc', Options::decode( '{"fs_wc":1,"x":2}' ) === array( 'fs_wc' => 1, 'x' => 2 ) && Options::decode( 'nope' ) === array() && 1 === Options::fs_wc( array( 'fs_wc' => '1' ) ) && '{}' === Options::encode( array() ) );

/* ---------------- Cloudflare Turnstile (mocked siteverify) ---------------- */
$sent = null;
$mock = static function ( $answer ) use ( &$sent ) {
	return static function ( $url, $fields ) use ( $answer, &$sent ) {
		$sent = array( $url, $fields );
		if ( $answer instanceof \Exception ) {
			throw $answer;
		}
		return $answer;
	};
};
$sec  = '1x0000000000000000000000000000000AA';
check( 'turnstile: Cloudflare dummy keys have a valid format', '1x00000000000000000000AA' === Turnstile::site_key( '1x00000000000000000000AA' ) && $sec === Turnstile::secret_key( $sec ) && '2x0000000000000000000000000000000AA' === Turnstile::secret_key( '2x0000000000000000000000000000000AA' ) );
check( 'turnstile: malformed keys refused', '' === Turnstile::site_key( 'no key!' ) && '' === Turnstile::site_key( 'short' ) && '' === Turnstile::secret_key( "1x00000000\n00000" ) && '' === Turnstile::secret_key( array() ) );
check( 'turnstile: on only with both keys', Turnstile::enabled( array( 'turnstile_site_key' => '1x00000000000000000000AA', 'turnstile_secret' => $sec ) ) && ! Turnstile::enabled( array( 'turnstile_site_key' => '1x00000000000000000000AA', 'turnstile_secret' => '' ) ) && ! Turnstile::enabled( array() ) );
check( 'turnstile: success → ok; secret, token and remoteip sent to siteverify', Turnstile::OK === Turnstile::verify( $sec, 'XXXX.DUMMY.TOKEN.XXXX', '203.0.113.5', $mock( '{"success":true,"error-codes":[],"hostname":"example.com"}' ) ) && Turnstile::VERIFY_URL === $sent[0] && $sec === $sent[1]['secret'] && 'XXXX.DUMMY.TOKEN.XXXX' === $sent[1]['response'] && '203.0.113.5' === $sent[1]['remoteip'] );
check( 'turnstile: our action accepted', Turnstile::OK === Turnstile::verify( $sec, 'tok', '', $mock( '{"success":true,"action":"abp-signup"}' ) ) );
check( 'turnstile: refused / wrong action / "success":"true" / missing success → failed', Turnstile::FAILED === Turnstile::verify( $sec, 'tok', '', $mock( '{"success":false,"error-codes":["invalid-input-response"]}' ) ) && Turnstile::FAILED === Turnstile::verify( $sec, 'tok', '', $mock( '{"success":true,"action":"login"}' ) ) && Turnstile::FAILED === Turnstile::verify( $sec, 'tok', '', $mock( '{"success":"true"}' ) ) && Turnstile::FAILED === Turnstile::verify( $sec, 'tok', '', $mock( '{"error-codes":[]}' ) ) );
check( 'turnstile: network error, exception, empty body, bad JSON → failed (fail closed)', Turnstile::FAILED === Turnstile::verify( $sec, 'tok', '', $mock( false ) ) && Turnstile::FAILED === Turnstile::verify( $sec, 'tok', '', $mock( new \Exception( 'timeout' ) ) ) && Turnstile::FAILED === Turnstile::verify( $sec, 'tok', '', $mock( '' ) ) && Turnstile::FAILED === Turnstile::verify( $sec, 'tok', '', $mock( '<html>' ) ) );
$sent = null;
check( 'turnstile: no token → missing, Cloudflare not asked', Turnstile::MISSING === Turnstile::verify( $sec, '', '1.2.3.4', $mock( '{"success":true}' ) ) && Turnstile::MISSING === Turnstile::verify( $sec, null, '1.2.3.4', $mock( '{"success":true}' ) ) && null === $sent );
check( 'turnstile: oversized / control-character token, bad secret → failed; a bad IP is left out', Turnstile::FAILED === Turnstile::verify( $sec, str_repeat( 'a', 3000 ), '', $mock( '{"success":true}' ) ) && Turnstile::FAILED === Turnstile::verify( $sec, "a\nb", '', $mock( '{"success":true}' ) ) && Turnstile::FAILED === Turnstile::verify( 'bad secret', 'tok', '', $mock( '{"success":true}' ) ) && Turnstile::OK === Turnstile::verify( $sec, 'tok', 'not-an-ip', $mock( '{"success":true}' ) ) && ! isset( $sent[1]['remoteip'] ) );

/* ---------------- Confirmation tokens (re-subscribe rule) ---------------- */
$now = 1793000000;
$tok = Subscribers::new_token( $now );
check( 'tokens: 64 hex digits, creation time in the first 8', 1 === preg_match( '/^[0-9a-f]{64}$/', $tok ) && $now === Subscribers::token_time( $tok ) && Subscribers::new_token( $now ) !== $tok );
check( 'tokens: fresh for 7 days, not older, not from the future (beyond 5 minutes)', Subscribers::token_fresh( $tok, $now ) && Subscribers::token_fresh( $tok, $now + 7 * 86400 ) && ! Subscribers::token_fresh( $tok, $now + 7 * 86400 + 1 ) && Subscribers::token_fresh( Subscribers::new_token( $now + 200 ), $now ) && ! Subscribers::token_fresh( Subscribers::new_token( $now + 400 ), $now ) );
check( 'tokens: anything else is not a token', 0 === Subscribers::token_time( 'xyz' ) && ! Subscribers::token_fresh( str_repeat( 'g', 64 ), $now ) && ! Subscribers::token_fresh( '', $now ) );
$GLOBALS['abpro_opts']['abpro_secret'] = str_repeat( 'ab', 32 );
$d1 = Subscribers::derived_unsubscribe( $tok );
check( 'tokens: derived unsubscribe token: 64 hex, bound to the token and the site secret', 1 === preg_match( '/^[0-9a-f]{64}$/', $d1 ) && $d1 === Subscribers::derived_unsubscribe( $tok ) && $d1 !== Subscribers::derived_unsubscribe( Subscribers::new_token( $now ) ) && $d1 === hash_hmac( 'sha256', 'unsub|' . $tok, str_repeat( 'ab', 32 ) ) );

/* ---------------- Free plugin: no licence or premium code (wordpress.org guideline 5) ---------------- */
$hits = array();
$it   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( dirname( __DIR__, 2 ) . '/announcement-bar-pro', FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $file ) {
	if ( ! preg_match( '/\.(php|js|css)$/', $file->getFilename() ) ) {
		continue;
	}
	$src = preg_replace( '/Announcement Bar Pro|announcement-bar-pro|AnnouncementBarPro|abpro|GPL-2\.0-or-later|License URI:\s*\S+|License:\s*\S+/', '', file_get_contents( $file->getPathname() ) );
	if ( preg_match( '/licen[cs]e|is_pro\b|abp-pro-badge|abp-locked|premium|upgrade to|Pro features|\(Pro\)|— Pro/i', $src, $m ) ) {
		$hits[] = $file->getFilename() . ': ' . $m[0];
	}
}
check( 'free plugin: no licence check, locked UI or premium upsell in its code', ! $hits, implode( ', ', array_slice( $hits, 0, 5 ) ) );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
