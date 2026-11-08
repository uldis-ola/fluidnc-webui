<?php defined('VERSION') || exit; /* Generated from the OpenCart 3 Twig template by tools/twig2tpl.py — edit the Twig source, then rebuild. */ ?>
<?php echo (isset($header) ? $header : null); ?><?php echo (isset($column_left) ? $column_left : null); ?>

<div id="content" class="abp-admin">
  <link rel="stylesheet" href="<?php echo AbpOc2::e((isset($admin_css) ? $admin_css : null)); ?>" />
  <div class="page-header">
    <div class="container-fluid">
      <div class="pull-right">
        <button type="submit" form="form-message" data-toggle="tooltip" title="<?php echo AbpOc2::e((isset($button_save) ? $button_save : null)); ?>" class="btn btn-primary"><i class="fa fa-save"></i></button>
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
    </div>
    <form action="<?php echo (isset($action) ? $action : null); ?>" method="post" enctype="multipart/form-data" id="form-message" class="form-horizontal">

            <?php if (!empty($is_new)) { ?>
      <div class="panel panel-default abp-card" id="abp-card-templates">
        <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-magic"></i></span> <?php echo (isset($text_section_templates) ? $text_section_templates : null); ?> <small><?php echo (isset($help_templates) ? $help_templates : null); ?></small></div></div>
        <div class="panel-body"><div class="abp-tpl-grid" id="abp-templates" role="list"></div></div>
      </div>
      <?php } ?>

            <div class="panel panel-default abp-card abp-preview-card">
        <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-eye"></i></span> <?php echo (isset($text_preview) ? $text_preview : null); ?> <small><?php echo (isset($text_form) ? $text_form : null); ?></small></div></div>
        <div class="panel-body" style="padding-bottom:14px;">
          <div class="abp-pv-tools">
            <span class="abp-pv-label"><?php echo (isset($text_editing_language) ? $text_editing_language : null); ?></span>
            <span class="abp-langs" role="group" aria-label="<?php echo AbpOc2::e((isset($text_editing_language) ? $text_editing_language : null)); ?>">
              <?php $_s2 = (array)(isset($languages) ? $languages : null); $_n2 = count($_s2); $_i2 = 0; $_o2 = isset($loop) ? $loop : null; foreach ($_s2 as $language) { $loop = array('index0' => $_i2, 'index' => $_i2 + 1, 'first' => $_i2 === 0, 'last' => $_i2 === $_n2 - 1); ?><button type="button" data-abp-lang="<?php echo (isset($language['language_id']) ? $language['language_id'] : null); ?>" aria-pressed="false"><img src="<?php echo AbpOc2::e(isset($language['flag']) ? $language['flag'] : null); ?>" alt="" /> <?php echo AbpOc2::e((isset($language['name']) ? $language['name'] : null)); ?></button><?php $_i2++; } $loop = $_o2; ?>
            </span>
            <span class="abp-seg" role="group" aria-label="<?php echo AbpOc2::e((isset($text_preview) ? $text_preview : null)); ?>"><button type="button" class="active" data-abp-pv="device" data-value="desktop" aria-pressed="true"><i class="fa fa-desktop"></i> <?php echo (isset($text_preview_desktop) ? $text_preview_desktop : null); ?></button><button type="button" data-abp-pv="device" data-value="phone" aria-pressed="false"><i class="fa fa-mobile"></i> <?php echo (isset($text_preview_phone) ? $text_preview_phone : null); ?></button></span>
            <span class="abp-seg" role="group" aria-label="<?php echo AbpOc2::e((isset($text_preview_variant) ? $text_preview_variant : null)); ?>"><button type="button" class="active" data-abp-pv="variant" data-value="a" aria-pressed="true">A</button><button type="button" data-abp-pv="variant" data-value="b" aria-pressed="false">B</button></span>
            <span class="abp-seg" id="abp-pv-cart" role="group" aria-label="<?php echo AbpOc2::e((isset($text_preview_cart) ? $text_preview_cart : null)); ?>"><button type="button" class="active" data-abp-pv="cart" data-value="below" aria-pressed="true"><?php echo (isset($text_preview_cart_below) ? $text_preview_cart_below : null); ?></button><button type="button" data-abp-pv="cart" data-value="done" aria-pressed="false"><?php echo (isset($text_preview_cart_done) ? $text_preview_cart_done : null); ?></button></span>
          </div>
          <div id="abp-preview-wrap"><iframe id="abp-preview-frame" title="<?php echo AbpOc2::e((isset($text_preview) ? $text_preview : null)); ?>" sandbox="allow-scripts"></iframe></div>
          <p class="abp-pv-note"><i class="fa fa-info-circle"></i> <?php echo (isset($text_preview_note) ? $text_preview_note : null); ?><?php if (!empty($color_auto)) { ?> <?php echo (isset($text_preview_auto) ? $text_preview_auto : null); ?><?php } ?> <span id="abp-pv-extra"></span></p>
          <div id="abp-contrast" class="text-warning" hidden><i class="fa fa-exclamation-triangle"></i> <?php echo (isset($text_low_contrast) ? $text_low_contrast : null); ?></div>
        </div>
      </div>

            <div class="panel panel-default abp-card" id="abp-card-content">
        <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-pencil"></i></span> <?php echo (isset($text_section_content) ? $text_section_content : null); ?></div></div>
        <div class="panel-body">
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-status"><?php echo (isset($entry_status) ? $entry_status : null); ?></label>
            <div class="col-sm-10">
              <select name="status" id="input-status" class="form-control abp-num">
                <option value="1"<?php if (!empty($status)) { ?> selected="selected"<?php } ?>><?php echo (isset($text_enabled) ? $text_enabled : null); ?></option>
                <option value="0"<?php if (empty($status)) { ?> selected="selected"<?php } ?>><?php echo (isset($text_disabled) ? $text_disabled : null); ?></option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-type"><?php echo (isset($entry_type) ? $entry_type : null); ?></label>
            <div class="col-sm-10">
              <select name="type" id="input-type" class="form-control" style="max-width:320px;">
                <?php $_s3 = (array)array('standard', 'free_shipping', 'tiers'); $_n3 = count($_s3); $_i3 = 0; $_o3 = isset($loop) ? $loop : null; foreach ($_s3 as $value) { $loop = array('index0' => $_i3, 'index' => $_i3 + 1, 'first' => $_i3 === 0, 'last' => $_i3 === $_n3 - 1); ?>
                <option value="<?php echo (isset($value) ? $value : null); ?>"<?php if (((isset($type) ? $type : null) == (isset($value) ? $value : null))) { ?> selected="selected"<?php } ?>><?php echo (isset($_context[('text_type_' . (isset($value) ? $value : null))]) ? $_context[('text_type_' . (isset($value) ? $value : null))] : null); ?></option>
                <?php $_i3++; } $loop = $_o3; ?>
              </select>
              <span class="abp-help"><?php echo (isset($help_type) ? $help_type : null); ?></span>
              <?php if (isset($error['type'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['type']) ? $error['type'] : null)); ?></div><?php } ?>
            </div>
          </div>
          <p class="abp-hint" data-abp-show="type=free_shipping"><i class="fa fa-lightbulb-o"></i> <?php echo (isset($help_shipping_text) ? $help_shipping_text : null); ?></p>
          <p class="abp-hint" data-abp-show="type=tiers"><i class="fa fa-lightbulb-o"></i> <?php echo (isset($help_tiers_text) ? $help_tiers_text : null); ?></p>
          <?php $_s4 = (array)(isset($languages) ? $languages : null); $_n4 = count($_s4); $_i4 = 0; $_o4 = isset($loop) ? $loop : null; foreach ($_s4 as $language) { $loop = array('index0' => $_i4, 'index' => $_i4 + 1, 'first' => $_i4 === 0, 'last' => $_i4 === $_n4 - 1); ?>
          <?php $lid = (isset($language['language_id']) ? $language['language_id'] : null); ?>
          <div class="abp-lang-pane" data-lang="<?php echo (isset($lid) ? $lid : null); ?>">
            <div class="form-group required">
              <label class="col-sm-2 control-label" for="input-text<?php echo (isset($lid) ? $lid : null); ?>"><?php echo (isset($entry_text) ? $entry_text : null); ?><span class="abp-lang-tag"><?php echo AbpOc2::e((isset($language['code']) ? $language['code'] : null)); ?></span></label>
              <div class="col-sm-10">
                <textarea name="message_description[<?php echo (isset($lid) ? $lid : null); ?>][text]" rows="2" maxlength="<?php echo (isset($max_text) ? $max_text : null); ?>" id="input-text<?php echo (isset($lid) ? $lid : null); ?>" class="form-control abp-text"><?php echo AbpOc2::e((isset($message_description[(isset($lid) ? $lid : null)]['text']) ? $message_description[(isset($lid) ? $lid : null)]['text'] : null)); ?></textarea>
                <span class="abp-help"><?php echo (!empty($allow_html) ? (isset($help_text_html) ? $help_text_html : null) : (isset($help_text_plain) ? $help_text_plain : null)); ?> <?php echo (isset($help_countdown_placeholder) ? $help_countdown_placeholder : null); ?> <?php echo (isset($help_lang_fallback) ? $help_lang_fallback : null); ?></span>
                <?php if (isset($error['text'][(isset($lid) ? $lid : null)])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['text'][(isset($lid) ? $lid : null)]) ? $error['text'][(isset($lid) ? $lid : null)] : null)); ?></div><?php } ?>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-2 control-label" for="input-mobile-text<?php echo (isset($lid) ? $lid : null); ?>"><?php echo (isset($entry_mobile_text) ? $entry_mobile_text : null); ?></label>
              <div class="col-sm-10">
                <textarea name="message_description[<?php echo (isset($lid) ? $lid : null); ?>][mobile_text]" rows="2" maxlength="<?php echo (isset($max_text) ? $max_text : null); ?>" id="input-mobile-text<?php echo (isset($lid) ? $lid : null); ?>" class="form-control abp-mobile-text"><?php echo AbpOc2::e((isset($message_description[(isset($lid) ? $lid : null)]['mobile_text']) ? $message_description[(isset($lid) ? $lid : null)]['mobile_text'] : null)); ?></textarea>
                <span class="abp-help"><?php echo (isset($help_mobile_text) ? $help_mobile_text : null); ?></span>
                <?php if (isset($error['mobile_text'][(isset($lid) ? $lid : null)])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['mobile_text'][(isset($lid) ? $lid : null)]) ? $error['mobile_text'][(isset($lid) ? $lid : null)] : null)); ?></div><?php } ?>
              </div>
            </div>
          </div>
          <?php $_i4++; } $loop = $_o4; ?>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-icon"><?php echo (isset($entry_icon) ? $entry_icon : null); ?></label>
            <div class="col-sm-10">
              <div class="input-group" style="max-width:300px;">
                <span class="input-group-addon"><i id="abp-icon-preview" class="fa <?php echo AbpOc2::e((isset($icon) ? $icon : null)); ?>"></i></span>
                <input type="text" name="icon" value="<?php echo AbpOc2::e((isset($icon) ? $icon : null)); ?>" maxlength="44" placeholder="fa-truck" id="input-icon" class="form-control" />
              </div>
              <span class="abp-help"><?php echo (isset($help_icon) ? $help_icon : null); ?></span>
              <?php if (isset($error['icon'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['icon']) ? $error['icon'] : null)); ?></div><?php } ?>
            </div>
          </div>
        </div>
      </div>

            <div class="panel panel-default abp-card" id="abp-card-shipping" data-abp-show="type=free_shipping|tiers">
        <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-truck"></i></span> <?php echo (isset($text_section_goal) ? $text_section_goal : null); ?></div></div>
        <div class="panel-body">
          <div data-abp-show="type=free_shipping">
            <div class="form-group">
              <label class="col-sm-2 control-label" for="input-fs-oc"><?php echo (isset($entry_fs_oc) ? $entry_fs_oc : null); ?></label>
              <div class="col-sm-10">
                <div class="checkbox"><label><input type="checkbox" name="opt[fs_oc]" id="input-fs-oc" value="1"<?php if (!empty($opt['fs_oc'])) { ?> checked="checked"<?php } ?> /> <?php echo (isset($text_fs_oc) ? $text_fs_oc : null); ?></label></div>
                <span class="abp-help"><?php echo AbpOc2::e(sprintf((string)(isset($help_fs_oc) ? $help_fs_oc : null), (isset($oc_free_shipping['total']) ? $oc_free_shipping['total'] : null), (!empty($oc_free_shipping['status']) ? (isset($text_enabled) ? $text_enabled : null) : (isset($text_disabled) ? $text_disabled : null)))); ?></span>
              </div>
            </div>
            <div class="form-group required" data-abp-show="!opt[fs_oc]">
              <label class="col-sm-2 control-label" for="input-threshold"><?php echo (isset($entry_threshold) ? $entry_threshold : null); ?></label>
              <div class="col-sm-10">
                <div class="input-group" style="max-width:220px;">
                  <input type="text" inputmode="decimal" name="threshold" value="<?php echo AbpOc2::e((isset($threshold) ? $threshold : null)); ?>" placeholder="50.00" id="input-threshold" class="form-control" />
                  <span class="input-group-addon"><?php echo AbpOc2::e((isset($currency_code) ? $currency_code : null)); ?></span>
                </div>
                <span class="abp-help"><?php echo AbpOc2::e(sprintf((string)(isset($help_threshold) ? $help_threshold : null), (isset($currency_code) ? $currency_code : null))); ?></span>
                <?php if (isset($error['threshold'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['threshold']) ? $error['threshold'] : null)); ?></div><?php } ?>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-2 control-label"><?php echo (isset($entry_fs_zones) ? $entry_fs_zones : null); ?></label>
              <div class="col-sm-10">
                <div class="table-responsive">
                  <table class="table table-condensed abp-rows" id="abp-zone-rows">
                    <thead><tr><td><?php echo (isset($entry_country) ? $entry_country : null); ?></td><td><?php echo (isset($entry_zone) ? $entry_zone : null); ?></td><td><?php echo (isset($entry_amount) ? $entry_amount : null); ?> (<?php echo AbpOc2::e((isset($currency_code) ? $currency_code : null)); ?>)</td></tr></thead>
                    <tbody>
                      <?php $_s5 = (array)(isset($fs_zone_rows) ? $fs_zone_rows : null); $_n5 = count($_s5); $_i5 = 0; $_o5 = isset($loop) ? $loop : null; foreach ($_s5 as $row) { $loop = array('index0' => $_i5, 'index' => $_i5 + 1, 'first' => $_i5 === 0, 'last' => $_i5 === $_n5 - 1); ?>
                      <?php $i = (isset($loop['index0']) ? $loop['index0'] : null); ?>
                      <tr>
                        <td><select name="opt[fs_zones][<?php echo (isset($i) ? $i : null); ?>][country_id]" class="form-control abp-zone-country" aria-label="<?php echo AbpOc2::e((isset($entry_country) ? $entry_country : null)); ?>"><option value="0">—</option><?php $_s6 = (array)(isset($country_list) ? $country_list : null); $_n6 = count($_s6); $_i6 = 0; $_o6 = isset($loop) ? $loop : null; foreach ($_s6 as $id => $name) { $loop = array('index0' => $_i6, 'index' => $_i6 + 1, 'first' => $_i6 === 0, 'last' => $_i6 === $_n6 - 1); ?><option value="<?php echo (isset($id) ? $id : null); ?>"<?php if (((isset($id) ? $id : null) == (isset($row['country_id']) ? $row['country_id'] : null))) { ?> selected="selected"<?php } ?>><?php echo AbpOc2::e((isset($name) ? $name : null)); ?></option><?php $_i6++; } $loop = $_o6; ?></select></td>
                        <td><select name="opt[fs_zones][<?php echo (isset($i) ? $i : null); ?>][zone_id]" class="form-control abp-zone-zone" aria-label="<?php echo AbpOc2::e((isset($entry_zone) ? $entry_zone : null)); ?>"><option value="0"><?php echo (isset($text_all_zones) ? $text_all_zones : null); ?></option><?php $_s7 = (array)(isset($row['zones']) ? $row['zones'] : null); $_n7 = count($_s7); $_i7 = 0; $_o7 = isset($loop) ? $loop : null; foreach ($_s7 as $zone) { $loop = array('index0' => $_i7, 'index' => $_i7 + 1, 'first' => $_i7 === 0, 'last' => $_i7 === $_n7 - 1); ?><option value="<?php echo (isset($zone['id']) ? $zone['id'] : null); ?>"<?php if (((isset($zone['id']) ? $zone['id'] : null) == (isset($row['zone_id']) ? $row['zone_id'] : null))) { ?> selected="selected"<?php } ?>><?php echo AbpOc2::e((isset($zone['name']) ? $zone['name'] : null)); ?></option><?php $_i7++; } $loop = $_o7; ?></select></td>
                        <td><input type="text" inputmode="decimal" name="opt[fs_zones][<?php echo (isset($i) ? $i : null); ?>][amount]" value="<?php echo AbpOc2::e((isset($row['amount']) ? $row['amount'] : null)); ?>" class="form-control" aria-label="<?php echo AbpOc2::e((isset($entry_amount) ? $entry_amount : null)); ?>" /></td>
                      </tr>
                      <?php $_i5++; } $loop = $_o5; ?>
                    </tbody>
                  </table>
                </div>
                <button type="button" class="btn btn-default btn-sm" data-abp-add-row="#abp-zone-rows"><i class="fa fa-plus"></i> <?php echo (isset($button_add_row) ? $button_add_row : null); ?></button>
                <span class="abp-help"><?php echo (isset($help_fs_zones) ? $help_fs_zones : null); ?></span>
                <?php if (isset($error['opt_fs_zones'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['opt_fs_zones']) ? $error['opt_fs_zones'] : null)); ?></div><?php } ?>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-2 control-label"><?php echo (isset($entry_fs_currencies) ? $entry_fs_currencies : null); ?></label>
              <div class="col-sm-10">
                <?php if (!empty($currency_rows)) { ?>
                <div class="abp-cur-grid">
                  <?php $_s8 = (array)(isset($currency_rows) ? $currency_rows : null); $_n8 = count($_s8); $_i8 = 0; $_o8 = isset($loop) ? $loop : null; foreach ($_s8 as $c) { $loop = array('index0' => $_i8, 'index' => $_i8 + 1, 'first' => $_i8 === 0, 'last' => $_i8 === $_n8 - 1); ?><div class="input-group"><input type="text" inputmode="decimal" name="opt[fs_currencies][<?php echo AbpOc2::e((isset($c['code']) ? $c['code'] : null)); ?>]" value="<?php echo AbpOc2::e((isset($c['amount']) ? $c['amount'] : null)); ?>" class="form-control" aria-label="<?php echo AbpOc2::e((isset($entry_fs_currencies) ? $entry_fs_currencies : null)); ?> <?php echo AbpOc2::e((isset($c['code']) ? $c['code'] : null)); ?>" placeholder="—" /><span class="input-group-addon" title="<?php echo AbpOc2::e((isset($c['title']) ? $c['title'] : null)); ?>"><?php echo AbpOc2::e((isset($c['code']) ? $c['code'] : null)); ?></span></div><?php $_i8++; } $loop = $_o8; ?>
                </div>
                <span class="abp-help"><?php echo (isset($help_fs_currencies) ? $help_fs_currencies : null); ?></span>
                <?php } else { ?>
                <span class="abp-help"><?php echo (isset($text_one_currency) ? $text_one_currency : null); ?></span>
                <?php } ?>
                <?php if (isset($error['opt_fs_currencies'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['opt_fs_currencies']) ? $error['opt_fs_currencies'] : null)); ?></div><?php } ?>
              </div>
            </div>
          </div>
          <div data-abp-show="type=tiers">
            <div class="form-group required">
              <label class="col-sm-2 control-label"><?php echo (isset($entry_tiers) ? $entry_tiers : null); ?></label>
              <div class="col-sm-10">
                <div class="table-responsive">
                  <table class="table table-condensed abp-rows abp-tiers">
                    <thead><tr><td style="width:1px;">#</td><td style="width:150px;"><?php echo (isset($entry_amount) ? $entry_amount : null); ?> (<?php echo AbpOc2::e((isset($currency_code) ? $currency_code : null)); ?>)</td><td><?php echo (isset($entry_tier_label) ? $entry_tier_label : null); ?></td><td><?php echo (isset($entry_tier_text) ? $entry_tier_text : null); ?></td></tr></thead>
                    <tbody>
                      <?php $_s9 = (array)range(0, 4); $_n9 = count($_s9); $_i9 = 0; $_o9 = isset($loop) ? $loop : null; foreach ($_s9 as $i) { $loop = array('index0' => $_i9, 'index' => $_i9 + 1, 'first' => $_i9 === 0, 'last' => $_i9 === $_n9 - 1); ?>
                      <tr>
                        <td class="abp-muted"><?php echo ((isset($i) ? $i : null) + 1); ?></td>
                        <td><input type="text" inputmode="decimal" name="opt[tiers][]" value="<?php echo AbpOc2::e((isset($tier_rows[(isset($i) ? $i : null)]) ? $tier_rows[(isset($i) ? $i : null)] : null)); ?>" class="form-control abp-tier-amount" aria-label="<?php echo AbpOc2::e(sprintf((string)(isset($text_tier) ? $text_tier : null), ((isset($i) ? $i : null) + 1))); ?> — <?php echo AbpOc2::e((isset($entry_amount) ? $entry_amount : null)); ?>" /></td>
                        <td><?php $_s10 = (array)(isset($languages) ? $languages : null); $_n10 = count($_s10); $_i10 = 0; $_o10 = isset($loop) ? $loop : null; foreach ($_s10 as $language) { $loop = array('index0' => $_i10, 'index' => $_i10 + 1, 'first' => $_i10 === 0, 'last' => $_i10 === $_n10 - 1); ?><?php $lid = (isset($language['language_id']) ? $language['language_id'] : null); ?><span class="abp-lang-pane abp-lang-inline" data-lang="<?php echo (isset($lid) ? $lid : null); ?>"><input type="text" name="message_description[<?php echo (isset($lid) ? $lid : null); ?>][tier_label][]" value="<?php echo AbpOc2::e((isset($message_description[(isset($lid) ? $lid : null)]['tier_label'][(isset($i) ? $i : null)]) ? $message_description[(isset($lid) ? $lid : null)]['tier_label'][(isset($i) ? $i : null)] : null)); ?>" maxlength="160" class="form-control" aria-label="<?php echo AbpOc2::e(sprintf((string)(isset($text_tier) ? $text_tier : null), ((isset($i) ? $i : null) + 1))); ?> — <?php echo AbpOc2::e((isset($entry_tier_label) ? $entry_tier_label : null)); ?> (<?php echo AbpOc2::e((isset($language['code']) ? $language['code'] : null)); ?>)" /></span><?php $_i10++; } $loop = $_o10; ?></td>
                        <td><?php $_s11 = (array)(isset($languages) ? $languages : null); $_n11 = count($_s11); $_i11 = 0; $_o11 = isset($loop) ? $loop : null; foreach ($_s11 as $language) { $loop = array('index0' => $_i11, 'index' => $_i11 + 1, 'first' => $_i11 === 0, 'last' => $_i11 === $_n11 - 1); ?><?php $lid = (isset($language['language_id']) ? $language['language_id'] : null); ?><span class="abp-lang-pane abp-lang-inline" data-lang="<?php echo (isset($lid) ? $lid : null); ?>"><input type="text" name="message_description[<?php echo (isset($lid) ? $lid : null); ?>][tier_text][]" value="<?php echo AbpOc2::e((isset($message_description[(isset($lid) ? $lid : null)]['tier_text'][(isset($i) ? $i : null)]) ? $message_description[(isset($lid) ? $lid : null)]['tier_text'][(isset($i) ? $i : null)] : null)); ?>" maxlength="300" class="form-control" aria-label="<?php echo AbpOc2::e(sprintf((string)(isset($text_tier) ? $text_tier : null), ((isset($i) ? $i : null) + 1))); ?> — <?php echo AbpOc2::e((isset($entry_tier_text) ? $entry_tier_text : null)); ?> (<?php echo AbpOc2::e((isset($language['code']) ? $language['code'] : null)); ?>)" /></span><?php $_i11++; } $loop = $_o11; ?></td>
                      </tr>
                      <?php $_i9++; } $loop = $_o9; ?>
                    </tbody>
                  </table>
                </div>
                <span class="abp-help"><?php echo (isset($help_tiers) ? $help_tiers : null); ?></span>
                <?php if (isset($error['opt_tiers'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['opt_tiers']) ? $error['opt_tiers'] : null)); ?></div><?php } ?>
                <?php $_s12 = (array)(isset($languages) ? $languages : null); $_n12 = count($_s12); $_i12 = 0; $_o12 = isset($loop) ? $loop : null; foreach ($_s12 as $language) { $loop = array('index0' => $_i12, 'index' => $_i12 + 1, 'first' => $_i12 === 0, 'last' => $_i12 === $_n12 - 1); ?><?php if (isset($error['tier_label'][(isset($language['language_id']) ? $language['language_id'] : null)])) { ?><div class="text-danger abp-lang-pane abp-lang-inline" data-lang="<?php echo (isset($language['language_id']) ? $language['language_id'] : null); ?>"><?php echo AbpOc2::e((isset($error['tier_label'][(isset($language['language_id']) ? $language['language_id'] : null)]) ? $error['tier_label'][(isset($language['language_id']) ? $language['language_id'] : null)] : null)); ?></div><?php } ?><?php if (isset($error['tier_text'][(isset($language['language_id']) ? $language['language_id'] : null)])) { ?><div class="text-danger abp-lang-pane abp-lang-inline" data-lang="<?php echo (isset($language['language_id']) ? $language['language_id'] : null); ?>"><?php echo AbpOc2::e((isset($error['tier_text'][(isset($language['language_id']) ? $language['language_id'] : null)]) ? $error['tier_text'][(isset($language['language_id']) ? $language['language_id'] : null)] : null)); ?></div><?php } ?><?php $_i12++; } $loop = $_o12; ?>
              </div>
            </div>
          </div>
          <?php $_s13 = (array)(isset($languages) ? $languages : null); $_n13 = count($_s13); $_i13 = 0; $_o13 = isset($loop) ? $loop : null; foreach ($_s13 as $language) { $loop = array('index0' => $_i13, 'index' => $_i13 + 1, 'first' => $_i13 === 0, 'last' => $_i13 === $_n13 - 1); ?>
          <?php $lid = (isset($language['language_id']) ? $language['language_id'] : null); ?>
          <div class="abp-lang-pane" data-lang="<?php echo (isset($lid) ? $lid : null); ?>">
            <div class="form-group required">
              <label class="col-sm-2 control-label" for="input-success-text<?php echo (isset($lid) ? $lid : null); ?>"><?php echo (isset($entry_success_text) ? $entry_success_text : null); ?><span class="abp-lang-tag"><?php echo AbpOc2::e((isset($language['code']) ? $language['code'] : null)); ?></span></label>
              <div class="col-sm-10">
                <textarea name="message_description[<?php echo (isset($lid) ? $lid : null); ?>][success_text]" rows="2" maxlength="<?php echo (isset($max_text) ? $max_text : null); ?>" id="input-success-text<?php echo (isset($lid) ? $lid : null); ?>" class="form-control"><?php echo AbpOc2::e((isset($message_description[(isset($lid) ? $lid : null)]['success_text']) ? $message_description[(isset($lid) ? $lid : null)]['success_text'] : null)); ?></textarea>
                <span class="abp-help"><?php echo (isset($help_success_text) ? $help_success_text : null); ?></span>
                <?php if (isset($error['success_text'][(isset($lid) ? $lid : null)])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['success_text'][(isset($lid) ? $lid : null)]) ? $error['success_text'][(isset($lid) ? $lid : null)] : null)); ?></div><?php } ?>
              </div>
            </div>
          </div>
          <?php $_i13++; } $loop = $_o13; ?>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-progress-bar"><?php echo (isset($entry_progress_bar) ? $entry_progress_bar : null); ?></label>
            <div class="col-sm-10">
              <div class="checkbox"><label><input type="checkbox" name="progress_bar" id="input-progress-bar" value="1"<?php if (!empty($progress_bar)) { ?> checked="checked"<?php } ?> /> <?php echo (isset($text_progress_bar) ? $text_progress_bar : null); ?></label></div>
            </div>
          </div>
        </div>
      </div>

            <div class="panel panel-default abp-card" id="abp-card-button">
        <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-hand-pointer-o"></i></span> <?php echo (isset($text_section_button) ? $text_section_button : null); ?></div></div>
        <div class="panel-body">
          <?php $_s14 = (array)(isset($languages) ? $languages : null); $_n14 = count($_s14); $_i14 = 0; $_o14 = isset($loop) ? $loop : null; foreach ($_s14 as $language) { $loop = array('index0' => $_i14, 'index' => $_i14 + 1, 'first' => $_i14 === 0, 'last' => $_i14 === $_n14 - 1); ?>
          <?php $lid = (isset($language['language_id']) ? $language['language_id'] : null); ?>
          <div class="abp-lang-pane" data-lang="<?php echo (isset($lid) ? $lid : null); ?>">
            <div class="form-group">
              <label class="col-sm-2 control-label" for="input-link-text<?php echo (isset($lid) ? $lid : null); ?>"><?php echo (isset($entry_link_text) ? $entry_link_text : null); ?><span class="abp-lang-tag"><?php echo AbpOc2::e((isset($language['code']) ? $language['code'] : null)); ?></span></label>
              <div class="col-sm-10">
                <input type="text" name="message_description[<?php echo (isset($lid) ? $lid : null); ?>][link_text]" value="<?php echo AbpOc2::e((isset($message_description[(isset($lid) ? $lid : null)]['link_text']) ? $message_description[(isset($lid) ? $lid : null)]['link_text'] : null)); ?>" maxlength="<?php echo (isset($max_link_text) ? $max_link_text : null); ?>" id="input-link-text<?php echo (isset($lid) ? $lid : null); ?>" class="form-control abp-link-text" />
                <span class="abp-help"><?php echo (isset($help_link_text) ? $help_link_text : null); ?></span>
                <?php if (isset($error['link_text'][(isset($lid) ? $lid : null)])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['link_text'][(isset($lid) ? $lid : null)]) ? $error['link_text'][(isset($lid) ? $lid : null)] : null)); ?></div><?php } ?>
              </div>
            </div>
          </div>
          <?php $_i14++; } $loop = $_o14; ?>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-link"><?php echo (isset($entry_link) ? $entry_link : null); ?></label>
            <div class="col-sm-10">
              <input type="text" name="link" value="<?php echo AbpOc2::e((isset($link) ? $link : null)); ?>" maxlength="255" placeholder="https://… / index.php?route=…" id="input-link" class="form-control" />
              <div class="checkbox"><label><input type="checkbox" name="link_new_tab" value="1"<?php if (!empty($link_new_tab)) { ?> checked="checked"<?php } ?> /> <?php echo (isset($entry_link_new_tab) ? $entry_link_new_tab : null); ?></label></div>
              <span class="abp-help"><?php echo (isset($help_link) ? $help_link : null); ?></span>
              <?php if (isset($error['link'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['link']) ? $error['link'] : null)); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label"><?php echo (isset($entry_button_style) ? $entry_button_style : null); ?></label>
            <div class="col-sm-10">
              <span class="abp-seg" role="radiogroup" aria-label="<?php echo AbpOc2::e((isset($entry_button_style) ? $entry_button_style : null)); ?>">
                <?php $_s15 = (array)array('filled', 'outline', 'link'); $_n15 = count($_s15); $_i15 = 0; $_o15 = isset($loop) ? $loop : null; foreach ($_s15 as $value) { $loop = array('index0' => $_i15, 'index' => $_i15 + 1, 'first' => $_i15 === 0, 'last' => $_i15 === $_n15 - 1); ?><input type="radio" name="button_style" id="input-button-style-<?php echo (isset($value) ? $value : null); ?>" value="<?php echo (isset($value) ? $value : null); ?>"<?php if (((isset($button_style) ? $button_style : null) == (isset($value) ? $value : null))) { ?> checked="checked"<?php } ?> /><label for="input-button-style-<?php echo (isset($value) ? $value : null); ?>"><?php echo (isset($_context[('text_button_' . (isset($value) ? $value : null))]) ? $_context[('text_button_' . (isset($value) ? $value : null))] : null); ?></label><?php $_i15++; } $loop = $_o15; ?>
              </span>
              <?php if (isset($error['button_style'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['button_style']) ? $error['button_style'] : null)); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-coupon-id"><?php echo (isset($entry_coupon_id) ? $entry_coupon_id : null); ?></label>
            <div class="col-sm-10">
              <div class="abp-chips" id="input-coupon-id" data-abp-ac="coupon" data-name="opt[coupon_id]" data-max="1">
                <?php $_s16 = (array)(isset($coupon_chip) ? $coupon_chip : null); $_n16 = count($_s16); $_i16 = 0; $_o16 = isset($loop) ? $loop : null; foreach ($_s16 as $id => $name) { $loop = array('index0' => $_i16, 'index' => $_i16 + 1, 'first' => $_i16 === 0, 'last' => $_i16 === $_n16 - 1); ?><span class="abp-chip"><span class="abp-chip-text"><?php echo AbpOc2::e((isset($name) ? $name : null)); ?></span><input type="hidden" name="opt[coupon_id]" value="<?php echo (isset($id) ? $id : null); ?>" /><button type="button" class="abp-chip-x" aria-label="<?php echo AbpOc2::e((isset($button_remove) ? $button_remove : null)); ?>: <?php echo AbpOc2::e((isset($name) ? $name : null)); ?>">&times;</button></span><?php $_i16++; } $loop = $_o16; ?>
                <input type="text" class="abp-ac-input" placeholder="<?php echo AbpOc2::e((isset($text_autocomplete_coupon) ? $text_autocomplete_coupon : null)); ?>" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-label="<?php echo AbpOc2::e((isset($entry_coupon_id) ? $entry_coupon_id : null)); ?>" />
                <div class="abp-ac-menu" role="listbox" hidden></div>
              </div>
              <span class="abp-help"><?php echo (isset($help_coupon_id) ? $help_coupon_id : null); ?></span>
              <?php if (isset($error['opt_coupon_id'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['opt_coupon_id']) ? $error['opt_coupon_id'] : null)); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group" data-abp-show="!opt[coupon_id]">
            <label class="col-sm-2 control-label" for="input-coupon"><?php echo (isset($entry_coupon) ? $entry_coupon : null); ?></label>
            <div class="col-sm-10">
              <div class="input-group" style="max-width:300px;">
                <span class="input-group-addon"><i class="fa fa-ticket"></i></span>
                <input type="text" name="coupon" value="<?php echo AbpOc2::e((isset($coupon) ? $coupon : null)); ?>" maxlength="32" placeholder="SPRING10" id="input-coupon" class="form-control" style="text-transform:uppercase;font-family:monospace;letter-spacing:.06em;" autocomplete="off" />
              </div>
              <span class="abp-help"><?php echo (isset($help_coupon) ? $help_coupon : null); ?></span>
              <?php if (isset($error['coupon'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['coupon']) ? $error['coupon'] : null)); ?></div><?php } ?>
            </div>
          </div>
        </div>
      </div>

            <div class="panel panel-default abp-card" id="abp-card-email">
        <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-envelope"></i></span> <?php echo (isset($text_section_email) ? $text_section_email : null); ?></div></div>
        <div class="panel-body">
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-email"><?php echo (isset($entry_email_capture) ? $entry_email_capture : null); ?></label>
            <div class="col-sm-10">
              <div class="checkbox"><label><input type="checkbox" name="opt[email]" id="input-email" value="1"<?php if (!empty($opt['email'])) { ?> checked="checked"<?php } ?> /> <?php echo (isset($text_email_capture) ? $text_email_capture : null); ?></label></div>
              <span class="abp-help"><?php echo (isset($help_email_capture) ? $help_email_capture : null); ?></span>
            </div>
          </div>
          <div data-abp-show="opt[email]">
            <?php $_s17 = (array)(isset($languages) ? $languages : null); $_n17 = count($_s17); $_i17 = 0; $_o17 = isset($loop) ? $loop : null; foreach ($_s17 as $language) { $loop = array('index0' => $_i17, 'index' => $_i17 + 1, 'first' => $_i17 === 0, 'last' => $_i17 === $_n17 - 1); ?>
            <?php $lid = (isset($language['language_id']) ? $language['language_id'] : null); ?>
            <div class="abp-lang-pane" data-lang="<?php echo (isset($lid) ? $lid : null); ?>">
              <?php $_s18 = (array)array('email_placeholder', 'email_button', 'email_consent', 'email_success'); $_n18 = count($_s18); $_i18 = 0; $_o18 = isset($loop) ? $loop : null; foreach ($_s18 as $field) { $loop = array('index0' => $_i18, 'index' => $_i18 + 1, 'first' => $_i18 === 0, 'last' => $_i18 === $_n18 - 1); ?>
              <div class="form-group">
                <label class="col-sm-2 control-label" for="input-<?php echo (isset($field) ? $field : null); ?><?php echo (isset($lid) ? $lid : null); ?>"><?php echo (isset($_context[('entry_' . (isset($field) ? $field : null))]) ? $_context[('entry_' . (isset($field) ? $field : null))] : null); ?><span class="abp-lang-tag"><?php echo AbpOc2::e((isset($language['code']) ? $language['code'] : null)); ?></span></label>
                <div class="col-sm-10">
                  <input type="text" name="message_description[<?php echo (isset($lid) ? $lid : null); ?>][<?php echo (isset($field) ? $field : null); ?>]" value="<?php echo AbpOc2::e((isset($message_description[(isset($lid) ? $lid : null)][(isset($field) ? $field : null)]) ? $message_description[(isset($lid) ? $lid : null)][(isset($field) ? $field : null)] : null)); ?>" maxlength="<?php echo (((isset($field) ? $field : null) == 'email_consent') ? 300 : 160); ?>" id="input-<?php echo (isset($field) ? $field : null); ?><?php echo (isset($lid) ? $lid : null); ?>" class="form-control" placeholder="<?php echo AbpOc2::e((isset($_context[(('text_' . (isset($field) ? $field : null)) . '_default')]) ? $_context[(('text_' . (isset($field) ? $field : null)) . '_default')] : null)); ?>" />
                  <?php if ((isset($error[(isset($field) ? $field : null)]) && isset($error[(isset($field) ? $field : null)][(isset($lid) ? $lid : null)]))) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error[(isset($field) ? $field : null)][(isset($lid) ? $lid : null)]) ? $error[(isset($field) ? $field : null)][(isset($lid) ? $lid : null)] : null)); ?></div><?php } ?>
                </div>
              </div>
              <?php $_i18++; } $loop = $_o18; ?>
            </div>
            <?php $_i17++; } $loop = $_o17; ?>
            <p class="abp-hint"><i class="fa fa-shield"></i> <?php echo (isset($help_email_texts) ? $help_email_texts : null); ?></p>
          </div>
        </div>
      </div>

            <div class="panel panel-default abp-card" id="abp-card-ab">
        <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-random"></i></span> <?php echo (isset($text_section_ab) ? $text_section_ab : null); ?></div></div>
        <div class="panel-body">
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-ab-test"><?php echo (isset($entry_ab_test) ? $entry_ab_test : null); ?></label>
            <div class="col-sm-10">
              <div class="checkbox"><label><input type="checkbox" name="ab_test" id="input-ab-test" value="1"<?php if (!empty($ab_test)) { ?> checked="checked"<?php } ?> /> <?php echo (isset($text_ab_enable) ? $text_ab_enable : null); ?></label></div>
              <span class="abp-help"><?php echo (isset($help_ab) ? $help_ab : null); ?></span>
            </div>
          </div>
          <div data-abp-show="ab_test">
            <?php $_s19 = (array)(isset($languages) ? $languages : null); $_n19 = count($_s19); $_i19 = 0; $_o19 = isset($loop) ? $loop : null; foreach ($_s19 as $language) { $loop = array('index0' => $_i19, 'index' => $_i19 + 1, 'first' => $_i19 === 0, 'last' => $_i19 === $_n19 - 1); ?>
            <?php $lid = (isset($language['language_id']) ? $language['language_id'] : null); ?>
            <div class="abp-lang-pane" data-lang="<?php echo (isset($lid) ? $lid : null); ?>">
              <div class="form-group required">
                <label class="col-sm-2 control-label" for="input-text-b<?php echo (isset($lid) ? $lid : null); ?>"><?php echo (isset($entry_text_b) ? $entry_text_b : null); ?><span class="abp-lang-tag"><?php echo AbpOc2::e((isset($language['code']) ? $language['code'] : null)); ?></span></label>
                <div class="col-sm-10">
                  <textarea name="message_description[<?php echo (isset($lid) ? $lid : null); ?>][text_b]" rows="2" maxlength="<?php echo (isset($max_text) ? $max_text : null); ?>" id="input-text-b<?php echo (isset($lid) ? $lid : null); ?>" class="form-control"><?php echo AbpOc2::e((isset($message_description[(isset($lid) ? $lid : null)]['text_b']) ? $message_description[(isset($lid) ? $lid : null)]['text_b'] : null)); ?></textarea>
                  <?php if (isset($error['text_b'][(isset($lid) ? $lid : null)])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['text_b'][(isset($lid) ? $lid : null)]) ? $error['text_b'][(isset($lid) ? $lid : null)] : null)); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-2 control-label" for="input-mobile-text-b<?php echo (isset($lid) ? $lid : null); ?>"><?php echo (isset($entry_mobile_text_b) ? $entry_mobile_text_b : null); ?></label>
                <div class="col-sm-10">
                  <textarea name="message_description[<?php echo (isset($lid) ? $lid : null); ?>][mobile_text_b]" rows="2" maxlength="<?php echo (isset($max_text) ? $max_text : null); ?>" id="input-mobile-text-b<?php echo (isset($lid) ? $lid : null); ?>" class="form-control"><?php echo AbpOc2::e((isset($message_description[(isset($lid) ? $lid : null)]['mobile_text_b']) ? $message_description[(isset($lid) ? $lid : null)]['mobile_text_b'] : null)); ?></textarea>
                  <?php if (isset($error['mobile_text_b'][(isset($lid) ? $lid : null)])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['mobile_text_b'][(isset($lid) ? $lid : null)]) ? $error['mobile_text_b'][(isset($lid) ? $lid : null)] : null)); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-2 control-label" for="input-link-text-b<?php echo (isset($lid) ? $lid : null); ?>"><?php echo (isset($entry_link_text_b) ? $entry_link_text_b : null); ?></label>
                <div class="col-sm-10">
                  <input type="text" name="message_description[<?php echo (isset($lid) ? $lid : null); ?>][link_text_b]" value="<?php echo AbpOc2::e((isset($message_description[(isset($lid) ? $lid : null)]['link_text_b']) ? $message_description[(isset($lid) ? $lid : null)]['link_text_b'] : null)); ?>" maxlength="<?php echo (isset($max_link_text) ? $max_link_text : null); ?>" id="input-link-text-b<?php echo (isset($lid) ? $lid : null); ?>" class="form-control" />
                  <span class="abp-help"><?php echo (isset($help_link_text_b) ? $help_link_text_b : null); ?></span>
                  <?php if (isset($error['link_text_b'][(isset($lid) ? $lid : null)])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['link_text_b'][(isset($lid) ? $lid : null)]) ? $error['link_text_b'][(isset($lid) ? $lid : null)] : null)); ?></div><?php } ?>
                </div>
              </div>
            </div>
            <?php $_i19++; } $loop = $_o19; ?>
          </div>
        </div>
      </div>

            <div class="panel panel-default abp-card" id="abp-card-behaviour">
        <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-th-large"></i></span> <?php echo (isset($text_section_behaviour) ? $text_section_behaviour : null); ?></div></div>
        <div class="panel-body">
          <div class="form-group">
            <label class="col-sm-2 control-label"><?php echo (isset($entry_placement) ? $entry_placement : null); ?></label>
            <div class="col-sm-10">
              <div class="abp-places" role="radiogroup" aria-label="<?php echo AbpOc2::e((isset($entry_placement) ? $entry_placement : null)); ?>">
                <?php $_s20 = (array)(isset($placements) ? $placements : null); $_n20 = count($_s20); $_i20 = 0; $_o20 = isset($loop) ? $loop : null; foreach ($_s20 as $value) { $loop = array('index0' => $_i20, 'index' => $_i20 + 1, 'first' => $_i20 === 0, 'last' => $_i20 === $_n20 - 1); ?>
                <?php $key = (((isset($value) ? $value : null) == '') ? 'default' : (isset($value) ? $value : null)); ?>
                <div class="abp-place-opt"><input type="radio" name="placement" id="input-placement-<?php echo (isset($key) ? $key : null); ?>" value="<?php echo (isset($value) ? $value : null); ?>"<?php if (((isset($placement) ? $placement : null) == (isset($value) ? $value : null))) { ?> checked="checked"<?php } ?> /><label for="input-placement-<?php echo (isset($key) ? $key : null); ?>"><span class="abp-pl-ico abp-pl-<?php echo (isset($key) ? $key : null); ?>" aria-hidden="true"><i></i></span><span class="abp-name"><?php echo (isset($_context[('text_placement_' . (isset($key) ? $key : null))]) ? $_context[('text_placement_' . (isset($key) ? $key : null))] : null); ?></span></label></div>
                <?php $_i20++; } $loop = $_o20; ?>
              </div>
              <span class="abp-help"><?php echo (isset($help_placement) ? $help_placement : null); ?></span>
              <?php if (isset($error['placement'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['placement']) ? $error['placement'] : null)); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-priority"><?php echo (isset($entry_priority) ? $entry_priority : null); ?></label>
            <div class="col-sm-10">
              <input type="number" min="0" max="100" name="priority" value="<?php echo AbpOc2::e((isset($priority) ? $priority : null)); ?>" id="input-priority" class="form-control abp-num" />
              <span class="abp-help"><?php echo (isset($help_priority) ? $help_priority : null); ?></span>
              <?php if (isset($error['priority'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['priority']) ? $error['priority'] : null)); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-trigger"><?php echo (isset($entry_trigger) ? $entry_trigger : null); ?></label>
            <div class="col-sm-10">
              <div class="abp-inline-fields">
                <select name="opt[trigger]" id="input-trigger" class="form-control" style="max-width:260px;">
                  <?php $_s21 = (array)array('none', 'delay', 'scroll', 'exit'); $_n21 = count($_s21); $_i21 = 0; $_o21 = isset($loop) ? $loop : null; foreach ($_s21 as $value) { $loop = array('index0' => $_i21, 'index' => $_i21 + 1, 'first' => $_i21 === 0, 'last' => $_i21 === $_n21 - 1); ?><option value="<?php echo (isset($value) ? $value : null); ?>"<?php if (((isset($opt['trigger']) ? $opt['trigger'] : null) == (isset($value) ? $value : null))) { ?> selected="selected"<?php } ?>><?php echo (isset($_context[('text_trigger_' . (isset($value) ? $value : null))]) ? $_context[('text_trigger_' . (isset($value) ? $value : null))] : null); ?></option><?php $_i21++; } $loop = $_o21; ?>
                </select>
                <span data-abp-show="opt[trigger]=delay|scroll" class="abp-inline-fields"><input type="number" min="1" name="opt[trigger_value]" value="<?php echo (!empty($opt['trigger_value']) ? AbpOc2::e((isset($opt['trigger_value']) ? $opt['trigger_value'] : null)) : ''); ?>" id="input-trigger-value" class="form-control abp-num" aria-label="<?php echo AbpOc2::e((isset($entry_trigger_value) ? $entry_trigger_value : null)); ?>" /><span class="abp-unit" data-abp-show="opt[trigger]=delay"><?php echo (isset($text_seconds) ? $text_seconds : null); ?></span><span class="abp-unit" data-abp-show="opt[trigger]=scroll">%</span></span>
              </div>
              <span class="abp-help"><?php echo (isset($help_trigger) ? $help_trigger : null); ?></span>
              <?php if (isset($error['opt_trigger'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['opt_trigger']) ? $error['opt_trigger'] : null)); ?></div><?php } ?>
            </div>
          </div>
        </div>
      </div>

            <div class="panel panel-default abp-card" id="abp-card-schedule">
        <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-calendar"></i></span> <?php echo (isset($text_section_schedule) ? $text_section_schedule : null); ?></div></div>
        <div class="panel-body">
          <?php $_s22 = (array)array('date_start', 'date_end'); $_n22 = count($_s22); $_i22 = 0; $_o22 = isset($loop) ? $loop : null; foreach ($_s22 as $key) { $loop = array('index0' => $_i22, 'index' => $_i22 + 1, 'first' => $_i22 === 0, 'last' => $_i22 === $_n22 - 1); ?>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-<?php echo (isset($key) ? $key : null); ?>"><?php echo (isset($_context[('entry_' . (isset($key) ? $key : null))]) ? $_context[('entry_' . (isset($key) ? $key : null))] : null); ?></label>
            <div class="col-sm-10">
              <div class="input-group datetime" style="max-width:300px;">
                <input type="text" name="<?php echo (isset($key) ? $key : null); ?>" value="<?php echo AbpOc2::e((isset($_context[(isset($key) ? $key : null)]) ? $_context[(isset($key) ? $key : null)] : null)); ?>" placeholder="YYYY-MM-DD HH:mm" data-date-format="YYYY-MM-DD HH:mm" id="input-<?php echo (isset($key) ? $key : null); ?>" class="form-control" />
                <span class="input-group-btn"><button type="button" class="btn btn-default" aria-label="<?php echo AbpOc2::e((isset($_context[('entry_' . (isset($key) ? $key : null))]) ? $_context[('entry_' . (isset($key) ? $key : null))] : null)); ?>"><i class="fa fa-calendar"></i></button></span>
              </div>
              <?php if (((isset($key) ? $key : null) == 'date_end')) { ?><div class="checkbox"><label><input type="checkbox" name="end_all_day" id="input-end-all-day" value="1"<?php if (!empty($end_all_day)) { ?> checked="checked"<?php } ?> /> <?php echo (isset($entry_end_all_day) ? $entry_end_all_day : null); ?></label></div><?php } ?>
              <span class="abp-help"><?php echo (isset($help_dates) ? $help_dates : null); ?></span>
              <?php if (isset($error[(isset($key) ? $key : null)])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error[(isset($key) ? $key : null)]) ? $error[(isset($key) ? $key : null)] : null)); ?></div><?php } ?>
            </div>
          </div>
          <?php $_i22++; } $loop = $_o22; ?>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-countdown"><?php echo (isset($entry_countdown) ? $entry_countdown : null); ?></label>
            <div class="col-sm-10">
              <div class="checkbox"><label><input type="checkbox" name="countdown" id="input-countdown" value="1"<?php if (!empty($countdown)) { ?> checked="checked"<?php } ?> /> <?php echo (isset($text_yes) ? $text_yes : null); ?></label></div>
              <div class="abp-inline-fields" data-abp-show="countdown">
                <select name="opt[recur]" id="input-recur" class="form-control" style="max-width:280px;" aria-label="<?php echo AbpOc2::e((isset($entry_recur) ? $entry_recur : null)); ?>">
                  <?php $_s23 = (array)array('', 'daily', 'weekly'); $_n23 = count($_s23); $_i23 = 0; $_o23 = isset($loop) ? $loop : null; foreach ($_s23 as $value) { $loop = array('index0' => $_i23, 'index' => $_i23 + 1, 'first' => $_i23 === 0, 'last' => $_i23 === $_n23 - 1); ?><option value="<?php echo (isset($value) ? $value : null); ?>"<?php if (((isset($opt['recur']) ? $opt['recur'] : null) == (isset($value) ? $value : null))) { ?> selected="selected"<?php } ?>><?php echo (isset($_context[('text_recur_' . (((isset($value) ? $value : null) == '') ? 'end' : (isset($value) ? $value : null)))]) ? $_context[('text_recur_' . (((isset($value) ? $value : null) == '') ? 'end' : (isset($value) ? $value : null)))] : null); ?></option><?php $_i23++; } $loop = $_o23; ?>
                </select>
                <span data-abp-show="opt[recur]=weekly"><select name="opt[recur_day]" class="form-control" aria-label="<?php echo AbpOc2::e((isset($entry_recur_day) ? $entry_recur_day : null)); ?>"><?php $_s24 = (array)range(1, 7); $_n24 = count($_s24); $_i24 = 0; $_o24 = isset($loop) ? $loop : null; foreach ($_s24 as $day) { $loop = array('index0' => $_i24, 'index' => $_i24 + 1, 'first' => $_i24 === 0, 'last' => $_i24 === $_n24 - 1); ?><option value="<?php echo (isset($day) ? $day : null); ?>"<?php if (((isset($opt['recur_day']) ? $opt['recur_day'] : null) == (isset($day) ? $day : null))) { ?> selected="selected"<?php } ?>><?php echo (isset($_context[('text_wd_' . (isset($day) ? $day : null))]) ? $_context[('text_wd_' . (isset($day) ? $day : null))] : null); ?></option><?php $_i24++; } $loop = $_o24; ?></select></span>
                <span data-abp-show="opt[recur]=daily|weekly" class="abp-inline-fields"><span><?php echo (isset($text_at) ? $text_at : null); ?></span><input type="time" name="opt[recur_time]" value="<?php echo AbpOc2::e((isset($opt['recur_time']) ? $opt['recur_time'] : null)); ?>" class="form-control" aria-label="<?php echo AbpOc2::e((isset($entry_recur_time) ? $entry_recur_time : null)); ?>" /></span>
              </div>
              <div data-abp-show="opt[recur]=daily" class="abp-recur-skip">
                <span class="abp-sub-label"><?php echo (isset($entry_recur_skip) ? $entry_recur_skip : null); ?></span>
                <div class="abp-days" role="group" aria-label="<?php echo AbpOc2::e((isset($entry_recur_skip) ? $entry_recur_skip : null)); ?>">
                  <?php $_s25 = (array)(isset($recur_skip_list) ? $recur_skip_list : null); $_n25 = count($_s25); $_i25 = 0; $_o25 = isset($loop) ? $loop : null; foreach ($_s25 as $day) { $loop = array('index0' => $_i25, 'index' => $_i25 + 1, 'first' => $_i25 === 0, 'last' => $_i25 === $_n25 - 1); ?><label class="abp-day"><input type="checkbox" name="opt[recur_skip][]" value="<?php echo (isset($day['value']) ? $day['value'] : null); ?>"<?php if (!empty($day['checked'])) { ?> checked="checked"<?php } ?> /><span><?php echo AbpOc2::e((isset($day['name']) ? $day['name'] : null)); ?></span></label><?php $_i25++; } $loop = $_o25; ?>
                </div>
              </div>
              <span class="abp-help"><?php echo (isset($help_countdown) ? $help_countdown : null); ?> <?php echo (isset($help_recur) ? $help_recur : null); ?></span>
              <?php if (isset($error['countdown'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['countdown']) ? $error['countdown'] : null)); ?></div><?php } ?>
              <?php if (isset($error['opt_recur'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['opt_recur']) ? $error['opt_recur'] : null)); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label"><?php echo (isset($entry_weekdays) ? $entry_weekdays : null); ?></label>
            <div class="col-sm-10">
              <div class="abp-days" role="group" aria-label="<?php echo AbpOc2::e((isset($entry_weekdays) ? $entry_weekdays : null)); ?>">
                <?php $_s26 = (array)(isset($weekday_list) ? $weekday_list : null); $_n26 = count($_s26); $_i26 = 0; $_o26 = isset($loop) ? $loop : null; foreach ($_s26 as $day) { $loop = array('index0' => $_i26, 'index' => $_i26 + 1, 'first' => $_i26 === 0, 'last' => $_i26 === $_n26 - 1); ?><label class="abp-day"><input type="checkbox" name="weekdays[]" value="<?php echo (isset($day['value']) ? $day['value'] : null); ?>"<?php if (!empty($day['checked'])) { ?> checked="checked"<?php } ?> /><span><?php echo AbpOc2::e((isset($day['name']) ? $day['name'] : null)); ?></span></label><?php $_i26++; } $loop = $_o26; ?>
              </div>
              <span class="abp-help"><?php echo (isset($help_weekdays) ? $help_weekdays : null); ?></span>
              <?php if (isset($error['weekdays'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['weekdays']) ? $error['weekdays'] : null)); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-time-start"><?php echo (isset($entry_time) ? $entry_time : null); ?></label>
            <div class="col-sm-10">
              <div class="abp-time">
                <span><?php echo (isset($text_time_from) ? $text_time_from : null); ?></span><input type="time" name="time_start" value="<?php echo AbpOc2::e((isset($time_start) ? $time_start : null)); ?>" id="input-time-start" class="form-control" />
                <span><?php echo (isset($text_time_to) ? $text_time_to : null); ?></span><input type="time" name="time_end" value="<?php echo AbpOc2::e((isset($time_end) ? $time_end : null)); ?>" id="input-time-end" class="form-control" aria-label="<?php echo AbpOc2::e((isset($entry_time) ? $entry_time : null)); ?> <?php echo AbpOc2::e((isset($text_time_to) ? $text_time_to : null)); ?>" />
              </div>
              <span class="abp-help"><?php echo (isset($help_time) ? $help_time : null); ?></span>
              <?php if (isset($error['time_start'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['time_start']) ? $error['time_start'] : null)); ?></div><?php } ?>
            </div>
          </div>
        </div>
      </div>

            <div class="panel panel-default abp-card" id="abp-card-targeting">
        <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-crosshairs"></i></span> <?php echo (isset($text_section_targeting) ? $text_section_targeting : null); ?></div></div>
        <div class="panel-body">
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-device"><?php echo (isset($entry_device) ? $entry_device : null); ?></label>
            <div class="col-sm-10">
              <select name="device" id="input-device" class="form-control">
                <?php $_s27 = (array)array('all', 'desktop', 'mobile'); $_n27 = count($_s27); $_i27 = 0; $_o27 = isset($loop) ? $loop : null; foreach ($_s27 as $value) { $loop = array('index0' => $_i27, 'index' => $_i27 + 1, 'first' => $_i27 === 0, 'last' => $_i27 === $_n27 - 1); ?>
                <option value="<?php echo (isset($value) ? $value : null); ?>"<?php if (((isset($device) ? $device : null) == (isset($value) ? $value : null))) { ?> selected="selected"<?php } ?>><?php echo (isset($_context[('text_device_' . (isset($value) ? $value : null))]) ? $_context[('text_device_' . (isset($value) ? $value : null))] : null); ?></option>
                <?php $_i27++; } $loop = $_o27; ?>
              </select>
              <span class="abp-help"><?php echo (isset($help_device) ? $help_device : null); ?></span>
              <?php if (isset($error['device'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['device']) ? $error['device'] : null)); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-page-mode"><?php echo (isset($entry_page_mode) ? $entry_page_mode : null); ?></label>
            <div class="col-sm-10">
              <select name="page_mode" id="input-page-mode" class="form-control">
                <?php $_s28 = (array)array('all', 'home', 'include', 'exclude'); $_n28 = count($_s28); $_i28 = 0; $_o28 = isset($loop) ? $loop : null; foreach ($_s28 as $value) { $loop = array('index0' => $_i28, 'index' => $_i28 + 1, 'first' => $_i28 === 0, 'last' => $_i28 === $_n28 - 1); ?>
                <option value="<?php echo (isset($value) ? $value : null); ?>"<?php if (((isset($page_mode) ? $page_mode : null) == (isset($value) ? $value : null))) { ?> selected="selected"<?php } ?>><?php echo (isset($_context[('text_page_' . (isset($value) ? $value : null))]) ? $_context[('text_page_' . (isset($value) ? $value : null))] : null); ?></option>
                <?php $_i28++; } $loop = $_o28; ?>
              </select>
              <?php if (isset($error['page_mode'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['page_mode']) ? $error['page_mode'] : null)); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group" id="abp-routes-group" data-abp-show="page_mode=include|exclude">
            <label class="col-sm-2 control-label" for="input-page-routes"><?php echo (isset($entry_page_routes) ? $entry_page_routes : null); ?></label>
            <div class="col-sm-10">
              <textarea name="page_routes" rows="4" id="input-page-routes" placeholder="product/product&#10;product/category&#10;checkout/*" class="form-control"><?php echo AbpOc2::e((isset($page_routes) ? $page_routes : null)); ?></textarea>
              <span class="abp-help"><?php echo (isset($help_routes) ? $help_routes : null); ?></span>
              <?php if (isset($error['page_routes'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['page_routes']) ? $error['page_routes'] : null)); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-categories"><?php echo (isset($entry_categories) ? $entry_categories : null); ?></label>
            <div class="col-sm-10">
              <div class="abp-chips" id="input-categories" data-abp-ac="category" data-name="categories[]">
                <?php $_s29 = (array)(isset($category_chips) ? $category_chips : null); $_n29 = count($_s29); $_i29 = 0; $_o29 = isset($loop) ? $loop : null; foreach ($_s29 as $id => $name) { $loop = array('index0' => $_i29, 'index' => $_i29 + 1, 'first' => $_i29 === 0, 'last' => $_i29 === $_n29 - 1); ?><span class="abp-chip"><span class="abp-chip-text"><?php echo AbpOc2::e((isset($name) ? $name : null)); ?></span><input type="hidden" name="categories[]" value="<?php echo (isset($id) ? $id : null); ?>" /><button type="button" class="abp-chip-x" aria-label="<?php echo AbpOc2::e((isset($button_remove) ? $button_remove : null)); ?>: <?php echo AbpOc2::e((isset($name) ? $name : null)); ?>">&times;</button></span><?php $_i29++; } $loop = $_o29; ?>
                <input type="text" class="abp-ac-input" placeholder="<?php echo AbpOc2::e((isset($text_autocomplete) ? $text_autocomplete : null)); ?>" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-label="<?php echo AbpOc2::e((isset($entry_categories) ? $entry_categories : null)); ?>" />
                <div class="abp-ac-menu" role="listbox" hidden></div>
              </div>
              <?php if (isset($error['categories'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['categories']) ? $error['categories'] : null)); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-products"><?php echo (isset($entry_products) ? $entry_products : null); ?></label>
            <div class="col-sm-10">
              <div class="abp-chips" id="input-products" data-abp-ac="product" data-name="products[]">
                <?php $_s30 = (array)(isset($product_chips) ? $product_chips : null); $_n30 = count($_s30); $_i30 = 0; $_o30 = isset($loop) ? $loop : null; foreach ($_s30 as $id => $name) { $loop = array('index0' => $_i30, 'index' => $_i30 + 1, 'first' => $_i30 === 0, 'last' => $_i30 === $_n30 - 1); ?><span class="abp-chip"><span class="abp-chip-text"><?php echo AbpOc2::e((isset($name) ? $name : null)); ?></span><input type="hidden" name="products[]" value="<?php echo (isset($id) ? $id : null); ?>" /><button type="button" class="abp-chip-x" aria-label="<?php echo AbpOc2::e((isset($button_remove) ? $button_remove : null)); ?>: <?php echo AbpOc2::e((isset($name) ? $name : null)); ?>">&times;</button></span><?php $_i30++; } $loop = $_o30; ?>
                <input type="text" class="abp-ac-input" placeholder="<?php echo AbpOc2::e((isset($text_autocomplete) ? $text_autocomplete : null)); ?>" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-label="<?php echo AbpOc2::e((isset($entry_products) ? $entry_products : null)); ?>" />
                <div class="abp-ac-menu" role="listbox" hidden></div>
              </div>
              <span class="abp-help"><?php echo (isset($help_targeting) ? $help_targeting : null); ?></span>
              <?php if (isset($error['products'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['products']) ? $error['products'] : null)); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label"><?php echo (isset($entry_store) ? $entry_store : null); ?></label>
            <div class="col-sm-10">
              <?php if (!empty($orphaned['stores'])) { ?><div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> <?php echo (isset($text_orphan_stores) ? $text_orphan_stores : null); ?></div><?php } ?>
              <div class="well well-sm" style="max-height:120px;overflow:auto;margin-bottom:4px;">
                <?php $_s31 = (array)(isset($store_list) ? $store_list : null); $_n31 = count($_s31); $_i31 = 0; $_o31 = isset($loop) ? $loop : null; foreach ($_s31 as $store) { $loop = array('index0' => $_i31, 'index' => $_i31 + 1, 'first' => $_i31 === 0, 'last' => $_i31 === $_n31 - 1); ?>
                <div class="checkbox"><label><input type="checkbox" name="stores[]" value="<?php echo (isset($store['store_id']) ? $store['store_id'] : null); ?>"<?php if (AbpOc2::in((isset($store['store_id']) ? $store['store_id'] : null), (isset($stores) ? $stores : null))) { ?> checked="checked"<?php } ?> /> <?php echo (isset($store['name']) ? $store['name'] : null); ?></label></div>
                <?php $_i31++; } $loop = $_o31; ?>
              </div>
              <span class="abp-help"><?php echo (isset($help_empty_all) ? $help_empty_all : null); ?></span>
              <?php if (isset($error['stores'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['stores']) ? $error['stores'] : null)); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label"><?php echo (isset($entry_customer_group) ? $entry_customer_group : null); ?></label>
            <div class="col-sm-10">
              <?php if (!empty($orphaned['customer_groups'])) { ?><div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> <?php echo (isset($text_orphan_customer_groups) ? $text_orphan_customer_groups : null); ?></div><?php } ?>
              <div class="well well-sm" style="max-height:120px;overflow:auto;margin-bottom:4px;">
                <?php $_s32 = (array)(isset($customer_group_list) ? $customer_group_list : null); $_n32 = count($_s32); $_i32 = 0; $_o32 = isset($loop) ? $loop : null; foreach ($_s32 as $group) { $loop = array('index0' => $_i32, 'index' => $_i32 + 1, 'first' => $_i32 === 0, 'last' => $_i32 === $_n32 - 1); ?>
                <div class="checkbox"><label><input type="checkbox" name="customer_groups[]" value="<?php echo (isset($group['customer_group_id']) ? $group['customer_group_id'] : null); ?>"<?php if (AbpOc2::in((isset($group['customer_group_id']) ? $group['customer_group_id'] : null), (isset($customer_groups) ? $customer_groups : null))) { ?> checked="checked"<?php } ?> /> <?php echo (isset($group['name']) ? $group['name'] : null); ?></label></div>
                <?php $_i32++; } $loop = $_o32; ?>
              </div>
              <span class="abp-help"><?php echo (isset($help_empty_all) ? $help_empty_all : null); ?></span>
              <?php if (isset($error['customer_groups'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['customer_groups']) ? $error['customer_groups'] : null)); ?></div><?php } ?>
            </div>
          </div>
        </div>
      </div>

            <div class="panel panel-default abp-card" id="abp-card-audience">
        <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-globe"></i></span> <?php echo (isset($text_section_audience) ? $text_section_audience : null); ?></div></div>
        <div class="panel-body">
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-countries-mode"><?php echo (isset($entry_countries) ? $entry_countries : null); ?></label>
            <div class="col-sm-10">
              <select name="opt[countries_mode]" id="input-countries-mode" class="form-control" style="max-width:320px;">
                <?php $_s33 = (array)array('all', 'include', 'exclude'); $_n33 = count($_s33); $_i33 = 0; $_o33 = isset($loop) ? $loop : null; foreach ($_s33 as $value) { $loop = array('index0' => $_i33, 'index' => $_i33 + 1, 'first' => $_i33 === 0, 'last' => $_i33 === $_n33 - 1); ?><option value="<?php echo (isset($value) ? $value : null); ?>"<?php if (((isset($opt['countries_mode']) ? $opt['countries_mode'] : null) == (isset($value) ? $value : null))) { ?> selected="selected"<?php } ?>><?php echo (isset($_context[('text_countries_' . (isset($value) ? $value : null))]) ? $_context[('text_countries_' . (isset($value) ? $value : null))] : null); ?></option><?php $_i33++; } $loop = $_o33; ?>
              </select>
              <div class="abp-chips" id="input-countries" data-abp-ac="country" data-name="opt[countries][]" data-abp-show="opt[countries_mode]=include|exclude" style="margin-top:8px;">
                <?php $_s34 = (array)(isset($country_chips) ? $country_chips : null); $_n34 = count($_s34); $_i34 = 0; $_o34 = isset($loop) ? $loop : null; foreach ($_s34 as $id => $name) { $loop = array('index0' => $_i34, 'index' => $_i34 + 1, 'first' => $_i34 === 0, 'last' => $_i34 === $_n34 - 1); ?><span class="abp-chip"><span class="abp-chip-text"><?php echo AbpOc2::e((isset($name) ? $name : null)); ?></span><input type="hidden" name="opt[countries][]" value="<?php echo (isset($id) ? $id : null); ?>" /><button type="button" class="abp-chip-x" aria-label="<?php echo AbpOc2::e((isset($button_remove) ? $button_remove : null)); ?>: <?php echo AbpOc2::e((isset($name) ? $name : null)); ?>">&times;</button></span><?php $_i34++; } $loop = $_o34; ?>
                <input type="text" class="abp-ac-input" placeholder="<?php echo AbpOc2::e((isset($text_autocomplete) ? $text_autocomplete : null)); ?>" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-label="<?php echo AbpOc2::e((isset($entry_countries) ? $entry_countries : null)); ?>" />
                <div class="abp-ac-menu" role="listbox" hidden></div>
              </div>
              <span class="abp-help"><?php echo (isset($help_countries) ? $help_countries : null); ?></span>
              <?php if (isset($error['opt_countries'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['opt_countries']) ? $error['opt_countries'] : null)); ?></div><?php } ?>
            </div>
          </div>
          <?php $_s35 = (array)array('utm_source', 'utm_medium', 'utm_campaign', 'referrer'); $_n35 = count($_s35); $_i35 = 0; $_o35 = isset($loop) ? $loop : null; foreach ($_s35 as $key) { $loop = array('index0' => $_i35, 'index' => $_i35 + 1, 'first' => $_i35 === 0, 'last' => $_i35 === $_n35 - 1); ?>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-<?php echo (isset($key) ? $key : null); ?>"><?php echo (isset($_context[('entry_' . (isset($key) ? $key : null))]) ? $_context[('entry_' . (isset($key) ? $key : null))] : null); ?></label>
            <div class="col-sm-10">
              <input type="text" name="opt[<?php echo (isset($key) ? $key : null); ?>]" value="<?php echo AbpOc2::e((isset($opt[(isset($key) ? $key : null)]) ? $opt[(isset($key) ? $key : null)] : null)); ?>" id="input-<?php echo (isset($key) ? $key : null); ?>" class="form-control" placeholder="<?php echo (((isset($key) ? $key : null) == 'referrer') ? 'facebook.com, instagram.com' : (((isset($key) ? $key : null) == 'utm_source') ? 'newsletter, google' : (((isset($key) ? $key : null) == 'utm_medium') ? 'email, cpc' : 'black-friday'))); ?>" autocomplete="off" spellcheck="false" />
              <?php if (!empty($loop['last'])) { ?><span class="abp-help"><?php echo (isset($help_campaign) ? $help_campaign : null); ?></span><?php } ?>
              <?php if (isset($error[('opt_' . (isset($key) ? $key : null))])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error[('opt_' . (isset($key) ? $key : null))]) ? $error[('opt_' . (isset($key) ? $key : null))] : null)); ?></div><?php } ?>
            </div>
          </div>
          <?php $_i35++; } $loop = $_o35; ?>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-visitor"><?php echo (isset($entry_visitor) ? $entry_visitor : null); ?></label>
            <div class="col-sm-10">
              <select name="opt[visitor]" id="input-visitor" class="form-control" style="max-width:320px;">
                <?php $_s36 = (array)array('all', 'new', 'returning'); $_n36 = count($_s36); $_i36 = 0; $_o36 = isset($loop) ? $loop : null; foreach ($_s36 as $value) { $loop = array('index0' => $_i36, 'index' => $_i36 + 1, 'first' => $_i36 === 0, 'last' => $_i36 === $_n36 - 1); ?><option value="<?php echo (isset($value) ? $value : null); ?>"<?php if (((isset($opt['visitor']) ? $opt['visitor'] : null) == (isset($value) ? $value : null))) { ?> selected="selected"<?php } ?>><?php echo (isset($_context[('text_visitor_' . (isset($value) ? $value : null))]) ? $_context[('text_visitor_' . (isset($value) ? $value : null))] : null); ?></option><?php $_i36++; } $loop = $_o36; ?>
              </select>
              <span class="abp-help"><?php echo (isset($help_visitor) ? $help_visitor : null); ?></span>
              <?php if (isset($error['opt_visitor'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['opt_visitor']) ? $error['opt_visitor'] : null)); ?></div><?php } ?>
            </div>
          </div>
        </div>
      </div>

            <div class="panel panel-default abp-card" id="abp-card-cart">
        <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-shopping-cart"></i></span> <?php echo (isset($text_section_cart) ? $text_section_cart : null); ?></div></div>
        <div class="panel-body">
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-cart-subtotal-min"><?php echo (isset($entry_cart_subtotal) ? $entry_cart_subtotal : null); ?></label>
            <div class="col-sm-10">
              <div class="abp-inline-fields">
                <span><?php echo (isset($text_min) ? $text_min : null); ?></span><input type="text" inputmode="decimal" name="opt[cart_subtotal_min]" value="<?php echo AbpOc2::e((isset($opt['cart_subtotal_min']) ? $opt['cart_subtotal_min'] : null)); ?>" id="input-cart-subtotal-min" class="form-control abp-num" />
                <span><?php echo (isset($text_max) ? $text_max : null); ?></span><input type="text" inputmode="decimal" name="opt[cart_subtotal_max]" value="<?php echo AbpOc2::e((isset($opt['cart_subtotal_max']) ? $opt['cart_subtotal_max'] : null)); ?>" class="form-control abp-num" aria-label="<?php echo AbpOc2::e((isset($entry_cart_subtotal) ? $entry_cart_subtotal : null)); ?> <?php echo AbpOc2::e((isset($text_max) ? $text_max : null)); ?>" />
                <span class="abp-unit"><?php echo AbpOc2::e((isset($currency_code) ? $currency_code : null)); ?></span>
              </div>
              <?php if (isset($error['opt_cart_subtotal_min'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['opt_cart_subtotal_min']) ? $error['opt_cart_subtotal_min'] : null)); ?></div><?php } ?>
              <?php if (isset($error['opt_cart_subtotal_max'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['opt_cart_subtotal_max']) ? $error['opt_cart_subtotal_max'] : null)); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-cart-qty-min"><?php echo (isset($entry_cart_qty) ? $entry_cart_qty : null); ?></label>
            <div class="col-sm-10">
              <div class="abp-inline-fields">
                <span><?php echo (isset($text_min) ? $text_min : null); ?></span><input type="number" min="0" name="opt[cart_qty_min]" value="<?php echo AbpOc2::e((isset($opt['cart_qty_min']) ? $opt['cart_qty_min'] : null)); ?>" id="input-cart-qty-min" class="form-control abp-num" />
                <span><?php echo (isset($text_max) ? $text_max : null); ?></span><input type="number" min="0" name="opt[cart_qty_max]" value="<?php echo AbpOc2::e((isset($opt['cart_qty_max']) ? $opt['cart_qty_max'] : null)); ?>" class="form-control abp-num" aria-label="<?php echo AbpOc2::e((isset($entry_cart_qty) ? $entry_cart_qty : null)); ?> <?php echo AbpOc2::e((isset($text_max) ? $text_max : null)); ?>" />
              </div>
              <?php if (isset($error['opt_cart_qty_min'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['opt_cart_qty_min']) ? $error['opt_cart_qty_min'] : null)); ?></div><?php } ?>
              <?php if (isset($error['opt_cart_qty_max'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['opt_cart_qty_max']) ? $error['opt_cart_qty_max'] : null)); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-cart-products"><?php echo (isset($entry_cart_products) ? $entry_cart_products : null); ?></label>
            <div class="col-sm-10">
              <div class="abp-chips" id="input-cart-products" data-abp-ac="product" data-name="opt[cart_products][]">
                <?php $_s37 = (array)(isset($cart_product_chips) ? $cart_product_chips : null); $_n37 = count($_s37); $_i37 = 0; $_o37 = isset($loop) ? $loop : null; foreach ($_s37 as $id => $name) { $loop = array('index0' => $_i37, 'index' => $_i37 + 1, 'first' => $_i37 === 0, 'last' => $_i37 === $_n37 - 1); ?><span class="abp-chip"><span class="abp-chip-text"><?php echo AbpOc2::e((isset($name) ? $name : null)); ?></span><input type="hidden" name="opt[cart_products][]" value="<?php echo (isset($id) ? $id : null); ?>" /><button type="button" class="abp-chip-x" aria-label="<?php echo AbpOc2::e((isset($button_remove) ? $button_remove : null)); ?>: <?php echo AbpOc2::e((isset($name) ? $name : null)); ?>">&times;</button></span><?php $_i37++; } $loop = $_o37; ?>
                <input type="text" class="abp-ac-input" placeholder="<?php echo AbpOc2::e((isset($text_autocomplete) ? $text_autocomplete : null)); ?>" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-label="<?php echo AbpOc2::e((isset($entry_cart_products) ? $entry_cart_products : null)); ?>" />
                <div class="abp-ac-menu" role="listbox" hidden></div>
              </div>
              <?php if (isset($error['opt_cart_products'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['opt_cart_products']) ? $error['opt_cart_products'] : null)); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-cart-categories"><?php echo (isset($entry_cart_categories) ? $entry_cart_categories : null); ?></label>
            <div class="col-sm-10">
              <div class="abp-chips" id="input-cart-categories" data-abp-ac="category" data-name="opt[cart_categories][]">
                <?php $_s38 = (array)(isset($cart_category_chips) ? $cart_category_chips : null); $_n38 = count($_s38); $_i38 = 0; $_o38 = isset($loop) ? $loop : null; foreach ($_s38 as $id => $name) { $loop = array('index0' => $_i38, 'index' => $_i38 + 1, 'first' => $_i38 === 0, 'last' => $_i38 === $_n38 - 1); ?><span class="abp-chip"><span class="abp-chip-text"><?php echo AbpOc2::e((isset($name) ? $name : null)); ?></span><input type="hidden" name="opt[cart_categories][]" value="<?php echo (isset($id) ? $id : null); ?>" /><button type="button" class="abp-chip-x" aria-label="<?php echo AbpOc2::e((isset($button_remove) ? $button_remove : null)); ?>: <?php echo AbpOc2::e((isset($name) ? $name : null)); ?>">&times;</button></span><?php $_i38++; } $loop = $_o38; ?>
                <input type="text" class="abp-ac-input" placeholder="<?php echo AbpOc2::e((isset($text_autocomplete) ? $text_autocomplete : null)); ?>" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-label="<?php echo AbpOc2::e((isset($entry_cart_categories) ? $entry_cart_categories : null)); ?>" />
                <div class="abp-ac-menu" role="listbox" hidden></div>
              </div>
              <span class="abp-help"><?php echo (isset($help_cart) ? $help_cart : null); ?></span>
              <?php if (isset($error['opt_cart_categories'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['opt_cart_categories']) ? $error['opt_cart_categories'] : null)); ?></div><?php } ?>
            </div>
          </div>
        </div>
      </div>

            <div class="panel panel-default abp-card" id="abp-card-appearance">
        <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-paint-brush"></i></span> <?php echo (isset($text_section_appearance) ? $text_section_appearance : null); ?></div></div>
        <div class="panel-body">
          <div class="form-group">
            <label class="col-sm-2 control-label"><?php echo (isset($entry_message_style) ? $entry_message_style : null); ?></label>
            <div class="col-sm-10">
              <div class="abp-styles" role="radiogroup" aria-label="<?php echo AbpOc2::e((isset($entry_message_style) ? $entry_message_style : null)); ?>">
                <div class="abp-style-opt">
                  <input type="radio" name="style" id="input-style-default" value=""<?php if (empty($style)) { ?> checked="checked"<?php } ?> />
                  <label for="input-style-default"><span class="abp-sw abp-sw-default"><i></i><b></b></span><span class="abp-name"><?php echo AbpOc2::e(sprintf((string)(isset($text_style_default) ? $text_style_default : null), (isset($module_style) ? $module_style : null))); ?></span></label>
                </div>
                <?php $_s39 = (array)(isset($styles) ? $styles : null); $_n39 = count($_s39); $_i39 = 0; $_o39 = isset($loop) ? $loop : null; foreach ($_s39 as $item) { $loop = array('index0' => $_i39, 'index' => $_i39 + 1, 'first' => $_i39 === 0, 'last' => $_i39 === $_n39 - 1); ?>
                <div class="abp-style-opt">
                  <input type="radio" name="style" id="input-style-<?php echo (isset($item['value']) ? $item['value'] : null); ?>" value="<?php echo (isset($item['value']) ? $item['value'] : null); ?>"<?php if (((isset($style) ? $style : null) == (isset($item['value']) ? $item['value'] : null))) { ?> checked="checked"<?php } ?> />
                  <label for="input-style-<?php echo (isset($item['value']) ? $item['value'] : null); ?>"><span class="abp-sw abp-sw-<?php echo (isset($item['value']) ? $item['value'] : null); ?>"><i></i><b></b></span><span class="abp-name"><?php echo AbpOc2::e((isset($item['name']) ? $item['name'] : null)); ?></span><span class="abp-desc"><?php echo AbpOc2::e((isset($item['help']) ? $item['help'] : null)); ?></span></label>
                </div>
                <?php $_i39++; } $loop = $_o39; ?>
              </div>
              <span class="abp-help"><?php echo (isset($help_style) ? $help_style : null); ?></span>
              <?php if (isset($error['style'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['style']) ? $error['style'] : null)); ?></div><?php } ?>
            </div>
          </div>
          <?php if (!empty($color_auto)) { ?><p class="abp-hint"><i class="fa fa-magic"></i> <?php echo (isset($text_color_auto_note) ? $text_color_auto_note : null); ?></p><?php } ?>
          <?php $_s40 = (array)array('bg_color', 'text_color'); $_n40 = count($_s40); $_i40 = 0; $_o40 = isset($loop) ? $loop : null; foreach ($_s40 as $key) { $loop = array('index0' => $_i40, 'index' => $_i40 + 1, 'first' => $_i40 === 0, 'last' => $_i40 === $_n40 - 1); ?>
          <div class="form-group required">
            <label class="col-sm-2 control-label" for="input-<?php echo (isset($key) ? $key : null); ?>"><?php echo (isset($_context[('entry_' . (isset($key) ? $key : null))]) ? $_context[('entry_' . (isset($key) ? $key : null))] : null); ?></label>
            <div class="col-sm-10">
              <div class="input-group abp-color">
                <span class="input-group-addon"><input type="color" value="<?php echo AbpOc2::e((isset($_context[(isset($key) ? $key : null)]) ? $_context[(isset($key) ? $key : null)] : null)); ?>" aria-label="<?php echo AbpOc2::e((isset($_context[('entry_' . (isset($key) ? $key : null))]) ? $_context[('entry_' . (isset($key) ? $key : null))] : null)); ?>" /></span>
                <input type="text" name="<?php echo (isset($key) ? $key : null); ?>" id="input-<?php echo (isset($key) ? $key : null); ?>" value="<?php echo AbpOc2::e((isset($_context[(isset($key) ? $key : null)]) ? $_context[(isset($key) ? $key : null)] : null)); ?>" maxlength="7" class="form-control" />
              </div>
              <?php if (isset($error[(isset($key) ? $key : null)])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error[(isset($key) ? $key : null)]) ? $error[(isset($key) ? $key : null)] : null)); ?></div><?php } ?>
            </div>
          </div>
          <?php $_i40++; } $loop = $_o40; ?>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-image"><?php echo (isset($entry_image) ? $entry_image : null); ?></label>
            <div class="col-sm-10">
              <div class="abp-image">
                <a href="" id="thumb-image" data-toggle="image" class="img-thumbnail" aria-label="<?php echo AbpOc2::e((isset($entry_image) ? $entry_image : null)); ?>"><img src="<?php echo AbpOc2::e((isset($image_thumb) ? $image_thumb : null)); ?>" alt="" data-placeholder="<?php echo AbpOc2::e((isset($placeholder) ? $placeholder : null)); ?>" /></a>
                <input type="hidden" name="opt[image]" value="<?php echo AbpOc2::e((isset($opt['image']) ? $opt['image'] : null)); ?>" id="input-image" />
                <div class="abp-image-side" data-abp-show="opt[image]!=">
                  <label class="abp-sub-label" for="input-overlay"><?php echo (isset($entry_overlay) ? $entry_overlay : null); ?> <output id="abp-overlay-out"><?php echo (isset($opt['overlay']) ? $opt['overlay'] : null); ?></output>%</label>
                  <input type="range" min="0" max="90" step="5" name="opt[overlay]" value="<?php echo AbpOc2::e((isset($opt['overlay']) ? $opt['overlay'] : null)); ?>" id="input-overlay" />
                </div>
              </div>
              <span class="abp-help"><?php echo (isset($help_image) ? $help_image : null); ?></span>
              <?php if (isset($error['opt_image'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['opt_image']) ? $error['opt_image'] : null)); ?></div><?php } ?>
              <?php if (isset($error['opt_overlay'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['opt_overlay']) ? $error['opt_overlay'] : null)); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-font"><?php echo (isset($entry_font) ? $entry_font : null); ?></label>
            <div class="col-sm-10">
              <select name="opt[font]" id="input-font" class="form-control" style="max-width:320px;">
                <option value=""<?php if (((isset($opt['font']) ? $opt['font'] : null) == '')) { ?> selected="selected"<?php } ?>><?php echo (isset($text_font_module) ? $text_font_module : null); ?></option>
                <?php $_s41 = (array)(isset($font_list) ? $font_list : null); $_n41 = count($_s41); $_i41 = 0; $_o41 = isset($loop) ? $loop : null; foreach ($_s41 as $f) { $loop = array('index0' => $_i41, 'index' => $_i41 + 1, 'first' => $_i41 === 0, 'last' => $_i41 === $_n41 - 1); ?><?php if (((isset($f['value']) ? $f['value'] : null) != '')) { ?><option value="<?php echo (isset($f['value']) ? $f['value'] : null); ?>"<?php if (((isset($opt['font']) ? $opt['font'] : null) == (isset($f['value']) ? $f['value'] : null))) { ?> selected="selected"<?php } ?> style="font-family:<?php echo AbpOc2::e((isset($f['stack']) ? $f['stack'] : null)); ?>"><?php echo AbpOc2::e((isset($f['name']) ? $f['name'] : null)); ?></option><?php } ?><?php $_i41++; } $loop = $_o41; ?>
              </select>
              <span class="abp-help"><?php echo (isset($help_font) ? $help_font : null); ?></span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-sort-order"><?php echo (isset($entry_sort_order) ? $entry_sort_order : null); ?></label>
            <div class="col-sm-10">
              <input type="number" name="sort_order" value="<?php echo AbpOc2::e((isset($sort_order) ? $sort_order : null)); ?>" id="input-sort-order" class="form-control abp-num" />
              <?php if (isset($error['sort_order'])) { ?><div class="text-danger"><?php echo AbpOc2::e((isset($error['sort_order']) ? $error['sort_order'] : null)); ?></div><?php } ?>
            </div>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>
<script type="application/json" id="abp-config"><?php echo (isset($config_json) ? $config_json : null); ?></script>
<script type="text/javascript" src="<?php echo AbpOc2::e((isset($admin_js) ? $admin_js : null)); ?>"></script>
<script type="text/javascript"><!--
if (window.jQuery && jQuery.fn.datetimepicker) {
  jQuery('.datetime').datetimepicker({ language: '<?php echo AbpOc2::escapeJs((isset($datepicker) ? $datepicker : null)); ?>', pickDate: true, pickTime: true });
}
//--></script>
<?php echo (isset($footer) ? $footer : null); ?>

