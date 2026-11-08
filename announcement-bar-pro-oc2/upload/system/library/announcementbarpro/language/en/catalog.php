<?php
defined('VERSION') || exit; // not callable directly
$_['abp_text_label'] = 'Announcement';
$_['abp_text_close'] = 'Close';
$_['abp_text_days']  = 'd';
$_['abp_text_copy']      = 'Copy';
$_['abp_text_copied']    = 'Copied!';
$_['abp_text_copy_code'] = 'Copy code %s';
$_['abp_text_prev']      = 'Previous message';
$_['abp_text_next']      = 'Next message';
// 2.4.0
$_['abp_text_show']               = 'Show announcements';
$_['abp_text_pause']              = 'Pause messages';
$_['abp_text_play']               = 'Play messages';
$_['abp_text_privacy']            = 'Privacy policy';
$_['abp_text_email_placeholder']  = 'Your e-mail address';
$_['abp_text_email_button']       = 'Subscribe';
$_['abp_text_email_consent']      = 'I agree to receive news and offers by e-mail. I can unsubscribe at any time.';
$_['abp_text_email_success']      = 'Almost done — please confirm with the link we sent to your inbox.';
$_['abp_text_email_done']         = 'Thank you — you are subscribed!';
$_['abp_text_sending']            = 'Sending…';
$_['abp_error_email']             = 'Please enter a valid e-mail address.';
$_['abp_error_consent']           = 'Please tick the box to agree.';
$_['abp_error_token']             = 'This page is too old — please reload it and try again.';
$_['abp_error_rate']              = 'Too many attempts — please try again later.';
$_['abp_error_failed']            = 'Sorry, the sign-up did not work. Please try again later.';
$_['abp_heading_confirm']         = 'Confirm your subscription';
$_['abp_text_confirm_intro']      = 'Please confirm that %s may send news and offers to %s.';
$_['abp_button_confirm']          = 'Confirm subscription';
$_['abp_text_confirmed']          = 'Thank you — your subscription is confirmed.';
$_['abp_text_invalid_link']       = 'This link is invalid or has expired.';
$_['abp_heading_unsubscribe']     = 'Unsubscribe';
$_['abp_text_unsubscribe_intro']  = 'Stop e-mails from %s to %s?';
$_['abp_button_unsubscribe']      = 'Unsubscribe';
$_['abp_text_unsubscribed']       = 'You have been unsubscribed. You will not receive any more e-mails from us.';
$_['abp_mail_subject']            = 'Please confirm your subscription — %s';
$_['abp_mail_hello']              = 'Hello,';
$_['abp_mail_body']               = 'please confirm that you want to receive news and offers from %s at this address. Open this link and press the button:';
$_['abp_mail_ignore']             = 'If you did not ask for this, simply ignore this e-mail — you will not be subscribed. The link is valid for 7 days.';
$_['abp_mail_unsubscribe']        = 'Unsubscribe at any time:';
$_['abp_text_continue']           = 'Continue shopping';
// 2.4.1 Cloudflare Turnstile (optional anti-bot check of the sign-up form)
$_['abp_text_ts_wait']              = 'This form uses Cloudflare\'s anti-bot check. Please accept cookies to use it.';
$_['abp_error_ts_wait']             = 'The anti-bot check is not ready yet — please wait a moment or reload the page.';
$_['abp_error_ts_missing']          = 'Please wait a moment until the anti-bot check is done, then send again.';
$_['abp_error_ts_failed']           = 'The anti-bot check failed. Please reload the page and try again.';
