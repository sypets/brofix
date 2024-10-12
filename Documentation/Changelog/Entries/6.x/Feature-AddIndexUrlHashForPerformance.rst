.. include:: /Includes.rst.txt

============================================
Feature - Add index url_hash for performance
============================================

*since verion 6.2.0*

An index is added to the database table tx_brofix_broken_links for the
fields link_type, url_hash and check_status. A new field url_hash is
introduced which generates a hash for the URL.

This results may result in significant performance improvements when opening
records with RTE fields and many links in the backend. When the RTE is opened,
pre-processing is performed in order to mark the broken links as broken. For
this, a db query is performed for each link.

Migration
=========

.. important::

   *  Update Database
   *  Perform upgrade wizard


.. code-block:: bash

   php vendor/bin/typo3 database:updateschema
   php vendor/bin/typo3 upgrade:run brofix_urlHashUpgradeWizard

