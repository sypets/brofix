.. include:: /Includes.rst.txt

.. _introduction:

============
Introduction
============


.. _about-this-document:

About
=====

Broken Link Fixer (brofix) is an extension which enables you to conveniently
check your website for broken links. This manual explains how to
install, configure and use the extension.

The extension was started using the core EXT:linkvalidator source code and documentation
but is now an independent project.

.. _what-does-it-do:

What does it do?
================

Broken Link Fixer checks the links in your website, reports
broken links and provides a way to fix these problems.

It includes the following features:

- Broken Link Fixer can check all kinds of links. This includes internal
  links to pages and content elements, file links to files in the local
  file system and external links to files somewhere else in the web.

- Broken Link Fixer checks a number of fields by default, for example
  header fields and text fields of content elements.
  It can be configured to check any field you like (via :ref:`TSconfig <configuration>`).

- A console command can be setup to check
  automatically. This can also generate a report which is sent via email.

- Broken Link Fixer is extendable. It provides hooks to check special types
  of links or override how the checking of external, file and page
  links works.


.. _what-is-the-diffence-to-linkvalidator:

Difference to linkvalidator
===========================

Broken Links Fixer was forked off linkvalidator but then developed independantly,
which made it possible to make more significant changes:

-  improved user interface with better handling of list of broken links:

   -  sort (by page, link type, link target, error type etc.)
   -  paginate (if more than 100 broken links are displayed)
   -  filter the broken link list, e.g. by URL
   - "clickfilter": filter by content element or URL by click
   -  possible to recheck for a specific URL by clicking a button
      "Check link again" - **all** broken link records with
      this target will be updated if status changes
   -  more descriptive (flash) messages to show what is going on
   -  visible hints for "stale" broken link records (e.g. if content element
      was edited after last link check)

-  more visibility of broken links by showing number of broken links for the
   page in the page module (if EXT:page_callouts is installed)

-  better handling of external links

   -  possibility to exclude links from being checked to avoid false positives
   -  link target cache to avoid frequent rechecking of external links
   -  crawl delay: automatically delay between checking links of one domain

-  link checking

   -  the scheduler task was replaced by a console command
   -  it is not necessary to specify the start pid, if no pid is given, the
      site configuration is used
   -  configuration from Global Configuration is used, if not explicitly
      specified in link configuration (e.g. :ref:`from email address <globalConfiguation>`)
   -  the broken link records are not removed and created again, but updated.
      In linkvalidator, the entire list (for current check criteria) is removed
      at beginning of link check. This might result in duplicates and in broken
      links missing during link check.
   -  content fields are not checked if they are not editable in the BE. This
      includes permission checks (which linkvalidator also handles), but also
      checks via FormEnginge - for example tt_content.bodytext is not editable
      in the BE for plugins. If CE types are switching in content elements, this
      can be a problem with linkvalidator.
   -  broken link records are automatically removed via DataHandler hook if a
      record is deleted.

.. _credits:

Credits
=======

This extension is based on the TYPO3 core extension
`EXT:linkvalidator <https://github.com/TYPO3-CMS/linkvalidator>`__. It was
forked from the source code of linkvalidator. Thus, it is based on the work
of the original authors and maintainers.
