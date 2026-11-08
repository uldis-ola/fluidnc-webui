<?php /* Announcement Bar Pro 2.4.2 — generated from the OpenCart 3 Twig template by tools/twig2tpl.py, then adapted for OpenCart 1.5. */ ?>
<?php echo abp15_v($header); ?>
<div id="abp-page" class="container">
  <div class="row"><?php echo abp15_v($column_left); ?>
    <div id="content" class="col-sm-12"><?php echo abp15_v($content_top); ?>
      <h1><?php echo abp15_e(abp15_v($title)); ?></h1>
      <p><?php echo abp15_e(abp15_v($text)); ?></p>
      <?php if (abp15_v($button)) { ?>
      <form action="<?php echo abp15_v($action); ?>" method="post">
        <input type="hidden" name="t" value="<?php echo abp15_e(abp15_v($token)); ?>" />
        <button type="submit" class="btn btn-primary"><?php echo abp15_e(abp15_v($button)); ?></button>
      </form>
      <?php } else { ?>
      <p><a href="<?php echo abp15_v($home); ?>" class="btn btn-default"><?php echo abp15_e(abp15_v($continue)); ?></a></p>
      <?php } ?>
      <?php echo abp15_v($content_bottom); ?></div>
    <?php echo abp15_v($column_right); ?></div>
</div>
<?php echo abp15_v($footer); ?>
