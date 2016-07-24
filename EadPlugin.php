<?php
/**
 * Ead
 *
 * @copyright Copyright 2015-2016 Daniel Berthereau
 * @license https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
 */

/**
 * The EAD plugin.
 *
 * @package Omeka\Plugins\Ead
 */
class EadPlugin extends Omeka_Plugin_AbstractPlugin
{
    /**
     * @var array Hooks for the plugin.
     */
    protected $_hooks = array(
        'initialize',
        'install',
        'upgrade',
        'uninstall',
        'uninstall_message',
        'config_form',
        'config',
        'public_items_show',
    );

    /**
     * @var array Filters for the plugin.
     */
    protected $_filters = array(
        'archive_folder_mappings',
        'archive_folder_add_parameters',
        'oai_pmh_static_repository_mappings',
//        'oai_pmh_static_repository_formats',
//        'oai_pmh_repository_metadata_formats',
        'oai_pmh_harvester_maps',
    );

    /**
     * @var array Options and their default values.
     */
    protected $_options = array(
        'ead_max_level' => 15,
        'ead_append_to_item' => array(
            'tree for finding aid' => true,
            'tree' => false,
            'finding aid' => false,
            'ancestors' => true,
            'parent' => false,
            'siblings' => false,
            'children' => true,
            'descendants' => false,
        ),
        'ead_oaipmh_expose' => true,
    );

    /**
     * Initialize this plugin.
     */
    public function hookInitialize()
    {
        // Add translation.
        add_translation_source(dirname(__FILE__) . '/languages');
    }

    /**
     * Install the plugin.
     */
    public function hookInstall()
    {
        $lib = dirname(__FILE__)
            . DIRECTORY_SEPARATOR . 'libraries'
            . DIRECTORY_SEPARATOR . 'external'
            . DIRECTORY_SEPARATOR . 'Ead2DCterms'
            . DIRECTORY_SEPARATOR . 'ead2dcterms.xsl';
        if (!file_exists($lib)) {
            throw new Omeka_Plugin_Exception(__('EAD2DCTerms library should be installed. See %sReadme%s.',
                '<a href="https://github.com/Daniel-KM/Ead4Omeka#installation">', '</a>'));
        }

        // Load elements to add.
        require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'elements.php';

        // Checks if element sets and item types exist.
        if (isset($elementSetsMetadata)) {
            foreach ($elementSetsMetadata as $metadata) {
                $elementSet = get_record('ElementSet', array('name' => $metadata['name']));
                if ($elementSet) {
                    throw new Omeka_Plugin_Exception('An element set by the name "' . $metadata['name'] . '" already exists. You must delete that element set before to install this plugin.');
                }
            }
        }

        if (isset($itemTypesMetadata)) {
            foreach ($itemTypesMetadata as $metadata) {
                $itemType = get_record('ItemType', array('name' => $metadata['name']));
                if ($itemType) {
                    throw new Omeka_Plugin_Exception('An item type by the name "' . $metadata['name'] . '" already exists. You must delete that item type before to install this plugin.');
                }
            }
        }

        // Process.
        if (isset($elementSetsMetadata)) {
            foreach ($elementSetsMetadata as $metadata) {
                foreach ($metadata['elements'] as &$element) {
                    $element['name'] = $element['label'];
                }
                $elements = $metadata['elements'];
                unset($metadata['elements']);
                insert_element_set($metadata, $elements);
            }
        }

        if (isset($itemTypesMetadata)) {
            foreach ($itemTypesMetadata as $metadata) {
                foreach ($metadata['elements'] as &$element) {
                    $element['name'] = $element['label'];
                    $existing = $this->_getElement('Item Type Metadata', $element['name']);
                    if ($existing) {
                        $element = $existing;
                    }
                }
                $elements = $metadata['elements'];
                unset($metadata['elements']);
                $itemType = insert_item_type($metadata, $elements);
            }
        }

        $this->_options['ead_append_to_item'] = json_encode(array_keys(array_filter($this->_options['ead_append_to_item'])));

        $this->_installOptions();
    }

    /**
     * Upgrade the plugin.
     */
    public function hookUpgrade($args)
    {
        $oldVersion = $args['old_version'];
        $newVersion = $args['new_version'];
        $db = $this->_db;

        if (version_compare($oldVersion, '2.4.1', '<')) {
            $appendToItem = unserialize(get_option('ead_append_to_item')) ?: array();
            set_option('ead_append_to_item', json_encode($appendToItem));
        }
    }

