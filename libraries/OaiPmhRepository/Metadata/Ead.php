<?php
/**
 * Class implementing EAD metadata output format.
 *
 * Only finding aids and parts are exposed, accordingly to the OAI-PMH protocol.
 *
 * @package Ead
 */
 class OaiPmhRepository_Metadata_Ead implements OaiPmhRepository_Metadata_FormatInterface
{
    // Xml schema and OAI prefix for the format represented by this class.
    // These constants are required for all maps.
    const METADATA_NAMESPACE = 'http://www.loc.gov/ead';
    const METADATA_PREFIX = 'ead';
    const METADATA_SCHEMA = 'http://www.loc.gov/ead/ead.xsd';

    /**
     * Appends the metadata for one Omeka item to the XML document.
     *
     * @param Item $item
     * @param DOMElement $parentElement
     */
    public function appendMetadata($item, $parentElement)
    {
        // TODO Metadata for EAD.
    }
}
