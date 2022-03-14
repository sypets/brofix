.. include:: /Includes.rst.txt

.. _introduction:

============
Introduction
============


.. _about-this-document:

About this document
===================

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


.. _credits:

Credits
=======

This extension is based on the TYPO3 core extension
`EXT:linkvalidator <https://github.com/TYPO3-CMS/linkvalidator>`__. It was
forked from the source code of linkvalidator. Thus, it is based on the work
of the original authors and maintainers.


.. _what-is-the-diffence-to-linkvalidator:

What is the diffence to linkvalidator?
======================================

Broken Links Fixer includes some improvements that not have made it into the core yet. E.g.:

- impoved GUI with possibility to sort and filter the links

- possibility to exclude links from being checked to avoid false positives

- better configuration options
