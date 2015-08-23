<h2><?php echo __('Finding Aid'); ?></h2>
<?php if (!empty($record)): ?>
<div class="ead ead-link ead-ancestor">
    <?php echo link_to_item(null, array(), 'show', $record); ?>
</div>
<?php else: ?>
<p>
    <?php echo __('None.'); ?>
</p>
<?php endif; ?>
