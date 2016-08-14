Ead (plugin for Omeka)
======================

[Ead] is a plugin for [Omeka] that
- creates an element set for the [Encoded Archival Description] standard `EAD`
and adds the metadata that can't be easily replaced by a Dublin Core element;
- displays the items of an EAD collection in a hierarchical view, so the full
original structure can be browsed.

Currently, this plugin is not an EAD xml editor, even if it's possible to edit
each item.

Nevertheless, the plugin allows to import and to export EAD xml files via some
other optional plugins. Mappings are ready for:
- [Archive Folder] imports EAD xml files automatically;
- [OAI-PMH Static Repository] converts an EAD xml file and attached files into
an OAI-PMH static repository, that can be managed via the plugin [OAI-PMH Gateway];
- [OAI-PMH Harvester] imports EAD metadata from OAI-PMH servers, but the
structure is not rebuilt;
- [OAI-PMH Repository] allows to expose EAD metadata via OAI-PMH (to be
finished).

[Archive Folder] is the recommended plugin to import EAD files.

Finding aid tree, parts, upper or lower levels, etc. are automatically displayed
in the view of each item belonging to a finding aid. Dynamic display via
javascript is not available currently, but can be added simply.

About the conversion from EAD to Dublin Core, see the xsl tool [Ead2DCterms].
The mapping uses the Dublin Core Metadata Terms, not the only basic terms. It
can be modified and adapted directly in the xml file.

This is a beta release. Some functions may not work as expected.


Samples
-------

For testing purposes, the official EAD examples can be imported easily.

First, install this plugin and all associated plugins (see below).

Then, copy the files "ead_example_1.xml" and "ead_example_2.xml" that are in the
folder "samples" of the tool [Ead2DCterms] in a directory that the server can
access (check rights).

Next, choose and install [Archive Folder] or [OAI-PMH Static Repository].

