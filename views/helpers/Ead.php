<?php
/**
 * Helpers for EAD.
 *
 * The process search for the main finding aid, whatever the function.
 *
 * @todo Improve and group queries, or use the cache of descendants.
 * @todo Check public.
 *
 * @package Ead\View\Helper
 */
class Ead_View_Helper_Ead extends Zend_View_Helper_Abstract
{
    protected $_db;

    // These database ids simplify the sql queries.
    protected $_idFindingAid = 0;
    protected $_idIdentifier = 0;
    protected $_idIsPartOf = 0;
    protected $_idHasPart = 0;

    protected $_maxLevel = 15;

    // This temporary cache allows to get the flat ordered list of all levels of
    // a finding aid. It should be reset before processing a tree.
    protected $_cacheChildren = array();

    public function __construct()
    {
        $this->_db = get_db();
        $db = $this->_db;

        $select = $db->getTable('ItemType')
            ->getSelect()
            ->columns(array('id'))
            ->where("`name` = 'Archival Finding Aid'");
        $this->_idFindingAid = $db->fetchOne($select);
        $this->_idIdentifier = $db->getTable('Element')
            ->findByElementSetNameAndElementName('Dublin Core', 'Identifier')
            ->id;
        $this->_idIsPartOf = $db->getTable('Element')
            ->findByElementSetNameAndElementName('Dublin Core', 'Is Part Of')
            ->id;
        $this->_idHasPart = $db->getTable('Element')
            ->findByElementSetNameAndElementName('Dublin Core', 'Has Part')
            ->id;

        // Avoid heavy process and no level.
        $maxLevel = (integer) get_option('ead_max_level');
        $this->_maxLevel =  $maxLevel > $this->_maxLevel ? $this->_maxLevel : $maxLevel;
    }

    /**
     * Get the ead.
     *
     * @return This view helper.
     */
    public function ead()
    {
        return $this;
    }

    /**
     * Return the finding aid of an item. Return nothing if it's a finding aid
     * itself.
     *
     * @param Item|integer|null $item
     * @return integer The finding aid, else 0.
     */
    public function finding_aid($item = null)
    {
        $ancestor = $this->ancestor($item);
        if (empty($ancestor) || !$this->is_finding_aid($ancestor)) {
            return 0;
        }

        return $ancestor;
    }

    /**
     * Return the list of ancestors of an item.
     *
     * This doesn't check if the item is a finding aid.
     *
     * @param Item|integer|null $item
     * @param boolean $upToDown If true, return the farthest ancestor first.
     * @return array The list of ancestors.
     */
    public function ancestors($item = null, $upToDown = true)
    {
        $item = $this->_getItem($item);
        if (empty($item)) {
            return array();
        }

        // TODO Improve process to avoid too many queries (but server cache).
        $ancestors = array();
        $parentId = $item->id;
        $i = 0;
        do {
            $parentId = $this->_parent($parentId);
            $ancestors[] = $parentId;
        }
        while ($parentId && ++$i < $this->_maxLevel);

        // This avoids the check each time in the loop.
        if (empty($parentId)) {
            $key = count($ancestors) - 1;
            unset($ancestors[$key]);
        }

        return $upToDown
            ? array_reverse($ancestors)
            : $ancestors;
    }

    /**
     * Return the ancestor of an item.
     *
     * This doesn't check if the item is a finding aid.
     *
     * @param Item|integer|null $item
     * @return integer The finding aid, else 0.
     */
    public function ancestor($item = null)
    {
        $ancestors = $this->ancestors($item);
        return $ancestors ? reset($ancestors) : 0;
    }

    /**
     * Get the parent of an item.
     *
     * @param Item|integer|null $item
     * @return integer The parent id if any, else 0.
     */
    public function parent($item = null)
    {
        $item = $this->_getItem($item);
        if (empty($item)) {
            return 0;
        }

        $parent = $this->_parent($item->id);

        return empty($parent)
            ? 0
            : $parent;
    }

