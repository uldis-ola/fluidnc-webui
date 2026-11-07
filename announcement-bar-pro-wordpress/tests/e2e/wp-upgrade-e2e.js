// Upgrade path of the WordPress edition: single-plugin build 1.0.0 with Pro data (licence key inside the plugin) →
// free plugin in the same folder (wordpress.org build) → Premium add-on activated. Same tables and options; the old
// licence key keeps working. Storefront checked with plain HTTP requests (guest), admin pages with WP-CLI.
// Env: WPBASE WPDIR WPCLI MYSQL MYSQLARGS SYNC (command that copies the new plugin sources) SEED (upgrade-seed.php)
const { execSync, execFileSync } = require('child_process');
const fs = require('fs');

const BASE = process.env.WPBASE, DIR = process.env.WPDIR;
const R = [];
const ok = (n, c, x = '') => { R.push((c ? 'PASS ' : 'FAIL ') + n + (!c && x !== '' ? ' — ' + String(x).slice(0, 500) : '')); console.log(R[R.length - 1]); };
const wp = args => { try { return execSync(process.env.WPCLI + ' ' + args, { stdio: ['pipe', 'pipe', 'pipe'] }).toString().replace(/^.*wp_update_plugins.*$/mg, '').trim(); } catch (e) { return 'WPERR ' + (e.stdout || '') + (e.stderr || ''); } };
const q = s => { try { return execFileSync(process.env.MYSQL, [...process.env.MYSQLARGS.split(' '), '-N', '-e', s], { stdio: ['pipe', 'pipe', 'ignore'] }).toString().trim(); } catch (e) { return 'SQLERR ' + e.message.slice(0, 200); } };
const get = async url => { const r = await fetch(BASE + url, { redirect: 'follow' }); return { status: r.status, html: await r.text() }; };
const phpErr = h => /(Fatal error|Warning<\/b>:|Notice<\/b>:|Deprecated<\/b>:|Parse error)/.test(h);
const logFile = DIR + '/wp-content/debug.log';

