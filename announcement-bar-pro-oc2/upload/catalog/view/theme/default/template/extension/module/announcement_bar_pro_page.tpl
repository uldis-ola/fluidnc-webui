<?php defined('VERSION') || exit; /* Generated from the OpenCart 3 Twig template by tools/twig2tpl.py — edit the Twig source, then rebuild. */ ?>
<?php echo (isset($header) ? $header : null); ?>

<div id="abp-page" class="container">
  <div class="row"><?php echo (isset($column_left) ? $column_left : null); ?>

    <div id="content" class="col-sm-12"><?php echo (isset($content_top) ? $content_top : null); ?>

      <h1><?php echo AbpOc2::e((isset($title) ? $title : null)); ?></h1>
      <p><?php echo AbpOc2::e((isset($text) ? $text : null)); ?></p>
      <?php if (!empty($button)) { ?>
      <form action="<?php echo (isset($action) ? $action : null); ?>" method="post">
        <input type="hidden" name="t" value="<?php echo AbpOc2::e((isset($token) ? $token : null)); ?>" />
        <button type="submit" class="btn btn-primary"><?php echo AbpOc2::e((isset($button) ? $button : null)); ?></button>
      </form>
      <?php } else { ?>
      <p><a href="<?php echo (isset($home) ? $home : null); ?>" class="btn btn-default"><?php echo AbpOc2::e((isset($continue) ? $continue : null)); ?></a></p>
      <?php } ?>
      <?php echo (isset($content_bottom) ? $content_bottom : null); ?></div>
    <?php echo (isset($column_right) ? $column_right : null); ?></div>
</div>
<?php echo (isset($footer) ? $footer : null); ?>

