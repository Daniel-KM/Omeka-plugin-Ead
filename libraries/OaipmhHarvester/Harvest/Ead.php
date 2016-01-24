<?php
/**
 * Metadata format map for the Ead format.
 *
 * @package Ead
 */
class OaipmhHarvester_Harvest_Ead extends OaipmhHarvester_Harvest_Abstract
{
    // Xml schema and OAI prefix for the format represented by this class.
    // These constants are required for all maps.
    const METADATA_SCHEMA = 'http://www.loc.gov/ead/ead.xsd';
    const METADATA_NAMESPACE = 'http://www.loc.gov/ead';
    const METADATA_PREFIX = 'ead';

    // The xsi is required for each record according to oai-pmh protocol.
    const XSI_PREFIX = 'xsi';
    const XSI_NAMESPACE = 'http://www.w3.org/2001/XMLSchema-instance';

    // Collection to insert items into.
    protected $_collection;

    protected $_xslMainPart = 'libraries/xsl/ead-part2dcterms-omeka.xsl';
    protected $_xslSecondary = 'libraries/xsl/dcterms-omeka2documents.xsl';

    // EAD converts itself into the "documents" format.
    private $_harvestDocument = null;

    /**
     * Class constructor.
     *
     * Prepares the harvest process.
     *
     * @param OaipmhHarvester_Harvest $harvest The OaipmhHarvester_Harvest object
     * model
     * @return void
     */
    public function __construct($harvest)
    {
        $this->_xslMainPart = PLUGIN_DIR
            . DIRECTORY_SEPARATOR . 'Ead'
            . DIRECTORY_SEPARATOR . $this->_xslMainPart;
        $this->_xslSecondary = PLUGIN_DIR
            . DIRECTORY_SEPARATOR . 'Ead'
            . DIRECTORY_SEPARATOR . $this->_xslSecondary;

        $this->_harvestDocument = new OaipmhHarvester_Harvest_Document($harvest);

        parent::__construct($harvest);
    }

    /**
     * Actions to be carried out before the harvest of any items begins.
     */
    protected function _beforeHarvest()
    {
        $this->_harvestDocument->_beforeHarvest();

        // TODO Try to rebuild structure?
    }

    /**
     * Harvest one record.
     *
     * @param SimpleXMLIterator $record XML metadata record
     * @return array Array of item-level, element texts and file metadata.
     */
    protected function _harvestRecord($record)
    {
        // Get document record from record.
        $metadata = $record->metadata;
        if (empty($metadata) || $metadata->asXml() == '') {
            return;
        }

        $metadata = $this->_innerXML($metadata);
        if (empty($metadata)) {
            return;
        }

        // Because there is no xsd for oai ead (by part), because the process
        // may use an external processor and because the context is lost when
        // the intermediate file is created, the namespace for the required
        // prefix "xsi" should be added in the record. This is done here.
        $dom = new DOMDocument;
        $dom->formatOutput = true;
        $dom->loadXML($metadata);
        $dom->documentElement->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:' . self::XSI_PREFIX, self::XSI_NAMESPACE);
        $dom->documentElement->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:' . self::METADATA_PREFIX, self::METADATA_NAMESPACE);
        // For the same reason, the "ead" namespace should be set as default if
        // not set.
        $ns = $dom->documentElement->getAttribute('xmlns');
        if (empty($ns)) {
            $dom->documentElement->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns', self::METADATA_NAMESPACE);
        }

        // Convert it via xslt into Dublin Core + specific.

        // The process may use an external processor, so the metadata should be
        // saved in a temp file.
        $input = tempnam(sys_get_temp_dir(), 'omk_');
        $check = $dom->save($input);
        if (empty($check)) {
            unlink($input);
            return;
        }

        // Process the xml file via the stylesheet.
        $intermediatePath = $this->_processXslt($input, $this->_xslMainPart);
        if (filesize($intermediatePath) == 0) {
            return;
        }

        // Process the intermediate xml file via the secondary stylesheet.
        $xmlpath = $this->_processXslt($intermediatePath, $this->_xslSecondary);
        if (filesize($xmlpath) == 0) {
            return;
        }

        // Convert this standard document into an oai record.
        $recordXml = $this->_harvestDocument->createOaiRecordFromXmlDocument($record, $xmlpath);
        if (empty($recordXml)) {
            return;
        }

        // Finally, process it as an oai record formatted as a standard document.
        $result = $this->_harvestDocument->_harvestRecord($recordXml);
        return $result;
    }

   /**
     * Ingest specific data and fire the hook "oai_pmh_static_repository_ingest_extra"
     * for the item and each file.
     *
     * @param Record $record
     * @param array $harvestedRecord
     * @param string $performed Can be "inserted", "updated" or "skipped".
     * @return void
     */
    protected function _harvestRecordSpecific($record, $harvestedRecord, $performed)
    {
        // Note The same function of the harverst document can't be used,
        // because the record ($this->_record) is not set for update. Anyway, if
        // needed, it can be done here.
    }

