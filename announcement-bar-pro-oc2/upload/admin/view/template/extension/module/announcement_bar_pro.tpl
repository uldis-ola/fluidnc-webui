<?php defined('VERSION') || exit; /* Generated from the OpenCart 3 Twig template by tools/twig2tpl.py — edit the Twig source, then rebuild. */ ?>
<?php echo (isset($header) ? $header : null); ?><?php echo (isset($column_left) ? $column_left : null); ?>

<div id="content" class="abp-admin">
  <link rel="stylesheet" href="<?php echo AbpOc2::e((isset($admin_css) ? $admin_css : null)); ?>" />
  <div class="page-header">
    <div class="container-fluid">
      <div class="pull-right">
        <button type="submit" form="form-settings" data-toggle="tooltip" title="<?php echo AbpOc2::e((isset($button_save) ? $button_save : null)); ?>" class="btn btn-primary"><i class="fa fa-save"></i></button>
        <a href="<?php echo (isset($cancel) ? $cancel : null); ?>" data-toggle="tooltip" title="<?php echo AbpOc2::e((isset($button_cancel) ? $button_cancel : null)); ?>" class="btn btn-default"><i class="fa fa-reply"></i></a>
      </div>
      <h1><?php echo (isset($heading_title) ? $heading_title : null); ?> <small>v<?php echo AbpOc2::e((isset($version) ? $version : null)); ?></small></h1>
      <ul class="breadcrumb">
        <?php $_s1 = (array)(isset($breadcrumbs) ? $breadcrumbs : null); $_n1 = count($_s1); $_i1 = 0; $_o1 = isset($loop) ? $loop : null; foreach ($_s1 as $breadcrumb) { $loop = array('index0' => $_i1, 'index' => $_i1 + 1, 'first' => $_i1 === 0, 'last' => $_i1 === $_n1 - 1); ?>
        <li><a href="<?php echo (isset($breadcrumb['href']) ? $breadcrumb['href'] : null); ?>"><?php echo (isset($breadcrumb['text']) ? $breadcrumb['text'] : null); ?></a></li>
        <?php $_i1++; } $loop = $_o1; ?>
      </ul>
    </div>
  </div>
  <div class="container-fluid">
    <div id="abp-alerts">
    <?php if (!empty($error_warning)) { ?>
    <div class="alert alert-danger alert-dismissible"><i class="fa fa-exclamation-circle"></i> <?php echo AbpOc2::e((isset($error_warning) ? $error_warning : null)); ?>

      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php } ?>
    <?php if (!empty($error_session)) { ?>
    <div class="alert alert-danger alert-dismissible"><i class="fa fa-exclamation-circle"></i> <?php echo AbpOc2::e((isset($error_session) ? $error_session : null)); ?>

      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php } ?>
    <?php if (!empty($success)) { ?>
    <div class="alert alert-success alert-dismissible"><i class="fa fa-check-circle"></i> <?php echo AbpOc2::e((isset($success) ? $success : null)); ?>

      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php } ?>
    </div>

    <ul class="nav nav-tabs">
      <li<?php if (((isset($active_tab) ? $active_tab : null) == 'messages')) { ?> class="active"<?php } ?>><a href="#tab-messages" data-toggle="tab"><i class="fa fa-bullhorn"></i> <?php echo (isset($tab_messages) ? $tab_messages : null); ?> <span class="badge"><?php echo AbpOc2::length((isset($messages) ? $messages : null)); ?></span></a></li>
      <li<?php if (((isset($active_tab) ? $active_tab : null) == 'settings')) { ?> class="active"<?php } ?>><a href="#tab-settings" data-toggle="tab"><i class="fa fa-cog"></i> <?php echo (isset($tab_settings) ? $tab_settings : null); ?></a></li>
    </ul>

    <div class="tab-content">
            <div class="tab-pane<?php if (((isset($active_tab) ? $active_tab : null) == 'messages')) { ?> active<?php } ?>" id="tab-messages">
        <div class="panel panel-default abp-card">
          <div class="panel-heading clearfix">
            <div class="pull-left abp-card-title" style="padding-top:5px;"><span class="abp-ico-badge"><i class="fa fa-list"></i></span> <?php echo (isset($text_message_list) ? $text_message_list : null); ?></div>
            <div class="pull-right abp-toolbar">
              <a href="<?php echo (isset($export) ? $export : null); ?>" data-toggle="tooltip" title="<?php echo AbpOc2::e((isset($help_export) ? $help_export : null)); ?>" class="btn btn-default btn-sm"><i class="fa fa-download"></i> <?php echo (isset($button_export) ? $button_export : null); ?></a>
              <button type="button" data-abp-import="<?php echo (isset($import) ? $import : null); ?>" data-reload="<?php echo (isset($reload) ? $reload : null); ?>" data-toggle="tooltip" title="<?php echo AbpOc2::e((isset($help_import) ? $help_import : null)); ?>" class="btn btn-default btn-sm"><i class="fa fa-upload"></i> <?php echo (isset($button_import) ? $button_import : null); ?></button>
              <a href="<?php echo (isset($add) ? $add : null); ?>" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> <?php echo (isset($button_add) ? $button_add : null); ?></a>
              <button type="button" data-toggle="tooltip" title="<?php echo AbpOc2::e((isset($button_delete) ? $button_delete : null)); ?>" class="btn btn-danger btn-sm" id="abp-delete"><i class="fa fa-trash-o"></i></button>
            </div>
          </div>
          <div class="panel-body">
            <?php if (empty($setting['status'])) { ?>
            <div class="alert alert-warning"><i class="fa fa-info-circle"></i> <?php echo (isset($text_module_disabled) ? $text_module_disabled : null); ?></div>
            <?php } ?>
            <form action="<?php echo (isset($delete) ? $delete : null); ?>" method="post" id="form-delete">
              <div class="table-responsive">
                <table class="table table-hover abp-table">
                  <thead>
                    <tr>
                      <td style="width:1px;" class="text-center"><input type="checkbox" aria-label="<?php echo AbpOc2::e((isset($button_delete) ? $button_delete : null)); ?>" onclick="$('input[name*=\'selected\']').prop('checked', this.checked);" /></td>
                      <td><?php echo (isset($column_text) ? $column_text : null); ?></td>
                      <td class="text-center"><?php echo (isset($column_status) ? $column_status : null); ?></td>
                      <td class="hidden-xs hidden-sm"><?php echo (isset($column_period) ? $column_period : null); ?></td>
                      <td class="text-right"><span data-toggle="tooltip" title="<?php echo AbpOc2::e((isset($help_stats) ? $help_stats : null)); ?>"><?php echo (isset($column_views) ? $column_views : null); ?></span></td>
                      <td class="text-right"><?php echo (isset($column_clicks) ? $column_clicks : null); ?></td>
                      <td class="text-right hidden-xs"><?php echo (isset($column_copies) ? $column_copies : null); ?></td>
                      <td class="text-right"><?php echo (isset($column_ctr) ? $column_ctr : null); ?></td>
                      <td class="text-right" style="white-space:nowrap;"><?php echo (isset($column_action) ? $column_action : null); ?></td>
                    </tr>
                  </thead>
                  <tbody>
                    <?php $_s2 = (array)(isset($messages) ? $messages : null); $_n2 = count($_s2); $_i2 = 0; $_o2 = isset($loop) ? $loop : null; foreach ($_s2 as $message) { $loop = array('index0' => $_i2, 'index' => $_i2 + 1, 'first' => $_i2 === 0, 'last' => $_i2 === $_n2 - 1); ?>
                    <tr data-message="<?php echo (isset($message['message_id']) ? $message['message_id'] : null); ?>">
                      <td class="text-center"><input type="checkbox" name="selected[]" value="<?php echo (isset($message['message_id']) ? $message['message_id'] : null); ?>" aria-label="<?php echo AbpOc2::e((isset($message['text']) ? $message['text'] : null)); ?>" /></td>
                      <td>
                        <div class="abp-msg">
                          <span class="abp-swatch" style="background:<?php echo AbpOc2::e((isset($message['bg_color']) ? $message['bg_color'] : null)); ?>;color:<?php echo AbpOc2::e((isset($message['text_color']) ? $message['text_color'] : null)); ?>;"><span class="abp-dot"></span><?php echo AbpOc2::e((isset($message['text']) ? $message['text'] : null)); ?></span>
                          <div class="abp-badges">
                            <?php if (!empty($message['fs'])) { ?><span class="abp-badge abp-b-fs" data-toggle="tooltip" title="<?php echo AbpOc2::e((isset($message['fs']) ? $message['fs'] : null)); ?>"><i class="fa fa-truck"></i> <?php echo AbpOc2::e((isset($message['fs']) ? $message['fs'] : null)); ?></span><?php } ?>
                            <?php if (!empty($message['coupon'])) { ?><span class="abp-badge abp-b-coupon" data-toggle="tooltip" title="<?php echo AbpOc2::e((isset($message['coupon']) ? $message['coupon'] : null)); ?>"><i class="fa fa-ticket"></i> <?php echo AbpOc2::e((isset($message['coupon']) ? $message['coupon'] : null)); ?></span><?php } ?>
                            <?php if (!empty($message['ab'])) { ?><span class="abp-badge abp-b-ab" data-toggle="tooltip" title="<?php echo AbpOc2::e((isset($text_badge_ab) ? $text_badge_ab : null)); ?>"><i class="fa fa-random"></i> A/B</span><?php } ?>
                            <?php if (!empty($message['schedule'])) { ?><span class="abp-badge abp-b-schedule" data-toggle="tooltip" title="<?php echo AbpOc2::e((isset($message['schedule']) ? $message['schedule'] : null)); ?>"><i class="fa fa-clock-o"></i> <?php echo AbpOc2::e((isset($message['schedule']) ? $message['schedule'] : null)); ?></span><?php } ?>
                            <?php if (!empty($message['targeting'])) { ?><span class="abp-badge abp-b-target" data-toggle="tooltip" title="<?php echo AbpOc2::e((isset($message['targeting']) ? $message['targeting'] : null)); ?>"><i aria-hidden="true" class="fa fa-crosshairs"></i> <?php echo AbpOc2::e((isset($text_badge_targeted) ? $text_badge_targeted : null)); ?></span><?php } ?>
                            <?php $_s3 = (array)(isset($message['badges']) ? $message['badges'] : null); $_n3 = count($_s3); $_i3 = 0; $_o3 = isset($loop) ? $loop : null; foreach ($_s3 as $badge) { $loop = array('index0' => $_i3, 'index' => $_i3 + 1, 'first' => $_i3 === 0, 'last' => $_i3 === $_n3 - 1); ?><span class="abp-badge <?php echo AbpOc2::e((isset($badge['class']) ? $badge['class'] : null)); ?>" data-toggle="tooltip" title="<?php echo AbpOc2::e((isset($badge['title']) ? $badge['title'] : null)); ?>"><i aria-hidden="true" class="fa <?php echo AbpOc2::e((isset($badge['icon']) ? $badge['icon'] : null)); ?>"></i> <?php echo AbpOc2::e((isset($badge['label']) ? $badge['label'] : null)); ?></span><?php $_i3++; } $loop = $_o3; ?>
                            <?php if (((isset($message['device']) ? $message['device'] : null) != 'all')) { ?><span class="abp-badge" data-toggle="tooltip" title="<?php echo AbpOc2::e((isset($_context[('text_device_' . (isset($message['device']) ? $message['device'] : null))]) ? $_context[('text_device_' . (isset($message['device']) ? $message['device'] : null))] : null)); ?>"><i aria-hidden="true" class="fa <?php echo (((isset($message['device']) ? $message['device'] : null) == 'mobile') ? 'fa-mobile' : 'fa-desktop'); ?>"></i> <?php echo AbpOc2::e((isset($_context[('text_badge_device_' . (isset($message['device']) ? $message['device'] : null))]) ? $_context[('text_badge_device_' . (isset($message['device']) ? $message['device'] : null))] : null)); ?></span><?php } ?>
                            <?php if (!empty($message['missing'])) { ?><span class="abp-badge abp-b-warn" data-toggle="tooltip" title="<?php echo AbpOc2::e((isset($message['missing']) ? $message['missing'] : null)); ?>"><i aria-hidden="true" class="fa fa-language"></i> <?php echo AbpOc2::e((isset($text_badge_missing) ? $text_badge_missing : null)); ?></span><?php } ?>
                          </div>
                        </div>
                      </td>
                      <td class="text-center"><span class="abp-state abp-state-<?php echo AbpOc2::e((isset($message['state']) ? $message['state'] : null)); ?>"><?php echo AbpOc2::e((isset($message['state_text']) ? $message['state_text'] : null)); ?></span></td>
                      <td class="hidden-xs hidden-sm abp-muted" style="white-space:nowrap;"><?php echo (!empty($message['date_start']) ? AbpOc2::e((isset($message['date_start']) ? $message['date_start'] : null)) : '—'); ?> → <?php echo (!empty($message['date_end']) ? AbpOc2::e((isset($message['date_end']) ? $message['date_end'] : null)) : '∞'); ?></td>
                      <?php if (!empty($message['ab'])) { ?>
                      <td class="text-right abp-stats"><span class="abp-ctr"><span class="abp-ctr-v">A</span> <?php echo (isset($message['views']) ? $message['views'] : null); ?></span><span class="abp-ctr"><span class="abp-ctr-v">B</span> <?php echo (isset($message['views_b']) ? $message['views_b'] : null); ?></span></td>
                      <td class="text-right abp-stats"><span class="abp-ctr"><?php echo (isset($message['clicks']) ? $message['clicks'] : null); ?></span><span class="abp-ctr"><?php echo (isset($message['clicks_b']) ? $message['clicks_b'] : null); ?></span></td>
                      <td class="text-right abp-stats hidden-xs"><span class="abp-ctr"><?php echo (isset($message['copies']) ? $message['copies'] : null); ?></span><span class="abp-ctr"><?php echo (isset($message['copies_b']) ? $message['copies_b'] : null); ?></span></td>
                      <td class="text-right abp-stats"<?php if (empty($message['better'])) { ?> title="<?php echo AbpOc2::e((isset($text_ab_waiting) ? $text_ab_waiting : null)); ?>"<?php } ?>>
                        <span class="abp-ctr<?php if (((isset($message['better']) ? $message['better'] : null) == 'a')) { ?> abp-win<?php } ?>" data-abp-ctr="a"<?php if (((isset($message['better']) ? $message['better'] : null) == 'a')) { ?> data-toggle="tooltip" title="<?php echo AbpOc2::e((isset($text_ab_better) ? $text_ab_better : null)); ?>"<?php } ?>><?php echo (((isset($message['ctr']) ? $message['ctr'] : null) != '') ? (AbpOc2::e((isset($message['ctr']) ? $message['ctr'] : null)) . ' %') : '—'); ?><?php if (((isset($message['better']) ? $message['better'] : null) == 'a')) { ?> <i class="fa fa-star abp-star"></i><?php } ?></span>
                        <span class="abp-ctr<?php if (((isset($message['better']) ? $message['better'] : null) == 'b')) { ?> abp-win<?php } ?>" data-abp-ctr="b"<?php if (((isset($message['better']) ? $message['better'] : null) == 'b')) { ?> data-toggle="tooltip" title="<?php echo AbpOc2::e((isset($text_ab_better) ? $text_ab_better : null)); ?>"<?php } ?>><?php echo (((isset($message['ctr_b']) ? $message['ctr_b'] : null) != '') ? (AbpOc2::e((isset($message['ctr_b']) ? $message['ctr_b'] : null)) . ' %') : '—'); ?><?php if (((isset($message['better']) ? $message['better'] : null) == 'b')) { ?> <i class="fa fa-star abp-star"></i><?php } ?></span>
                      </td>
                      <?php } else { ?>
                      <td class="text-right abp-stats"><?php echo (isset($message['views']) ? $message['views'] : null); ?></td>
                      <td class="text-right abp-stats"><?php echo (isset($message['clicks']) ? $message['clicks'] : null); ?></td>
                      <td class="text-right abp-stats hidden-xs"><?php echo (isset($message['copies']) ? $message['copies'] : null); ?></td>
                      <td class="text-right abp-stats"><?php echo (((isset($message['ctr']) ? $message['ctr'] : null) != '') ? (AbpOc2::e((isset($message['ctr']) ? $message['ctr'] : null)) . ' %') : '—'); ?></td>
                      <?php } ?>
                      <td class="text-right abp-actions">
                        <a href="<?php echo (isset($message['edit']) ? $message['edit'] : null); ?>" data-toggle="tooltip" title="<?php echo AbpOc2::e((isset($button_edit) ? $button_edit : null)); ?>" class="btn btn-primary btn-sm"><i class="fa fa-pencil"></i></a>
                        <?php if (!empty($message['ab'])) { ?><button type="button" data-abp-use-b="<?php echo (isset($use_b) ? $use_b : null); ?>" data-reload="<?php echo (isset($reload) ? $reload : null); ?>" data-id="<?php echo (isset($message['message_id']) ? $message['message_id'] : null); ?>" data-toggle="tooltip" title="<?php echo AbpOc2::e((isset($button_use_b) ? $button_use_b : null)); ?>" class="btn btn-success btn-sm abp-use-b"><i class="fa fa-trophy"></i> B</button><?php } ?>
                        <button type="button" data-abp-post="<?php echo (isset($duplicate) ? $duplicate : null); ?>" value="<?php echo (isset($message['message_id']) ? $message['message_id'] : null); ?>" data-toggle="tooltip" title="<?php echo AbpOc2::e((isset($button_duplicate) ? $button_duplicate : null)); ?>" class="btn btn-default btn-sm abp-duplicate"><i class="fa fa-copy"></i></button>
                        <button type="button" data-abp-post="<?php echo (isset($reset) ? $reset : null); ?>" data-abp-confirm="1" value="<?php echo (isset($message['message_id']) ? $message['message_id'] : null); ?>" data-toggle="tooltip" title="<?php echo AbpOc2::e((isset($button_reset_stats) ? $button_reset_stats : null)); ?>" class="btn btn-warning btn-sm abp-reset"<?php if (empty($message['has_stats'])) { ?> disabled="disabled"<?php } ?>><i class="fa fa-eraser"></i></button>
                      </td>
                    </tr>
                    <?php $_i2++; } $loop = $_o2; if (!$_n2) { ?>
                    <tr>
                      <td class="abp-empty" colspan="9"><i class="fa fa-bullhorn"></i><?php echo (isset($text_no_messages) ? $text_no_messages : null); ?></td>
                    </tr>
                    <?php } ?>
                  </tbody>
                </table>
              </div>
            </form>
            <p class="help-block"><i class="fa fa-bar-chart"></i> <?php echo (isset($help_stats) ? $help_stats : null); ?></p>
          </div>
        </div>
      </div>

            <div class="tab-pane<?php if (((isset($active_tab) ? $active_tab : null) == 'settings')) { ?> active<?php } ?> abp-settings" id="tab-settings">
        <form action="<?php echo (isset($action) ? $action : null); ?>" method="post" enctype="multipart/form-data" id="form-settings" class="form-horizontal">
          <div class="panel panel-default abp-card">
            <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-sliders"></i></span> <?php echo (isset($text_general) ? $text_general : null); ?></div></div>
            <div class="panel-body">
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-status"><?php echo (isset($entry_status) ? $entry_status : null); ?></label>
                <div class="col-sm-9">
                  <select name="module_announcement_bar_pro_status" id="input-status" class="form-control abp-num">
                    <option value="1"<?php if (!empty($setting['status'])) { ?> selected="selected"<?php } ?>><?php echo (isset($text_enabled) ? $text_enabled : null); ?></option>
                    <option value="0"<?php if (empty($setting['status'])) { ?> selected="selected"<?php } ?>><?php echo (isset($text_disabled) ? $text_disabled : null); ?></option>
                  </select>
                  <span class="abp-help"><?php echo (isset($help_status) ? $help_status : null); ?></span>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-mode"><?php echo (isset($entry_mode) ? $entry_mode : null); ?></label>
                <div class="col-sm-9">
                  <select name="module_announcement_bar_pro_mode" id="input-mode" class="form-control">
                    <?php $_s4 = (array)array('rotate', 'marquee', 'stack'); $_n4 = count($_s4); $_i4 = 0; $_o4 = isset($loop) ? $loop : null; foreach ($_s4 as $value) { $loop = array('index0' => $_i4, 'index' => $_i4 + 1, 'first' => $_i4 === 0, 'last' => $_i4 === $_n4 - 1); ?>
                    <option value="<?php echo (isset($value) ? $value : null); ?>"<?php if (((isset($setting['mode']) ? $setting['mode'] : null) == (isset($value) ? $value : null))) { ?> selected="selected"<?php } ?>><?php echo (isset($_context[('text_mode_' . (isset($value) ? $value : null))]) ? $_context[('text_mode_' . (isset($value) ? $value : null))] : null); ?></option>
                    <?php $_i4++; } $loop = $_o4; ?>
                  </select>
                  <span class="abp-help"><?php echo (isset($help_mode) ? $help_mode : null); ?></span>
                  <?php if (!empty($error_settings['mode'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error_settings['mode']) ? $error_settings['mode'] : null)); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group" data-abp-show-mode="rotate">
                <label class="col-sm-3 control-label" for="input-interval"><?php echo (isset($entry_interval) ? $entry_interval : null); ?></label>
                <div class="col-sm-9">
                  <input type="number" min="2" max="60" name="module_announcement_bar_pro_interval" id="input-interval" value="<?php echo AbpOc2::e((isset($setting['interval']) ? $setting['interval'] : null)); ?>" class="form-control abp-num" />
                  <span class="abp-help"><?php echo (isset($help_interval) ? $help_interval : null); ?></span>
                  <?php if (!empty($error_settings['interval'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error_settings['interval']) ? $error_settings['interval'] : null)); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group" data-abp-show-mode="rotate">
                <label class="col-sm-3 control-label" for="input-transition"><?php echo (isset($entry_transition) ? $entry_transition : null); ?></label>
                <div class="col-sm-9">
                  <select name="module_announcement_bar_pro_transition" id="input-transition" class="form-control abp-num" style="max-width:220px;">
                    <?php $_s5 = (array)array('fade', 'slide'); $_n5 = count($_s5); $_i5 = 0; $_o5 = isset($loop) ? $loop : null; foreach ($_s5 as $value) { $loop = array('index0' => $_i5, 'index' => $_i5 + 1, 'first' => $_i5 === 0, 'last' => $_i5 === $_n5 - 1); ?>
                    <option value="<?php echo (isset($value) ? $value : null); ?>"<?php if (((isset($setting['transition']) ? $setting['transition'] : null) == (isset($value) ? $value : null))) { ?> selected="selected"<?php } ?>><?php echo (isset($_context[('text_transition_' . (isset($value) ? $value : null))]) ? $_context[('text_transition_' . (isset($value) ? $value : null))] : null); ?></option>
                    <?php $_i5++; } $loop = $_o5; ?>
                  </select>
                  <span class="abp-help"><?php echo (isset($help_transition) ? $help_transition : null); ?></span>
                  <?php if (!empty($error_settings['transition'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error_settings['transition']) ? $error_settings['transition'] : null)); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group" data-abp-show-mode="rotate">
                <label class="col-sm-3 control-label" for="input-arrows"><?php echo (isset($entry_arrows) ? $entry_arrows : null); ?></label>
                <div class="col-sm-9">
                  <div class="checkbox"><label><input type="checkbox" name="module_announcement_bar_pro_arrows" id="input-arrows" value="1"<?php if (!empty($setting['arrows'])) { ?> checked="checked"<?php } ?> /> <?php echo (isset($text_yes) ? $text_yes : null); ?></label></div>
                  <span class="abp-help"><?php echo (isset($help_arrows) ? $help_arrows : null); ?></span>
                </div>
              </div>
              <div class="form-group" data-abp-show-mode="marquee">
                <label class="col-sm-3 control-label" for="input-speed"><?php echo (isset($entry_speed) ? $entry_speed : null); ?></label>
                <div class="col-sm-9">
                  <input type="number" min="20" max="300" name="module_announcement_bar_pro_speed" id="input-speed" value="<?php echo AbpOc2::e((isset($setting['speed']) ? $setting['speed'] : null)); ?>" class="form-control abp-num" />
                  <span class="abp-help"><?php echo (isset($help_speed) ? $help_speed : null); ?></span>
                  <?php if (!empty($error_settings['speed'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error_settings['speed']) ? $error_settings['speed'] : null)); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-allow-html"><?php echo (isset($entry_allow_html) ? $entry_allow_html : null); ?></label>
                <div class="col-sm-9">
                  <div class="checkbox"><label><input type="checkbox" name="module_announcement_bar_pro_allow_html" id="input-allow-html" value="1"<?php if (!empty($setting['allow_html'])) { ?> checked="checked"<?php } ?> /> <?php echo (isset($text_yes) ? $text_yes : null); ?></label></div>
                  <span class="abp-help"><?php echo (isset($help_allow_html) ? $help_allow_html : null); ?></span>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-max-messages"><?php echo (isset($entry_max_messages) ? $entry_max_messages : null); ?></label>
                <div class="col-sm-9">
                  <input type="number" min="0" max="20" name="module_announcement_bar_pro_max_messages" id="input-max-messages" value="<?php echo AbpOc2::e((isset($setting['max_messages']) ? $setting['max_messages'] : null)); ?>" class="form-control abp-num" />
                  <span class="abp-help"><?php echo (isset($help_max_messages) ? $help_max_messages : null); ?></span>
                  <?php if (!empty($error_settings['max_messages'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error_settings['max_messages']) ? $error_settings['max_messages'] : null)); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-autohide"><?php echo (isset($entry_autohide) ? $entry_autohide : null); ?></label>
                <div class="col-sm-9">
                  <input type="number" min="0" max="600" name="module_announcement_bar_pro_autohide" id="input-autohide" value="<?php echo AbpOc2::e((isset($setting['autohide']) ? $setting['autohide'] : null)); ?>" class="form-control abp-num" />
                  <span class="abp-help"><?php echo (isset($help_autohide) ? $help_autohide : null); ?></span>
                  <?php if (!empty($error_settings['autohide'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error_settings['autohide']) ? $error_settings['autohide'] : null)); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-pause-button"><?php echo (isset($entry_pause_button) ? $entry_pause_button : null); ?></label>
                <div class="col-sm-9">
                  <div class="checkbox"><label><input type="checkbox" name="module_announcement_bar_pro_pause_button" id="input-pause-button" value="1"<?php if (!empty($setting['pause_button'])) { ?> checked="checked"<?php } ?> /> <?php echo (isset($text_yes) ? $text_yes : null); ?></label></div>
                  <span class="abp-help"><?php echo (isset($help_pause_button) ? $help_pause_button : null); ?></span>
                </div>
              </div>
            </div>
          </div>

          <div class="panel panel-default abp-card">
            <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-paint-brush"></i></span> <?php echo (isset($text_appearance) ? $text_appearance : null); ?></div></div>
            <div class="panel-body">
              <div class="form-group">
                <label class="col-sm-3 control-label"><?php echo (isset($entry_style) ? $entry_style : null); ?></label>
                <div class="col-sm-9">
                  <div class="abp-styles" role="radiogroup" aria-label="<?php echo AbpOc2::e((isset($entry_style) ? $entry_style : null)); ?>">
                    <?php $_s6 = (array)(isset($styles) ? $styles : null); $_n6 = count($_s6); $_i6 = 0; $_o6 = isset($loop) ? $loop : null; foreach ($_s6 as $style) { $loop = array('index0' => $_i6, 'index' => $_i6 + 1, 'first' => $_i6 === 0, 'last' => $_i6 === $_n6 - 1); ?>
                    <div class="abp-style-opt">
                      <input type="radio" name="module_announcement_bar_pro_style" id="input-style-<?php echo (isset($style['value']) ? $style['value'] : null); ?>" value="<?php echo (isset($style['value']) ? $style['value'] : null); ?>"<?php if (((isset($setting['style']) ? $setting['style'] : null) == (isset($style['value']) ? $style['value'] : null))) { ?> checked="checked"<?php } ?> />
                      <label for="input-style-<?php echo (isset($style['value']) ? $style['value'] : null); ?>"><span class="abp-sw abp-sw-<?php echo (isset($style['value']) ? $style['value'] : null); ?>"><i></i><b></b></span><span class="abp-name"><?php echo AbpOc2::e((isset($style['name']) ? $style['name'] : null)); ?></span><span class="abp-desc"><?php echo AbpOc2::e((isset($style['help']) ? $style['help'] : null)); ?></span></label>
                    </div>
                    <?php $_i6++; } $loop = $_o6; ?>
                  </div>
                  <span class="abp-help"><?php echo (isset($help_style) ? $help_style : null); ?></span>
                  <?php if (!empty($error_settings['style'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error_settings['style']) ? $error_settings['style'] : null)); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-color-mode"><?php echo (isset($entry_color_mode) ? $entry_color_mode : null); ?></label>
                <div class="col-sm-9">
                  <select name="module_announcement_bar_pro_color_mode" id="input-color-mode" class="form-control">
                    <option value="auto"<?php if (((isset($setting['color_mode']) ? $setting['color_mode'] : null) == 'auto')) { ?> selected="selected"<?php } ?>><?php echo (isset($text_color_auto) ? $text_color_auto : null); ?></option>
                    <option value="custom"<?php if (((isset($setting['color_mode']) ? $setting['color_mode'] : null) != 'auto')) { ?> selected="selected"<?php } ?>><?php echo (isset($text_color_custom) ? $text_color_custom : null); ?></option>
                  </select>
                  <span class="abp-help"><?php echo (isset($help_color_mode) ? $help_color_mode : null); ?></span>
                  <?php if (!empty($error_settings['color_mode'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error_settings['color_mode']) ? $error_settings['color_mode'] : null)); ?></div><?php } ?>
                </div>
              </div>
              <?php $_s7 = (array)array('default_bg_color', 'default_text_color'); $_n7 = count($_s7); $_i7 = 0; $_o7 = isset($loop) ? $loop : null; foreach ($_s7 as $key) { $loop = array('index0' => $_i7, 'index' => $_i7 + 1, 'first' => $_i7 === 0, 'last' => $_i7 === $_n7 - 1); ?>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-<?php echo (isset($key) ? $key : null); ?>"><?php echo (isset($_context[('entry_' . (isset($key) ? $key : null))]) ? $_context[('entry_' . (isset($key) ? $key : null))] : null); ?></label>
                <div class="col-sm-9">
                  <div class="input-group abp-color">
                    <span class="input-group-addon"><input type="color" value="<?php echo AbpOc2::e((isset($setting[(isset($key) ? $key : null)]) ? $setting[(isset($key) ? $key : null)] : null)); ?>" aria-label="<?php echo AbpOc2::e((isset($_context[('entry_' . (isset($key) ? $key : null))]) ? $_context[('entry_' . (isset($key) ? $key : null))] : null)); ?>" /></span>
                    <input type="text" name="module_announcement_bar_pro_<?php echo (isset($key) ? $key : null); ?>" id="input-<?php echo (isset($key) ? $key : null); ?>" value="<?php echo AbpOc2::e((isset($setting[(isset($key) ? $key : null)]) ? $setting[(isset($key) ? $key : null)] : null)); ?>" maxlength="7" class="form-control" />
                  </div>
                  <span class="abp-help"><?php echo (isset($help_default_color) ? $help_default_color : null); ?></span>
                  <?php if (isset($error_settings[(isset($key) ? $key : null)])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error_settings[(isset($key) ? $key : null)]) ? $error_settings[(isset($key) ? $key : null)] : null)); ?></div><?php } ?>
                </div>
              </div>
              <?php $_i7++; } $loop = $_o7; ?>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-position"><?php echo (isset($entry_position) ? $entry_position : null); ?></label>
                <div class="col-sm-9">
                  <select name="module_announcement_bar_pro_position" id="input-position" class="form-control">
                    <option value="top"<?php if (((isset($setting['position']) ? $setting['position'] : null) == 'top')) { ?> selected="selected"<?php } ?>><?php echo (isset($text_position_top) ? $text_position_top : null); ?></option>
                    <option value="bottom"<?php if (((isset($setting['position']) ? $setting['position'] : null) == 'bottom')) { ?> selected="selected"<?php } ?>><?php echo (isset($text_position_bottom) ? $text_position_bottom : null); ?></option>
                  </select>
                  <span class="abp-help"><?php echo (isset($help_position) ? $help_position : null); ?></span>
                  <?php if (!empty($error_settings['position'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error_settings['position']) ? $error_settings['position'] : null)); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group" id="abp-sticky-group">
                <label class="col-sm-3 control-label" for="input-sticky"><?php echo (isset($entry_sticky) ? $entry_sticky : null); ?></label>
                <div class="col-sm-9">
                  <div class="checkbox"><label><input type="checkbox" name="module_announcement_bar_pro_sticky" id="input-sticky" value="1"<?php if (!empty($setting['sticky'])) { ?> checked="checked"<?php } ?> /> <?php echo (isset($text_yes) ? $text_yes : null); ?></label></div>
                  <span class="abp-help"><?php echo (isset($help_sticky) ? $help_sticky : null); ?></span>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-font-size"><?php echo (isset($entry_font_size) ? $entry_font_size : null); ?></label>
                <div class="col-sm-9">
                  <input type="number" min="10" max="30" name="module_announcement_bar_pro_font_size" id="input-font-size" value="<?php echo AbpOc2::e((isset($setting['font_size']) ? $setting['font_size'] : null)); ?>" class="form-control abp-num" />
                  <span class="abp-help"><?php echo (isset($help_font_size) ? $help_font_size : null); ?></span>
                  <?php if (!empty($error_settings['font_size'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error_settings['font_size']) ? $error_settings['font_size'] : null)); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-padding"><?php echo (isset($entry_padding) ? $entry_padding : null); ?></label>
                <div class="col-sm-9">
                  <input type="number" min="0" max="30" name="module_announcement_bar_pro_padding" id="input-padding" value="<?php echo AbpOc2::e((isset($setting['padding']) ? $setting['padding'] : null)); ?>" class="form-control abp-num" />
                  <span class="abp-help"><?php echo (isset($help_padding) ? $help_padding : null); ?></span>
                  <?php if (!empty($error_settings['padding'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error_settings['padding']) ? $error_settings['padding'] : null)); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-font"><?php echo (isset($entry_font) ? $entry_font : null); ?></label>
                <div class="col-sm-9">
                  <select name="module_announcement_bar_pro_font" id="input-font" class="form-control" style="max-width:320px;">
                    <?php $_s8 = (array)(isset($font_list) ? $font_list : null); $_n8 = count($_s8); $_i8 = 0; $_o8 = isset($loop) ? $loop : null; foreach ($_s8 as $f) { $loop = array('index0' => $_i8, 'index' => $_i8 + 1, 'first' => $_i8 === 0, 'last' => $_i8 === $_n8 - 1); ?><option value="<?php echo (isset($f['value']) ? $f['value'] : null); ?>"<?php if (((isset($setting['font']) ? $setting['font'] : null) == (isset($f['value']) ? $f['value'] : null))) { ?> selected="selected"<?php } ?><?php if (!empty($f['stack'])) { ?> style="font-family:<?php echo AbpOc2::e((isset($f['stack']) ? $f['stack'] : null)); ?>"<?php } ?>><?php echo AbpOc2::e((isset($f['name']) ? $f['name'] : null)); ?></option><?php $_i8++; } $loop = $_o8; ?>
                  </select>
                  <span class="abp-help"><?php echo (isset($help_font_module) ? $help_font_module : null); ?></span>
                  <?php if (!empty($error_settings['font'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error_settings['font']) ? $error_settings['font'] : null)); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-custom-css"><?php echo (isset($entry_custom_css) ? $entry_custom_css : null); ?></label>
                <div class="col-sm-9">
                  <textarea name="module_announcement_bar_pro_custom_css" id="input-custom-css" rows="5" class="form-control abp-code" spellcheck="false" placeholder="#abp-bar .abp-btn { border-radius: 6px; }"><?php echo AbpOc2::e((isset($setting['custom_css']) ? $setting['custom_css'] : null)); ?></textarea>
                  <span class="abp-help"><?php echo (isset($help_custom_css) ? $help_custom_css : null); ?></span>
                  <?php if (!empty($error_settings['custom_css'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error_settings['custom_css']) ? $error_settings['custom_css'] : null)); ?></div><?php } ?>
                </div>
              </div>
            </div>
          </div>

          <div class="panel panel-default abp-card">
            <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-globe"></i></span> <?php echo (isset($text_geo) ? $text_geo : null); ?></div></div>
            <div class="panel-body">
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-geo-cf"><?php echo (isset($entry_geo_cf) ? $entry_geo_cf : null); ?></label>
                <div class="col-sm-9">
                  <div class="checkbox"><label><input type="checkbox" name="module_announcement_bar_pro_geo_cf" id="input-geo-cf" value="1"<?php if (!empty($setting['geo_cf'])) { ?> checked="checked"<?php } ?> /> <?php echo (isset($text_yes) ? $text_yes : null); ?></label></div>
                  <span class="abp-help"><?php echo (isset($help_geo_cf) ? $help_geo_cf : null); ?></span>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-geo-fallback"><?php echo (isset($entry_geo_fallback) ? $entry_geo_fallback : null); ?></label>
                <div class="col-sm-9">
                  <select name="module_announcement_bar_pro_geo_fallback" id="input-geo-fallback" class="form-control" style="max-width:320px;">
                    <option value="store"<?php if (((isset($setting['geo_fallback']) ? $setting['geo_fallback'] : null) != 'none')) { ?> selected="selected"<?php } ?>><?php echo (isset($text_geo_store) ? $text_geo_store : null); ?></option>
                    <option value="none"<?php if (((isset($setting['geo_fallback']) ? $setting['geo_fallback'] : null) == 'none')) { ?> selected="selected"<?php } ?>><?php echo (isset($text_geo_none) ? $text_geo_none : null); ?></option>
                  </select>
                  <span class="abp-help"><?php echo (isset($help_geo_fallback) ? $help_geo_fallback : null); ?></span>
                  <?php if (!empty($error_settings['geo_fallback'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error_settings['geo_fallback']) ? $error_settings['geo_fallback'] : null)); ?></div><?php } ?>
                </div>
              </div>
            </div>
          </div>

          <div class="panel panel-default abp-card" id="abp-newsletter">
            <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-envelope"></i></span> <?php echo (isset($text_newsletter) ? $text_newsletter : null); ?></div></div>
            <div class="panel-body">
              <?php if (empty($mail_ready)) { ?><div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> <?php echo (isset($text_mail_missing) ? $text_mail_missing : null); ?></div><?php } ?>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-privacy-id"><?php echo (isset($entry_privacy_id) ? $entry_privacy_id : null); ?></label>
                <div class="col-sm-9">
                  <select name="module_announcement_bar_pro_privacy_id" id="input-privacy-id" class="form-control" style="max-width:360px;">
                    <option value="0"><?php echo (isset($text_none) ? $text_none : null); ?></option>
                    <?php $_s9 = (array)(isset($information_pages) ? $information_pages : null); $_n9 = count($_s9); $_i9 = 0; $_o9 = isset($loop) ? $loop : null; foreach ($_s9 as $page) { $loop = array('index0' => $_i9, 'index' => $_i9 + 1, 'first' => $_i9 === 0, 'last' => $_i9 === $_n9 - 1); ?><option value="<?php echo (isset($page['id']) ? $page['id'] : null); ?>"<?php if (((isset($setting['privacy_id']) ? $setting['privacy_id'] : null) == (isset($page['id']) ? $page['id'] : null))) { ?> selected="selected"<?php } ?>><?php echo AbpOc2::e((isset($page['title']) ? $page['title'] : null)); ?></option><?php $_i9++; } $loop = $_o9; ?>
                  </select>
                  <span class="abp-help"><?php echo (isset($help_privacy_id) ? $help_privacy_id : null); ?></span>
                  <?php if (!empty($error_settings['privacy_id'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error_settings['privacy_id']) ? $error_settings['privacy_id'] : null)); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-double-optin"><?php echo (isset($entry_double_optin) ? $entry_double_optin : null); ?></label>
                <div class="col-sm-9">
                  <div class="checkbox"><label><input type="checkbox" name="module_announcement_bar_pro_double_optin" id="input-double-optin" value="1"<?php if (!empty($setting['double_optin'])) { ?> checked="checked"<?php } ?> /> <?php echo (isset($text_yes) ? $text_yes : null); ?></label></div>
                  <span class="abp-help"><?php echo (isset($help_double_optin) ? $help_double_optin : null); ?></span>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-signup-limit-ip"><?php echo (isset($entry_signup_limit_ip) ? $entry_signup_limit_ip : null); ?></label>
                <div class="col-sm-9">
                  <input type="number" min="1" max="1000" name="module_announcement_bar_pro_signup_limit_ip" id="input-signup-limit-ip" value="<?php echo AbpOc2::e((isset($setting['signup_limit_ip']) ? $setting['signup_limit_ip'] : null)); ?>" class="form-control abp-num" />
                  <span class="abp-help"><?php echo (isset($help_signup_limit_ip) ? $help_signup_limit_ip : null); ?></span>
                  <?php if (!empty($error_settings['signup_limit_ip'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error_settings['signup_limit_ip']) ? $error_settings['signup_limit_ip'] : null)); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-signup-limit-total"><?php echo (isset($entry_signup_limit_total) ? $entry_signup_limit_total : null); ?></label>
                <div class="col-sm-9">
                  <input type="number" min="1" max="100000" name="module_announcement_bar_pro_signup_limit_total" id="input-signup-limit-total" value="<?php echo AbpOc2::e((isset($setting['signup_limit_total']) ? $setting['signup_limit_total'] : null)); ?>" class="form-control abp-num" />
                  <span class="abp-help"><?php echo (isset($help_signup_limit_total) ? $help_signup_limit_total : null); ?></span>
                  <?php if (!empty($error_settings['signup_limit_total'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error_settings['signup_limit_total']) ? $error_settings['signup_limit_total'] : null)); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group" id="abp-turnstile">
                <label class="col-sm-3 control-label" for="input-ts-site"><?php echo (isset($entry_ts_site) ? $entry_ts_site : null); ?></label>
                <div class="col-sm-9">
                  <input type="text" name="module_announcement_bar_pro_ts_site" id="input-ts-site" value="<?php echo AbpOc2::e((isset($setting['ts_site']) ? $setting['ts_site'] : null)); ?>" class="form-control" style="max-width:420px;" maxlength="100" autocomplete="off" spellcheck="false" />
                  <span class="abp-help"><span class="abp-state <?php echo (!empty($ts_on) ? 'abp-state-active' : 'abp-state-disabled'); ?>"><?php echo (!empty($ts_on) ? (isset($text_ts_on) ? $text_ts_on : null) : (isset($text_ts_off) ? $text_ts_off : null)); ?></span> <?php echo (isset($help_ts) ? $help_ts : null); ?> <a href="https://developers.cloudflare.com/turnstile/" target="_blank" rel="noopener noreferrer"><?php echo (isset($text_ts_docs) ? $text_ts_docs : null); ?></a></span>
                  <?php if (!empty($error_settings['ts_site'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error_settings['ts_site']) ? $error_settings['ts_site'] : null)); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-ts-secret"><?php echo (isset($entry_ts_secret) ? $entry_ts_secret : null); ?></label>
                <div class="col-sm-9">
                  <input type="password" name="module_announcement_bar_pro_ts_secret" id="input-ts-secret" value="" class="form-control" style="max-width:420px;" maxlength="200" autocomplete="new-password" spellcheck="false" />
                  <span class="abp-help"><?php echo (!empty($ts_secret_saved) ? (isset($help_ts_secret_saved) ? $help_ts_secret_saved : null) : (isset($help_ts_secret_new) ? $help_ts_secret_new : null)); ?></span>
                  <?php if (!empty($ts_secret_saved)) { ?><div class="checkbox"><label><input type="checkbox" name="module_announcement_bar_pro_ts_secret_remove" id="input-ts-secret-remove" value="1" /> <?php echo (isset($text_ts_remove) ? $text_ts_remove : null); ?></label></div><?php } ?>
                  <?php if (!empty($error_settings['ts_secret'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error_settings['ts_secret']) ? $error_settings['ts_secret'] : null)); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label"><?php echo (isset($entry_subscribers) ? $entry_subscribers : null); ?></label>
                <div class="col-sm-9">
                  <p class="form-control-static abp-subs"><span class="abp-state abp-state-active"><?php echo (isset($subscriber_counts[1]) ? $subscriber_counts[1] : null); ?> <?php echo (isset($text_sub_confirmed) ? $text_sub_confirmed : null); ?></span> <span class="abp-state abp-state-scheduled"><?php echo (isset($subscriber_counts[0]) ? $subscriber_counts[0] : null); ?> <?php echo (isset($text_sub_pending) ? $text_sub_pending : null); ?></span> <span class="abp-state abp-state-disabled"><?php echo (isset($subscriber_counts[2]) ? $subscriber_counts[2] : null); ?> <?php echo (isset($text_sub_unsubscribed) ? $text_sub_unsubscribed : null); ?></span></p>
                  <a href="<?php echo (isset($export_subscribers) ? $export_subscribers : null); ?>" class="btn btn-default btn-sm"><i class="fa fa-download"></i> <?php echo (isset($button_export_subscribers) ? $button_export_subscribers : null); ?></a>
                  <span class="abp-help"><?php echo (isset($help_subscribers) ? $help_subscribers : null); ?></span>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-erase-email"><?php echo (isset($entry_erase_email) ? $entry_erase_email : null); ?></label>
                <div class="col-sm-9">
                  <div class="input-group" style="max-width:420px;">
                    <input type="email" id="input-erase-email" class="form-control" autocomplete="off" placeholder="name@example.com" form="abp-no-form" />
                    <span class="input-group-btn"><button type="button" class="btn btn-danger" data-abp-erase="<?php echo (isset($erase_subscriber) ? $erase_subscriber : null); ?>"><i class="fa fa-eraser"></i> <?php echo (isset($button_erase) ? $button_erase : null); ?></button></span>
                  </div>
                  <span class="abp-help"><?php echo (isset($help_erase) ? $help_erase : null); ?></span>
                </div>
              </div>
            </div>
          </div>

          <div class="panel panel-default abp-card">
            <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-line-chart"></i></span> <?php echo (isset($text_analytics) ? $text_analytics : null); ?></div></div>
            <div class="panel-body">
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-ga4"><?php echo (isset($entry_ga4) ? $entry_ga4 : null); ?></label>
                <div class="col-sm-9">
                  <div class="checkbox"><label><input type="checkbox" name="module_announcement_bar_pro_ga4" id="input-ga4" value="1"<?php if (!empty($setting['ga4'])) { ?> checked="checked"<?php } ?> /> <?php echo (isset($text_yes) ? $text_yes : null); ?></label></div>
                  <span class="abp-help"><?php echo (isset($help_ga4) ? $help_ga4 : null); ?></span>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-consent-wait"><?php echo (isset($entry_consent_wait) ? $entry_consent_wait : null); ?></label>
                <div class="col-sm-9">
                  <div class="checkbox"><label><input type="checkbox" name="module_announcement_bar_pro_consent_wait" id="input-consent-wait" value="1"<?php if (!empty($setting['consent_wait'])) { ?> checked="checked"<?php } ?> /> <?php echo (isset($text_yes) ? $text_yes : null); ?></label></div>
                  <span class="abp-help"><?php echo (isset($help_consent_wait) ? $help_consent_wait : null); ?></span>
                </div>
              </div>
            </div>
          </div>

          <div class="panel panel-default abp-card">
            <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-times-circle"></i></span> <?php echo (isset($text_dismiss) ? $text_dismiss : null); ?></div></div>
            <div class="panel-body">
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-dismissible"><?php echo (isset($entry_dismissible) ? $entry_dismissible : null); ?></label>
                <div class="col-sm-9">
                  <div class="checkbox"><label><input type="checkbox" name="module_announcement_bar_pro_dismissible" id="input-dismissible" value="1"<?php if (!empty($setting['dismissible'])) { ?> checked="checked"<?php } ?> /> <?php echo (isset($text_yes) ? $text_yes : null); ?></label></div>
                  <span class="abp-help"><?php echo (isset($help_dismissible) ? $help_dismissible : null); ?></span>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-dismiss-days"><?php echo (isset($entry_dismiss_days) ? $entry_dismiss_days : null); ?></label>
                <div class="col-sm-9">
                  <input type="number" min="0" max="365" name="module_announcement_bar_pro_dismiss_days" id="input-dismiss-days" value="<?php echo AbpOc2::e((isset($setting['dismiss_days']) ? $setting['dismiss_days'] : null)); ?>" class="form-control abp-num" />
                  <span class="abp-help"><?php echo (isset($help_dismiss_days) ? $help_dismiss_days : null); ?></span>
                  <?php if (!empty($error_settings['dismiss_days'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error_settings['dismiss_days']) ? $error_settings['dismiss_days'] : null)); ?></div><?php } ?>
                </div>
              </div>
            </div>
          </div>

          <div class="panel panel-default abp-card">
            <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-wrench"></i></span> <?php echo (isset($text_advanced) ? $text_advanced : null); ?></div></div>
            <div class="panel-body">
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-delete-data"><?php echo (isset($entry_delete_data) ? $entry_delete_data : null); ?></label>
                <div class="col-sm-9">
                  <div class="checkbox"><label><input type="checkbox" name="module_announcement_bar_pro_delete_data" id="input-delete-data" value="1"<?php if (!empty($setting['delete_data'])) { ?> checked="checked"<?php } ?> /> <?php echo (isset($text_yes) ? $text_yes : null); ?></label></div>
                  <span class="abp-help"><?php echo (isset($help_delete_data) ? $help_delete_data : null); ?></span>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<script type="application/json" id="abp-config"><?php echo (isset($config_json) ? $config_json : null); ?></script>