    /**
     * Helper to get the parent of an item.
     *
     * An item is an ead part only when the metadata "identifier", "is part of"
     * and "has part" match.
     *
     * @param integer $itemId
     * @return integer The parent id if any, else 0.
     */
    protected function _parent($itemId)
    {
        $db = $this->_db;

        $sql = "
            SELECT DISTINCT element_texts.text
            FROM {$db->ElementText} element_texts
            WHERE element_texts.record_type = 'Item'
                AND element_texts.element_id = $this->_idIdentifier
                AND element_texts.record_id = ?
        ";
        $identifiers = $db->fetchCol($sql, array($itemId));
        if (empty($identifiers)) {
            return 0;
        }

        $sql = "
            SELECT DISTINCT element_texts.text
            FROM {$db->ElementText} element_texts
            WHERE element_texts.record_type = 'Item'
                AND element_texts.element_id = $this->_idIsPartOf
                AND element_texts.record_id = ?
        ";
        $partOfs = $db->fetchCol($sql, array($itemId));
        if (empty($partOfs)) {
            return 0;
        }

        // Direct query, sot need to quote result (TODO : use where()).
        $quotedPartOfs = array();
        foreach ($partOfs as $partOf) {
            $quotedPartOfs[] = $db->quote($partOf);
        }
        $quotedPartOfs = implode(',', $quotedPartOfs);

        $sql = "
            SELECT DISTINCT element_texts.record_id
            FROM {$db->ElementText} element_texts
            WHERE element_texts.record_type = 'Item'
                AND element_texts.element_id = $this->_idIdentifier
                AND element_texts.text IN (" . $quotedPartOfs . ")
        ";
       $partOfIds = $db->fetchCol($sql);
        if (empty($partOfIds)) {
            return 0;
        }

        // Direct query, sot need to quote result (TODO : use where()).
        $quotedIdentifiers = array();
        foreach ($identifiers as $identifier) {
            $quotedIdentifiers[] = $db->quote($identifier);
        }
        $quotedIdentifiers = implode(',', $quotedIdentifiers);

        $sql = "
            SELECT DISTINCT element_texts.record_id
            FROM {$db->ElementText} element_texts
            WHERE element_texts.record_type = 'Item'
                AND element_texts.element_id = $this->_idHasPart
                AND element_texts.record_id IN (" . implode(',', $partOfIds). ")
                AND element_texts.text IN (" . $quotedIdentifiers . ")
        ";
        $result = $db->fetchCol($sql);

        // There can be only one parent.
        return $result ? reset($result) : 0;
    }

    /**
     * List the siblings of an item.
     *
     * @param Item|integer|null $item
     * @return array The list of siblings of the item.
     */
    public function siblings($item = null)
    {
        $item = $this->_getItem($item);
        if (empty($item)) {
            return array();
        }

        $parent = $this->_parent($item->id);
        if (empty($parent)) {
            return array();
        }

        $children = $this->_children($parent);
        return $children;
    }

    /**
     * List the direct children of an item.
     *
     * @param Item|integer|null $item
     * @return array The list of direct children of the item.
     */
    public function children($item = null)
    {
        $item = $this->_getItem($item);
        if (empty($item)) {
            return array();
        }

        $children = $this->_children($item->id);
        return $children;
    }

    /**
     * Helper to list the direct children of an item.
     *
     * An item is an ead part only when the metadata "identifier", "is part of"
     * and "has part" match.
     *
     * @todo Manage public.
     *
     * @param integer $itemId
     * @return array The list of direct children of the item.
     */
    protected function _children($itemId, $public = true)
    {
        $db = $this->_db;

        $sql = "
            SELECT DISTINCT element_texts.text
            FROM {$db->ElementText} element_texts
            WHERE element_texts.record_type = 'Item'
                AND element_texts.element_id = $this->_idIdentifier
                AND element_texts.record_id = ?
        ";
        $identifiers = $db->fetchCol($sql, array($itemId));

        if (empty($identifiers)) {
            return array();
        }

        $sql = "
            SELECT DISTINCT element_texts.text
            FROM {$db->ElementText} element_texts
            WHERE element_texts.record_type = 'Item'
                AND element_texts.element_id = $this->_idHasPart
                AND element_texts.record_id = ?
        ";
        $parts = $db->fetchCol($sql, array($itemId));

        if (empty($parts)) {
            return array();
        }

        // Direct query, so need to quote result (TODO : use where()).
        $quotedParts = array();
        foreach ($parts as $part) {
            $quotedParts[] = $db->quote($part);
        }
        $quotedParts = implode(',', $quotedParts);

        $sql = "
            SELECT DISTINCT element_texts.record_id
            FROM {$db->ElementText} element_texts
            WHERE element_texts.record_type = 'Item'
                AND element_texts.element_id = $this->_idIdentifier
                AND element_texts.text IN (" . $quotedParts . ")
        ";
       $partIds = $db->fetchCol($sql);
        if (empty($partIds)) {
            return array();
        }

        // Direct query, so need to quote result (TODO : use where()).
        $quotedIdentifiers = array();
        foreach ($identifiers as $identifier) {
            $quotedIdentifiers[] = $db->quote($identifier);
        }
        $quotedIdentifiers = implode(',', $quotedIdentifiers);

        $sql = "
            SELECT DISTINCT element_texts.record_id
            FROM {$db->ElementText} element_texts
            WHERE element_texts.record_type = 'Item'
                AND element_texts.element_id = $this->_idIsPartOf
                AND element_texts.record_id IN (" . implode(',', $partIds). ")
                AND element_texts.text IN (" . $quotedIdentifiers . ")
        ";
        $result = $db->fetchCol($sql);

        return $result;
    }

