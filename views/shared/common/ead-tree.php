<h2><?php echo __('Archival Finding Aid'); ?></h2>
<?php if (!empty($tree)): ?>
<div class="ead ead-tree ead-full">
    <?php echo $this->ead()->html_tree($tree); ?>
</div>
<?php else: ?>
<p>
    <?php echo __('None.'); ?>
</p>
<?php endif; ?>
