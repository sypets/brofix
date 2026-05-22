.. include:: /Includes.rst.txt

============================================================
Feature - Add field for page id in DB table for broken links
============================================================

*since verion 7.0.0*

.. important::

   When updating to version 7.0.0, should perform DB schema updates and execute
   upgrade wizard!

A field :db:`record_pageid`  is added to the database table
:db:`tx_brofix_broken_links`. This will always contain the uid of the related
page, either of the page itself if the broken link is in the pages table, or
the pid if any other record.

Impact
======

*  performance improvements (depending on number of pages)

Migration
=========

.. important::

   *  Update database schema
   *  Perform upgrade wizard


.. code-block:: bash

   php vendor/bin/typo3 database:updateschema
   vendor/bin/typo3 upgrade:run brofix_copyPidToPageid

Details about change
====================

*For users of brofix, it is not necessary to read this. It provides further
details for developers of this extension.*

Adding the field :db:`record_pageid`  to the database table :db:`tx_brofix_broken_links`
makes it possible to simplify a number of database queries and improve the
sorting of elements.

Previously, it was always necessary to query if
:db:`tx_brofix_broken_links.table_name` containes 'pages' or not and then use
either :db:`record_uid` or :db:`record_pid` as field to obtain the page
id. The previous behavior doubled the number of parameters in prepared statement.

This used to not be a big problem in previous versions because a workaround was
introduced chunking the array of page ids if they reached a certain limit so the
query did not reach the number of parameters in prepared statement limit.
Reducing the limit to 50% might result in performance improvement in cases with
large number of pages.

Additionally, the array chunking made it impossible to properly paginate fetching
only the items for the current page, which also has a performance impact.

So, due to this change, further performance improvements are possible in the
future.
