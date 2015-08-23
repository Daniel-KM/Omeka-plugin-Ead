<?php
$record = get_record_by_id('Item', $itemId);
$countChildren = count($tree[$itemId]);
echo link_to_item(null, array(), 'show', $record);
echo $countChildren ? ' (' . $countChildren . ')' : '';
?>
