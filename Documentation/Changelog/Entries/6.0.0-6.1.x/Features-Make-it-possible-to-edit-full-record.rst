.. include:: /Includes.rst.txt

.. _feature-352:

=============================================================
Feature: issue 352 - Make it possible to edit the full record
=============================================================

See

* core :issue:`103493`
* brofix issue https://github.com/sypets/brofix/issues/352

Description
===========

Previously, a form showing only the field with the broken link is opened, if
clicking the "pencil" button in the Broken Link Fixer report.

This is not ideal in some cases because relevant context is missing, for example
when editing redirect records.

For this reason, it is now possible to also edit the full record, but this is
configurable (see Extension Configuration).


Impact
======

A new button is now displayed in the broken link list BE module, in addition to the
already existing button. The buttons have the following functionality:

1. button to edit only the field (same as before)
2. button to edit the entire record (which contains an additional icon)

If this makes sense depends on which records / fields are checked and if it is
helpful to have more context. If not, this can be deactivated in the Extension
Configuration.
