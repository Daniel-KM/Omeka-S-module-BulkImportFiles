Bulk Import Files (module for Omeka S)
======================================

[Bulk Import Files] is a module for [Omeka S] that allows to import files in
bulk with their internal metadata (for example exif, iptc and xmp for images,
audio and video, or pdf properties, etc.).


Installation
------------

The module uses an external library, [`getid3`], so use the release zip to
install it, or use and init the source.

See general end user documentation for [installing a module].

* From the zip

Download the last release [`BulkImportFiles.zip`] from the list of releases (the
master does not contain the dependency), and uncompress it in the `modules`
directory.

* From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `BulkImportFiles`, go to the root of the module, and run:

```
    composer install
```

The next times:

```
    composer update
```

Then install it like any other Omeka module.


Usage
-----

*** Configuration

The mapping of each media type (`image/jpg`, `image/png`, `application/pdf`) is
managed via items with the resource template `Bulk import files`, that is an
empty template created by the module.

So the first thing to do is to create private items will all the needed
properties.

For example, for the `JPG` format, the values are the one that are exposed via
the following xml paths (`xmp` is xml and provide all `iptc` and `exif` metadata):

```
Title [dcterms:title] = image/jpeg
Title [dcterms:title] = /x:xmpmeta/rdf:RDF/rdf:Description/@xmp:Label
Date Created [dcterms:created] = /x:xmpmeta/rdf:RDF/rdf:Description/@xmp:CreateDate
Date Modified [dcterms:modified] = /x:xmpmeta/rdf:RDF/rdf:Description/@xmp:ModifyDate
Format [dcterms:format] = /x:xmpmeta/rdf:RDF/rdf:Description/@tiff:Model
Subject [dcterms:subject] = /x:xmpmeta/rdf:RDF/rdf:Description/dc:subject//rdf:li
```

Note that the first title is used as media type to import files, and the second
as title, if any.

These items should be kept private, else they will be displayed in public.

Once saved, all the specific items can be checked in the main menu `Bulk import files`
on the main sidebar.

This menu displays a second menu `Create mappings` to automatically create
items from a config file, but it is still in development.

*** Upload

Once the item templates are ready, you can upload files via the third sub-menu
`Process import`. Just choose the folder, then check and add the files.

This workflow is experimental and will probably change in the future to avoid
the creation of specific items.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitHub.


License
-------

This module is published under the [CeCILL v2.1] licence, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

This software is governed by the CeCILL license under French law and abiding by
the rules of distribution of free software. You can use, modify and/ or
redistribute the software under the terms of the CeCILL license as circulated by
CEA, CNRS and INRIA at the following URL "http://www.cecill.info".

As a counterpart to the access to the source code and rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors have only limited liability.

In this respect, the user’s attention is drawn to the risks associated with
loading, using, modifying and/or developing or reproducing the software by the
user in light of its specific status of free software, that may mean that it is
complicated to manipulate, and that also therefore means that it is reserved for
developers and experienced professionals having in-depth computer knowledge.
Users are therefore encouraged to load and test the software’s suitability as
regards their requirements in conditions enabling the security of their systems
and/or data to be ensured and, more generally, to use and operate it in the same
conditions as regards security.

The fact that you are presently reading this means that you have had knowledge
of the CeCILL license and that you accept its terms.


Copyright
---------

* Copyright Daniel Berthereau, 2019


[Bulk Import Files]: https://github.com/Daniel-KM/Omeka-S-module-BulkImportFiles
[Omeka S]: https://omeka.org/s
[`BulkImportFiles.zip`]: https://github.com/Daniel-KM/Omeka-S-module-BulkImportFiles/releases
[installing a module]: http://dev.omeka.org/docs/s/user-manual/modules/#installing-modules
[module issues]: https://github.com/Daniel-KM/Omeka-S-module-BulkImportFiles/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[Daniel-KM]: https://github.com/Daniel-KM "Daniel Berthereau"
