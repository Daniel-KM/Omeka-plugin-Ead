<?php
/**
 * Only elements that can't be easily mapped into Dublin Core Terms, mainly
 * textual content, are added.
 *
 * @see https://github.com/Daniel-KM/Ead2DCterms
 * @see http://www.loc.gov/ead/tglib/index.html
 */

 $elementSetsMetadata = array(
    array(
        // The name is "EAD Archive", because elements for the finding aid
        // itself is not managed here.
        'name' => 'EAD Archive',
        'description' => 'The Encoded Archival Description is a common standard used to describe collections of small pieces and to create hierarchical and structured finding aids.',
        'record_type' => 'Item',
        'elements' => array(
            /*
            // TODO Check: Simply use description?
            array(
                'name' => 'dsc',
                'label' => 'Description of Subordinate Components',
                'description' => 'Description of subordinate components.',
            ),
            */
            // Units are elements used to describe the archive and each component.
            array(
                // Name is used only for internal import. Label is used for elements.
                'name' => 'unit-did-head',
                'label' => 'Descriptive Identification : Heading',
                'description' => 'Title or caption for a section of text, including a list, inside the descriptive identification of an archival description or a component.',
                // 'comment' => '',
                // 'order',
                // 'element_set',
            ),
            array(
                'name' => 'unit-did-note',
                'label' => 'Descriptive Identification : Note',
                'description' => 'Note inside the descriptive identification of an archival description or a component.',
            ),
            array(
                'name' => 'unit-appraisal',
                'label' => 'Appraisal Information',
                'description' => 'Information about the process of determining the archival value and thus the disposition of records.',
            ),
            array(
                'name' => 'unit-arrangement',
                'label' => 'Arrangement',
                'description' => 'Information on how the described materials have been subdivided into smaller units.',
            ),
            array(
                'name' => 'unit-bioghist',
                'label' => 'Biography or History',
                'description' => 'A concise essay or chronology that places the archival materials in context by providing information about their creator(s).',
            ),
            array(
                'name' => 'unit-index',
                'label' => 'Index',
                'description' => 'A list of key terms and reference pointers that have been assembled to enhance access to the materials being described.',
            ),
            array(
                'name' => 'unit-level',
                'label' => 'Level',
                'description' => 'The hierarchical level of the materials being described by the element (may be other level too).',
            ),
            array(
                'name' => 'unit-note',
                'label' => 'Note',
                'description' => 'Note inside a the archival description or a component.',
            ),
            array(
                'name' => 'unit-odd',
                'label' => 'Other Descriptive Data',
                'description' => 'An element for information about the described materials that is not easily incorporated into one of the other named elements within archival description and components.',
            ),
            array(
                'name' => 'unit-processinfo',
                'label' => 'Processing Information',
                'description' => 'Information about accessioning, arranging, describing, preserving, storing, or otherwise preparing the described materials for research use.',
            ),
            array(
                'name' => 'unit-scopecontent',
                'label' => 'Scope and Content',
                'description' => 'A prose statement summarizing the range and topical coverage of the described materials.',
            ),
            array(
                'name' => 'unit-head',
                'label' => 'Heading',
                'description' => 'Title or caption for a section of text, including a list, inside an archival description or a component.',
            ),
            array(
                'name' => 'unit-thead',
                'label' => 'Table Head',
                'description' => 'Provides column headings for components or the description of subordinate components.',
            ),
        ),
    ),
);

// Elements for the finding aid (Ead Header and Front Matter).
$itemTypesMetadata = array(
    array(
        'name' => 'Archival Finding Aid',
        'description' => 'A catalog of documents or an inventory of pieces of archive that summarizes their content and organization to facilitate their access.',
        'elements' => array(
            array(
                'name' => 'eadheader-editionstmt',
                'label' => 'Edition Statement',
                'description' => 'Groups information about a finding aid edition by providing an Edition element as well as a Paragraph element for narrative statements.',
            ),
            array(
                'name' => 'eadheader-publicationstmt',
                'label' => 'Publication Statement',
                'description' => "Publication or distribution of the encoded finding aid, including the publisher's name and address, the date of publication, and other relevant details.",
            ),
            array(
                'name' => 'eadheader-notestmt',
                'label' => 'Note statement',
                'description' => 'Piece of descriptive information about the finding aid, similar to the "general notes" in traditional bibliographic descriptions.',
            ),
            array(
                'name' => 'eadheader-profiledesc-creation',
                'label' => 'Profile description : Creation',
                'description' => 'Information about the encoding of the finding aid, including the person(s) or agency(ies) responsible for the encoding, the date, and the circumstances under which the encoding was done.',
            ),
            array(
                'name' => 'eadheader-profiledesc-descrules',
                'label' => 'Profile description : Descriptive Rules',
                'description' => 'Enumeration of the rules, standards, conventions, and protocols used in preparing the description.',
            ),
            array(
                'name' => 'eadheader-profiledesc-langusage',
                'label' => 'Profile description : Language Usage',
                'description' => 'Languages, sublanguages, and dialects represented in an encoded finding aid.',
            ),
            array(
                'name' => 'eadheader-revisiondesc-change',
                'label' => 'Revision Description : Change',
                'description' => 'Brief description of an update made to an EAD document.',
            ),
            array(
                'name' => 'eadheader-revisiondesc-list',
                'label' => 'Revision Description : List',
                'description' => 'Series of words or numerals informations used to describe revisions.',
            ),
            // Elements for the front matter, that is a part of the main description of
            // the finding aid.
            array(
                'name' => 'frontmatter-titlepage',
                'label' => 'Front matter : Title page',
                'description' => 'Prefatory text that focuses on the creation, publication, or use of the finding aid rather than information about the materials being described.',
            ),
            /**
            // TODO Use sub-elements of the title page, not used currently?
            array(
                'name' => 'frontmatter-titlepage-blockquote',
                'label' => 'Front matter : Title page : Block Quote',
                'description' => 'An extended quotation inside the title page of the front matter.',
            ),
            array(
                'name' => 'frontmatter-titlepage-chronlist',
                'label' => 'Front matter : Title page : Chronology list',
                'description' => 'Chronology list inside the title page of the front matter.',
            ),
            array(
                'name' => 'frontmatter-titlepage-list',
                'label' => 'Front matter : Title page : List',
                'description' => 'List inside the title page of the front matter.',
            ),
            array(
                'name' => 'frontmatter-titlepage-note',
                'label' => 'Front matter : Title page : Note',
                'description' => 'Note inside the title page of the front matter.',
            ),
            array(
                'name' => 'frontmatter-titlepage-p',
                'label' => 'Front matter : Title page : Paragraph',
                'description' => 'Paragraph inside the title page of the front matter.',
            ),
            array(
                'name' => 'frontmatter-titlepage-table',
                'label' => 'Front matter : Title page : Table',
                'description' => 'Table inside the title page of the front matter.',
            ),
            */
            array(
                'name' => 'frontmatter-div',
                'label' => 'Front matter : Division',
                'description' => 'A division inside the front matter to group similar informations.',
            ),
        ),
    ),
);