    /**
     * The next functions are used to process xslt and are copied from the
     * plugin OAI-PMH Static Repository. They will be removed when an interface
     * will be built. Last one may be updated inside the plugin.
     * TODO Merge interface.
     */

    /**
     * Apply a process (xslt stylesheet) on an input (xml file) and save output.
     *
     * @param string $input Path of input file.
     * @param string $stylesheet Path of the stylesheet.
     * @param string $output Path of the output file. If none, a temp file will
     * be used.
     * @param array $parameters Parameters array.
     * @return string|null Path to the output file if ok, null else.
     */
    protected function _processXslt($input, $stylesheet, $output = '', $parameters = array())
    {
        $command = get_option('oai_pmh_static_repository_processor');

        // Default is the internal xslt processor of php.
        return empty($command)
            ? $this->_processXsltViaPhp($input, $stylesheet, $output, $parameters)
            : $this->_processXsltViaExternal($input, $stylesheet, $output, $parameters);
    }

    /**
     * Apply a process (xslt stylesheet) on an input (xml file) and save output.
     *
     * @param string $input Path of input file.
     * @param string $stylesheet Path of the stylesheet.
     * @param string $output Path of the output file. If none, a temp file will
     * be used.
     * @param array $parameters Parameters array.
     * @return string|null Path to the output file if ok, null else.
     */
    private function _processXsltViaExternal($input, $stylesheet, $output = '', $parameters = array())
    {
        if (empty($output)) {
            $output = tempnam(sys_get_temp_dir(), 'omk_');
        }

        $command = get_option('oai_pmh_static_repository_processor');

        $command = sprintf($command, escapeshellarg($input), escapeshellarg($stylesheet), escapeshellarg($output));
        foreach ($parameters as $name => $parameter) {
            $command .= ' ' . escapeshellarg($name . '=' . $parameter);
        }

        $result = (int) shell_exec($command . ' 2>&- || echo 1');
        @chmod($output, 0640);

        // In Shell, 0 is a correct result.
        return ($result == 1) ? NULL : $output;
    }

    /**
     * Apply a process (xslt stylesheet) on an input (xml file) and save output.
     *
     * @param string $input Path of input file.
     * @param string $stylesheet Path of the stylesheet.
     * @param string $output Path of the output file. If none, a temp file will
     * be used.
     * @param array $parameters Parameters array.
     * @return string|null Path to the output file if ok, null else.
     */
    private function _processXsltViaPhp($input, $stylesheet, $output = '', $parameters = array())
    {
        if (empty($output)) {
            $output = tempnam(sys_get_temp_dir(), 'omk_');
        }

        try {
            $domXml = $this->_domXmlLoad($input);
            $domXsl = $this->_domXmlLoad($stylesheet);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        $proc = new XSLTProcessor;
        $proc->importStyleSheet($domXsl);
        $proc->setParameter('', $parameters);
        $result = $proc->transformToURI($domXml, $output);
        @chmod($output, 0640);

        return ($result === FALSE) ? NULL : $output;
    }

    /**
     * Load a xml or xslt file into a Dom document via file system or http.
     *
     * @param string $filepath Path of xml file on file system or via http.
     * @return DomDocument or throw error message.
     */
    private function _domXmlLoad($filepath)
    {
        $domDocument = new DomDocument;

        // Default import via file system.
        if (parse_url($filepath, PHP_URL_SCHEME) != 'http' && parse_url($filepath, PHP_URL_SCHEME) != 'https') {
            $domDocument->load($filepath);
        }

        // If xml file is over http, need to get it locally to process xslt.
        else {
            $xmlContent = file_get_contents($filepath);
            if ($xmlContent === false) {
                $message = __('Enable to load "%s". Verify that you have rights to access this folder and subfolders.', $filepath);
                throw new Exception($message);
            }
            elseif (empty($xmlContent)) {
                $message = __('The file "%s" is empty. Process is aborted.', $filepath);
                throw new Exception($message);
            }
            $domDocument->loadXML($xmlContent);
        }

        return $domDocument;
    }

    /**
     * Return the full inner content of an xml element, as string or as xml.
     *
     * @todo Fully manage cdata
     *
     * @see OaiPmhStaticRepository_Mapping_Document::_innerXML()
     *
     * @param SimpleXml $xml
     * @return string
     */
    protected function _innerXML($xml)
    {
        $output = $xml->asXml();
        $pos = strpos($output, '>') + 1;
        $len = strrpos($output, '<') - $pos;
        return trim(substr($output, $pos, $len));
    }
}
