<?php
defined('VERSION') || exit; // not callable directly (in the universal package these files also sit, unused, inside OpenCart 4)
// Heading
$_['heading_title']              = 'Announcement Bar Pro';

// Tabs
$_['tab_messages']               = 'Messages';
$_['tab_settings']               = 'Settings';
$_['tab_content']                = 'Content';
$_['tab_schedule']               = 'Schedule';
$_['tab_visibility']             = 'Visibility';

// Text
$_['text_extension']             = 'Extensions';
$_['text_success']               = 'Success: You have modified Announcement Bar Pro!';
$_['text_message_list']          = 'Messages';
$_['text_add_message']           = 'Add message';
$_['text_edit_message']          = 'Edit message';
$_['text_no_messages']           = 'No messages yet. Click "Add New" to create the first one.';
$_['text_module_disabled']       = 'The module is disabled — messages are not shown in the store. Enable it in the Settings tab.';
$_['text_general']               = 'General';
$_['text_appearance']            = 'Appearance';
$_['text_dismiss']               = 'Close button';
$_['text_advanced']              = 'Advanced';
$_['text_mode_rotate']           = 'Rotate (one at a time)';
$_['text_mode_marquee']          = 'Scrolling line (marquee)';
$_['text_mode_stack']            = 'Show all (stacked)';
$_['text_position_top']          = 'Top of page';
$_['text_position_bottom']       = 'Bottom of page (fixed)';
$_['text_state_active']          = 'Active';
$_['text_state_scheduled']       = 'Scheduled';
$_['text_state_expired']         = 'Expired';
$_['text_state_disabled']        = 'Disabled';
$_['text_page_all']              = 'All pages';
$_['text_page_home']             = 'Home page only';
$_['text_page_include']          = 'Only on listed pages';
$_['text_page_exclude']          = 'All pages except listed';

// Column
$_['column_text']                = 'Message';
$_['column_status']              = 'Status';
$_['column_period']              = 'Period';
$_['column_sort_order']          = 'Sort order';
$_['column_action']              = 'Action';

// Entry
$_['entry_status']               = 'Status';
$_['entry_mode']                 = 'Display mode';
$_['entry_interval']             = 'Rotation interval (s)';
$_['entry_speed']                = 'Scroll speed (px/s)';
$_['entry_allow_html']           = 'Allow basic HTML';
$_['entry_position']             = 'Position';
$_['entry_sticky']               = 'Sticky on scroll';
$_['entry_font_size']            = 'Font size (px)';
$_['entry_padding']              = 'Vertical padding (px)';
$_['entry_default_bg_color']     = 'Default background colour';
$_['entry_default_text_color']   = 'Default text colour';
$_['entry_dismissible']          = 'Show close button (×)';
$_['entry_dismiss_days']         = 'Remember closing (days)';
$_['entry_delete_data']          = 'Delete messages on uninstall';
$_['entry_text']                 = 'Text';
$_['entry_link_text']            = 'Button text';
$_['entry_link']                 = 'Link';
$_['entry_link_new_tab']         = 'Open in new tab';
$_['entry_icon']                 = 'Icon';
$_['entry_bg_color']             = 'Background colour';
$_['entry_text_color']           = 'Text colour';
$_['entry_preview']              = 'Preview';
$_['entry_sort_order']           = 'Sort order';
$_['entry_date_start']           = 'Start';
$_['entry_date_end']             = 'End';
$_['entry_countdown']            = 'Show countdown to end';
$_['entry_store']                = 'Stores';
$_['entry_customer_group']       = 'Customer groups';
$_['entry_page_mode']            = 'Pages';
$_['entry_page_routes']          = 'Page routes';

// Help
$_['help_mode']                  = 'How several active messages are shown.';
$_['help_allow_html']            = 'Allows &lt;b&gt;, &lt;strong&gt;, &lt;i&gt;, &lt;em&gt;, &lt;u&gt;, &lt;s&gt;, &lt;br&gt;, &lt;span&gt; and links. All other tags and attributes are removed.';
$_['help_sticky']                = 'Bar stays visible at the top while scrolling.';
$_['help_default_color']         = 'Used as the starting colour for new messages.';
$_['help_dismiss_days']          = '0 = only until the browser tab is closed. The bar reappears when messages change.';
$_['help_delete_data']           = 'If ticked, uninstalling the module permanently deletes all messages. Leave unticked to keep them for reinstall.';
$_['help_text_plain']            = 'Plain text (HTML is removed).';
$_['help_text_html']             = 'Basic HTML allowed: b, strong, i, em, u, s, br, span, a.';
$_['help_countdown_placeholder'] = 'Use {countdown} to place the timer inside the text.';
$_['help_link_text']             = 'Optional. If empty and a link is set, the whole message is clickable.';
$_['help_link']                  = 'Full URL (https://…), a site path (/sale) or index.php?route=…';
$_['help_icon']                  = 'Font Awesome 4 class, e.g. fa-truck, fa-gift, fa-percent.';
$_['help_dates']                 = 'Store time. Leave empty for no limit.';
$_['help_countdown']             = 'Requires an end date. Message hides automatically when time is up.';
$_['help_empty_all']             = 'Nothing ticked = all.';
$_['help_routes']                = 'One OpenCart route per line, e.g. common/home, product/product, product/category, checkout/*';