    /**
     * List the descendant tree for an item.
     *
     * @param Item|integer|null $item
     * @return array The list of descendants of the item.
     */
    public function descendants($item = null)
    {
        $item = $this->_getItem($item);
        if (empty($item)) {
            return array();
        }

        $descendants = $this->_descendants($item->id, 'flat');
        return $descendants;
    }

    /**
     * Helper to get the ordered descendants tree for an item.
     *
     * The list can be flat (array of children), nested or without detail.
     *
     * The process use children(), so the public is checked.
     *
     * @param integer $itemId
     * @param string $mode Can be "flat" (default), "nest" or "set".
     * @return array The list of descendants of the item.
     */
    protected function _descendants($itemId, $mode = 'flat')
    {
        // Static cache for finding aids.
        static $_findingAids = array();

        if (!isset($findingAids['flat'][$itemId])) {
            $this->_cacheChildren = array();
            $findingAids['nest'][$itemId] = $this->_descendantsNest($itemId);
            $findingAids['flat'][$itemId] = $this->_cacheChildren;
        }

        switch ($mode) {
            case 'flat':
            case 'nest':
                return $findingAids[$mode][$itemId];
            case 'set':
                return array_keys($findingAids['flat'][$itemId]);
            default:
                return array();
        }
    }

    /**
     * Recursive helper to get all descendants of an item nested.
     *
     * @param Item|integer|null $item
     * @param integer $level For internal use.
     * @return array The list of descendants of the item.
     */
    protected function _descendantsNest($itemId, $level = 0)
    {
        $descendants = array();
        // Check to avoid recursion.
        if (++$level <= $this->_maxLevel) {
            $currentChildren = $this->children($itemId);
            $this->_cacheChildren[$itemId] = $currentChildren;
            foreach ($currentChildren as $child) {
                // Recursevely process each child.
                $children = $this->_descendantsNest($child, $level);
                $descendants[$child] = $children;
            }
        }

        return $descendants;
    }

    /**
     * Helper to check and get the item.
     *
     * @param Item|integer|null $item
     * @return Item|null
     */
    protected function _getItem($item = null)
    {
        if (is_object($item)) {
            return get_class($item) === 'Item'
                ? $item
                : null;
        }
        if (is_null($item)) {
            return get_current_record('item');
        }
        if (is_numeric($item)) {
            return get_record_by_id('Item', $item);
        }
        return null;
    }

    /**
     * Check if an item is a finding aid or in a finding aid.
     *
     * @param Item|integer|null $item
     * @return boolean
     */
    public function is_ead($item = null)
    {
        $item = $this->_getItem($item);
        if (empty($item)) {
            return false;
        }

        return $this->is_finding_aid($item) || $this->is_in_finding_aid($item);
    }

    /**
     * Check if an item is a finding aid.
     *
     * @param Item|integer|null $item
     * @return boolean
     */
    public function is_finding_aid($item = null)
    {
        $item = $this->_getItem($item);
        if (empty($item)) {
            return false;
        }
        return $item->item_type_id == $this->_idFindingAid;
    }