    /**
     * Uninstall the plugin.
     */
    public function hookUninstall()
    {
        // Load elements to remove.
        require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'elements.php';

        if (isset($elementSetsMetadata)) {
            foreach ($elementSetsMetadata as $metadata) {
                $this->_deleteElementSet($metadata['name']);
            }
        }

        if (isset($itemTypesMetadata)) {
            foreach ($itemTypesMetadata as $metadata) {
                // TODO Don't delete existing elements if any (not currently).
                foreach ($metadata['elements'] as $element) {
                    $element['name'] = $element['label'];
                    $element = $this->_getElement('Item Type Metadata', $element['name']);
                    if ($element) {
                        $element->delete();
                    }
                }

                $this->_deleteItemType($metadata['name']);
            }
        }

        $this->_uninstallOptions();
    }

    /**
     * Display the uninstall message.
     */
    public function hookUninstallMessage()
    {
        echo __('%sWarning%s: This will remove all the EAD elements added by this plugin and permanently delete all element texts entered in those fields.%s', '<p><strong>', '</strong>', '</p>');
    }

    /**
     * Shows plugin configuration page.
     *
     * @return void
     */
    public function hookConfigForm($args)
    {
        $view = get_view();
        echo $view->partial(
            'plugins/ead-config-form.php',
            array(
                'display' => array_keys($this->_options['ead_append_to_item']),
                'appendToItem' => json_decode(get_option('ead_append_to_item'), true) ?: array(),
        ));
    }

    /**
     * Processes the configuration form.
     *
     * @return void
     */
    public function hookConfig($args)
    {
        $post = $args['post'];
        foreach ($this->_options as $optionKey => $optionValue) {
            if (isset($post[$optionKey])) {
                if (in_array($optionKey, array(
                        'ead_append_to_item',
                    ))) {
                    $post[$optionKey] = json_encode($post[$optionKey]) ?: json_encode(array());
                }
                set_option($optionKey, $post[$optionKey]);
            }
        }
    }

    public function hookPublicItemsShow($args)
    {
        $view = $args['view'];
        $item = $args['item'];
        if (!$view->ead()->is_ead($item)) {
            return;
        }
        $options = json_decode(get_option('ead_append_to_item'), true);
        foreach ($options as $option) {
            switch ($option) {
                case 'tree for finding aid':
                    echo $view->ead()->display_finding_aid($item, true);
                    break;
                case 'tree':
                    echo $view->ead()->display_finding_aid($item, false);
                    break;
                case 'finding aid':
                    echo $view->ead()->link_to_finding_aid($item);
                    break;
                case 'ancestors':
                    echo $view->ead()->link_to_ancestors($item);
                    break;
                case 'parent':
                    echo $view->ead()->link_to_parent($item);
                    break;
                case 'siblings':
                    echo $view->ead()->link_to_siblings($item);
                    break;
                case 'children':
                    echo $view->ead()->link_to_children($item);
                    break;
                case 'descendants':
                    echo $view->ead()->link_to_descendants($item);
                    break;
            }
        }
    }

    /**
     * Add the mappings to convert metadata files into Omeka elements.
     *
     * @param array $mappings Available mappings.
     * @return array Filtered mappings array.
    */
    public function filterArchiveFolderMappings($mappings)
    {
        $mappings['ead'] = array(
            'class' => 'ArchiveFolder_Mapping_Ead',
            'description' => __('EAD xml (Encoded Archival Description)'),
        );
        return $mappings;
    }

