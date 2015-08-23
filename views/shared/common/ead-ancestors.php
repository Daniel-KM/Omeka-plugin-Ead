<h2><?php echo __('Upper levels'); ?></h2>
<?php if (!empty($records)): ?>
<div class="ead ead-list ead-ancestors">
    <ul>
        <?php foreach ($records as $record): ?>
            <li>
                <?php echo link_to_item(null, array(), 'show', $record); ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php else: ?>
<p>
    <?php echo __('None.'); ?>
</p>
<?php endif; ?>
