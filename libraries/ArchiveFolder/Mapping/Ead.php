<?php
/**
 * Map Ead xml files into Omeka elements for each item and file via xsl.
 *
 * @package Omeka\Plugins\Ead
 */
class ArchiveFolder_Mapping_Ead extends ArchiveFolder_Mapping_Abstract
{
    const XML_ROOT = 'ead';
    const XML_PREFIX = 'ead';
    const XML_NAMESPACE = 'http://www.loc.gov/ead';

    protected $_checkMetadataFile = array('extension', 'validate xml');
    protected $_extension = 'xml';
    protected $_formatXml = self::XML_PREFIX;
    protected $_xmlRoot = self::XML_ROOT;
    protected $_xmlNamespace = self::XML_NAMESPACE;
    protected $_xmlPrefix = self::XML_PREFIX;

    protected $_xslMain = 'libraries/external/Ead2DCterms/ead2dcterms-omeka.xsl';
    protected $_xslSecondary = 'libraries/xsl/dcterms-omeka2documents.xsl';
    protected $_xslParts = 'libraries/xsl/ead_parts.xsl';
    protected $_xmlConfig = 'libraries/external/Ead2DCterms/ead2dcterms-omeka_config.xml';

    public function __construct($uri, $parameters)
    {
        $this->_xslMain = PLUGIN_DIR
            . DIRECTORY_SEPARATOR . 'Ead'
            . DIRECTORY_SEPARATOR . $this->_xslMain;
        $this->_xslSecondary = PLUGIN_DIR
            . DIRECTORY_SEPARATOR . 'Ead'
            . DIRECTORY_SEPARATOR . $this->_xslSecondary;
        $this->_xslParts = PLUGIN_DIR
            . DIRECTORY_SEPARATOR . 'Ead'
            . DIRECTORY_SEPARATOR . $this->_xslParts;
        $this->_xmlConfig = PLUGIN_DIR
            . DIRECTORY_SEPARATOR . 'Ead'
            . DIRECTORY_SEPARATOR . $this->_xmlConfig;

        $this->_mappingDocument = new ArchiveFolder_Mapping_Document($uri, $parameters);

        parent::__construct($uri, $parameters);
    }

    /**
     * Prepare the list of documents set inside the current metadata file.
     */
    protected function _prepareDocuments()
    {
        $this->_processedFiles[$this->_metadataFilepath] = array();
        $documents = &$this->_processedFiles[$this->_metadataFilepath];

        // If the xml is too large, the php memory may be increased so it can be
        // processed directly via SimpleXml.
        $this->_xml = simplexml_load_file($this->_metadataFilepath, 'SimpleXMLElement', LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_PARSEHUGE);
        if ($this->_xml === false) {
            $message = __('The file "%s" is not xml.', $this->_metadataFilepath);
            throw new ArchiveFolder_Exception($message);
        }

        $this->_xml->registerXPathNamespace(self::XML_PREFIX, self::XML_NAMESPACE);

        $extraParameters = $this->_getParameter('extra_parameters');

        // Set the default file for the configuration.
        $configuration = empty($extraParameters['configuration'])
            ? $this->_xmlConfig
            : $extraParameters['configuration'];

        // Set the base id in the config file.
        $tempConfig = tempnam(sys_get_temp_dir(), 'ead2dcterms_');
        $result = copy($configuration, $tempConfig);
        if (empty($result)) {
            $message = __('Error during copy of the configuration file from "%s" into "%s".',
                $configuration, $tempConfig);
            throw new ArchiveFolder_Exception($message);
        }

        $configuration = $tempConfig;

        // In fact, if it is the same than the "baseid", it's useless, but it's
        // simpler to set it always.
        $baseIdXml = $this->_getBaseIdXml();
        $result = $this->_updateConfig($configuration, $baseIdXml);
        if (empty($result)) {
            $message = __('Error during update of the element "baseid" in the configuration file "%s".',
                $configuration);
            throw new ArchiveFolder_Exception($message);
        }

        $extraParameters['configuration'] = $configuration;

        // Process the xml file via the stylesheet.
        $intermediatePath = $this->_processXslt($this->_metadataFilepath, $this->_xslMain, '', $extraParameters);
        if (filesize($intermediatePath) == 0) {
            return;
        }

        // Process the intermediate xml file via the secondary stylesheet.
        $xmlpath = $this->_processXslt($intermediatePath, $this->_xslSecondary);
        if (filesize($xmlpath) == 0) {
            return;
        }

        // Now, the xml is a standard document, so process it with the class.
        $documents = $this->_mappingDocument->listDocuments($xmlpath);

        // Reset each intermediate xml metadata by the original one.
        $this->_setXmlMetadata();
    }