(async () => {
	/* ---------------- 1. Single-plugin build 1.0.0 with Pro data ---------------- */
	ok('single 1.0.0: activated', /Success/.test(wp('plugin activate announcement-bar-pro')));
	ok('single 1.0.0: it is the old build (licence code inside, no add-on hooks)', fs.existsSync(DIR + '/wp-content/plugins/announcement-bar-pro/includes/class-licence.php') && !fs.existsSync(DIR + '/wp-content/plugins/announcement-bar-pro/includes/class-options.php'));
	const ids = JSON.parse((wp(`eval-file "${process.env.SEED}"`).match(/\{.*\}/) || ['{}'])[0]);
	ok('single 1.0.0: Pro data seeded (licence, settings, 5 messages, 2 subscribers)', Object.keys(ids).length === 5 && q('select count(*) from wp_abpro_subscribers') === '2', JSON.stringify(ids));
	let h = (await get('/')).html;
	ok('single 1.0.0: Pro storefront (bottom bar with the code, pause button)', /id="abp-bar-bottom"/.test(h) && /SAVE5/.test(h) && /abp-has-pause/.test(h));
	const before = {
		messages: q("select md5(group_concat(concat_ws('|', message_id, status, priority, placement, coupon, style, page_mode, page_rules, roles, options) order by message_id)) from wp_abpro_messages"),
		texts: q("select md5(group_concat(concat_ws('|', message_id, locale, text, extra) order by message_id)) from wp_abpro_message_texts"),
		subs: q("select md5(group_concat(concat_ws('|', email, status, unsubscribe, consent) order by email)) from wp_abpro_subscribers"),
		settings: wp('option get abpro_settings --format=json'),
		licence: wp('option get abpro_licence --format=json'),
		secret: wp('option get abpro_secret'),
	};

	/* ---------------- 2. Update to the free plugin (same folder) ---------------- */
	execSync(process.env.SYNC, { stdio: 'ignore' });
	ok('free: the folder now holds the free plugin (add-on hooks, no licence code)', fs.existsSync(DIR + '/wp-content/plugins/announcement-bar-pro/includes/class-options.php') && !fs.existsSync(DIR + '/wp-content/plugins/announcement-bar-pro/includes/class-licence.php'));
	ok('free: still active after the update', /announcement-bar-pro\s+active/.test(wp('plugin list --fields=name,status')));
	h = (await get('/')).html;
	ok('free: storefront without PHP errors', !phpErr(h));
	ok('free: the free message is shown', /UPG free message/.test(h));
	ok('free: Pro messages that only change the look show in the main bar without their extras', /UPG bottom coupon/.test(h) && !/id="abp-bar-bottom"/.test(h) && !/SAVE5|data-style="gradient"|abp-has-pause|data-track=/.test(h));
	ok('free: Pro messages that narrow the audience stay hidden (page rule, country)', !/UPG sale page only|UPG not for Latvia/.test(h));
	ok('free: no sign-up form without the add-on', !/class="abp-form"/.test(h));
	const sale = (await get('/sale/')).html;
	ok('free: page-rule message hidden on its page too (the free plugin cannot check page rules)', !/UPG sale page only/.test(sale));
	const listHtml = wp(`eval "require_once ABPRO_DIR . 'includes/class-admin.php'; wp_set_current_user(1); set_current_screen('toplevel_page_announcement-bar-pro'); ob_start(); (new AnnouncementBarPro\\Admin())->page_messages(); echo ob_get_clean();"`);
	ok('free: admin list renders all 5 messages, the hidden ones marked "Not shown"', !phpErr(listHtml) && (listHtml.match(/abp-msg-title/g) || []).length === 5 && (listHtml.match(/Not shown/g) || []).length === 2, listHtml.slice(0, 300));
	ok('free: tables and options unchanged by the update', q("select md5(group_concat(concat_ws('|', message_id, status, priority, placement, coupon, style, page_mode, page_rules, roles, options) order by message_id)) from wp_abpro_messages") === before.messages && q("select md5(group_concat(concat_ws('|', message_id, locale, text, extra) order by message_id)) from wp_abpro_message_texts") === before.texts && q("select md5(group_concat(concat_ws('|', email, status, unsubscribe, consent) order by email)) from wp_abpro_subscribers") === before.subs && wp('option get abpro_settings --format=json') === before.settings && wp('option get abpro_licence --format=json') === before.licence && wp('option get abpro_secret') === before.secret);

	/* ---------------- 3. Premium add-on activated: the old licence key keeps working ---------------- */
	ok('add-on: activated', /Success/.test(wp('plugin activate announcement-bar-pro-premium')));
	ok('add-on: the licence key of the single build is valid (same option)', wp('eval "echo AnnouncementBarPro\\Premium\\Licence::is_pro() ? 1 : 0;"') === '1');
	h = (await get('/')).html;
	ok('add-on: storefront without PHP errors', !phpErr(h));
	ok('add-on: bottom bar with the code, gradient style, pause button and statistics are back', /id="abp-bar-bottom"/.test(h) && /<span class="abp-code">SAVE5<\/span>/.test(h) && /data-style="gradient"/.test(h) && /abp-has-pause/.test(h) && /data-track=/.test(h));
	ok('add-on: Pro settings in force again (sticky, custom CSS, GA4)', /abp-sticky/.test(h) && /letter-spacing:\.1em/.test(h) && /data-ga="1"/.test(h));
	ok('add-on: country rule checked again (LV store: hidden), page rule checked again (/sale/ only)', !/UPG not for Latvia/.test(h) && !/UPG sale page only/.test(h) && /UPG sale page only/.test((await get('/sale/')).html));
	ok('add-on: sign-up form back, with the stored consent text', /class="abp-form"/.test(h) && /UPG consent text/.test(h));
	ok('add-on: the subscriber list is there (1 subscribed, 1 unsubscribed)', wp('eval "echo json_encode(AnnouncementBarPro\\Premium\\Pro_DB::subscriber_counts());"') === '[0,1,1]');
	ok('add-on: nothing in the database changed by the activation', q("select md5(group_concat(concat_ws('|', email, status, unsubscribe, consent) order by email)) from wp_abpro_subscribers") === before.subs && q("select md5(group_concat(concat_ws('|', message_id, status, priority, placement, coupon, style, page_mode, page_rules, roles, options) order by message_id)) from wp_abpro_messages") === before.messages);
	const settingsHtml = wp(`eval "require_once ABPRO_DIR . 'includes/class-admin.php'; wp_set_current_user(1); set_current_screen('announcement-bar_page_announcement-bar-pro-settings'); ob_start(); (new AnnouncementBarPro\\Admin_Settings_Page())->render(); echo ob_get_clean();"`);
	ok('add-on: settings screen shows the stored Pro values and the active licence', !phpErr(settingsHtml) && /Premium active/.test(settingsHtml) && /name="pause_button" value="1" checked/.test(settingsHtml), settingsHtml.slice(0, 300));

	// WP-CLI's own update check cannot reach WordPress.org here (WP_HTTP_BLOCK_EXTERNAL): not the plugin.
	const log = fs.readFileSync(logFile, 'utf8').split(/\r?\n/).filter(l => l.trim() !== '' && !/wp_update_(plugins|themes)\(\)/.test(l));
	ok('debug.log: no PHP notices / warnings / errors', log.length === 0, log.slice(0, 5).join('\n'));

	const fails = R.filter(r => r.startsWith('FAIL'));
	console.log(`\nupgrade: ${R.length - fails.length} passed, ${fails.length} failed`);
	process.exit(fails.length ? 1 : 0);
})().catch(e => { console.error('CRASH', e); process.exit(2); });
