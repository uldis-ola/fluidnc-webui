<?php /* Announcement Bar Pro 2.4.2 — generated from the OpenCart 3 Twig template by tools/twig2tpl.py, then adapted for OpenCart 1.5. */ ?>
<?php echo abp15_v($header); ?>
<div id="content" class="abp-admin abp15">
  <link rel="stylesheet" href="<?php echo abp15_e(abp15_v($fa_css)); ?>" />
  <link rel="stylesheet" href="<?php echo abp15_e(abp15_v($admin_css)); ?>" />
  <link rel="stylesheet" href="<?php echo abp15_e(abp15_v($oc15_css)); ?>" />
  <div class="page-header">
    <div class="container-fluid">
      <div class="pull-right">
        <button type="submit" form="form-settings" data-toggle="tooltip" title="<?php echo abp15_e(abp15_v($button_save)); ?>" class="btn btn-primary"><i class="fa fa-save"></i></button>
        <a href="<?php echo abp15_v($cancel); ?>" data-toggle="tooltip" title="<?php echo abp15_e(abp15_v($button_cancel)); ?>" class="btn btn-default"><i class="fa fa-reply"></i></a>
      </div>
      <h1><?php echo abp15_v($heading_title); ?> <small>v<?php echo abp15_e(abp15_v($version)); ?></small></h1>
      <ul class="breadcrumb">
        <?php $__k1 = array(); foreach (array('breadcrumb') as $__x) { $__k1[$__x] = isset($$__x) ? array($$__x) : null; } $__s1 = (array)abp15_v($breadcrumbs); $__c1 = count($__s1); $__i1 = 0; foreach ($__s1 as $breadcrumb) { $loop1 = array('index0' => $__i1, 'index' => $__i1 + 1, 'first' => $__i1 === 0, 'last' => $__i1 === $__c1 - 1); $__i1++; ?>
        <li><a href="<?php echo abp15_v($breadcrumb['href']); ?>"><?php echo abp15_v($breadcrumb['text']); ?></a></li>
        <?php } foreach ($__k1 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
      </ul>
    </div>
  </div>
  <div class="container-fluid">
    <div id="abp-alerts">
    <?php if (abp15_v($error_warning)) { ?>
    <div class="alert alert-danger alert-dismissible"><i class="fa fa-exclamation-circle"></i> <?php echo abp15_e(abp15_v($error_warning)); ?>
      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php } ?>
    <?php if (abp15_v($error_session)) { ?>
    <div class="alert alert-danger alert-dismissible"><i class="fa fa-exclamation-circle"></i> <?php echo abp15_e(abp15_v($error_session)); ?>
      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php } ?>
    <?php if (abp15_v($success)) { ?>
    <div class="alert alert-success alert-dismissible"><i class="fa fa-check-circle"></i> <?php echo abp15_e(abp15_v($success)); ?>
      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php } ?>
    </div>

    <ul class="nav nav-tabs">
      <li<?php if ((abp15_v($active_tab) == 'messages')) { ?> class="active"<?php } ?>><a href="#tab-messages" data-toggle="tab"><i class="fa fa-bullhorn"></i> <?php echo abp15_v($tab_messages); ?> <span class="badge"><?php echo abp15_len(abp15_v($messages)); ?></span></a></li>
      <li<?php if ((abp15_v($active_tab) == 'settings')) { ?> class="active"<?php } ?>><a href="#tab-settings" data-toggle="tab"><i class="fa fa-cog"></i> <?php echo abp15_v($tab_settings); ?></a></li>
    </ul>

    <div class="tab-content">
      
      <div class="tab-pane<?php if ((abp15_v($active_tab) == 'messages')) { ?> active<?php } ?>" id="tab-messages">
        <div class="panel panel-default abp-card">
          <div class="panel-heading clearfix">
            <div class="pull-left abp-card-title" style="padding-top:5px;"><span class="abp-ico-badge"><i class="fa fa-list"></i></span> <?php echo abp15_v($text_message_list); ?></div>
            <div class="pull-right abp-toolbar">
              <a href="<?php echo abp15_v($export); ?>" data-toggle="tooltip" title="<?php echo abp15_e(abp15_v($help_export)); ?>" class="btn btn-default btn-sm"><i class="fa fa-download"></i> <?php echo abp15_v($button_export); ?></a>
              <button type="button" data-abp-import="<?php echo abp15_v($import); ?>" data-reload="<?php echo abp15_v($reload); ?>" data-toggle="tooltip" title="<?php echo abp15_e(abp15_v($help_import)); ?>" class="btn btn-default btn-sm"><i class="fa fa-upload"></i> <?php echo abp15_v($button_import); ?></button>
              <a href="<?php echo abp15_v($add); ?>" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> <?php echo abp15_v($button_add); ?></a>
              <button type="button" data-toggle="tooltip" title="<?php echo abp15_e(abp15_v($button_delete)); ?>" class="btn btn-danger btn-sm" id="abp-delete"><i class="fa fa-trash-o"></i></button>
            </div>
          </div>
          <div class="panel-body">
            <?php if (!(abp15_v($setting['status']))) { ?>
            <div class="alert alert-warning"><i class="fa fa-info-circle"></i> <?php echo abp15_v($text_module_disabled); ?></div>
            <?php } ?>
            <form action="<?php echo abp15_v($delete); ?>" method="post" id="form-delete">
              <div class="table-responsive">
                <table class="table table-hover abp-table">
                  <thead>
                    <tr>
                      <td style="width:1px;" class="text-center"><input type="checkbox" aria-label="<?php echo abp15_e(abp15_v($button_delete)); ?>" onclick="$('input[name*=\'selected\']').prop('checked', this.checked);" /></td>
                      <td><?php echo abp15_v($column_text); ?></td>
                      <td class="text-center"><?php echo abp15_v($column_status); ?></td>
                      <td class="hidden-xs hidden-sm"><?php echo abp15_v($column_period); ?></td>
                      <td class="text-right"><span data-toggle="tooltip" title="<?php echo abp15_e(abp15_v($help_stats)); ?>"><?php echo abp15_v($column_views); ?></span></td>
                      <td class="text-right"><?php echo abp15_v($column_clicks); ?></td>
                      <td class="text-right hidden-xs"><?php echo abp15_v($column_copies); ?></td>
                      <td class="text-right"><?php echo abp15_v($column_ctr); ?></td>
                      <td class="text-right" style="white-space:nowrap;"><?php echo abp15_v($column_action); ?></td>
                    </tr>
                  </thead>
                  <tbody>
                    <?php $__k2 = array(); foreach (array('message') as $__x) { $__k2[$__x] = isset($$__x) ? array($$__x) : null; } $__s2 = (array)abp15_v($messages); $__c2 = count($__s2); $__i2 = 0; foreach ($__s2 as $message) { $loop2 = array('index0' => $__i2, 'index' => $__i2 + 1, 'first' => $__i2 === 0, 'last' => $__i2 === $__c2 - 1); $__i2++; ?>
                    <tr data-message="<?php echo abp15_v($message['message_id']); ?>">
                      <td class="text-center"><input type="checkbox" name="selected[]" value="<?php echo abp15_v($message['message_id']); ?>" aria-label="<?php echo abp15_e(abp15_v($message['text'])); ?>" /></td>
                      <td>
                        <div class="abp-msg">
                          <span class="abp-swatch" style="background:<?php echo abp15_e(abp15_v($message['bg_color'])); ?>;color:<?php echo abp15_e(abp15_v($message['text_color'])); ?>;"><span class="abp-dot"></span><?php echo abp15_e(abp15_v($message['text'])); ?></span>
                          <div class="abp-badges">
                            <?php if (abp15_v($message['fs'])) { ?><span class="abp-badge abp-b-fs" data-toggle="tooltip" title="<?php echo abp15_e(abp15_v($message['fs'])); ?>"><i class="fa fa-truck"></i> <?php echo abp15_e(abp15_v($message['fs'])); ?></span><?php } ?>
                            <?php if (abp15_v($message['coupon'])) { ?><span class="abp-badge abp-b-coupon" data-toggle="tooltip" title="<?php echo abp15_e(abp15_v($message['coupon'])); ?>"><i class="fa fa-ticket"></i> <?php echo abp15_e(abp15_v($message['coupon'])); ?></span><?php } ?>
                            <?php if (abp15_v($message['ab'])) { ?><span class="abp-badge abp-b-ab" data-toggle="tooltip" title="<?php echo abp15_e(abp15_v($text_badge_ab)); ?>"><i class="fa fa-random"></i> A/B</span><?php } ?>
                            <?php if (abp15_v($message['schedule'])) { ?><span class="abp-badge abp-b-schedule" data-toggle="tooltip" title="<?php echo abp15_e(abp15_v($message['schedule'])); ?>"><i class="fa fa-clock-o"></i> <?php echo abp15_e(abp15_v($message['schedule'])); ?></span><?php } ?>
                            <?php if (abp15_v($message['targeting'])) { ?><span class="abp-badge abp-b-target" data-toggle="tooltip" title="<?php echo abp15_e(abp15_v($message['targeting'])); ?>"><i aria-hidden="true" class="fa fa-crosshairs"></i> <?php echo abp15_e(abp15_v($text_badge_targeted)); ?></span><?php } ?>
                            <?php $__k3 = array(); foreach (array('badge') as $__x) { $__k3[$__x] = isset($$__x) ? array($$__x) : null; } $__s3 = (array)abp15_v($message['badges']); $__c3 = count($__s3); $__i3 = 0; foreach ($__s3 as $badge) { $loop3 = array('index0' => $__i3, 'index' => $__i3 + 1, 'first' => $__i3 === 0, 'last' => $__i3 === $__c3 - 1); $__i3++; ?><span class="abp-badge <?php echo abp15_e(abp15_v($badge['class'])); ?>" data-toggle="tooltip" title="<?php echo abp15_e(abp15_v($badge['title'])); ?>"><i aria-hidden="true" class="fa <?php echo abp15_e(abp15_v($badge['icon'])); ?>"></i> <?php echo abp15_e(abp15_v($badge['label'])); ?></span><?php } foreach ($__k3 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
                            <?php if ((abp15_v($message['device']) != 'all')) { ?><span class="abp-badge" data-toggle="tooltip" title="<?php echo abp15_e(abp15_v(${('text_device_' . abp15_v($message['device']))})); ?>"><i aria-hidden="true" class="fa <?php echo ((abp15_v($message['device']) == 'mobile') ? 'fa-mobile' : 'fa-desktop'); ?>"></i> <?php echo abp15_e(abp15_v(${('text_badge_device_' . abp15_v($message['device']))})); ?></span><?php } ?>
                            <?php if (abp15_v($message['missing'])) { ?><span class="abp-badge abp-b-warn" data-toggle="tooltip" title="<?php echo abp15_e(abp15_v($message['missing'])); ?>"><i aria-hidden="true" class="fa fa-language"></i> <?php echo abp15_e(abp15_v($text_badge_missing)); ?></span><?php } ?>
                          </div>
                        </div>
                      </td>
                      <td class="text-center"><span class="abp-state abp-state-<?php echo abp15_e(abp15_v($message['state'])); ?>"><?php echo abp15_e(abp15_v($message['state_text'])); ?></span></td>
                      <td class="hidden-xs hidden-sm abp-muted" style="white-space:nowrap;"><?php echo (abp15_v($message['date_start']) ? abp15_e(abp15_v($message['date_start'])) : '—'); ?> → <?php echo (abp15_v($message['date_end']) ? abp15_e(abp15_v($message['date_end'])) : '∞'); ?></td>
                      <?php if (abp15_v($message['ab'])) { ?>
                      <td class="text-right abp-stats"><span class="abp-ctr"><span class="abp-ctr-v">A</span> <?php echo abp15_v($message['views']); ?></span><span class="abp-ctr"><span class="abp-ctr-v">B</span> <?php echo abp15_v($message['views_b']); ?></span></td>
                      <td class="text-right abp-stats"><span class="abp-ctr"><?php echo abp15_v($message['clicks']); ?></span><span class="abp-ctr"><?php echo abp15_v($message['clicks_b']); ?></span></td>
                      <td class="text-right abp-stats hidden-xs"><span class="abp-ctr"><?php echo abp15_v($message['copies']); ?></span><span class="abp-ctr"><?php echo abp15_v($message['copies_b']); ?></span></td>
                      <td class="text-right abp-stats"<?php if (!(abp15_v($message['better']))) { ?> title="<?php echo abp15_e(abp15_v($text_ab_waiting)); ?>"<?php } ?>>
                        <span class="abp-ctr<?php if ((abp15_v($message['better']) == 'a')) { ?> abp-win<?php } ?>" data-abp-ctr="a"<?php if ((abp15_v($message['better']) == 'a')) { ?> data-toggle="tooltip" title="<?php echo abp15_e(abp15_v($text_ab_better)); ?>"<?php } ?>><?php echo ((abp15_v($message['ctr']) != '') ? (abp15_e(abp15_v($message['ctr'])) . ' %') : '—'); ?><?php if ((abp15_v($message['better']) == 'a')) { ?> <i class="fa fa-star abp-star"></i><?php } ?></span>
                        <span class="abp-ctr<?php if ((abp15_v($message['better']) == 'b')) { ?> abp-win<?php } ?>" data-abp-ctr="b"<?php if ((abp15_v($message['better']) == 'b')) { ?> data-toggle="tooltip" title="<?php echo abp15_e(abp15_v($text_ab_better)); ?>"<?php } ?>><?php echo ((abp15_v($message['ctr_b']) != '') ? (abp15_e(abp15_v($message['ctr_b'])) . ' %') : '—'); ?><?php if ((abp15_v($message['better']) == 'b')) { ?> <i class="fa fa-star abp-star"></i><?php } ?></span>
                      </td>
                      <?php } else { ?>
                      <td class="text-right abp-stats"><?php echo abp15_v($message['views']); ?></td>
                      <td class="text-right abp-stats"><?php echo abp15_v($message['clicks']); ?></td>
                      <td class="text-right abp-stats hidden-xs"><?php echo abp15_v($message['copies']); ?></td>
                      <td class="text-right abp-stats"><?php echo ((abp15_v($message['ctr']) != '') ? (abp15_e(abp15_v($message['ctr'])) . ' %') : '—'); ?></td>
                      <?php } ?>
                      <td class="text-right abp-actions">
                        <a href="<?php echo abp15_v($message['edit']); ?>" data-toggle="tooltip" title="<?php echo abp15_e(abp15_v($button_edit)); ?>" class="btn btn-primary btn-sm"><i class="fa fa-pencil"></i></a>
                        <?php if (abp15_v($message['ab'])) { ?><button type="button" data-abp-use-b="<?php echo abp15_v($use_b); ?>" data-reload="<?php echo abp15_v($reload); ?>" data-id="<?php echo abp15_v($message['message_id']); ?>" data-toggle="tooltip" title="<?php echo abp15_e(abp15_v($button_use_b)); ?>" class="btn btn-success btn-sm abp-use-b"><i class="fa fa-trophy"></i> B</button><?php } ?>
                        <button type="button" data-abp-post="<?php echo abp15_v($duplicate); ?>" value="<?php echo abp15_v($message['message_id']); ?>" data-toggle="tooltip" title="<?php echo abp15_e(abp15_v($button_duplicate)); ?>" class="btn btn-default btn-sm abp-duplicate"><i class="fa fa-copy"></i></button>
                        <button type="button" data-abp-post="<?php echo abp15_v($reset); ?>" data-abp-confirm="1" value="<?php echo abp15_v($message['message_id']); ?>" data-toggle="tooltip" title="<?php echo abp15_e(abp15_v($button_reset_stats)); ?>" class="btn btn-warning btn-sm abp-reset"<?php if (!(abp15_v($message['has_stats']))) { ?> disabled="disabled"<?php } ?>><i class="fa fa-eraser"></i></button>
                      </td>
                    </tr>
                    <?php } if ($__c2 === 0) { ?>
                    <tr>
                      <td class="abp-empty" colspan="9"><i class="fa fa-bullhorn"></i><?php echo abp15_v($text_no_messages); ?></td>
                    </tr>
                    <?php } foreach ($__k2 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
                  </tbody>
                </table>
              </div>
            </form>
            <p class="help-block"><i class="fa fa-bar-chart"></i> <?php echo abp15_v($help_stats); ?></p>
          </div>
        </div>
      </div>

      
      <div class="tab-pane<?php if ((abp15_v($active_tab) == 'settings')) { ?> active<?php } ?> abp-settings" id="tab-settings">
        <form action="<?php echo abp15_v($action); ?>" method="post" enctype="multipart/form-data" id="form-settings" class="form-horizontal">
          <div class="panel panel-default abp-card">
            <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-sliders"></i></span> <?php echo abp15_v($text_general); ?></div></div>
            <div class="panel-body">
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-status"><?php echo abp15_v($entry_status); ?></label>
                <div class="col-sm-9">
                  <select name="announcement_bar_pro_status" id="input-status" class="form-control abp-num">
                    <option value="1"<?php if (abp15_v($setting['status'])) { ?> selected="selected"<?php } ?>><?php echo abp15_v($text_enabled); ?></option>
                    <option value="0"<?php if (!(abp15_v($setting['status']))) { ?> selected="selected"<?php } ?>><?php echo abp15_v($text_disabled); ?></option>
                  </select>
                  <span class="abp-help"><?php echo abp15_v($help_status); ?></span>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-mode"><?php echo abp15_v($entry_mode); ?></label>
                <div class="col-sm-9">
                  <select name="announcement_bar_pro_mode" id="input-mode" class="form-control">
                    <?php $__k4 = array(); foreach (array('value') as $__x) { $__k4[$__x] = isset($$__x) ? array($$__x) : null; } $__s4 = (array)array('rotate', 'marquee', 'stack'); $__c4 = count($__s4); $__i4 = 0; foreach ($__s4 as $value) { $loop4 = array('index0' => $__i4, 'index' => $__i4 + 1, 'first' => $__i4 === 0, 'last' => $__i4 === $__c4 - 1); $__i4++; ?>
                    <option value="<?php echo abp15_v($value); ?>"<?php if ((abp15_v($setting['mode']) == abp15_v($value))) { ?> selected="selected"<?php } ?>><?php echo abp15_v(${('text_mode_' . abp15_v($value))}); ?></option>
                    <?php } foreach ($__k4 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
                  </select>
                  <span class="abp-help"><?php echo abp15_v($help_mode); ?></span>
                  <?php if (abp15_v($error_settings['mode'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error_settings['mode'])); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group" data-abp-show-mode="rotate">
                <label class="col-sm-3 control-label" for="input-interval"><?php echo abp15_v($entry_interval); ?></label>
                <div class="col-sm-9">
                  <input type="number" min="2" max="60" name="announcement_bar_pro_interval" id="input-interval" value="<?php echo abp15_e(abp15_v($setting['interval'])); ?>" class="form-control abp-num" />
                  <span class="abp-help"><?php echo abp15_v($help_interval); ?></span>
                  <?php if (abp15_v($error_settings['interval'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error_settings['interval'])); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group" data-abp-show-mode="rotate">
                <label class="col-sm-3 control-label" for="input-transition"><?php echo abp15_v($entry_transition); ?></label>
                <div class="col-sm-9">
                  <select name="announcement_bar_pro_transition" id="input-transition" class="form-control abp-num" style="max-width:220px;">
                    <?php $__k5 = array(); foreach (array('value') as $__x) { $__k5[$__x] = isset($$__x) ? array($$__x) : null; } $__s5 = (array)array('fade', 'slide'); $__c5 = count($__s5); $__i5 = 0; foreach ($__s5 as $value) { $loop5 = array('index0' => $__i5, 'index' => $__i5 + 1, 'first' => $__i5 === 0, 'last' => $__i5 === $__c5 - 1); $__i5++; ?>
                    <option value="<?php echo abp15_v($value); ?>"<?php if ((abp15_v($setting['transition']) == abp15_v($value))) { ?> selected="selected"<?php } ?>><?php echo abp15_v(${('text_transition_' . abp15_v($value))}); ?></option>
                    <?php } foreach ($__k5 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
                  </select>
                  <span class="abp-help"><?php echo abp15_v($help_transition); ?></span>
                  <?php if (abp15_v($error_settings['transition'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error_settings['transition'])); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group" data-abp-show-mode="rotate">
                <label class="col-sm-3 control-label" for="input-arrows"><?php echo abp15_v($entry_arrows); ?></label>
                <div class="col-sm-9">
                  <div class="checkbox"><label><input type="checkbox" name="announcement_bar_pro_arrows" id="input-arrows" value="1"<?php if (abp15_v($setting['arrows'])) { ?> checked="checked"<?php } ?> /> <?php echo abp15_v($text_yes); ?></label></div>
                  <span class="abp-help"><?php echo abp15_v($help_arrows); ?></span>
                </div>
              </div>
              <div class="form-group" data-abp-show-mode="marquee">
                <label class="col-sm-3 control-label" for="input-speed"><?php echo abp15_v($entry_speed); ?></label>
                <div class="col-sm-9">
                  <input type="number" min="20" max="300" name="announcement_bar_pro_speed" id="input-speed" value="<?php echo abp15_e(abp15_v($setting['speed'])); ?>" class="form-control abp-num" />
                  <span class="abp-help"><?php echo abp15_v($help_speed); ?></span>
                  <?php if (abp15_v($error_settings['speed'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error_settings['speed'])); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-allow-html"><?php echo abp15_v($entry_allow_html); ?></label>
                <div class="col-sm-9">
                  <div class="checkbox"><label><input type="checkbox" name="announcement_bar_pro_allow_html" id="input-allow-html" value="1"<?php if (abp15_v($setting['allow_html'])) { ?> checked="checked"<?php } ?> /> <?php echo abp15_v($text_yes); ?></label></div>
                  <span class="abp-help"><?php echo abp15_v($help_allow_html); ?></span>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-max-messages"><?php echo abp15_v($entry_max_messages); ?></label>
                <div class="col-sm-9">
                  <input type="number" min="0" max="20" name="announcement_bar_pro_max_messages" id="input-max-messages" value="<?php echo abp15_e(abp15_v($setting['max_messages'])); ?>" class="form-control abp-num" />
                  <span class="abp-help"><?php echo abp15_v($help_max_messages); ?></span>
                  <?php if (abp15_v($error_settings['max_messages'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error_settings['max_messages'])); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-autohide"><?php echo abp15_v($entry_autohide); ?></label>
                <div class="col-sm-9">
                  <input type="number" min="0" max="600" name="announcement_bar_pro_autohide" id="input-autohide" value="<?php echo abp15_e(abp15_v($setting['autohide'])); ?>" class="form-control abp-num" />
                  <span class="abp-help"><?php echo abp15_v($help_autohide); ?></span>
                  <?php if (abp15_v($error_settings['autohide'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error_settings['autohide'])); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-pause-button"><?php echo abp15_v($entry_pause_button); ?></label>
                <div class="col-sm-9">
                  <div class="checkbox"><label><input type="checkbox" name="announcement_bar_pro_pause_button" id="input-pause-button" value="1"<?php if (abp15_v($setting['pause_button'])) { ?> checked="checked"<?php } ?> /> <?php echo abp15_v($text_yes); ?></label></div>
                  <span class="abp-help"><?php echo abp15_v($help_pause_button); ?></span>
                </div>
              </div>
            </div>
          </div>

          <div class="panel panel-default abp-card">
            <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-paint-brush"></i></span> <?php echo abp15_v($text_appearance); ?></div></div>
            <div class="panel-body">
              <div class="form-group">
                <label class="col-sm-3 control-label"><?php echo abp15_v($entry_style); ?></label>
                <div class="col-sm-9">
                  <div class="abp-styles" role="radiogroup" aria-label="<?php echo abp15_e(abp15_v($entry_style)); ?>">
                    <?php $__k6 = array(); foreach (array('style') as $__x) { $__k6[$__x] = isset($$__x) ? array($$__x) : null; } $__s6 = (array)abp15_v($styles); $__c6 = count($__s6); $__i6 = 0; foreach ($__s6 as $style) { $loop6 = array('index0' => $__i6, 'index' => $__i6 + 1, 'first' => $__i6 === 0, 'last' => $__i6 === $__c6 - 1); $__i6++; ?>
                    <div class="abp-style-opt">
                      <input type="radio" name="announcement_bar_pro_style" id="input-style-<?php echo abp15_v($style['value']); ?>" value="<?php echo abp15_v($style['value']); ?>"<?php if ((abp15_v($setting['style']) == abp15_v($style['value']))) { ?> checked="checked"<?php } ?> />
                      <label for="input-style-<?php echo abp15_v($style['value']); ?>"><span class="abp-sw abp-sw-<?php echo abp15_v($style['value']); ?>"><i></i><b></b></span><span class="abp-name"><?php echo abp15_e(abp15_v($style['name'])); ?></span><span class="abp-desc"><?php echo abp15_e(abp15_v($style['help'])); ?></span></label>
                    </div>
                    <?php } foreach ($__k6 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
                  </div>
                  <span class="abp-help"><?php echo abp15_v($help_style); ?></span>
                  <?php if (abp15_v($error_settings['style'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error_settings['style'])); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-color-mode"><?php echo abp15_v($entry_color_mode); ?></label>
                <div class="col-sm-9">
                  <select name="announcement_bar_pro_color_mode" id="input-color-mode" class="form-control">
                    <option value="auto"<?php if ((abp15_v($setting['color_mode']) == 'auto')) { ?> selected="selected"<?php } ?>><?php echo abp15_v($text_color_auto); ?></option>
                    <option value="custom"<?php if ((abp15_v($setting['color_mode']) != 'auto')) { ?> selected="selected"<?php } ?>><?php echo abp15_v($text_color_custom); ?></option>
                  </select>
                  <span class="abp-help"><?php echo abp15_v($help_color_mode); ?></span>
                  <?php if (abp15_v($error_settings['color_mode'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error_settings['color_mode'])); ?></div><?php } ?>
                </div>
              </div>
              <?php $__k7 = array(); foreach (array('key') as $__x) { $__k7[$__x] = isset($$__x) ? array($$__x) : null; } $__s7 = (array)array('default_bg_color', 'default_text_color'); $__c7 = count($__s7); $__i7 = 0; foreach ($__s7 as $key) { $loop7 = array('index0' => $__i7, 'index' => $__i7 + 1, 'first' => $__i7 === 0, 'last' => $__i7 === $__c7 - 1); $__i7++; ?>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-<?php echo abp15_v($key); ?>"><?php echo abp15_v(${('entry_' . abp15_v($key))}); ?></label>
                <div class="col-sm-9">
                  <div class="input-group abp-color">
                    <span class="input-group-addon"><input type="color" value="<?php echo abp15_e(abp15_v($setting[abp15_v($key)])); ?>" aria-label="<?php echo abp15_e(abp15_v(${('entry_' . abp15_v($key))})); ?>" /></span>
                    <input type="text" name="announcement_bar_pro_<?php echo abp15_v($key); ?>" id="input-<?php echo abp15_v($key); ?>" value="<?php echo abp15_e(abp15_v($setting[abp15_v($key)])); ?>" maxlength="7" class="form-control" />
                  </div>
                  <span class="abp-help"><?php echo abp15_v($help_default_color); ?></span>
                  <?php if (isset($error_settings[abp15_v($key)])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error_settings[abp15_v($key)])); ?></div><?php } ?>
                </div>
              </div>
              <?php } foreach ($__k7 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-position"><?php echo abp15_v($entry_position); ?></label>
                <div class="col-sm-9">
                  <select name="announcement_bar_pro_position" id="input-position" class="form-control">
                    <option value="top"<?php if ((abp15_v($setting['position']) == 'top')) { ?> selected="selected"<?php } ?>><?php echo abp15_v($text_position_top); ?></option>
                    <option value="bottom"<?php if ((abp15_v($setting['position']) == 'bottom')) { ?> selected="selected"<?php } ?>><?php echo abp15_v($text_position_bottom); ?></option>
                  </select>
                  <span class="abp-help"><?php echo abp15_v($help_position); ?></span>
                  <?php if (abp15_v($error_settings['position'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error_settings['position'])); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group" id="abp-sticky-group">
                <label class="col-sm-3 control-label" for="input-sticky"><?php echo abp15_v($entry_sticky); ?></label>
                <div class="col-sm-9">
                  <div class="checkbox"><label><input type="checkbox" name="announcement_bar_pro_sticky" id="input-sticky" value="1"<?php if (abp15_v($setting['sticky'])) { ?> checked="checked"<?php } ?> /> <?php echo abp15_v($text_yes); ?></label></div>
                  <span class="abp-help"><?php echo abp15_v($help_sticky); ?></span>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-font-size"><?php echo abp15_v($entry_font_size); ?></label>
                <div class="col-sm-9">
                  <input type="number" min="10" max="30" name="announcement_bar_pro_font_size" id="input-font-size" value="<?php echo abp15_e(abp15_v($setting['font_size'])); ?>" class="form-control abp-num" />
                  <span class="abp-help"><?php echo abp15_v($help_font_size); ?></span>
                  <?php if (abp15_v($error_settings['font_size'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error_settings['font_size'])); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-padding"><?php echo abp15_v($entry_padding); ?></label>
                <div class="col-sm-9">
                  <input type="number" min="0" max="30" name="announcement_bar_pro_padding" id="input-padding" value="<?php echo abp15_e(abp15_v($setting['padding'])); ?>" class="form-control abp-num" />
                  <span class="abp-help"><?php echo abp15_v($help_padding); ?></span>
                  <?php if (abp15_v($error_settings['padding'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error_settings['padding'])); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-font"><?php echo abp15_v($entry_font); ?></label>
                <div class="col-sm-9">
                  <select name="announcement_bar_pro_font" id="input-font" class="form-control" style="max-width:320px;">
                    <?php $__k8 = array(); foreach (array('f') as $__x) { $__k8[$__x] = isset($$__x) ? array($$__x) : null; } $__s8 = (array)abp15_v($font_list); $__c8 = count($__s8); $__i8 = 0; foreach ($__s8 as $f) { $loop8 = array('index0' => $__i8, 'index' => $__i8 + 1, 'first' => $__i8 === 0, 'last' => $__i8 === $__c8 - 1); $__i8++; ?><option value="<?php echo abp15_v($f['value']); ?>"<?php if ((abp15_v($setting['font']) == abp15_v($f['value']))) { ?> selected="selected"<?php } ?><?php if (abp15_v($f['stack'])) { ?> style="font-family:<?php echo abp15_e(abp15_v($f['stack'])); ?>"<?php } ?>><?php echo abp15_e(abp15_v($f['name'])); ?></option><?php } foreach ($__k8 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
                  </select>
                  <span class="abp-help"><?php echo abp15_v($help_font_module); ?></span>
                  <?php if (abp15_v($error_settings['font'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error_settings['font'])); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-custom-css"><?php echo abp15_v($entry_custom_css); ?></label>
                <div class="col-sm-9">
                  <textarea name="announcement_bar_pro_custom_css" id="input-custom-css" rows="5" class="form-control abp-code" spellcheck="false" placeholder="#abp-bar .abp-btn { border-radius: 6px; }"><?php echo abp15_e(abp15_v($setting['custom_css'])); ?></textarea>
                  <span class="abp-help"><?php echo abp15_v($help_custom_css); ?></span>
                  <?php if (abp15_v($error_settings['custom_css'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error_settings['custom_css'])); ?></div><?php } ?>
                </div>
              </div>
            </div>
          </div>

          <div class="panel panel-default abp-card">
            <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-globe"></i></span> <?php echo abp15_v($text_geo); ?></div></div>
            <div class="panel-body">
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-geo-cf"><?php echo abp15_v($entry_geo_cf); ?></label>
                <div class="col-sm-9">
                  <div class="checkbox"><label><input type="checkbox" name="announcement_bar_pro_geo_cf" id="input-geo-cf" value="1"<?php if (abp15_v($setting['geo_cf'])) { ?> checked="checked"<?php } ?> /> <?php echo abp15_v($text_yes); ?></label></div>
                  <span class="abp-help"><?php echo abp15_v($help_geo_cf); ?></span>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-geo-fallback"><?php echo abp15_v($entry_geo_fallback); ?></label>
                <div class="col-sm-9">
                  <select name="announcement_bar_pro_geo_fallback" id="input-geo-fallback" class="form-control" style="max-width:320px;">
                    <option value="store"<?php if ((abp15_v($setting['geo_fallback']) != 'none')) { ?> selected="selected"<?php } ?>><?php echo abp15_v($text_geo_store); ?></option>
                    <option value="none"<?php if ((abp15_v($setting['geo_fallback']) == 'none')) { ?> selected="selected"<?php } ?>><?php echo abp15_v($text_geo_none); ?></option>
                  </select>
                  <span class="abp-help"><?php echo abp15_v($help_geo_fallback); ?></span>
                  <?php if (abp15_v($error_settings['geo_fallback'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error_settings['geo_fallback'])); ?></div><?php } ?>
                </div>
              </div>
            </div>
          </div>

          <div class="panel panel-default abp-card" id="abp-newsletter">
            <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-envelope"></i></span> <?php echo abp15_v($text_newsletter); ?></div></div>
            <div class="panel-body">
              <?php if (!(abp15_v($mail_ready))) { ?><div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> <?php echo abp15_v($text_mail_missing); ?></div><?php } ?>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-privacy-id"><?php echo abp15_v($entry_privacy_id); ?></label>
                <div class="col-sm-9">
                  <select name="announcement_bar_pro_privacy_id" id="input-privacy-id" class="form-control" style="max-width:360px;">
                    <option value="0"><?php echo abp15_v($text_none); ?></option>
                    <?php $__k9 = array(); foreach (array('page') as $__x) { $__k9[$__x] = isset($$__x) ? array($$__x) : null; } $__s9 = (array)abp15_v($information_pages); $__c9 = count($__s9); $__i9 = 0; foreach ($__s9 as $page) { $loop9 = array('index0' => $__i9, 'index' => $__i9 + 1, 'first' => $__i9 === 0, 'last' => $__i9 === $__c9 - 1); $__i9++; ?><option value="<?php echo abp15_v($page['id']); ?>"<?php if ((abp15_v($setting['privacy_id']) == abp15_v($page['id']))) { ?> selected="selected"<?php } ?>><?php echo abp15_e(abp15_v($page['title'])); ?></option><?php } foreach ($__k9 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
                  </select>
                  <span class="abp-help"><?php echo abp15_v($help_privacy_id); ?></span>
                  <?php if (abp15_v($error_settings['privacy_id'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error_settings['privacy_id'])); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-double-optin"><?php echo abp15_v($entry_double_optin); ?></label>
                <div class="col-sm-9">
                  <div class="checkbox"><label><input type="checkbox" name="announcement_bar_pro_double_optin" id="input-double-optin" value="1"<?php if (abp15_v($setting['double_optin'])) { ?> checked="checked"<?php } ?> /> <?php echo abp15_v($text_yes); ?></label></div>
                  <span class="abp-help"><?php echo abp15_v($help_double_optin); ?></span>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-signup-limit-ip"><?php echo abp15_v($entry_signup_limit_ip); ?></label>
                <div class="col-sm-9">
                  <input type="number" min="1" max="1000" name="announcement_bar_pro_signup_limit_ip" id="input-signup-limit-ip" value="<?php echo abp15_e(abp15_v($setting['signup_limit_ip'])); ?>" class="form-control abp-num" />
                  <span class="abp-help"><?php echo abp15_v($help_signup_limit_ip); ?></span>
                  <?php if (abp15_v($error_settings['signup_limit_ip'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error_settings['signup_limit_ip'])); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-signup-limit-total"><?php echo abp15_v($entry_signup_limit_total); ?></label>
                <div class="col-sm-9">
                  <input type="number" min="1" max="100000" name="announcement_bar_pro_signup_limit_total" id="input-signup-limit-total" value="<?php echo abp15_e(abp15_v($setting['signup_limit_total'])); ?>" class="form-control abp-num" />
                  <span class="abp-help"><?php echo abp15_v($help_signup_limit_total); ?></span>
                  <?php if (abp15_v($error_settings['signup_limit_total'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error_settings['signup_limit_total'])); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group" id="abp-turnstile">
                <label class="col-sm-3 control-label" for="input-ts-site"><?php echo abp15_v($entry_ts_site); ?></label>
                <div class="col-sm-9">
                  <input type="text" name="announcement_bar_pro_ts_site" id="input-ts-site" value="<?php echo abp15_e(abp15_v($setting['ts_site'])); ?>" class="form-control" style="max-width:420px;" maxlength="100" autocomplete="off" spellcheck="false" />
                  <span class="abp-help"><span class="abp-state <?php echo (abp15_v($ts_on) ? 'abp-state-active' : 'abp-state-disabled'); ?>"><?php echo (abp15_v($ts_on) ? abp15_v($text_ts_on) : abp15_v($text_ts_off)); ?></span> <?php echo abp15_v($help_ts); ?> <a href="https://developers.cloudflare.com/turnstile/" target="_blank" rel="noopener noreferrer"><?php echo abp15_v($text_ts_docs); ?></a></span>
                  <?php if (abp15_v($error_settings['ts_site'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error_settings['ts_site'])); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-ts-secret"><?php echo abp15_v($entry_ts_secret); ?></label>
                <div class="col-sm-9">
                  <input type="password" name="announcement_bar_pro_ts_secret" id="input-ts-secret" value="" class="form-control" style="max-width:420px;" maxlength="200" autocomplete="new-password" spellcheck="false" />
                  <span class="abp-help"><?php echo (abp15_v($ts_secret_saved) ? abp15_v($help_ts_secret_saved) : abp15_v($help_ts_secret_new)); ?></span>
                  <?php if (abp15_v($ts_secret_saved)) { ?><div class="checkbox"><label><input type="checkbox" name="announcement_bar_pro_ts_secret_remove" id="input-ts-secret-remove" value="1" /> <?php echo abp15_v($text_ts_remove); ?></label></div><?php } ?>
                  <?php if (abp15_v($error_settings['ts_secret'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error_settings['ts_secret'])); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label"><?php echo abp15_v($entry_subscribers); ?></label>
                <div class="col-sm-9">
                  <p class="form-control-static abp-subs"><span class="abp-state abp-state-active"><?php echo abp15_v($subscriber_counts[1]); ?> <?php echo abp15_v($text_sub_confirmed); ?></span> <span class="abp-state abp-state-scheduled"><?php echo abp15_v($subscriber_counts[0]); ?> <?php echo abp15_v($text_sub_pending); ?></span> <span class="abp-state abp-state-disabled"><?php echo abp15_v($subscriber_counts[2]); ?> <?php echo abp15_v($text_sub_unsubscribed); ?></span></p>
                  <a href="<?php echo abp15_v($export_subscribers); ?>" class="btn btn-default btn-sm"><i class="fa fa-download"></i> <?php echo abp15_v($button_export_subscribers); ?></a>
                  <span class="abp-help"><?php echo abp15_v($help_subscribers); ?></span>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-erase-email"><?php echo abp15_v($entry_erase_email); ?></label>
                <div class="col-sm-9">
                  <div class="input-group" style="max-width:420px;">
                    <input type="email" id="input-erase-email" class="form-control" autocomplete="off" placeholder="name@example.com" form="abp-no-form" />
                    <span class="input-group-btn"><button type="button" class="btn btn-danger" data-abp-erase="<?php echo abp15_v($erase_subscriber); ?>"><i class="fa fa-eraser"></i> <?php echo abp15_v($button_erase); ?></button></span>
                  </div>
                  <span class="abp-help"><?php echo abp15_v($help_erase); ?></span>
                </div>
              </div>
            </div>
          </div>

          <div class="panel panel-default abp-card">
            <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-line-chart"></i></span> <?php echo abp15_v($text_analytics); ?></div></div>
            <div class="panel-body">
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-ga4"><?php echo abp15_v($entry_ga4); ?></label>
                <div class="col-sm-9">
                  <div class="checkbox"><label><input type="checkbox" name="announcement_bar_pro_ga4" id="input-ga4" value="1"<?php if (abp15_v($setting['ga4'])) { ?> checked="checked"<?php } ?> /> <?php echo abp15_v($text_yes); ?></label></div>
                  <span class="abp-help"><?php echo abp15_v($help_ga4); ?></span>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-consent-wait"><?php echo abp15_v($entry_consent_wait); ?></label>
                <div class="col-sm-9">
                  <div class="checkbox"><label><input type="checkbox" name="announcement_bar_pro_consent_wait" id="input-consent-wait" value="1"<?php if (abp15_v($setting['consent_wait'])) { ?> checked="checked"<?php } ?> /> <?php echo abp15_v($text_yes); ?></label></div>
                  <span class="abp-help"><?php echo abp15_v($help_consent_wait); ?></span>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-fa-load"><?php echo abp15_v($entry_fa_load); ?></label>
                <div class="col-sm-9">
                  <div class="checkbox"><label><input type="checkbox" name="announcement_bar_pro_fa_load" id="input-fa-load" value="1"<?php if (abp15_v($setting['fa_load'])) { ?> checked="checked"<?php } ?> /> <?php echo abp15_v($text_yes); ?></label></div>
                  <span class="abp-help"><?php echo abp15_v($help_fa_load); ?></span>
                </div>
              </div>
            </div>
          </div>

          <div class="panel panel-default abp-card">
            <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-times-circle"></i></span> <?php echo abp15_v($text_dismiss); ?></div></div>
            <div class="panel-body">
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-dismissible"><?php echo abp15_v($entry_dismissible); ?></label>
                <div class="col-sm-9">
                  <div class="checkbox"><label><input type="checkbox" name="announcement_bar_pro_dismissible" id="input-dismissible" value="1"<?php if (abp15_v($setting['dismissible'])) { ?> checked="checked"<?php } ?> /> <?php echo abp15_v($text_yes); ?></label></div>
                  <span class="abp-help"><?php echo abp15_v($help_dismissible); ?></span>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-dismiss-days"><?php echo abp15_v($entry_dismiss_days); ?></label>
                <div class="col-sm-9">
                  <input type="number" min="0" max="365" name="announcement_bar_pro_dismiss_days" id="input-dismiss-days" value="<?php echo abp15_e(abp15_v($setting['dismiss_days'])); ?>" class="form-control abp-num" />
                  <span class="abp-help"><?php echo abp15_v($help_dismiss_days); ?></span>
                  <?php if (abp15_v($error_settings['dismiss_days'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error_settings['dismiss_days'])); ?></div><?php } ?>
                </div>
              </div>
            </div>
          </div>

          <div class="panel panel-default abp-card">
            <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-wrench"></i></span> <?php echo abp15_v($text_advanced); ?></div></div>
            <div class="panel-body">
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-delete-data"><?php echo abp15_v($entry_delete_data); ?></label>
                <div class="col-sm-9">
                  <div class="checkbox"><label><input type="checkbox" name="announcement_bar_pro_delete_data" id="input-delete-data" value="1"<?php if (abp15_v($setting['delete_data'])) { ?> checked="checked"<?php } ?> /> <?php echo abp15_v($text_yes); ?></label></div>
                  <span class="abp-help"><?php echo abp15_v($help_delete_data); ?></span>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<script type="application/json" id="abp-config"><?php echo abp15_v($config_json); ?></script>
<script type="text/javascript" src="<?php echo abp15_e(abp15_v($admin_js)); ?>"></script>
<script type="text/javascript"><!--
(function ($) {
  var hex = /^#[0-9a-fA-F]{6}$/;

  // Duplicate / reset: plain buttons that POST their own small form. They must not be type="submit":
  // OpenCart 3 admin common.js submits every form-* on the page when any submit button is clicked.
  // One request only (a double click must not create two copies).
  var abpSent = false;
  $('[data-abp-post]').on('click', function () {
    if (abpSent || this.disabled) { return; }
    if ($(this).data('abp-confirm') && !confirm('<?php echo abp15_js(abp15_v($text_confirm_reset)); ?>')) { return; }
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
    if (confirm('<?php echo abp15_js(abp15_v($text_confirm)); ?>')) { $('#form-delete').submit(); }
  });
})(jQuery);
//--></script>
<script type="text/javascript" src="view/javascript/jquery/ui/jquery-ui-timepicker-addon.js"></script>
<script type="text/javascript" src="<?php echo abp15_e(abp15_v($oc15_js)); ?>"></script>
<?php echo abp15_v($footer); ?>