    /**
     * Get the base id from the parameters.
     *
     * @return array Attributes of the "base_id" element.
     */
    protected function _getBaseIdXml()
    {
        $baseIdXml = array();

        $baseId = $this->_getParameter('ead_base_id');
        switch ($baseId) {
            case 'documentUri':
            default:
                $baseIdXml['from'] = '';
                $baseIdXml['default'] =$this->_metadataFilepath;
                break;
            case 'basename':
                $baseIdXml['from'] = '';
                $baseIdXml['default'] = pathinfo($this->_metadataFilepath, PATHINFO_BASENAME);
                break;
            case 'filename':
                $baseIdXml['from'] = '';
                $baseIdXml['default'] = pathinfo($this->_metadataFilepath, PATHINFO_FILENAME);
                break;
            case 'eadid':
                $baseIdXml['from'] = '/ead/eadheader/eadid';
                $baseIdXml['default'] =$this->_metadataFilepath;
                break;
            case 'publicid':
                $baseIdXml['from'] = '/ead/eadheader/eadid/@publicid';
                $baseIdXml['default'] =$this->_metadataFilepath;
                break;
            case 'identifier':
                $baseIdXml['from'] = '/ead/eadheader/eadid/@identifier';
                $baseIdXml['default'] =$this->_metadataFilepath;
                break;
            case 'url':
                $baseIdXml['from'] = '/ead/eadheader/eadid/@url';
                $baseIdXml['default'] =$this->_metadataFilepath;
                break;
            case 'custom':
                $baseIdXml['from'] = '';
                $baseIds = $this->_getParameter('ead_base_ids');
                $baseIds = $this->_stringParametersToArray($baseIds);
                $xpath = '/ead:ead/ead:eadheader/ead:eadid';
                $result = $this->_xml->xpath($xpath);
                $result = json_decode(json_encode($result), true);
                $result = $result[0]['@attributes'];
                $result = array_intersect(array_keys($baseIds), $result);
                if ($result) {
                    $result = array_shift($result);
                    $baseIdXml['default'] = $baseIds[$result];
                }
                // Unknown identifier.
                else {
                    $baseIdXml['default'] = $this->_metadataFilepath;
                }
                break;
        }
        $baseIdXml = array_map('xml_escape', $baseIdXml);
        return $baseIdXml;
    }

    protected function _updateConfig($configuration, $baseIdXml)
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $result = $dom->load($configuration);
        if (empty($result)) {
            return false;
        }

        $root = $dom->documentElement;
        $xpath = new DOMXPath($dom);

        $element = $xpath->query('/config/baseid')->item(0);
        $element->setAttribute('from', $baseIdXml['from']);
        $element->setAttribute('default', $baseIdXml['default']);

        // Because this is a temp file, the full path should be set when needed.
        $element = $xpath->query('/config/option[@name = "mappings"]')->item(0);
        $path = $element->getAttribute('value');
        if (realpath($path) != $path) {
            $path = dirname($this->_xmlConfig) . DIRECTORY_SEPARATOR . $path;
        }
        $element->setAttribute('value', $path);

        // Because this is a temp file, the full path should be set when needed.
        $element = $xpath->query('/config/option[@name = "rules"]')->item(0);
        $path = $element->getAttribute('value');
        if (realpath($path) != $path) {
            $path = dirname($this->_xmlConfig) . DIRECTORY_SEPARATOR . $path;
        }
        $element->setAttribute('value', $path);

        return $dom->save($configuration);
    }

    /**
     * Set the xml metadata of all documents, specially if a sub class is used.
     */
    protected function _setXmlMetadata()
    {
        $documents = &$this->_processedFiles[$this->_metadataFilepath];

        if (empty($documents)) {
            $message = __('The EAD file "%s" cannot be processed [last step].', $this->_metadataFilepath);
            throw new ArchiveFolder_Exception($message);
        }

        // Prepare the list of parts via the stylesheet.

        // Metadata are different when files are separated.
        $recordsForFiles = (boolean) $this->_getParameter('records_for_files');

        $partsPath = $this->_processXslt($this->_metadataFilepath, $this->_xslParts, '',
            array('set_digital_objects' => $recordsForFiles ? 'separated' : 'integrated'));
        if (filesize($partsPath) == 0) {
            return;
        }

        // By construction (see xsl), the root is the string before "/ead/eadheader".
        // TODO Warning: the root path may contain '/ead/eadheader' before,
        // even if this is very rare.
        $pathPos = strpos($documents[0]['process']['name'], '/ead/eadheader');

        foreach ($documents as &$document) {
            $partPath = isset($document['extra']['XPath'][0])
                ? $document['extra']['XPath'][0]
                : substr($document['process']['name'], $pathPos);
            $document['process']['xml'] = $this->_getXmlPart($partsPath, $partPath);
            if (empty($document['files'])) {
                continue;
            }
            foreach ($document['files'] as &$file) {
                $partPath = isset($file['extra']['XPath'][0])
                    ? $file['extra']['XPath'][0]
                    : '';
                $file['process']['xml'] = $this->_getXmlPart($partsPath, $partPath);
            }
        }
    }

    /**
     * Get an xml metadata part from the attribute "xpath" of a part.
     *
     * @param string $partsPath Path to the xml file.
     * @param string $partPath Attribute "xpath" of the part to get.
     * @return String value of the xml part, if any.
     *
     * @todo Avoid to reload the reader for each part.
     */
    protected function _getXmlPart($partsPath, $partPath)
    {
        if ($partsPath == '' || $partPath == '') {
            return '';
        }

        // Read the xml from the beginning.
        $reader = new XMLReader;
        $result = $reader->open($partsPath, null, LIBXML_NSCLEAN);
        if (!$result) {
            return;
        }

        $result = '';
        while ($reader->read()) {
            if ($reader->nodeType == XMLReader::ELEMENT
                    && $reader->name == 'part'
                    && $reader->getAttribute('xpath') === $partPath
                ) {
                $result = $reader->readInnerXml();
            }
        }
        return trim($result);
    }
}
