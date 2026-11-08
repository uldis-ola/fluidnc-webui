<?php /* Announcement Bar Pro 2.4.1 — generated from the OpenCart 3 Twig template by tools/twig2tpl.py, then adapted for OpenCart 1.5. */ ?>
<?php echo abp15_v($header); ?>
<div id="content" class="abp-admin abp15">
  <link rel="stylesheet" href="<?php echo abp15_e(abp15_v($fa_css)); ?>" />
  <link rel="stylesheet" href="<?php echo abp15_e(abp15_v($admin_css)); ?>" />
  <link rel="stylesheet" href="<?php echo abp15_e(abp15_v($oc15_css)); ?>" />
  <div class="page-header">
    <div class="container-fluid">
      <div class="pull-right">
        <button type="submit" form="form-message" data-toggle="tooltip" title="<?php echo abp15_e(abp15_v($button_save)); ?>" class="btn btn-primary"><i class="fa fa-save"></i></button>
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
    </div>
    <form action="<?php echo abp15_v($action); ?>" method="post" enctype="multipart/form-data" id="form-message" class="form-horizontal">

      
      <?php if (abp15_v($is_new)) { ?>
      <div class="panel panel-default abp-card" id="abp-card-templates">
        <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-magic"></i></span> <?php echo abp15_v($text_section_templates); ?> <small><?php echo abp15_v($help_templates); ?></small></div></div>
        <div class="panel-body"><div class="abp-tpl-grid" id="abp-templates" role="list"></div></div>
      </div>
      <?php } ?>

      
      <div class="panel panel-default abp-card abp-preview-card">
        <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-eye"></i></span> <?php echo abp15_v($text_preview); ?> <small><?php echo abp15_v($text_form); ?></small></div></div>
        <div class="panel-body" style="padding-bottom:14px;">
          <div class="abp-pv-tools">
            <span class="abp-pv-label"><?php echo abp15_v($text_editing_language); ?></span>
            <span class="abp-langs" role="group" aria-label="<?php echo abp15_e(abp15_v($text_editing_language)); ?>">
              <?php $__k2 = array(); foreach (array('language') as $__x) { $__k2[$__x] = isset($$__x) ? array($$__x) : null; } $__s2 = (array)abp15_v($languages); $__c2 = count($__s2); $__i2 = 0; foreach ($__s2 as $language) { $loop2 = array('index0' => $__i2, 'index' => $__i2 + 1, 'first' => $__i2 === 0, 'last' => $__i2 === $__c2 - 1); $__i2++; ?><button type="button" data-abp-lang="<?php echo abp15_v($language['language_id']); ?>" aria-pressed="false"><img src="view/image/flags/<?php echo abp15_url(abp15_v($language['image'])); ?>" alt="" /> <?php echo abp15_e(abp15_v($language['name'])); ?></button><?php } foreach ($__k2 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
            </span>
            <span class="abp-seg" role="group" aria-label="<?php echo abp15_e(abp15_v($text_preview)); ?>"><button type="button" class="active" data-abp-pv="device" data-value="desktop" aria-pressed="true"><i class="fa fa-desktop"></i> <?php echo abp15_v($text_preview_desktop); ?></button><button type="button" data-abp-pv="device" data-value="phone" aria-pressed="false"><i class="fa fa-mobile"></i> <?php echo abp15_v($text_preview_phone); ?></button></span>
            <span class="abp-seg" role="group" aria-label="<?php echo abp15_e(abp15_v($text_preview_variant)); ?>"><button type="button" class="active" data-abp-pv="variant" data-value="a" aria-pressed="true">A</button><button type="button" data-abp-pv="variant" data-value="b" aria-pressed="false">B</button></span>
            <span class="abp-seg" id="abp-pv-cart" role="group" aria-label="<?php echo abp15_e(abp15_v($text_preview_cart)); ?>"><button type="button" class="active" data-abp-pv="cart" data-value="below" aria-pressed="true"><?php echo abp15_v($text_preview_cart_below); ?></button><button type="button" data-abp-pv="cart" data-value="done" aria-pressed="false"><?php echo abp15_v($text_preview_cart_done); ?></button></span>
          </div>
          <div id="abp-preview-wrap"><iframe id="abp-preview-frame" title="<?php echo abp15_e(abp15_v($text_preview)); ?>" sandbox="allow-scripts"></iframe></div>
          <p class="abp-pv-note"><i class="fa fa-info-circle"></i> <?php echo abp15_v($text_preview_note); ?><?php if (abp15_v($color_auto)) { ?> <?php echo abp15_v($text_preview_auto); ?><?php } ?> <span id="abp-pv-extra"></span></p>
          <div id="abp-contrast" class="text-warning" hidden><i class="fa fa-exclamation-triangle"></i> <?php echo abp15_v($text_low_contrast); ?></div>
        </div>
      </div>

      
      <div class="panel panel-default abp-card" id="abp-card-content">
        <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-pencil"></i></span> <?php echo abp15_v($text_section_content); ?></div></div>
        <div class="panel-body">
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-status"><?php echo abp15_v($entry_status); ?></label>
            <div class="col-sm-10">
              <select name="status" id="input-status" class="form-control abp-num">
                <option value="1"<?php if (abp15_v($status)) { ?> selected="selected"<?php } ?>><?php echo abp15_v($text_enabled); ?></option>
                <option value="0"<?php if (!(abp15_v($status))) { ?> selected="selected"<?php } ?>><?php echo abp15_v($text_disabled); ?></option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-type"><?php echo abp15_v($entry_type); ?></label>
            <div class="col-sm-10">
              <select name="type" id="input-type" class="form-control" style="max-width:320px;">
                <?php $__k3 = array(); foreach (array('value') as $__x) { $__k3[$__x] = isset($$__x) ? array($$__x) : null; } $__s3 = (array)array('standard', 'free_shipping', 'tiers'); $__c3 = count($__s3); $__i3 = 0; foreach ($__s3 as $value) { $loop3 = array('index0' => $__i3, 'index' => $__i3 + 1, 'first' => $__i3 === 0, 'last' => $__i3 === $__c3 - 1); $__i3++; ?>
                <option value="<?php echo abp15_v($value); ?>"<?php if ((abp15_v($type) == abp15_v($value))) { ?> selected="selected"<?php } ?>><?php echo abp15_v(${('text_type_' . abp15_v($value))}); ?></option>
                <?php } foreach ($__k3 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
              </select>
              <span class="abp-help"><?php echo abp15_v($help_type); ?></span>
              <?php if (isset($error['type'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['type'])); ?></div><?php } ?>
            </div>
          </div>
          <p class="abp-hint" data-abp-show="type=free_shipping"><i class="fa fa-lightbulb-o"></i> <?php echo abp15_v($help_shipping_text); ?></p>
          <p class="abp-hint" data-abp-show="type=tiers"><i class="fa fa-lightbulb-o"></i> <?php echo abp15_v($help_tiers_text); ?></p>
          <?php $__k4 = array(); foreach (array('language') as $__x) { $__k4[$__x] = isset($$__x) ? array($$__x) : null; } $__s4 = (array)abp15_v($languages); $__c4 = count($__s4); $__i4 = 0; foreach ($__s4 as $language) { $loop4 = array('index0' => $__i4, 'index' => $__i4 + 1, 'first' => $__i4 === 0, 'last' => $__i4 === $__c4 - 1); $__i4++; ?>
          <?php $lid = abp15_v($language['language_id']); ?>
          <div class="abp-lang-pane" data-lang="<?php echo abp15_v($lid); ?>">
            <div class="form-group required">
              <label class="col-sm-2 control-label" for="input-text<?php echo abp15_v($lid); ?>"><?php echo abp15_v($entry_text); ?><span class="abp-lang-tag"><?php echo abp15_e(abp15_v($language['code'])); ?></span></label>
              <div class="col-sm-10">
                <textarea name="message_description[<?php echo abp15_v($lid); ?>][text]" rows="2" maxlength="<?php echo abp15_v($max_text); ?>" id="input-text<?php echo abp15_v($lid); ?>" class="form-control abp-text"><?php echo abp15_e(abp15_v($message_description[abp15_v($lid)]['text'])); ?></textarea>
                <span class="abp-help"><?php echo (abp15_v($allow_html) ? abp15_v($help_text_html) : abp15_v($help_text_plain)); ?> <?php echo abp15_v($help_countdown_placeholder); ?> <?php echo abp15_v($help_lang_fallback); ?></span>
                <?php if (isset($error['text'][abp15_v($lid)])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['text'][abp15_v($lid)])); ?></div><?php } ?>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-2 control-label" for="input-mobile-text<?php echo abp15_v($lid); ?>"><?php echo abp15_v($entry_mobile_text); ?></label>
              <div class="col-sm-10">
                <textarea name="message_description[<?php echo abp15_v($lid); ?>][mobile_text]" rows="2" maxlength="<?php echo abp15_v($max_text); ?>" id="input-mobile-text<?php echo abp15_v($lid); ?>" class="form-control abp-mobile-text"><?php echo abp15_e(abp15_v($message_description[abp15_v($lid)]['mobile_text'])); ?></textarea>
                <span class="abp-help"><?php echo abp15_v($help_mobile_text); ?></span>
                <?php if (isset($error['mobile_text'][abp15_v($lid)])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['mobile_text'][abp15_v($lid)])); ?></div><?php } ?>
              </div>
            </div>
          </div>
          <?php } foreach ($__k4 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-icon"><?php echo abp15_v($entry_icon); ?></label>
            <div class="col-sm-10">
              <div class="input-group" style="max-width:300px;">
                <span class="input-group-addon"><i id="abp-icon-preview" class="fa <?php echo abp15_e(abp15_v($icon)); ?>"></i></span>
                <input type="text" name="icon" value="<?php echo abp15_e(abp15_v($icon)); ?>" maxlength="44" placeholder="fa-truck" id="input-icon" class="form-control" />
              </div>
              <span class="abp-help"><?php echo abp15_v($help_icon); ?></span>
              <?php if (isset($error['icon'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['icon'])); ?></div><?php } ?>
            </div>
          </div>
        </div>
      </div>

      
      <div class="panel panel-default abp-card" id="abp-card-shipping" data-abp-show="type=free_shipping|tiers">
        <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-truck"></i></span> <?php echo abp15_v($text_section_goal); ?></div></div>
        <div class="panel-body">
          <div data-abp-show="type=free_shipping">
            <div class="form-group">
              <label class="col-sm-2 control-label" for="input-fs-oc"><?php echo abp15_v($entry_fs_oc); ?></label>
              <div class="col-sm-10">
                <div class="checkbox"><label><input type="checkbox" name="opt[fs_oc]" id="input-fs-oc" value="1"<?php if (abp15_v($opt['fs_oc'])) { ?> checked="checked"<?php } ?> /> <?php echo abp15_v($text_fs_oc); ?></label></div>
                <span class="abp-help"><?php echo abp15_e(sprintf(abp15_v($help_fs_oc), abp15_v($oc_free_shipping['total']), (abp15_v($oc_free_shipping['status']) ? abp15_v($text_enabled) : abp15_v($text_disabled)))); ?></span>
              </div>
            </div>
            <div class="form-group required" data-abp-show="!opt[fs_oc]">
              <label class="col-sm-2 control-label" for="input-threshold"><?php echo abp15_v($entry_threshold); ?></label>
              <div class="col-sm-10">
                <div class="input-group" style="max-width:220px;">
                  <input type="text" inputmode="decimal" name="threshold" value="<?php echo abp15_e(abp15_v($threshold)); ?>" placeholder="50.00" id="input-threshold" class="form-control" />
                  <span class="input-group-addon"><?php echo abp15_e(abp15_v($currency_code)); ?></span>
                </div>
                <span class="abp-help"><?php echo abp15_e(sprintf(abp15_v($help_threshold), abp15_v($currency_code))); ?></span>
                <?php if (isset($error['threshold'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['threshold'])); ?></div><?php } ?>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-2 control-label"><?php echo abp15_v($entry_fs_zones); ?></label>
              <div class="col-sm-10">
                <div class="table-responsive">
                  <table class="table table-condensed abp-rows" id="abp-zone-rows">
                    <thead><tr><td><?php echo abp15_v($entry_country); ?></td><td><?php echo abp15_v($entry_zone); ?></td><td><?php echo abp15_v($entry_amount); ?> (<?php echo abp15_e(abp15_v($currency_code)); ?>)</td></tr></thead>
                    <tbody>
                      <?php $__k5 = array(); foreach (array('row') as $__x) { $__k5[$__x] = isset($$__x) ? array($$__x) : null; } $__s5 = (array)abp15_v($fs_zone_rows); $__c5 = count($__s5); $__i5 = 0; foreach ($__s5 as $row) { $loop5 = array('index0' => $__i5, 'index' => $__i5 + 1, 'first' => $__i5 === 0, 'last' => $__i5 === $__c5 - 1); $__i5++; ?>
                      <?php $i = abp15_v($loop5['index0']); ?>
                      <tr>
                        <td><select name="opt[fs_zones][<?php echo abp15_v($i); ?>][country_id]" class="form-control abp-zone-country" aria-label="<?php echo abp15_e(abp15_v($entry_country)); ?>"><option value="0">—</option><?php $__k6 = array(); foreach (array('id', 'name') as $__x) { $__k6[$__x] = isset($$__x) ? array($$__x) : null; } $__s6 = (array)abp15_v($country_list); $__c6 = count($__s6); $__i6 = 0; foreach ($__s6 as $id => $name) { $loop6 = array('index0' => $__i6, 'index' => $__i6 + 1, 'first' => $__i6 === 0, 'last' => $__i6 === $__c6 - 1); $__i6++; ?><option value="<?php echo abp15_v($id); ?>"<?php if ((abp15_v($id) == abp15_v($row['country_id']))) { ?> selected="selected"<?php } ?>><?php echo abp15_e(abp15_v($name)); ?></option><?php } foreach ($__k6 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?></select></td>
                        <td><select name="opt[fs_zones][<?php echo abp15_v($i); ?>][zone_id]" class="form-control abp-zone-zone" aria-label="<?php echo abp15_e(abp15_v($entry_zone)); ?>"><option value="0"><?php echo abp15_v($text_all_zones); ?></option><?php $__k7 = array(); foreach (array('zone') as $__x) { $__k7[$__x] = isset($$__x) ? array($$__x) : null; } $__s7 = (array)abp15_v($row['zones']); $__c7 = count($__s7); $__i7 = 0; foreach ($__s7 as $zone) { $loop7 = array('index0' => $__i7, 'index' => $__i7 + 1, 'first' => $__i7 === 0, 'last' => $__i7 === $__c7 - 1); $__i7++; ?><option value="<?php echo abp15_v($zone['id']); ?>"<?php if ((abp15_v($zone['id']) == abp15_v($row['zone_id']))) { ?> selected="selected"<?php } ?>><?php echo abp15_e(abp15_v($zone['name'])); ?></option><?php } foreach ($__k7 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?></select></td>
                        <td><input type="text" inputmode="decimal" name="opt[fs_zones][<?php echo abp15_v($i); ?>][amount]" value="<?php echo abp15_e(abp15_v($row['amount'])); ?>" class="form-control" aria-label="<?php echo abp15_e(abp15_v($entry_amount)); ?>" /></td>
                      </tr>
                      <?php } foreach ($__k5 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
                    </tbody>
                  </table>
                </div>
                <button type="button" class="btn btn-default btn-sm" data-abp-add-row="#abp-zone-rows"><i class="fa fa-plus"></i> <?php echo abp15_v($button_add_row); ?></button>
                <span class="abp-help"><?php echo abp15_v($help_fs_zones); ?></span>
                <?php if (isset($error['opt_fs_zones'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['opt_fs_zones'])); ?></div><?php } ?>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-2 control-label"><?php echo abp15_v($entry_fs_currencies); ?></label>
              <div class="col-sm-10">
                <?php if (abp15_v($currency_rows)) { ?>
                <div class="abp-cur-grid">
                  <?php $__k8 = array(); foreach (array('c') as $__x) { $__k8[$__x] = isset($$__x) ? array($$__x) : null; } $__s8 = (array)abp15_v($currency_rows); $__c8 = count($__s8); $__i8 = 0; foreach ($__s8 as $c) { $loop8 = array('index0' => $__i8, 'index' => $__i8 + 1, 'first' => $__i8 === 0, 'last' => $__i8 === $__c8 - 1); $__i8++; ?><div class="input-group"><input type="text" inputmode="decimal" name="opt[fs_currencies][<?php echo abp15_e(abp15_v($c['code'])); ?>]" value="<?php echo abp15_e(abp15_v($c['amount'])); ?>" class="form-control" aria-label="<?php echo abp15_e(abp15_v($entry_fs_currencies)); ?> <?php echo abp15_e(abp15_v($c['code'])); ?>" placeholder="—" /><span class="input-group-addon" title="<?php echo abp15_e(abp15_v($c['title'])); ?>"><?php echo abp15_e(abp15_v($c['code'])); ?></span></div><?php } foreach ($__k8 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
                </div>
                <span class="abp-help"><?php echo abp15_v($help_fs_currencies); ?></span>
                <?php } else { ?>
                <span class="abp-help"><?php echo abp15_v($text_one_currency); ?></span>
                <?php } ?>
                <?php if (isset($error['opt_fs_currencies'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['opt_fs_currencies'])); ?></div><?php } ?>
              </div>
            </div>
          </div>
          <div data-abp-show="type=tiers">
            <div class="form-group required">
              <label class="col-sm-2 control-label"><?php echo abp15_v($entry_tiers); ?></label>
              <div class="col-sm-10">
                <div class="table-responsive">
                  <table class="table table-condensed abp-rows abp-tiers">
                    <thead><tr><td style="width:1px;">#</td><td style="width:150px;"><?php echo abp15_v($entry_amount); ?> (<?php echo abp15_e(abp15_v($currency_code)); ?>)</td><td><?php echo abp15_v($entry_tier_label); ?></td><td><?php echo abp15_v($entry_tier_text); ?></td></tr></thead>
                    <tbody>
                      <?php $__k9 = array(); foreach (array('i') as $__x) { $__k9[$__x] = isset($$__x) ? array($$__x) : null; } $__s9 = (array)range(0, 4); $__c9 = count($__s9); $__i9 = 0; foreach ($__s9 as $i) { $loop9 = array('index0' => $__i9, 'index' => $__i9 + 1, 'first' => $__i9 === 0, 'last' => $__i9 === $__c9 - 1); $__i9++; ?>
                      <tr>
                        <td class="abp-muted"><?php echo (abp15_v($i) + 1); ?></td>
                        <td><input type="text" inputmode="decimal" name="opt[tiers][]" value="<?php echo abp15_e(abp15_v($tier_rows[abp15_v($i)])); ?>" class="form-control abp-tier-amount" aria-label="<?php echo abp15_e(sprintf(abp15_v($text_tier), (abp15_v($i) + 1))); ?> — <?php echo abp15_e(abp15_v($entry_amount)); ?>" /></td>
                        <td><?php $__k10 = array(); foreach (array('language') as $__x) { $__k10[$__x] = isset($$__x) ? array($$__x) : null; } $__s10 = (array)abp15_v($languages); $__c10 = count($__s10); $__i10 = 0; foreach ($__s10 as $language) { $loop10 = array('index0' => $__i10, 'index' => $__i10 + 1, 'first' => $__i10 === 0, 'last' => $__i10 === $__c10 - 1); $__i10++; ?><?php $lid = abp15_v($language['language_id']); ?><span class="abp-lang-pane abp-lang-inline" data-lang="<?php echo abp15_v($lid); ?>"><input type="text" name="message_description[<?php echo abp15_v($lid); ?>][tier_label][]" value="<?php echo abp15_e(abp15_v($message_description[abp15_v($lid)]['tier_label'][abp15_v($i)])); ?>" maxlength="160" class="form-control" aria-label="<?php echo abp15_e(sprintf(abp15_v($text_tier), (abp15_v($i) + 1))); ?> — <?php echo abp15_e(abp15_v($entry_tier_label)); ?> (<?php echo abp15_e(abp15_v($language['code'])); ?>)" /></span><?php } foreach ($__k10 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?></td>
                        <td><?php $__k11 = array(); foreach (array('language') as $__x) { $__k11[$__x] = isset($$__x) ? array($$__x) : null; } $__s11 = (array)abp15_v($languages); $__c11 = count($__s11); $__i11 = 0; foreach ($__s11 as $language) { $loop11 = array('index0' => $__i11, 'index' => $__i11 + 1, 'first' => $__i11 === 0, 'last' => $__i11 === $__c11 - 1); $__i11++; ?><?php $lid = abp15_v($language['language_id']); ?><span class="abp-lang-pane abp-lang-inline" data-lang="<?php echo abp15_v($lid); ?>"><input type="text" name="message_description[<?php echo abp15_v($lid); ?>][tier_text][]" value="<?php echo abp15_e(abp15_v($message_description[abp15_v($lid)]['tier_text'][abp15_v($i)])); ?>" maxlength="300" class="form-control" aria-label="<?php echo abp15_e(sprintf(abp15_v($text_tier), (abp15_v($i) + 1))); ?> — <?php echo abp15_e(abp15_v($entry_tier_text)); ?> (<?php echo abp15_e(abp15_v($language['code'])); ?>)" /></span><?php } foreach ($__k11 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?></td>
                      </tr>
                      <?php } foreach ($__k9 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
                    </tbody>
                  </table>
                </div>
                <span class="abp-help"><?php echo abp15_v($help_tiers); ?></span>
                <?php if (isset($error['opt_tiers'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['opt_tiers'])); ?></div><?php } ?>
                <?php $__k12 = array(); foreach (array('language') as $__x) { $__k12[$__x] = isset($$__x) ? array($$__x) : null; } $__s12 = (array)abp15_v($languages); $__c12 = count($__s12); $__i12 = 0; foreach ($__s12 as $language) { $loop12 = array('index0' => $__i12, 'index' => $__i12 + 1, 'first' => $__i12 === 0, 'last' => $__i12 === $__c12 - 1); $__i12++; ?><?php if (isset($error['tier_label'][abp15_v($language['language_id'])])) { ?><div class="text-danger abp-lang-pane abp-lang-inline" data-lang="<?php echo abp15_v($language['language_id']); ?>"><?php echo abp15_e(abp15_v($error['tier_label'][abp15_v($language['language_id'])])); ?></div><?php } ?><?php if (isset($error['tier_text'][abp15_v($language['language_id'])])) { ?><div class="text-danger abp-lang-pane abp-lang-inline" data-lang="<?php echo abp15_v($language['language_id']); ?>"><?php echo abp15_e(abp15_v($error['tier_text'][abp15_v($language['language_id'])])); ?></div><?php } ?><?php } foreach ($__k12 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
              </div>
            </div>
          </div>
          <?php $__k13 = array(); foreach (array('language') as $__x) { $__k13[$__x] = isset($$__x) ? array($$__x) : null; } $__s13 = (array)abp15_v($languages); $__c13 = count($__s13); $__i13 = 0; foreach ($__s13 as $language) { $loop13 = array('index0' => $__i13, 'index' => $__i13 + 1, 'first' => $__i13 === 0, 'last' => $__i13 === $__c13 - 1); $__i13++; ?>
          <?php $lid = abp15_v($language['language_id']); ?>
          <div class="abp-lang-pane" data-lang="<?php echo abp15_v($lid); ?>">
            <div class="form-group required">
              <label class="col-sm-2 control-label" for="input-success-text<?php echo abp15_v($lid); ?>"><?php echo abp15_v($entry_success_text); ?><span class="abp-lang-tag"><?php echo abp15_e(abp15_v($language['code'])); ?></span></label>
              <div class="col-sm-10">
                <textarea name="message_description[<?php echo abp15_v($lid); ?>][success_text]" rows="2" maxlength="<?php echo abp15_v($max_text); ?>" id="input-success-text<?php echo abp15_v($lid); ?>" class="form-control"><?php echo abp15_e(abp15_v($message_description[abp15_v($lid)]['success_text'])); ?></textarea>
                <span class="abp-help"><?php echo abp15_v($help_success_text); ?></span>
                <?php if (isset($error['success_text'][abp15_v($lid)])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['success_text'][abp15_v($lid)])); ?></div><?php } ?>
              </div>
            </div>
          </div>
          <?php } foreach ($__k13 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-progress-bar"><?php echo abp15_v($entry_progress_bar); ?></label>
            <div class="col-sm-10">
              <div class="checkbox"><label><input type="checkbox" name="progress_bar" id="input-progress-bar" value="1"<?php if (abp15_v($progress_bar)) { ?> checked="checked"<?php } ?> /> <?php echo abp15_v($text_progress_bar); ?></label></div>
            </div>
          </div>
        </div>
      </div>

      
      <div class="panel panel-default abp-card" id="abp-card-button">
        <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-hand-pointer-o"></i></span> <?php echo abp15_v($text_section_button); ?></div></div>
        <div class="panel-body">
          <?php $__k14 = array(); foreach (array('language') as $__x) { $__k14[$__x] = isset($$__x) ? array($$__x) : null; } $__s14 = (array)abp15_v($languages); $__c14 = count($__s14); $__i14 = 0; foreach ($__s14 as $language) { $loop14 = array('index0' => $__i14, 'index' => $__i14 + 1, 'first' => $__i14 === 0, 'last' => $__i14 === $__c14 - 1); $__i14++; ?>
          <?php $lid = abp15_v($language['language_id']); ?>
          <div class="abp-lang-pane" data-lang="<?php echo abp15_v($lid); ?>">
            <div class="form-group">
              <label class="col-sm-2 control-label" for="input-link-text<?php echo abp15_v($lid); ?>"><?php echo abp15_v($entry_link_text); ?><span class="abp-lang-tag"><?php echo abp15_e(abp15_v($language['code'])); ?></span></label>
              <div class="col-sm-10">
                <input type="text" name="message_description[<?php echo abp15_v($lid); ?>][link_text]" value="<?php echo abp15_e(abp15_v($message_description[abp15_v($lid)]['link_text'])); ?>" maxlength="<?php echo abp15_v($max_link_text); ?>" id="input-link-text<?php echo abp15_v($lid); ?>" class="form-control abp-link-text" />
                <span class="abp-help"><?php echo abp15_v($help_link_text); ?></span>
                <?php if (isset($error['link_text'][abp15_v($lid)])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['link_text'][abp15_v($lid)])); ?></div><?php } ?>
              </div>
            </div>
          </div>
          <?php } foreach ($__k14 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-link"><?php echo abp15_v($entry_link); ?></label>
            <div class="col-sm-10">
              <input type="text" name="link" value="<?php echo abp15_e(abp15_v($link)); ?>" maxlength="255" placeholder="https://… / index.php?route=…" id="input-link" class="form-control" />
              <div class="checkbox"><label><input type="checkbox" name="link_new_tab" value="1"<?php if (abp15_v($link_new_tab)) { ?> checked="checked"<?php } ?> /> <?php echo abp15_v($entry_link_new_tab); ?></label></div>
              <span class="abp-help"><?php echo abp15_v($help_link); ?></span>
              <?php if (isset($error['link'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['link'])); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label"><?php echo abp15_v($entry_button_style); ?></label>
            <div class="col-sm-10">
              <span class="abp-seg" role="radiogroup" aria-label="<?php echo abp15_e(abp15_v($entry_button_style)); ?>">
                <?php $__k15 = array(); foreach (array('value') as $__x) { $__k15[$__x] = isset($$__x) ? array($$__x) : null; } $__s15 = (array)array('filled', 'outline', 'link'); $__c15 = count($__s15); $__i15 = 0; foreach ($__s15 as $value) { $loop15 = array('index0' => $__i15, 'index' => $__i15 + 1, 'first' => $__i15 === 0, 'last' => $__i15 === $__c15 - 1); $__i15++; ?><input type="radio" name="button_style" id="input-button-style-<?php echo abp15_v($value); ?>" value="<?php echo abp15_v($value); ?>"<?php if ((abp15_v($button_style) == abp15_v($value))) { ?> checked="checked"<?php } ?> /><label for="input-button-style-<?php echo abp15_v($value); ?>"><?php echo abp15_v(${('text_button_' . abp15_v($value))}); ?></label><?php } foreach ($__k15 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
              </span>
              <?php if (isset($error['button_style'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['button_style'])); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-coupon-id"><?php echo abp15_v($entry_coupon_id); ?></label>
            <div class="col-sm-10">
              <div class="abp-chips" id="input-coupon-id" data-abp-ac="coupon" data-name="opt[coupon_id]" data-max="1">
                <?php $__k16 = array(); foreach (array('id', 'name') as $__x) { $__k16[$__x] = isset($$__x) ? array($$__x) : null; } $__s16 = (array)abp15_v($coupon_chip); $__c16 = count($__s16); $__i16 = 0; foreach ($__s16 as $id => $name) { $loop16 = array('index0' => $__i16, 'index' => $__i16 + 1, 'first' => $__i16 === 0, 'last' => $__i16 === $__c16 - 1); $__i16++; ?><span class="abp-chip"><span class="abp-chip-text"><?php echo abp15_e(abp15_v($name)); ?></span><input type="hidden" name="opt[coupon_id]" value="<?php echo abp15_v($id); ?>" /><button type="button" class="abp-chip-x" aria-label="<?php echo abp15_e(abp15_v($button_remove)); ?>: <?php echo abp15_e(abp15_v($name)); ?>">&times;</button></span><?php } foreach ($__k16 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
                <input type="text" class="abp-ac-input" placeholder="<?php echo abp15_e(abp15_v($text_autocomplete_coupon)); ?>" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-label="<?php echo abp15_e(abp15_v($entry_coupon_id)); ?>" />
                <div class="abp-ac-menu" role="listbox" hidden></div>
              </div>
              <span class="abp-help"><?php echo abp15_v($help_coupon_id); ?></span>
              <?php if (isset($error['opt_coupon_id'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['opt_coupon_id'])); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group" data-abp-show="!opt[coupon_id]">
            <label class="col-sm-2 control-label" for="input-coupon"><?php echo abp15_v($entry_coupon); ?></label>
            <div class="col-sm-10">
              <div class="input-group" style="max-width:300px;">
                <span class="input-group-addon"><i class="fa fa-ticket"></i></span>
                <input type="text" name="coupon" value="<?php echo abp15_e(abp15_v($coupon)); ?>" maxlength="32" placeholder="SPRING10" id="input-coupon" class="form-control" style="text-transform:uppercase;font-family:monospace;letter-spacing:.06em;" autocomplete="off" />
              </div>
              <span class="abp-help"><?php echo abp15_v($help_coupon); ?></span>
              <?php if (isset($error['coupon'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['coupon'])); ?></div><?php } ?>
            </div>
          </div>
        </div>
      </div>

      
      <div class="panel panel-default abp-card" id="abp-card-email">
        <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-envelope"></i></span> <?php echo abp15_v($text_section_email); ?></div></div>
        <div class="panel-body">
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-email"><?php echo abp15_v($entry_email_capture); ?></label>
            <div class="col-sm-10">
              <div class="checkbox"><label><input type="checkbox" name="opt[email]" id="input-email" value="1"<?php if (abp15_v($opt['email'])) { ?> checked="checked"<?php } ?> /> <?php echo abp15_v($text_email_capture); ?></label></div>
              <span class="abp-help"><?php echo abp15_v($help_email_capture); ?></span>
            </div>
          </div>
          <div data-abp-show="opt[email]">
            <?php $__k17 = array(); foreach (array('language') as $__x) { $__k17[$__x] = isset($$__x) ? array($$__x) : null; } $__s17 = (array)abp15_v($languages); $__c17 = count($__s17); $__i17 = 0; foreach ($__s17 as $language) { $loop17 = array('index0' => $__i17, 'index' => $__i17 + 1, 'first' => $__i17 === 0, 'last' => $__i17 === $__c17 - 1); $__i17++; ?>
            <?php $lid = abp15_v($language['language_id']); ?>
            <div class="abp-lang-pane" data-lang="<?php echo abp15_v($lid); ?>">
              <?php $__k18 = array(); foreach (array('field') as $__x) { $__k18[$__x] = isset($$__x) ? array($$__x) : null; } $__s18 = (array)array('email_placeholder', 'email_button', 'email_consent', 'email_success'); $__c18 = count($__s18); $__i18 = 0; foreach ($__s18 as $field) { $loop18 = array('index0' => $__i18, 'index' => $__i18 + 1, 'first' => $__i18 === 0, 'last' => $__i18 === $__c18 - 1); $__i18++; ?>
              <div class="form-group">
                <label class="col-sm-2 control-label" for="input-<?php echo abp15_v($field); ?><?php echo abp15_v($lid); ?>"><?php echo abp15_v(${('entry_' . abp15_v($field))}); ?><span class="abp-lang-tag"><?php echo abp15_e(abp15_v($language['code'])); ?></span></label>
                <div class="col-sm-10">
                  <input type="text" name="message_description[<?php echo abp15_v($lid); ?>][<?php echo abp15_v($field); ?>]" value="<?php echo abp15_e(abp15_v($message_description[abp15_v($lid)][abp15_v($field)])); ?>" maxlength="<?php echo ((abp15_v($field) == 'email_consent') ? 300 : 160); ?>" id="input-<?php echo abp15_v($field); ?><?php echo abp15_v($lid); ?>" class="form-control" placeholder="<?php echo abp15_e(abp15_v(${(('text_' . abp15_v($field)) . '_default')})); ?>" />
                  <?php if ((isset($error[abp15_v($field)]) && isset($error[abp15_v($field)][abp15_v($lid)]))) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error[abp15_v($field)][abp15_v($lid)])); ?></div><?php } ?>
                </div>
              </div>
              <?php } foreach ($__k18 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
            </div>
            <?php } foreach ($__k17 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
            <p class="abp-hint"><i class="fa fa-shield"></i> <?php echo abp15_v($help_email_texts); ?></p>
          </div>
        </div>
      </div>

      
      <div class="panel panel-default abp-card" id="abp-card-ab">
        <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-random"></i></span> <?php echo abp15_v($text_section_ab); ?></div></div>
        <div class="panel-body">
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-ab-test"><?php echo abp15_v($entry_ab_test); ?></label>
            <div class="col-sm-10">
              <div class="checkbox"><label><input type="checkbox" name="ab_test" id="input-ab-test" value="1"<?php if (abp15_v($ab_test)) { ?> checked="checked"<?php } ?> /> <?php echo abp15_v($text_ab_enable); ?></label></div>
              <span class="abp-help"><?php echo abp15_v($help_ab); ?></span>
            </div>
          </div>
          <div data-abp-show="ab_test">
            <?php $__k19 = array(); foreach (array('language') as $__x) { $__k19[$__x] = isset($$__x) ? array($$__x) : null; } $__s19 = (array)abp15_v($languages); $__c19 = count($__s19); $__i19 = 0; foreach ($__s19 as $language) { $loop19 = array('index0' => $__i19, 'index' => $__i19 + 1, 'first' => $__i19 === 0, 'last' => $__i19 === $__c19 - 1); $__i19++; ?>
            <?php $lid = abp15_v($language['language_id']); ?>
            <div class="abp-lang-pane" data-lang="<?php echo abp15_v($lid); ?>">
              <div class="form-group required">
                <label class="col-sm-2 control-label" for="input-text-b<?php echo abp15_v($lid); ?>"><?php echo abp15_v($entry_text_b); ?><span class="abp-lang-tag"><?php echo abp15_e(abp15_v($language['code'])); ?></span></label>
                <div class="col-sm-10">
                  <textarea name="message_description[<?php echo abp15_v($lid); ?>][text_b]" rows="2" maxlength="<?php echo abp15_v($max_text); ?>" id="input-text-b<?php echo abp15_v($lid); ?>" class="form-control"><?php echo abp15_e(abp15_v($message_description[abp15_v($lid)]['text_b'])); ?></textarea>
                  <?php if (isset($error['text_b'][abp15_v($lid)])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['text_b'][abp15_v($lid)])); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-2 control-label" for="input-mobile-text-b<?php echo abp15_v($lid); ?>"><?php echo abp15_v($entry_mobile_text_b); ?></label>
                <div class="col-sm-10">
                  <textarea name="message_description[<?php echo abp15_v($lid); ?>][mobile_text_b]" rows="2" maxlength="<?php echo abp15_v($max_text); ?>" id="input-mobile-text-b<?php echo abp15_v($lid); ?>" class="form-control"><?php echo abp15_e(abp15_v($message_description[abp15_v($lid)]['mobile_text_b'])); ?></textarea>
                  <?php if (isset($error['mobile_text_b'][abp15_v($lid)])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['mobile_text_b'][abp15_v($lid)])); ?></div><?php } ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-2 control-label" for="input-link-text-b<?php echo abp15_v($lid); ?>"><?php echo abp15_v($entry_link_text_b); ?></label>
                <div class="col-sm-10">
                  <input type="text" name="message_description[<?php echo abp15_v($lid); ?>][link_text_b]" value="<?php echo abp15_e(abp15_v($message_description[abp15_v($lid)]['link_text_b'])); ?>" maxlength="<?php echo abp15_v($max_link_text); ?>" id="input-link-text-b<?php echo abp15_v($lid); ?>" class="form-control" />
                  <span class="abp-help"><?php echo abp15_v($help_link_text_b); ?></span>
                  <?php if (isset($error['link_text_b'][abp15_v($lid)])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['link_text_b'][abp15_v($lid)])); ?></div><?php } ?>
                </div>
              </div>
            </div>
            <?php } foreach ($__k19 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
          </div>
        </div>
      </div>

      
      <div class="panel panel-default abp-card" id="abp-card-behaviour">
        <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-th-large"></i></span> <?php echo abp15_v($text_section_behaviour); ?></div></div>
        <div class="panel-body">
          <div class="form-group">
            <label class="col-sm-2 control-label"><?php echo abp15_v($entry_placement); ?></label>
            <div class="col-sm-10">
              <div class="abp-places" role="radiogroup" aria-label="<?php echo abp15_e(abp15_v($entry_placement)); ?>">
                <?php $__k20 = array(); foreach (array('value') as $__x) { $__k20[$__x] = isset($$__x) ? array($$__x) : null; } $__s20 = (array)abp15_v($placements); $__c20 = count($__s20); $__i20 = 0; foreach ($__s20 as $value) { $loop20 = array('index0' => $__i20, 'index' => $__i20 + 1, 'first' => $__i20 === 0, 'last' => $__i20 === $__c20 - 1); $__i20++; ?>
                <?php $key = ((abp15_v($value) == '') ? 'default' : abp15_v($value)); ?>
                <div class="abp-place-opt"><input type="radio" name="placement" id="input-placement-<?php echo abp15_v($key); ?>" value="<?php echo abp15_v($value); ?>"<?php if ((abp15_v($placement) == abp15_v($value))) { ?> checked="checked"<?php } ?> /><label for="input-placement-<?php echo abp15_v($key); ?>"><span class="abp-pl-ico abp-pl-<?php echo abp15_v($key); ?>" aria-hidden="true"><i></i></span><span class="abp-name"><?php echo abp15_v(${('text_placement_' . abp15_v($key))}); ?></span></label></div>
                <?php } foreach ($__k20 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
              </div>
              <span class="abp-help"><?php echo abp15_v($help_placement); ?></span>
              <?php if (isset($error['placement'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['placement'])); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-priority"><?php echo abp15_v($entry_priority); ?></label>
            <div class="col-sm-10">
              <input type="number" min="0" max="100" name="priority" value="<?php echo abp15_e(abp15_v($priority)); ?>" id="input-priority" class="form-control abp-num" />
              <span class="abp-help"><?php echo abp15_v($help_priority); ?></span>
              <?php if (isset($error['priority'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['priority'])); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-trigger"><?php echo abp15_v($entry_trigger); ?></label>
            <div class="col-sm-10">
              <div class="abp-inline-fields">
                <select name="opt[trigger]" id="input-trigger" class="form-control" style="max-width:260px;">
                  <?php $__k21 = array(); foreach (array('value') as $__x) { $__k21[$__x] = isset($$__x) ? array($$__x) : null; } $__s21 = (array)array('none', 'delay', 'scroll', 'exit'); $__c21 = count($__s21); $__i21 = 0; foreach ($__s21 as $value) { $loop21 = array('index0' => $__i21, 'index' => $__i21 + 1, 'first' => $__i21 === 0, 'last' => $__i21 === $__c21 - 1); $__i21++; ?><option value="<?php echo abp15_v($value); ?>"<?php if ((abp15_v($opt['trigger']) == abp15_v($value))) { ?> selected="selected"<?php } ?>><?php echo abp15_v(${('text_trigger_' . abp15_v($value))}); ?></option><?php } foreach ($__k21 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
                </select>
                <span data-abp-show="opt[trigger]=delay|scroll" class="abp-inline-fields"><input type="number" min="1" name="opt[trigger_value]" value="<?php echo (abp15_v($opt['trigger_value']) ? abp15_e(abp15_v($opt['trigger_value'])) : ''); ?>" id="input-trigger-value" class="form-control abp-num" aria-label="<?php echo abp15_e(abp15_v($entry_trigger_value)); ?>" /><span class="abp-unit" data-abp-show="opt[trigger]=delay"><?php echo abp15_v($text_seconds); ?></span><span class="abp-unit" data-abp-show="opt[trigger]=scroll">%</span></span>
              </div>
              <span class="abp-help"><?php echo abp15_v($help_trigger); ?></span>
              <?php if (isset($error['opt_trigger'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['opt_trigger'])); ?></div><?php } ?>
            </div>
          </div>
        </div>
      </div>

      
      <div class="panel panel-default abp-card" id="abp-card-schedule">
        <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-calendar"></i></span> <?php echo abp15_v($text_section_schedule); ?></div></div>
        <div class="panel-body">
          <?php $__k22 = array(); foreach (array('key') as $__x) { $__k22[$__x] = isset($$__x) ? array($$__x) : null; } $__s22 = (array)array('date_start', 'date_end'); $__c22 = count($__s22); $__i22 = 0; foreach ($__s22 as $key) { $loop22 = array('index0' => $__i22, 'index' => $__i22 + 1, 'first' => $__i22 === 0, 'last' => $__i22 === $__c22 - 1); $__i22++; ?>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-<?php echo abp15_v($key); ?>"><?php echo abp15_v(${('entry_' . abp15_v($key))}); ?></label>
            <div class="col-sm-10">
              <div class="input-group datetime" style="max-width:300px;">
                <input type="text" name="<?php echo abp15_v($key); ?>" value="<?php echo abp15_e(abp15_v(${abp15_v($key)})); ?>" placeholder="YYYY-MM-DD HH:mm" data-date-format="YYYY-MM-DD HH:mm" id="input-<?php echo abp15_v($key); ?>" class="form-control" />
                <span class="input-group-btn"><button type="button" class="btn btn-default" aria-label="<?php echo abp15_e(abp15_v(${('entry_' . abp15_v($key))})); ?>"><i class="fa fa-calendar"></i></button></span>
              </div>
              <?php if ((abp15_v($key) == 'date_end')) { ?><div class="checkbox"><label><input type="checkbox" name="end_all_day" id="input-end-all-day" value="1"<?php if (abp15_v($end_all_day)) { ?> checked="checked"<?php } ?> /> <?php echo abp15_v($entry_end_all_day); ?></label></div><?php } ?>
              <span class="abp-help"><?php echo abp15_v($help_dates); ?></span>
              <?php if (isset($error[abp15_v($key)])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error[abp15_v($key)])); ?></div><?php } ?>
            </div>
          </div>
          <?php } foreach ($__k22 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-countdown"><?php echo abp15_v($entry_countdown); ?></label>
            <div class="col-sm-10">
              <div class="checkbox"><label><input type="checkbox" name="countdown" id="input-countdown" value="1"<?php if (abp15_v($countdown)) { ?> checked="checked"<?php } ?> /> <?php echo abp15_v($text_yes); ?></label></div>
              <div class="abp-inline-fields" data-abp-show="countdown">
                <select name="opt[recur]" id="input-recur" class="form-control" style="max-width:280px;" aria-label="<?php echo abp15_e(abp15_v($entry_recur)); ?>">
                  <?php $__k23 = array(); foreach (array('value') as $__x) { $__k23[$__x] = isset($$__x) ? array($$__x) : null; } $__s23 = (array)array('', 'daily', 'weekly'); $__c23 = count($__s23); $__i23 = 0; foreach ($__s23 as $value) { $loop23 = array('index0' => $__i23, 'index' => $__i23 + 1, 'first' => $__i23 === 0, 'last' => $__i23 === $__c23 - 1); $__i23++; ?><option value="<?php echo abp15_v($value); ?>"<?php if ((abp15_v($opt['recur']) == abp15_v($value))) { ?> selected="selected"<?php } ?>><?php echo abp15_v(${('text_recur_' . (((abp15_v($value) == '') ? 'end' : abp15_v($value))))}); ?></option><?php } foreach ($__k23 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
                </select>
                <span data-abp-show="opt[recur]=weekly"><select name="opt[recur_day]" class="form-control" aria-label="<?php echo abp15_e(abp15_v($entry_recur_day)); ?>"><?php $__k24 = array(); foreach (array('day') as $__x) { $__k24[$__x] = isset($$__x) ? array($$__x) : null; } $__s24 = (array)range(1, 7); $__c24 = count($__s24); $__i24 = 0; foreach ($__s24 as $day) { $loop24 = array('index0' => $__i24, 'index' => $__i24 + 1, 'first' => $__i24 === 0, 'last' => $__i24 === $__c24 - 1); $__i24++; ?><option value="<?php echo abp15_v($day); ?>"<?php if ((abp15_v($opt['recur_day']) == abp15_v($day))) { ?> selected="selected"<?php } ?>><?php echo abp15_v(${('text_wd_' . abp15_v($day))}); ?></option><?php } foreach ($__k24 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?></select></span>
                <span data-abp-show="opt[recur]=daily|weekly" class="abp-inline-fields"><span><?php echo abp15_v($text_at); ?></span><input type="time" name="opt[recur_time]" value="<?php echo abp15_e(abp15_v($opt['recur_time'])); ?>" class="form-control" aria-label="<?php echo abp15_e(abp15_v($entry_recur_time)); ?>" /></span>
              </div>
              <div data-abp-show="opt[recur]=daily" class="abp-recur-skip">
                <span class="abp-sub-label"><?php echo abp15_v($entry_recur_skip); ?></span>
                <div class="abp-days" role="group" aria-label="<?php echo abp15_e(abp15_v($entry_recur_skip)); ?>">
                  <?php $__k25 = array(); foreach (array('day') as $__x) { $__k25[$__x] = isset($$__x) ? array($$__x) : null; } $__s25 = (array)abp15_v($recur_skip_list); $__c25 = count($__s25); $__i25 = 0; foreach ($__s25 as $day) { $loop25 = array('index0' => $__i25, 'index' => $__i25 + 1, 'first' => $__i25 === 0, 'last' => $__i25 === $__c25 - 1); $__i25++; ?><label class="abp-day"><input type="checkbox" name="opt[recur_skip][]" value="<?php echo abp15_v($day['value']); ?>"<?php if (abp15_v($day['checked'])) { ?> checked="checked"<?php } ?> /><span><?php echo abp15_e(abp15_v($day['name'])); ?></span></label><?php } foreach ($__k25 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
                </div>
              </div>
              <span class="abp-help"><?php echo abp15_v($help_countdown); ?> <?php echo abp15_v($help_recur); ?></span>
              <?php if (isset($error['countdown'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['countdown'])); ?></div><?php } ?>
              <?php if (isset($error['opt_recur'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['opt_recur'])); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label"><?php echo abp15_v($entry_weekdays); ?></label>
            <div class="col-sm-10">
              <div class="abp-days" role="group" aria-label="<?php echo abp15_e(abp15_v($entry_weekdays)); ?>">
                <?php $__k26 = array(); foreach (array('day') as $__x) { $__k26[$__x] = isset($$__x) ? array($$__x) : null; } $__s26 = (array)abp15_v($weekday_list); $__c26 = count($__s26); $__i26 = 0; foreach ($__s26 as $day) { $loop26 = array('index0' => $__i26, 'index' => $__i26 + 1, 'first' => $__i26 === 0, 'last' => $__i26 === $__c26 - 1); $__i26++; ?><label class="abp-day"><input type="checkbox" name="weekdays[]" value="<?php echo abp15_v($day['value']); ?>"<?php if (abp15_v($day['checked'])) { ?> checked="checked"<?php } ?> /><span><?php echo abp15_e(abp15_v($day['name'])); ?></span></label><?php } foreach ($__k26 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
              </div>
              <span class="abp-help"><?php echo abp15_v($help_weekdays); ?></span>
              <?php if (isset($error['weekdays'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['weekdays'])); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-time-start"><?php echo abp15_v($entry_time); ?></label>
            <div class="col-sm-10">
              <div class="abp-time">
                <span><?php echo abp15_v($text_time_from); ?></span><input type="time" name="time_start" value="<?php echo abp15_e(abp15_v($time_start)); ?>" id="input-time-start" class="form-control" />
                <span><?php echo abp15_v($text_time_to); ?></span><input type="time" name="time_end" value="<?php echo abp15_e(abp15_v($time_end)); ?>" id="input-time-end" class="form-control" aria-label="<?php echo abp15_e(abp15_v($entry_time)); ?> <?php echo abp15_e(abp15_v($text_time_to)); ?>" />
              </div>
              <span class="abp-help"><?php echo abp15_v($help_time); ?></span>
              <?php if (isset($error['time_start'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['time_start'])); ?></div><?php } ?>
            </div>
          </div>
        </div>
      </div>

      
      <div class="panel panel-default abp-card" id="abp-card-targeting">
        <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-crosshairs"></i></span> <?php echo abp15_v($text_section_targeting); ?></div></div>
        <div class="panel-body">
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-device"><?php echo abp15_v($entry_device); ?></label>
            <div class="col-sm-10">
              <select name="device" id="input-device" class="form-control">
                <?php $__k27 = array(); foreach (array('value') as $__x) { $__k27[$__x] = isset($$__x) ? array($$__x) : null; } $__s27 = (array)array('all', 'desktop', 'mobile'); $__c27 = count($__s27); $__i27 = 0; foreach ($__s27 as $value) { $loop27 = array('index0' => $__i27, 'index' => $__i27 + 1, 'first' => $__i27 === 0, 'last' => $__i27 === $__c27 - 1); $__i27++; ?>
                <option value="<?php echo abp15_v($value); ?>"<?php if ((abp15_v($device) == abp15_v($value))) { ?> selected="selected"<?php } ?>><?php echo abp15_v(${('text_device_' . abp15_v($value))}); ?></option>
                <?php } foreach ($__k27 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
              </select>
              <span class="abp-help"><?php echo abp15_v($help_device); ?></span>
              <?php if (isset($error['device'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['device'])); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-page-mode"><?php echo abp15_v($entry_page_mode); ?></label>
            <div class="col-sm-10">
              <select name="page_mode" id="input-page-mode" class="form-control">
                <?php $__k28 = array(); foreach (array('value') as $__x) { $__k28[$__x] = isset($$__x) ? array($$__x) : null; } $__s28 = (array)array('all', 'home', 'include', 'exclude'); $__c28 = count($__s28); $__i28 = 0; foreach ($__s28 as $value) { $loop28 = array('index0' => $__i28, 'index' => $__i28 + 1, 'first' => $__i28 === 0, 'last' => $__i28 === $__c28 - 1); $__i28++; ?>
                <option value="<?php echo abp15_v($value); ?>"<?php if ((abp15_v($page_mode) == abp15_v($value))) { ?> selected="selected"<?php } ?>><?php echo abp15_v(${('text_page_' . abp15_v($value))}); ?></option>
                <?php } foreach ($__k28 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
              </select>
              <?php if (isset($error['page_mode'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['page_mode'])); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group" id="abp-routes-group" data-abp-show="page_mode=include|exclude">
            <label class="col-sm-2 control-label" for="input-page-routes"><?php echo abp15_v($entry_page_routes); ?></label>
            <div class="col-sm-10">
              <textarea name="page_routes" rows="4" id="input-page-routes" placeholder="product/product&#10;product/category&#10;checkout/*" class="form-control"><?php echo abp15_e(abp15_v($page_routes)); ?></textarea>
              <span class="abp-help"><?php echo abp15_v($help_routes); ?></span>
              <?php if (isset($error['page_routes'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['page_routes'])); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-categories"><?php echo abp15_v($entry_categories); ?></label>
            <div class="col-sm-10">
              <div class="abp-chips" id="input-categories" data-abp-ac="category" data-name="categories[]">
                <?php $__k29 = array(); foreach (array('id', 'name') as $__x) { $__k29[$__x] = isset($$__x) ? array($$__x) : null; } $__s29 = (array)abp15_v($category_chips); $__c29 = count($__s29); $__i29 = 0; foreach ($__s29 as $id => $name) { $loop29 = array('index0' => $__i29, 'index' => $__i29 + 1, 'first' => $__i29 === 0, 'last' => $__i29 === $__c29 - 1); $__i29++; ?><span class="abp-chip"><span class="abp-chip-text"><?php echo abp15_e(abp15_v($name)); ?></span><input type="hidden" name="categories[]" value="<?php echo abp15_v($id); ?>" /><button type="button" class="abp-chip-x" aria-label="<?php echo abp15_e(abp15_v($button_remove)); ?>: <?php echo abp15_e(abp15_v($name)); ?>">&times;</button></span><?php } foreach ($__k29 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
                <input type="text" class="abp-ac-input" placeholder="<?php echo abp15_e(abp15_v($text_autocomplete)); ?>" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-label="<?php echo abp15_e(abp15_v($entry_categories)); ?>" />
                <div class="abp-ac-menu" role="listbox" hidden></div>
              </div>
              <?php if (isset($error['categories'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['categories'])); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-products"><?php echo abp15_v($entry_products); ?></label>
            <div class="col-sm-10">
              <div class="abp-chips" id="input-products" data-abp-ac="product" data-name="products[]">
                <?php $__k30 = array(); foreach (array('id', 'name') as $__x) { $__k30[$__x] = isset($$__x) ? array($$__x) : null; } $__s30 = (array)abp15_v($product_chips); $__c30 = count($__s30); $__i30 = 0; foreach ($__s30 as $id => $name) { $loop30 = array('index0' => $__i30, 'index' => $__i30 + 1, 'first' => $__i30 === 0, 'last' => $__i30 === $__c30 - 1); $__i30++; ?><span class="abp-chip"><span class="abp-chip-text"><?php echo abp15_e(abp15_v($name)); ?></span><input type="hidden" name="products[]" value="<?php echo abp15_v($id); ?>" /><button type="button" class="abp-chip-x" aria-label="<?php echo abp15_e(abp15_v($button_remove)); ?>: <?php echo abp15_e(abp15_v($name)); ?>">&times;</button></span><?php } foreach ($__k30 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
                <input type="text" class="abp-ac-input" placeholder="<?php echo abp15_e(abp15_v($text_autocomplete)); ?>" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-label="<?php echo abp15_e(abp15_v($entry_products)); ?>" />
                <div class="abp-ac-menu" role="listbox" hidden></div>
              </div>
              <span class="abp-help"><?php echo abp15_v($help_targeting); ?></span>
              <?php if (isset($error['products'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['products'])); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label"><?php echo abp15_v($entry_store); ?></label>
            <div class="col-sm-10">
              <?php if (abp15_v($orphaned['stores'])) { ?><div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> <?php echo abp15_v($text_orphan_stores); ?></div><?php } ?>
              <div class="well well-sm" style="max-height:120px;overflow:auto;margin-bottom:4px;">
                <?php $__k31 = array(); foreach (array('store') as $__x) { $__k31[$__x] = isset($$__x) ? array($$__x) : null; } $__s31 = (array)abp15_v($store_list); $__c31 = count($__s31); $__i31 = 0; foreach ($__s31 as $store) { $loop31 = array('index0' => $__i31, 'index' => $__i31 + 1, 'first' => $__i31 === 0, 'last' => $__i31 === $__c31 - 1); $__i31++; ?>
                <div class="checkbox"><label><input type="checkbox" name="stores[]" value="<?php echo abp15_v($store['store_id']); ?>"<?php if (abp15_in(abp15_v($store['store_id']), abp15_v($stores))) { ?> checked="checked"<?php } ?> /> <?php echo abp15_v($store['name']); ?></label></div>
                <?php } foreach ($__k31 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
              </div>
              <span class="abp-help"><?php echo abp15_v($help_empty_all); ?></span>
              <?php if (isset($error['stores'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['stores'])); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label"><?php echo abp15_v($entry_customer_group); ?></label>
            <div class="col-sm-10">
              <?php if (abp15_v($orphaned['customer_groups'])) { ?><div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> <?php echo abp15_v($text_orphan_customer_groups); ?></div><?php } ?>
              <div class="well well-sm" style="max-height:120px;overflow:auto;margin-bottom:4px;">
                <?php $__k32 = array(); foreach (array('group') as $__x) { $__k32[$__x] = isset($$__x) ? array($$__x) : null; } $__s32 = (array)abp15_v($customer_group_list); $__c32 = count($__s32); $__i32 = 0; foreach ($__s32 as $group) { $loop32 = array('index0' => $__i32, 'index' => $__i32 + 1, 'first' => $__i32 === 0, 'last' => $__i32 === $__c32 - 1); $__i32++; ?>
                <div class="checkbox"><label><input type="checkbox" name="customer_groups[]" value="<?php echo abp15_v($group['customer_group_id']); ?>"<?php if (abp15_in(abp15_v($group['customer_group_id']), abp15_v($customer_groups))) { ?> checked="checked"<?php } ?> /> <?php echo abp15_v($group['name']); ?></label></div>
                <?php } foreach ($__k32 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
              </div>
              <span class="abp-help"><?php echo abp15_v($help_empty_all); ?></span>
              <?php if (isset($error['customer_groups'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['customer_groups'])); ?></div><?php } ?>
            </div>
          </div>
        </div>
      </div>

      
      <div class="panel panel-default abp-card" id="abp-card-audience">
        <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-globe"></i></span> <?php echo abp15_v($text_section_audience); ?></div></div>
        <div class="panel-body">
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-countries-mode"><?php echo abp15_v($entry_countries); ?></label>
            <div class="col-sm-10">
              <select name="opt[countries_mode]" id="input-countries-mode" class="form-control" style="max-width:320px;">
                <?php $__k33 = array(); foreach (array('value') as $__x) { $__k33[$__x] = isset($$__x) ? array($$__x) : null; } $__s33 = (array)array('all', 'include', 'exclude'); $__c33 = count($__s33); $__i33 = 0; foreach ($__s33 as $value) { $loop33 = array('index0' => $__i33, 'index' => $__i33 + 1, 'first' => $__i33 === 0, 'last' => $__i33 === $__c33 - 1); $__i33++; ?><option value="<?php echo abp15_v($value); ?>"<?php if ((abp15_v($opt['countries_mode']) == abp15_v($value))) { ?> selected="selected"<?php } ?>><?php echo abp15_v(${('text_countries_' . abp15_v($value))}); ?></option><?php } foreach ($__k33 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
              </select>
              <div class="abp-chips" id="input-countries" data-abp-ac="country" data-name="opt[countries][]" data-abp-show="opt[countries_mode]=include|exclude" style="margin-top:8px;">
                <?php $__k34 = array(); foreach (array('id', 'name') as $__x) { $__k34[$__x] = isset($$__x) ? array($$__x) : null; } $__s34 = (array)abp15_v($country_chips); $__c34 = count($__s34); $__i34 = 0; foreach ($__s34 as $id => $name) { $loop34 = array('index0' => $__i34, 'index' => $__i34 + 1, 'first' => $__i34 === 0, 'last' => $__i34 === $__c34 - 1); $__i34++; ?><span class="abp-chip"><span class="abp-chip-text"><?php echo abp15_e(abp15_v($name)); ?></span><input type="hidden" name="opt[countries][]" value="<?php echo abp15_v($id); ?>" /><button type="button" class="abp-chip-x" aria-label="<?php echo abp15_e(abp15_v($button_remove)); ?>: <?php echo abp15_e(abp15_v($name)); ?>">&times;</button></span><?php } foreach ($__k34 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
                <input type="text" class="abp-ac-input" placeholder="<?php echo abp15_e(abp15_v($text_autocomplete)); ?>" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-label="<?php echo abp15_e(abp15_v($entry_countries)); ?>" />
                <div class="abp-ac-menu" role="listbox" hidden></div>
              </div>
              <span class="abp-help"><?php echo abp15_v($help_countries); ?></span>
              <?php if (isset($error['opt_countries'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['opt_countries'])); ?></div><?php } ?>
            </div>
          </div>
          <?php $__k35 = array(); foreach (array('key') as $__x) { $__k35[$__x] = isset($$__x) ? array($$__x) : null; } $__s35 = (array)array('utm_source', 'utm_medium', 'utm_campaign', 'referrer'); $__c35 = count($__s35); $__i35 = 0; foreach ($__s35 as $key) { $loop35 = array('index0' => $__i35, 'index' => $__i35 + 1, 'first' => $__i35 === 0, 'last' => $__i35 === $__c35 - 1); $__i35++; ?>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-<?php echo abp15_v($key); ?>"><?php echo abp15_v(${('entry_' . abp15_v($key))}); ?></label>
            <div class="col-sm-10">
              <input type="text" name="opt[<?php echo abp15_v($key); ?>]" value="<?php echo abp15_e(abp15_v($opt[abp15_v($key)])); ?>" id="input-<?php echo abp15_v($key); ?>" class="form-control" placeholder="<?php echo ((abp15_v($key) == 'referrer') ? 'facebook.com, instagram.com' : (((abp15_v($key) == 'utm_source') ? 'newsletter, google' : (((abp15_v($key) == 'utm_medium') ? 'email, cpc' : 'black-friday'))))); ?>" autocomplete="off" spellcheck="false" />
              <?php if (abp15_v($loop35['last'])) { ?><span class="abp-help"><?php echo abp15_v($help_campaign); ?></span><?php } ?>
              <?php if (isset($error[('opt_' . abp15_v($key))])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error[('opt_' . abp15_v($key))])); ?></div><?php } ?>
            </div>
          </div>
          <?php } foreach ($__k35 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-visitor"><?php echo abp15_v($entry_visitor); ?></label>
            <div class="col-sm-10">
              <select name="opt[visitor]" id="input-visitor" class="form-control" style="max-width:320px;">
                <?php $__k36 = array(); foreach (array('value') as $__x) { $__k36[$__x] = isset($$__x) ? array($$__x) : null; } $__s36 = (array)array('all', 'new', 'returning'); $__c36 = count($__s36); $__i36 = 0; foreach ($__s36 as $value) { $loop36 = array('index0' => $__i36, 'index' => $__i36 + 1, 'first' => $__i36 === 0, 'last' => $__i36 === $__c36 - 1); $__i36++; ?><option value="<?php echo abp15_v($value); ?>"<?php if ((abp15_v($opt['visitor']) == abp15_v($value))) { ?> selected="selected"<?php } ?>><?php echo abp15_v(${('text_visitor_' . abp15_v($value))}); ?></option><?php } foreach ($__k36 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
              </select>
              <span class="abp-help"><?php echo abp15_v($help_visitor); ?></span>
              <?php if (isset($error['opt_visitor'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['opt_visitor'])); ?></div><?php } ?>
            </div>
          </div>
        </div>
      </div>

      
      <div class="panel panel-default abp-card" id="abp-card-cart">
        <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-shopping-cart"></i></span> <?php echo abp15_v($text_section_cart); ?></div></div>
        <div class="panel-body">
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-cart-subtotal-min"><?php echo abp15_v($entry_cart_subtotal); ?></label>
            <div class="col-sm-10">
              <div class="abp-inline-fields">
                <span><?php echo abp15_v($text_min); ?></span><input type="text" inputmode="decimal" name="opt[cart_subtotal_min]" value="<?php echo abp15_e(abp15_v($opt['cart_subtotal_min'])); ?>" id="input-cart-subtotal-min" class="form-control abp-num" />
                <span><?php echo abp15_v($text_max); ?></span><input type="text" inputmode="decimal" name="opt[cart_subtotal_max]" value="<?php echo abp15_e(abp15_v($opt['cart_subtotal_max'])); ?>" class="form-control abp-num" aria-label="<?php echo abp15_e(abp15_v($entry_cart_subtotal)); ?> <?php echo abp15_e(abp15_v($text_max)); ?>" />
                <span class="abp-unit"><?php echo abp15_e(abp15_v($currency_code)); ?></span>
              </div>
              <?php if (isset($error['opt_cart_subtotal_min'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['opt_cart_subtotal_min'])); ?></div><?php } ?>
              <?php if (isset($error['opt_cart_subtotal_max'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['opt_cart_subtotal_max'])); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-cart-qty-min"><?php echo abp15_v($entry_cart_qty); ?></label>
            <div class="col-sm-10">
              <div class="abp-inline-fields">
                <span><?php echo abp15_v($text_min); ?></span><input type="number" min="0" name="opt[cart_qty_min]" value="<?php echo abp15_e(abp15_v($opt['cart_qty_min'])); ?>" id="input-cart-qty-min" class="form-control abp-num" />
                <span><?php echo abp15_v($text_max); ?></span><input type="number" min="0" name="opt[cart_qty_max]" value="<?php echo abp15_e(abp15_v($opt['cart_qty_max'])); ?>" class="form-control abp-num" aria-label="<?php echo abp15_e(abp15_v($entry_cart_qty)); ?> <?php echo abp15_e(abp15_v($text_max)); ?>" />
              </div>
              <?php if (isset($error['opt_cart_qty_min'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['opt_cart_qty_min'])); ?></div><?php } ?>
              <?php if (isset($error['opt_cart_qty_max'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['opt_cart_qty_max'])); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-cart-products"><?php echo abp15_v($entry_cart_products); ?></label>
            <div class="col-sm-10">
              <div class="abp-chips" id="input-cart-products" data-abp-ac="product" data-name="opt[cart_products][]">
                <?php $__k37 = array(); foreach (array('id', 'name') as $__x) { $__k37[$__x] = isset($$__x) ? array($$__x) : null; } $__s37 = (array)abp15_v($cart_product_chips); $__c37 = count($__s37); $__i37 = 0; foreach ($__s37 as $id => $name) { $loop37 = array('index0' => $__i37, 'index' => $__i37 + 1, 'first' => $__i37 === 0, 'last' => $__i37 === $__c37 - 1); $__i37++; ?><span class="abp-chip"><span class="abp-chip-text"><?php echo abp15_e(abp15_v($name)); ?></span><input type="hidden" name="opt[cart_products][]" value="<?php echo abp15_v($id); ?>" /><button type="button" class="abp-chip-x" aria-label="<?php echo abp15_e(abp15_v($button_remove)); ?>: <?php echo abp15_e(abp15_v($name)); ?>">&times;</button></span><?php } foreach ($__k37 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
                <input type="text" class="abp-ac-input" placeholder="<?php echo abp15_e(abp15_v($text_autocomplete)); ?>" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-label="<?php echo abp15_e(abp15_v($entry_cart_products)); ?>" />
                <div class="abp-ac-menu" role="listbox" hidden></div>
              </div>
              <?php if (isset($error['opt_cart_products'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['opt_cart_products'])); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-cart-categories"><?php echo abp15_v($entry_cart_categories); ?></label>
            <div class="col-sm-10">
              <div class="abp-chips" id="input-cart-categories" data-abp-ac="category" data-name="opt[cart_categories][]">
                <?php $__k38 = array(); foreach (array('id', 'name') as $__x) { $__k38[$__x] = isset($$__x) ? array($$__x) : null; } $__s38 = (array)abp15_v($cart_category_chips); $__c38 = count($__s38); $__i38 = 0; foreach ($__s38 as $id => $name) { $loop38 = array('index0' => $__i38, 'index' => $__i38 + 1, 'first' => $__i38 === 0, 'last' => $__i38 === $__c38 - 1); $__i38++; ?><span class="abp-chip"><span class="abp-chip-text"><?php echo abp15_e(abp15_v($name)); ?></span><input type="hidden" name="opt[cart_categories][]" value="<?php echo abp15_v($id); ?>" /><button type="button" class="abp-chip-x" aria-label="<?php echo abp15_e(abp15_v($button_remove)); ?>: <?php echo abp15_e(abp15_v($name)); ?>">&times;</button></span><?php } foreach ($__k38 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
                <input type="text" class="abp-ac-input" placeholder="<?php echo abp15_e(abp15_v($text_autocomplete)); ?>" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-label="<?php echo abp15_e(abp15_v($entry_cart_categories)); ?>" />
                <div class="abp-ac-menu" role="listbox" hidden></div>
              </div>
              <span class="abp-help"><?php echo abp15_v($help_cart); ?></span>
              <?php if (isset($error['opt_cart_categories'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['opt_cart_categories'])); ?></div><?php } ?>
            </div>
          </div>
        </div>
      </div>

      
      <div class="panel panel-default abp-card" id="abp-card-appearance">
        <div class="panel-heading"><div class="abp-card-title"><span class="abp-ico-badge"><i class="fa fa-paint-brush"></i></span> <?php echo abp15_v($text_section_appearance); ?></div></div>
        <div class="panel-body">
          <div class="form-group">
            <label class="col-sm-2 control-label"><?php echo abp15_v($entry_message_style); ?></label>
            <div class="col-sm-10">
              <div class="abp-styles" role="radiogroup" aria-label="<?php echo abp15_e(abp15_v($entry_message_style)); ?>">
                <div class="abp-style-opt">
                  <input type="radio" name="style" id="input-style-default" value=""<?php if (!(abp15_v($style))) { ?> checked="checked"<?php } ?> />
                  <label for="input-style-default"><span class="abp-sw abp-sw-default"><i></i><b></b></span><span class="abp-name"><?php echo abp15_e(sprintf(abp15_v($text_style_default), abp15_v($module_style))); ?></span></label>
                </div>
                <?php $__k39 = array(); foreach (array('item') as $__x) { $__k39[$__x] = isset($$__x) ? array($$__x) : null; } $__s39 = (array)abp15_v($styles); $__c39 = count($__s39); $__i39 = 0; foreach ($__s39 as $item) { $loop39 = array('index0' => $__i39, 'index' => $__i39 + 1, 'first' => $__i39 === 0, 'last' => $__i39 === $__c39 - 1); $__i39++; ?>
                <div class="abp-style-opt">
                  <input type="radio" name="style" id="input-style-<?php echo abp15_v($item['value']); ?>" value="<?php echo abp15_v($item['value']); ?>"<?php if ((abp15_v($style) == abp15_v($item['value']))) { ?> checked="checked"<?php } ?> />
                  <label for="input-style-<?php echo abp15_v($item['value']); ?>"><span class="abp-sw abp-sw-<?php echo abp15_v($item['value']); ?>"><i></i><b></b></span><span class="abp-name"><?php echo abp15_e(abp15_v($item['name'])); ?></span><span class="abp-desc"><?php echo abp15_e(abp15_v($item['help'])); ?></span></label>
                </div>
                <?php } foreach ($__k39 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
              </div>
              <span class="abp-help"><?php echo abp15_v($help_style); ?></span>
              <?php if (isset($error['style'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['style'])); ?></div><?php } ?>
            </div>
          </div>
          <?php if (abp15_v($color_auto)) { ?><p class="abp-hint"><i class="fa fa-magic"></i> <?php echo abp15_v($text_color_auto_note); ?></p><?php } ?>
          <?php $__k40 = array(); foreach (array('key') as $__x) { $__k40[$__x] = isset($$__x) ? array($$__x) : null; } $__s40 = (array)array('bg_color', 'text_color'); $__c40 = count($__s40); $__i40 = 0; foreach ($__s40 as $key) { $loop40 = array('index0' => $__i40, 'index' => $__i40 + 1, 'first' => $__i40 === 0, 'last' => $__i40 === $__c40 - 1); $__i40++; ?>
          <div class="form-group required">
            <label class="col-sm-2 control-label" for="input-<?php echo abp15_v($key); ?>"><?php echo abp15_v(${('entry_' . abp15_v($key))}); ?></label>
            <div class="col-sm-10">
              <div class="input-group abp-color">
                <span class="input-group-addon"><input type="color" value="<?php echo abp15_e(abp15_v(${abp15_v($key)})); ?>" aria-label="<?php echo abp15_e(abp15_v(${('entry_' . abp15_v($key))})); ?>" /></span>
                <input type="text" name="<?php echo abp15_v($key); ?>" id="input-<?php echo abp15_v($key); ?>" value="<?php echo abp15_e(abp15_v(${abp15_v($key)})); ?>" maxlength="7" class="form-control" />
              </div>
              <?php if (isset($error[abp15_v($key)])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error[abp15_v($key)])); ?></div><?php } ?>
            </div>
          </div>
          <?php } foreach ($__k40 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-image"><?php echo abp15_v($entry_image); ?></label>
            <div class="col-sm-10">
              <div class="abp-image">
                <a href="#" id="thumb-image" data-abp15-image="input-image" data-manager="<?php echo abp15_e(abp15_v($filemanager)); ?>" data-thumb-url="<?php echo abp15_e(abp15_v($filemanager_image)); ?>" data-title="<?php echo abp15_e(abp15_v($text_image_manager)); ?>" class="img-thumbnail" aria-label="<?php echo abp15_e(abp15_v($entry_image)); ?>"><img src="<?php echo abp15_e(abp15_v($image_thumb)); ?>" alt="" data-placeholder="<?php echo abp15_e(abp15_v($placeholder)); ?>" /></a><button type="button" class="btn btn-default btn-sm abp15-image-clear" data-abp15-clear="input-image"><i class="fa fa-times"></i> <?php echo abp15_v($button_clear_image); ?></button>
                <input type="hidden" name="opt[image]" value="<?php echo abp15_e(abp15_v($opt['image'])); ?>" id="input-image" />
                <div class="abp-image-side" data-abp-show="opt[image]!=">
                  <label class="abp-sub-label" for="input-overlay"><?php echo abp15_v($entry_overlay); ?> <output id="abp-overlay-out"><?php echo abp15_v($opt['overlay']); ?></output>%</label>
                  <input type="range" min="0" max="90" step="5" name="opt[overlay]" value="<?php echo abp15_e(abp15_v($opt['overlay'])); ?>" id="input-overlay" />
                </div>
              </div>
              <span class="abp-help"><?php echo abp15_v($help_image); ?></span>
              <?php if (isset($error['opt_image'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['opt_image'])); ?></div><?php } ?>
              <?php if (isset($error['opt_overlay'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['opt_overlay'])); ?></div><?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-font"><?php echo abp15_v($entry_font); ?></label>
            <div class="col-sm-10">
              <select name="opt[font]" id="input-font" class="form-control" style="max-width:320px;">
                <option value=""<?php if ((abp15_v($opt['font']) == '')) { ?> selected="selected"<?php } ?>><?php echo abp15_v($text_font_module); ?></option>
                <?php $__k41 = array(); foreach (array('f') as $__x) { $__k41[$__x] = isset($$__x) ? array($$__x) : null; } $__s41 = (array)abp15_v($font_list); $__c41 = count($__s41); $__i41 = 0; foreach ($__s41 as $f) { $loop41 = array('index0' => $__i41, 'index' => $__i41 + 1, 'first' => $__i41 === 0, 'last' => $__i41 === $__c41 - 1); $__i41++; ?><?php if ((abp15_v($f['value']) != '')) { ?><option value="<?php echo abp15_v($f['value']); ?>"<?php if ((abp15_v($opt['font']) == abp15_v($f['value']))) { ?> selected="selected"<?php } ?> style="font-family:<?php echo abp15_e(abp15_v($f['stack'])); ?>"><?php echo abp15_e(abp15_v($f['name'])); ?></option><?php } ?><?php } foreach ($__k41 as $__x => $__y) { if ($__y === null) { unset($$__x); } else { $$__x = $__y[0]; } } ?>
              </select>
              <span class="abp-help"><?php echo abp15_v($help_font); ?></span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-sort-order"><?php echo abp15_v($entry_sort_order); ?></label>
            <div class="col-sm-10">
              <input type="number" name="sort_order" value="<?php echo abp15_e(abp15_v($sort_order)); ?>" id="input-sort-order" class="form-control abp-num" />
              <?php if (isset($error['sort_order'])) { ?><div class="text-danger"><?php echo abp15_e(abp15_v($error['sort_order'])); ?></div><?php } ?>
            </div>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>
<script type="application/json" id="abp-config"><?php echo abp15_v($config_json); ?></script>
<script type="text/javascript" src="<?php echo abp15_e(abp15_v($admin_js)); ?>"></script>
<script type="text/javascript"><!--
//--></script>
<script type="text/javascript" src="view/javascript/jquery/ui/jquery-ui-timepicker-addon.js"></script>
<script type="text/javascript" src="<?php echo abp15_e(abp15_v($oc15_js)); ?>"></script>
<?php echo abp15_v($footer); ?>