// Error
$_['error_permission']           = 'Warning: You do not have permission to modify Announcement Bar Pro!';
$_['error_form']                 = 'Warning: Please check the form carefully for errors!';
$_['error_value']                = 'Invalid value!';
$_['error_range']                = 'Must be a whole number from %s to %s!';
$_['error_color']                = 'Colour must be in #RRGGBB format!';
$_['error_text_length']          = 'Must not exceed %s characters!';
$_['error_text_required']        = 'Enter the text in at least one language!';
$_['error_link']                 = 'Invalid link! Use http(s)://…, /path or index.php?route=…';
$_['error_icon']                 = 'Icon must look like fa-truck!';
$_['error_date']                 = 'Date must be YYYY-MM-DD HH:mm!';
$_['error_date_order']           = 'End must be after start!';
$_['error_countdown']            = 'Countdown needs an end date!';
$_['error_routes']               = 'Invalid route list! Use letters, digits, _ and /, optional /* at the end; at most 50 routes.';
$_['error_routes_required']      = 'Enter at least one route!';

// 2.0.2
$_['text_missing_language']      = 'No text in: %s. Visitors using these languages see the store\'s default-language text (if the message has one).';
$_['text_low_contrast']          = 'Low contrast — the text may be hard to read.';
$_['error_not_found']            = 'Warning: The message was not found (it may have been deleted).';
$_['help_lang_fallback']         = 'Languages left empty show the text of the store\'s default language.';

// 2.0.3
$_['entry_end_all_day']         = 'Until the end of that day (23:59:59)';
$_['text_orphan_stores']         = 'This message was limited to stores that no longer exist. Choose where it should appear (leave all unticked only if it should show in every store).';
$_['text_orphan_customer_groups'] = 'This message was limited to customer groups that no longer exist. Choose who should see it.';
$_['error_orphan_stores']        = 'Choose at least one store — the stores this message was limited to were deleted.';
$_['error_orphan_customer_groups'] = 'Choose at least one customer group — the groups this message was limited to were deleted.';
$_['error_nothing_selected']     = 'Select at least one message to delete.';
$_['entry_color_mode']           = 'Colours';
$_['text_color_auto']            = 'Automatic — match the shop theme';
$_['text_color_custom']          = 'Custom — each message uses its own colours';
$_['help_color_mode']            = 'Automatic: the bar takes the main colour of your theme (buttons/menu) and picks readable text. Custom: the colours set on each message.';
$_['text_color_auto_note']       = 'Colours are set to Automatic in Settings: the bar uses the theme colour. The colours below apply when you switch to Custom.';

// 2.2.0
$_['entry_mobile_text']         = 'Text for phones (optional)';
$_['help_mobile_text']          = 'Shown instead of the text above on screens narrower than 768 px. Leave empty to use the same text everywhere. Same rules as the text; {countdown} works here too.';
$_['entry_device']              = 'Devices';
$_['text_device_all']           = 'All devices';
$_['text_device_desktop']       = 'Computers only (768 px and wider)';
$_['text_device_mobile']        = 'Phones only (narrower than 768 px)';
$_['help_device']               = 'Screen width decides, the same 768 px limit as the phone text. When nothing is left for a screen size, the bar is not shown there.';
$_['column_views']              = 'Views';
$_['column_clicks']             = 'Clicks';
$_['column_ctr']                = 'CTR (%)';
$_['help_stats']                = 'Statistics are simple counters: one view and one click per message per visitor session. No personal data and no cookies of its own; visitors with Do Not Track or Global Privacy Control are not counted.';
$_['button_duplicate']          = 'Duplicate';
$_['button_reset_stats']        = 'Reset statistics';
$_['text_duplicated']           = 'Success: message copied. The copy is switched off: edit it and switch it on.';
$_['text_stats_reset']          = 'Success: statistics of the message were reset.';
$_['text_confirm_reset']        = 'Reset the views and clicks of this message to zero?';
