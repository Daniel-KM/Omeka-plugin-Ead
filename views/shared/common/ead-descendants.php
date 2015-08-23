<h2><?php echo __('Lower levels'); ?></h2>
<?php if (!empty($tree) && count($tree) > 1): ?>
<div class="ead ead-tree ead-descendants">
    <?php echo $this->ead()->html_tree($tree, null, false); ?>
</div>
<?php else: ?>
<p>
    <?php echo __('None.'); ?>
</p>
<?php endif; ?>