    /**
     * Add parameters to the main form.
     *
     * @param ArchiveFolder_Form_Add $form
     * @return $form
     */
    public function filterArchiveFolderAddParameters($form)
    {
        $form->addElement('select', 'ead_base_id', array(
            'label' => __('Base ids'),
            'description' => __('Each item inside an EAD xml file is represented by a unique id, that is used to make relations between all items.')
                . ' ' . __('The base id is the first part of this id.')
                . ' ' . __('Default is the full document uri.'),
            'multiOptions' => array(
                'documentUri' => __('Document uri'),
                'basename' => __('Filename'),
                'filename' => __('Filename without extension'),
                'eadid' => __('Value of element "eadid"'),
                'publicid' => __('Attribute "publicid" of "eadid"'),
                'identifier' => __('Attribute "identifier" of "eadid"'),
                'url' => __('Attribute "url" of "eadid"'),
                'custom' => __('Custom, in the field below'),
            ),
            'value' => 'documentUri',
        ));

        $form->addElement('textarea', 'ead_base_ids', array(
            'description' => __('If "custom" is selected, specify the base ids to use, one by line, for each EAD xml file.')
                . ' ' . __('The base id should be linked to one of the attributes of the "eadid" element: "publicid", "identifier" or "url".'),
            'value' => '',
            'required' => false,
            'rows' => 5,
            'placeholder' => __('attribute value = base id of the file'),
            'filters' => array(
                'StringTrim',
            ),
            'validators' => array(
                array(
                    'callback',
                    false,
                    array(
                        'callback' => array('ArchiveFolder_Form_Validator', 'validateExtraParameters'),
                    ),
                    'messages' => array(
                        Zend_Validate_Callback::INVALID_VALUE => __('Each base id, one by line, should have a name separated from the value with a "=".'),
                    ),
                ),
            ),
        ));

        $form->addDisplayGroup(
            array(
                'ead_base_id',
                'ead_base_ids',
            ),
            'archive_folder_ead',
            array(
                'legend' => __('EAD'),
                'description' => __('Set specific parameters for EAD.')
                    . ' ' . __('All parameters are set in the file "ead2dcterms-omeka_config.xml".')
                    . ' ' . __('To use another file, set its full path as "configuration = https://example.org/full/path/to/custom-config.xml" in extra parameters.'),
        ));

        return $form;
    }

    /**
     * Add the mappings to convert metadata files into Omeka elements.
     *
     * @param array $mappings Available mappings.
     * @return array Filtered mappings array.
    */
    public function filterOaiPmhStaticRepositoryMappings($mappings)
    {
        $mappings['ead'] = array(
            'class' => 'OaiPmhStaticRepository_Mapping_Ead',
            'description' => __('EAD xml'),
        );

        return $mappings;
    }

    /**
     * Add the metadata formats that are available.
     *
     * @internal The prefix is a value to allow multiple ways to format data.
     *
     * @param array $metadataFormats Metadata formats array.
     * @return array Filtered metadata formats array.
    */
    public function filterOaiPmhStaticRepositoryFormats($formats)
    {
        $formats['ead'] = array(
            'prefix' => 'ead',
            'class' => 'OaiPmhStaticRepository_Format_Ead',
            'description' => __('EAD (Encoded Archival Description)'),
        );

        return $formats;
    }

    public function filterOaiPmhRepositoryMetadataFormats($formats)
    {
        if (get_option('ead_oaipmh_expose')) {
            $formats['ead'] = array(
                'class' => 'OaiPmhRepository_Metadata_Ead',
                'namespace' => OaiPmhRepository_Metadata_Ead::METADATA_NAMESPACE,
                'schema' => OaiPmhRepository_Metadata_Ead::METADATA_SCHEMA,
            );
        }

        return $formats;
    }

    /**
     * Get the available OAI-PMH to Omeka maps, which should correspond to
     * OAI-PMH metadata formats.
     *
     * @param array $maps Associative array of supported schemas.
     * @return array
     */
    public function filterOaiPmhHarvesterMaps($maps)
    {
        $maps[OaipmhHarvester_Harvest_Ead::METADATA_PREFIX] = array(
            'class' => 'OaipmhHarvester_Harvest_Ead',
            'schema' => OaipmhHarvester_Harvest_Ead::METADATA_SCHEMA,
        );

        return $maps;
    }

    /**
     * Helper to get an element by its set and name.
     *
     * @return object
     */
    private function _getElement($elementSetName, $elementName)
    {
        return $this->_db->getTable('Element')
            ->findByElementSetNameAndElementName($elementSetName, $elementName);
    }

    /**
     * Helper to remove an element set by name.
     */
    private function _deleteElementSet($elementSetName)
    {
        $elementSet = get_record('ElementSet', array('name' => $elementSetName));

        if ($elementSet) {
            $elements = $elementSet->getElements();
            foreach ($elements as $element) {
                $element->delete();
            }
            $elementSet->delete();
        }
    }

    /**
     * Helper to remove an item type by name.
     */
    private function _deleteItemType($itemTypeName)
    {
        $itemType = get_record('ItemType', array('name' => $itemTypeName));

        if ($itemType) {
            $itemType->delete();
        }
    }
}
