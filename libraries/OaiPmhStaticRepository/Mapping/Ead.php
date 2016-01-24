<?php
/**
 * Map Ead xml files into Omeka elements for each item and file.
 *
 * @package Ead
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

    protected $_xslMain = 'libraries/external/Ead2DCterms/ead2dcterms-omeka.xsl';
    protected $_xslSecondary = 'libraries/xsl/dcterms-omeka2documents.xsl';
    protected $_xslParts = 'libraries/xsl/ead_parts.xsl';

    // EAD converts itself into the "documents" format.
    private $_mappingDocument = null;

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
        $this->_xml = simplexml_load_file($this->_metadataFilepath);
        if ($this->_xml === false) {
            return;
        }

        // Process the xml file via the stylesheet.
        $intermediatePath = $this->_processXslt($this->_metadataFilepath, $this->_xslMain);
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
     * Set the xml metadata of all documents, specially if a sub class is used.
     */
    protected function _setXmlMetadata()
    {
        $documents = &$this->_processedFiles[$this->_metadataFilepath];

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
        $pathPos = strpos($documents[0]['name'], '/ead/eadheader');

        foreach ($documents as &$document) {
            $partPath = isset($document['metadata']['extra']['XPath'][0])
                ? $document['metadata']['extra']['XPath'][0]
                : substr($document['name'], $pathPos);
            $document['xml'] = $this->_getXmlPart($partsPath, $partPath);
            foreach ($document['files'] as &$file) {
                $partPath = isset($file['metadata']['extra']['XPath'][0])
                    ? $file['metadata']['extra']['XPath'][0]
                    : '';
                $file['xml'] = $this->_getXmlPart($partsPath, $partPath);
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