* Example with [Archive Folder]

  - Go to Archive Folders and click on "Add a new archive folder".
  - Set the uri : `http://example.com/path/to/the/samples/'.
  - select "One item by repository" (all files inside a subfolder belong to one
  item);
  - select "Dublin Core : Identifier" as Identifier field (this allows update);
  - Click "Add Folder", then "Check" and, if no error, "Process", and wait a
  minute.
  - Browse your items!

* Example with [OAI-PMH Static Repository]

  - Go to OAI-PMH Static Repositories > Add a new OAI-PMH Static Repository,
  fill the url to the directory where are the previous files. Default options
  are fine, but you can change them as long as you keep the format "Document"
  for the harvesting.
  - Click "Add Folder", then "Check" and, if no error, "Update", and wait a
  minute.
  - Browse your items!


Design Notes
------------

EAD is an xml format dedicated to archives and libraries that allows to create
archival finding aids. The main interest of this format is that manages the
structure of a collection.

EAD is a structural and textual oriented model, that can't be integrated in
Omeka directly, because it is based on the flat and data oriented model of the
Dublin Core. So some choices may be done for the integration.

Globally, the integration of EAD in Omeka can be done via two main ways. In the
first, all elements of the EAD are added in an element set, like the Dublin Core
one. Then, each item can be described only by the EAD elements, and the
Dublin Core ones are not used. This allows to manage each archival piece in one
standard. A second way consists to convert similar data of EAD and Dublin Core,
for example the EAD element "unittitle" can be mapped to the Dublin Core
"title".

In this plugin, this second way has been selected, because it allows to manage,
to search, to exchange and to display more easily each item. It allows too to
mix archives with other items in one instance of Omeka and allows to process any
type of item as an archive. Even a finding aid can be described as an archive.

In this logic, a mapping has been built between the two standards. More
precisely, between EAD 2002 and the revised version of Dublin Core, id est the
fifty-five terms of the DCMI Metadata Terms, instead of the basic fifteen
elements of the Dublin Core Metadata Element Set. Moreover, the mapping uses the
elements available for item types when appropriate.

A second choice has been done for the integration. The EAD model allows to
describe a finding aid on one hand, and each piece, each group of pieces, and
the whole archival fund on the another hand. For the latter, each unit can be
described has a normal item. For the former, there are two possibilities: it can
be described as a normal item, because this was commonly a print, or as a
collection, because a finding aid describes a collection of items. The first
option has been prefered, because Omeka allows to create a special item type
for finding aid, with special elements. Another option was to separate the
header (as item) and the front matter (as collection), but it may be complex to
manage, because some of the elements are repeated.

The plugin doesn't use [item Relations] currently. The relations between a
finding aid and each components are managed via the Dublin Core terms
"Identifier", "Has Part" and "Is Part Of". An item is an ead part only when the
three metadata match. This allows to distinguish from simple items.

According to these choices, the EAD elements are integrated in two parts, as
item type elements for the general description of the finding aid (edition
statement, profile, front matter, etc.), and as elements of an element set for
the archival fund and each component.


Notes
-----

- No check is done on the conformity of the content of the element with the
[EAD 2002] and the [Dublin Core] standards.
- The process uses the last identifier to get the url, so the config of the
mapper should set a unique one as the last Dublin Core Identifier. The
stylesheet take care of this point.
- The process requires to know the internal path of each part, so it is set as
identifier (part after the root path). The root path should not contain
"/ead/eadheader".
- OAI-PMH Repository: only items belonging to a finding aid have are represented
in EAD, in accordance to the OAI-PMH protocol.


TODO
----

- Confirm the mapping on more sources.
- Allow specific tags in the html editor.
- See notes in [Ead2dcterms], in particular for non-managed elements.
- Dynamic load for big collections.
- Integrate an xml editor? Or see Omeka-S?


Installation
------------

Install the required plugins [Dublin Core Extended] and [Archive Document].

Uncompress files and rename plugin folder "Ead".

Uncheck the "html purifier" box in security settings, or allow all EAD tags and
attributes. This allows to edit items without losing invisible EAD tags.

Unzip or git [Ead2DCterms] inside the plugin subdirectory"libraries/external".
Keep the main directory "Ead2DCterms".

Then install it like any other Omeka plugin.

To import metadata, two plugins can be used. The recommended is to use [Archive Folder].
The other one is [OAI-PMH Static Repository], that requires [OAI-PMH Gateway],
[OAI-PMH Harvester], [OAI-PMH Repository]. [Archive Repertory] can be installed
too.

Note: [Dublin Core Extended], [OAI-PMH Harvester] and [OAI-PMH Repository] are
fork of the official plugins, with some fixes and improvements that are not yet
committed in the upstream repositories. They are fully compatible with them.

The import process requires an xslt 2 processor, so you need to install one,
like the open-source Saxon-HE from [Saxonica]. With Linux, just install the
package "libsaxonhe-java". This is a requirement because [Ead2DCterms] supports
only xslt 2 currently. Anyway, it's ten times faster than the xslt 1 processor
included in php. The other solution, to use an integrated binding such as
[Saxon-C], is not supported currently.

Note that under Debian, Saxon 9.6 and 9.7 don't work, so use Saxon 9.5.

Set the command to this processor inside the OAI-PMH Static Repository config
page (see the install paragraph of the readme of [OAI-PMH Static Repository]).

If [OAI-PMH Static Repository] is used, read all the remarks, in particular for
files path and extensions.


Warning
-------

Use it at your own risk.

It's always recommended to backup your files and database regularly so you can
roll back if needed.


Troubleshooting
---------------

See online issues on the [plugin issues] page on GitHub.


License
-------

This plugin is published under the [CeCILL v2.1] licence, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

In consideration of access to the source code and the rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software's author, the holder of the economic rights, and the
successive licensors only have limited liability.

In this respect, the risks associated with loading, using, modifying and/or
developing or reproducing the software by the user are brought to the user's
attention, given its Free Software status, which may make it complicated to use,
with the result that its use is reserved for developers and experienced
professionals having in-depth computer knowledge. Users are therefore encouraged
to load and test the suitability of the software as regards their requirements
in conditions enabling the security of their systems and/or data to be ensured
and, more generally, to use and operate it in the same conditions of security.
This Agreement may be freely reproduced and published, provided it is not
altered, and that no provisions are either added or removed herefrom.


Contact
-------

Current maintainers:

* Daniel Berthereau (see [Daniel-KM] on GitHub)


Copyright
---------

* Copyright Daniel Berthereau, 2015


[Ead]: https://github.com/Daniel-KM/Ead4Omeka
[Omeka]: https://omeka.org
[Encoded Archival Description]: https://loc.gov/ead/index.html
[Archive Folder]: https://github.com/Daniel-KM/ArchiveFolder
[OAI-PMH Static Repository]: https://github.com/Daniel-KM/OaiPmhStaticRepository
[OAI-PMH Gateway]: https://github.com/Daniel-KM/OaiPmhGateway
[OAI-PMH Harvester]: https://github.com/Daniel-KM/OaipmhHarvester
[OAI-PMH Repository]: https://omeka.org/add-ons/plugins/oai-pmh-repository
[Ead2DCterms]: https://github.com/Daniel-KM/Ead2DCterms
[item Relations]: https://omeka.org/codex/Plugins/ItemRelations
[EAD 2002]: https://www.loc.gov/ead/tglib/index.html
[Dublin Core]: http://dublincore.org
[Saxonica]: http://www.saxonica.com/download/opensource.xml
[Saxon-C]: http://www.saxonica.com/saxon-c/index.xml
[Dublin Core Extended]: https://github.com/Daniel-KM/DublinCoreExtended
[Archive Document]: https://github.com/Daniel-KM/ArchiveDocument
[Archive Repertory]: https://github.com/Daniel-KM/ArchiveRepertory
[plugin issues]: https://github.com/Daniel-KM/Ead4Omeka/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[Daniel-KM]: https://github.com/Daniel-KM "Daniel Berthereau"
