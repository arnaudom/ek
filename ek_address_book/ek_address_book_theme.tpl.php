
<div class="row">
<?php if (!empty($display['left-content'])): ?>
  <aside class="col-sm-3" >
      <div class="region region-sidebar-first ">
      <?php print render($display['left-content']); ?>
      </div>
  </aside>
<?php endif; ?>
<?php 
$c = empty($display['left-content']) ? 'col-sm-12' : 'col-sm-9';
?>
<section class="<?php echo $c;?>">

  <div class="region region-content">
      <section  class="block block-system clearfix">
        <?php print render($display['main-content']); ?>
      <section></section>
  </div>
</section>
</div>