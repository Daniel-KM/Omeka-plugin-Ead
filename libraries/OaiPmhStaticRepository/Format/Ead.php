<?php
/**
 * Metadata format map for the EAD format.
 *
 * @package Ead
 */
class ArchiveFolder_Format_Ead extends ArchiveFolder_Format_Abstract
{
    const METADATA_PREFIX = 'ead';
    const METADATA_SCHEMA = 'http://www.loc.gov/ead/ead.xsd';
    const METADATA_NAMESPACE = 'http://www.loc.gov/ead';

    protected $_metadataPrefix = self::METADATA_PREFIX;
    protected $_metadataSchema = self::METADATA_SCHEMA;
    protected $_metadataNamespace = self::METADATA_NAMESPACE;

    protected $_parametersFormat = array(
        'use_dcterms' => true,
        'link_to_files' => true,
        'support_separated_files' => true,
    );

    protected function _fillMetadata($record = null)
    {
        // TODO Finish format for EAD.
        return;

        $writer = $this->_writer;

        if (is_null($record)) {
            $record = $this->_document;
        }

        // Prepare the oai record.
        $writer->startElement('ead:ead');
        $writer->writeAttribute('xmlns:' . self::METADATA_PREFIX, self::METADATA_NAMESPACE);
        $writer->writeAttribute('xsi:schemaLocation', self::METADATA_NAMESPACE . ' ' . self::METADATA_SCHEMA);

        // If finding aid, else unit.
        // $this->_fillDublinCore($record['metadata']);

        $writer->endElement();
    }
}