    /**
     * Check if an item belongs to a or any finding aid. Return nothing if it's
     * a finding aid itself.
     *
     * @param Item|integer|null $item
     * @param Item|integer|null $findingAid Optional If set, check if the item
     * belongs to this finding aid.
     * @return boolean
     */
    public function is_in_finding_aid($item = null, $findingAid = null)
    {
        $findingAidOfItem = $this->finding_aid($item);
        if (empty($findingAidOfItem)) {
            return false;
        }

        if (empty($findingAid)) {
            return !empty($findingAidOfItem);
        }

        $findingAid = $this->_getItem($findingAid);
        return $findingAid
            ? $findingAidOfItem == $findingAid->id
            : false;
    }

    /**
     * Check if an item is an archiv.al description (first level after header).
     *
     * @param Item|integer|null $item
     * @return boolean
     */
    public function is_archival_description($item = null)
    {
        $item = $this->_getItem($item);
        if (empty($item)) {
            return false;
        }

        $parent = $this->parent($item->id);
        if (empty($parent)) {
            return false;
        }

        $findingAid = $this->finding_aid($item);
        if (empty($findingAid)) {
            return false;
        }

        return $parent == $findingAid;
    }

    /**
     * Return the links to the ancestors.
     *
     * @param Item|integer|null $item
     * @return string
     */
    public function link_to_ancestors($item = null)
    {
        $item = $this->_getItem($item);
        if (empty($item)) {
            return '';
        }

        // Not for finding aid itself.
        if ($this->is_finding_aid($item)) {
            return '';
        }

        $ancestors = $this->ancestors($item);
        $records = empty($ancestors)
            ? array()
            : $this->_getRecords('Item', $ancestors);

        return common('ead-ancestors', array(
            'item' => $item,
            'records' => $records,
        ));
    }

    /**
     * Return the link to the finding aid.
     *
     * @param Item|integer|null $item
     * @return string
     */
    public function link_to_finding_aid($item = null)
    {
        $item = $this->_getItem($item);
        if (empty($item)) {
            return '';
        }

        $findingAid = $this->finding_aid($item);
        if (empty($findingAid) || $item->id === $findingAid) {
            return '';
        }

        $record = get_record_by_id('Item', $findingAid);

        return common('ead-ancestor', array(
            'item' => $item,
            'record' => $record,
        ));
    }

    /**
     * Return the link to the ancestor.
     *
     * @param Item|integer|null $item
     * @return string
     */
    public function link_to_ancestor($item = null)
    {
        $item = $this->_getItem($item);
        if (empty($item)) {
            return '';
        }

        $ancestor = $this->ancestor($item);
        if (empty($ancestor) || $item->id === $ancestor) {
            return '';
        }

        $record = get_record_by_id('Item', $ancestor);

        return common('ead-ancestor', array(
            'item' => $item,
            'record' => $record,
        ));
    }

    /**
     * Return the link to the parent.
     *
     * @param Item|integer|null $item
     * @return string
     */
    public function link_to_parent($item = null)
    {
        $item = $this->_getItem($item);
        if (empty($item)) {
            return '';
        }

        // Not for finding aid itself.
        if ($this->is_finding_aid($item)) {
            return '';
        }

        $parent = $this->parent($item);

        $record = empty($parent)
            ? null
            : get_record_by_id('Item', $parent);

        return common('ead-parent', array(
            'item' => $item,
            'record' => $record,
        ));
    }

    /**
     * Return the link to the siblings.
     *
     * @param Item|integer|null $item
     * @return string
     */
    public function link_to_siblings($item = null)
    {
        $item = $this->_getItem($item);
        if (empty($item)) {
            return '';
        }

        // Not for finding aid itself.
        if ($this->is_finding_aid($item)) {
            return '';
        }

        $siblings = $this->siblings($item);
        $records = empty($siblings)
            ? array()
            : $this->_getRecords('Item', $siblings);

        return common('ead-siblings', array(
            'item' => $item,
            'records' => $records,
        ));
    }

    /**
     * Return the link to the children.
     *
     * @param Item|integer|null $item
     * @return string
     */
    public function link_to_children($item = null)
    {
        $item = $this->_getItem($item);
        if (empty($item)) {
            return '';
        }

        // Not for finding aid itself.
        if ($this->is_finding_aid($item)) {
            return '';
        }

        $children = $this->children($item);
        $records = empty($children)
            ? array()
            : $this->_getRecords('Item', $children);

        return common('ead-children', array(
            'item' => $item,
            'records' => $records,
        ));
    }