<script type="text/javascript" src="<?php echo AbpOc2::e((isset($admin_js) ? $admin_js : null)); ?>"></script>
<script type="text/javascript"><!--
(function ($) {
  var hex = /^#[0-9a-fA-F]{6}$/;

  // Duplicate / reset: plain buttons that POST their own small form. They must not be type="submit":
  // OpenCart 3 admin common.js submits every form-* on the page when any submit button is clicked.
  // One request only (a double click must not create two copies).
  var abpSent = false;
  $('[data-abp-post]').on('click', function () {
    if (abpSent || this.disabled) { return; }
    if ($(this).data('abp-confirm') && !confirm('<?php echo AbpOc2::escapeJs((isset($text_confirm_reset) ? $text_confirm_reset : null)); ?>')) { return; }
    abpSent = true;
    $(this).prop('disabled', true);
    var f = $('<form method="post" style="display:none"></form>').attr('action', $(this).data('abp-post'));
    f.append($('<input type="hidden" name="message_id">').val(this.value));
    $('body').append(f);
    f.get(0).submit();
  });

  $('.abp-color').each(function () {
    var picker = $(this).find('input[type=color]'), text = $(this).find('input[type=text]');
    picker.on('input change', function () { text.val(this.value); });
    text.on('input change', function () { if (hex.test(this.value)) { picker.val(this.value); } });
  });

  function toggle() {
    var mode = $('#input-mode').val();
    $('[data-abp-show-mode]').each(function () { $(this).toggle($(this).data('abp-show-mode') === mode); });
    $('#abp-sticky-group').toggle($('#input-position').val() === 'top');
  }
  $('#input-mode, #input-position').on('change', toggle);
  toggle();

  $('#abp-delete').on('click', function () {
    if (!$('#form-delete input[name="selected[]"]:checked').length) { return; }
    if (confirm('<?php echo AbpOc2::escapeJs((isset($text_confirm) ? $text_confirm : null)); ?>')) { $('#form-delete').submit(); }
  });
})(jQuery);
//--></script>
<?php echo (isset($footer) ? $footer : null); ?>