    /**
     * Return the links to the descendants.
     *
     * @param Item|integer|null $item
     * @return string
     */
    public function link_to_descendants($item = null)
    {
        $item = $this->_getItem($item);
        if (empty($item)) {
            return '';
        }

        // Not for finding aid itself.
        if ($this->is_finding_aid($item)) {
            return '';
        }

        $tree = $this->descendants($item);
        return common('ead-descendants', array(
            'tree' => $tree,
            'item' => $item,
        ));
    }

    /**
     * Build a nested HTML ordered list of the full finding aid tree of an item.
     *
     * @param Item|integer|null $item
     * @param boolean|null  $select If true, only for the finding aid; if false,
     * only for other item; else all.
     * @return string
     */
    public function display_finding_aid($item = null, $select = true)
    {
        $item = $this->_getItem($item);
        if (empty($item)) {
            return '';
        }

        // Only for finding aid.
        if ($select === true) {
            if (!$this->is_finding_aid($item)) {
                return '';
            }
            $findingAid = $item;
        }
        // Check finding aid.
        else {
            $findingAid = $this->finding_aid($item);
            if (empty($findingAid)) {
                return '';
            }
            // Only for other items.
            if ($select === false) {
                if ($this->is_finding_aid($item)) {
                    return '';
                }
            }
            // Else for all.
        }

        $tree = $this->descendants($findingAid);
        return common('ead-tree', array(
            'tree' => $tree,
            'item' => $item,
        ));
    }

    /**
     * Build a nested HTML ordered list of a full tree of an item.
     *
     * @param Item|integer|null $item
     * @return string
     */
    public function display_tree($item = null)
    {
        $item = $this->_getItem($item);
        if (empty($item)) {
            return '';
        }

        $ancestor = $this->ancestor($item);
        $tree = $this->descendants($ancestor);

        return common('ead-tree', array(
            'tree' => $tree,
            'item' => $item,
        ));
    }

    /**
     * Helper to display the tree.
     *
     * @param array $tree
     * @param integer|null $baseId The base id, else the first.
     * @param boolean $withBase Display the first item or not.
     * @return string
     */
    public function html_tree($tree, $baseId = null, $withBase = true)
    {
        if (is_null($baseId)) {
            reset($tree);
            $baseId = key($tree);
        }
        return $this->_htmlTree($tree, $baseId, $withBase, true);
    }

    /**
     * Recursive helper to display the tree.
     *
     * @param array $tree
     * @param integer|null $baseId The base id, else the first.
     * @param boolean $withBase Display the first item or not.
     * @param boolean $isFirst
     * @return string
     */
    protected function _htmlTree($tree, $baseId, $withBase = true, $isFirst = false)
    {
        $html = '';
        if ($isFirst && $withBase) {
            $html .= '<ul><li>';
            $html .= $this->_htmlTreeItem($tree, $baseId);
        };

        $html .= '<ul>';
        foreach ($tree[$baseId] as $childrenId) {
            $html .= '<li>';
            $html .= $this->_htmlTreeItem($tree, $childrenId);
            if ($tree[$childrenId]) {
                $html .= $this->_htmlTree($tree, $childrenId);
            }
            $html .= '</li>';
        }
        $html .= '</ul>';

        if ($isFirst && $withBase) {
            $html .= '</li></ul>';
        }

        return $html;
    }

    /**
     * Helper to display an item of the tree.
     *
     * @param array $tree
     * @param integer|null $baseId The base id, else the first.
     * @param boolean $withBase Display the first item or not.
     * @return string
     */
    protected function _htmlTreeItem($tree, $itemId)
    {
        return common('ead-tree-item', array(
            'tree' => $tree,
            'itemId' => $itemId,
        ));
    }

    /**
     * Helper to get a list of records from a list of ids.
     *
     * @internal The standard Omeka doesn't seem to allow to get items by a list
     * of ids.
     *
     * @param string $recordType
     * @param  array $ids A list of ids.
     * @return array Array of objects.
     */
    protected function _getRecords($recordType, $ids)
    {
        $table = $this->_db->getTable($recordType);
        $alias = $table->getTableAlias();
        $ids = array_filter(array_map('intval', $ids));
        return $table->findBySql($alias . '.id IN (' . implode(',', $ids) . ')');
    }
}
