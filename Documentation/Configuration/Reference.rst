.. include:: /Includes.txt


.. _configurationReference:

=======================
Configuration reference
=======================

.. contents::
   :local:
   :depth: 1

Editor permissions
==================

Give your editors permission to the "Info" module. You may want to disable access
to the Log and TSconfig.

Scheduler task
==============

Set up a scheduler task "Broken Link Fixer" to regularly check for broken links.


.. _tsconfig-example:

TSconfig example
================

You can find the default configuration in
:file:`EXT:brofix/Configuration/TsConfig/Page/pagetsconfig.tsconfig`.

.. code-block:: typoscript

   # check links in this additional table + field
   mod.brofix.searchFields.tx_news_domain_model_news =  bodytext

   mod.brofix.mail.fromname = TYPO3 Administrator
   mod.brofix.mail.fromname = no_reply@mydomain.com
   mod.brofix.mail.replytoname = Your name
   mod.brofix.mail.replytoemail = youremail@domain.org
   mod.brofix.mail.subject = TYPO3 Broken Link Fixer report - example.com

   # !!! required: fill out User-Agent reasonably with at least a working URL and / or email,
   # e.g.
   # your vendorname is GrateSturff, URL is https://gratesturff.com/imprint.html
   mod.brofix.linktypesConfig.external.headers.User-Agent = Mozilla/5.0 (compatible; GrateSturff LinkChecker/1.1; +https://gratesturff.com/imprint.html

   # optional: adjust timeout
   mod.brofix.linktypesConfig.external.timeout = 10
   # optional: follow up to 5 redirects - if more redirects are found this is counted as error
   mod.brofix.linktypesConfig.external.redirects = 5

   # optional: adjust pid - use a page of type system folder
   mod.brofix.excludeLinkTarget.storagePid = 0
   # allowed link types for exclude list, set to empty to deactivate or use csv values
   mod.brofix.excludeLinkTarget.allowed = external,db


.. _tsconfigRef:

TSconfig reference
==================

You can set the following options in the TSconfig for a page (e.g. the
root page) and override them in user or groups TSconfig. You must
prefix them with mod.brofix, e.g.
:ts:`mod.brofix.searchFields.pages = canonical_link`.

.. contents::
   :local:
   :depth: 1

.. _tsconfigSearchfields:

searchFields.[table]
------------------

.. container:: table-row

   Property
      searchFields.[table]

   Data type
      string

   Description
      Comma separated list of table fields in which to check for
      broken links. Broken Link Fixer only checks fields that have
      been defined in :ts:`searchFields`.

      Broken Link Fixer ships with sensible defaults that work well
      for the TYPO3 core. Not all fields which contain links are
      currently checked though. You can configure additional fields
      for extensions.

      .. warning::

         Currently, Broken Link Fixer can only detect links for fields having at
         least one :ref:`softref <columns-input-properties-softref>` set in their TCA configuration.

         For this reason, it is currently not possible to check for
         `pages.media`.

         Examples for working fields:

         *  `pages.canonical_link`
         *  `pages.url`

         Examples for not working fields:

         *  `pages.media`


   Examples
      Only check for `bodytext` in `tt_content`:

      .. code-block:: typoscript

         tt_content = bodytext

   Default
      .. code-block:: typoscript

         pages = media,url
         tt_content = bodytext,header_link,records


.. _tsconfigexcludeCtype:

excludeCtype
------------

.. container:: table-row

   Property
      excludeCtype

   Data type
      string

   Description
      Exclude specific content types from link checking. 'html' is not
      checked by default, because the parsing for links does not always
      work correctly and may cause a number of links to be displayed as
      broken, which are in fact ok (false positives).

   Default
      html

.. _tsconfigLinktypes:

linktypes
---------

.. container:: table-row

   Property
      linktypes

   Data type
      string

   Description
      Comma separated list of hooks to load.

      **Possible values:**

      db: Check links to database records (pages, content elements).

      file: Check links to files located in your local TYPO3 installation.

      external: Check links to external files.

      This list may be extended by other extensions providing a linktype
      checker.

   Default
      db,file,external


.. _tsconfigReportHiddenRecords:

reportHiddenRecords
-------------------

.. container:: table-row

   Property
      reportHiddenRecords

   Data type
      int

   Description
      Whether links to hidden records should be treated as broken links.

      .. important::

         This used to be `linkhandler.reportHiddenRecords` but is now available
         as configuration option for any linktype.

   Default
      1


.. _tsconfigUserAgent:

User-Agent
----------

**required**

.. container:: table-row

   Property
      linktypesConfig.external.headers.User-Agent

   Data type
      string

   Description
      This is what is sent as "User-Agent" to the external site when
      checking external URLs. It should contain a working URL with
      contact information.

      code-block:: tsconfig

         linktypesConfig.external.headers.User-Agent = Mozilla/5.0 (compatible; Mysite LinkChecker/1.1; +https://mysite.com/imprint.html

   Default
      If not set, a default is automatically generated using the email address from Global Configuration
      :php:`$GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress']`.

Accept
------

.. container:: table-row

   Property
      linktypesConfig.external.headers.Accept

   Data type
      string

   Description
      HTTP request header "Accept". It is recommended to leave the default value and not change this.

   Default
      */*

Accept-Language
---------------

.. container:: table-row

   Property
      linktypesConfig.external.headers.Accept

   Data type
      string

   Description
      HTTP request header. It is recommended to leave the default value and not change this.

   Default
      *


Accept-Encoding
---------------

.. container:: table-row

   Property
      linktypesConfig.external.headers.Accept-Encoding

   Data type
      string

   Description
      HTTP request header. It is recommended to leave the default value and not change this.

   Default
      *

timeout
-------

.. container:: table-row

   Property
      linktypesConfig.external.timeout

   Data type
      int

   Description
      Timeout for HTTP request. It is recommended to leave the default value and not change this.

   Default
      10

redirects
---------

.. container:: table-row

   Property
      linktypesConfig.external.redirects

   Data type
      int

   Description
      Number of redirects to follow. If more redirects are necessary to reach the destination final URL,
      this is handled as broken link. It is recommended to leave the default value and not change this.

   Default
      5

.. _tsConfigExcludeLinkTargetStoragePid:

excludeLinkTarget.storagePid
------------------------------

.. container:: table-row

   Property
      excludeLinkTarget.storagePid

   Data type
      int

   Description
      The pid of the storage folder which contains the excluded link target records. If you want to
      enable editors to add URLs to list of excluded URLs, you must change this. Create a central
      folder to store the excluded URLs.

      .. hint::

         Excluded link targets (=URLs) are treated as valid URLs. This can be used for the **rare** case
         that an URL is detected as broken, but is not broken. This may be the case for some sites
         which require a link, but also for common sites where the automatic link checking mechanism
         yields false results.

   Default
      0


excludeLinkTarget.allowed
-------------------------

.. container:: table-row

   Property
      excludeLinkTarget.allowed

   Data type
      string

   Description
      Allowed link types which can be excluded. By default, it is only possible to exclude external
      URLs. If you would like to make this available for page links to, add db, e.g.

      .. code-block:: typoscript

         allowed = external,db

      You can set it to empty to disable the exclude URL functionality:

      .. code-block:: typoscript

         allowed =

   Default
      external


.. _tsconfigLinkTargetCacheExpires:

linkTargetCache.expiresLow
--------------------------

.. container:: table-row

   Property
      linkTargetCache.expiresLow

   Data type
      int

   Description
      When the link target cache expires in seconds. Whenever an external URL is checked or rechecked,
      the link target cache is used. Once the cache expires, the URL must be checked again.

      The value means that the information for external URLs is retained for that time without having to
      access the external site. Making a request to the external site may take several seconds and is
      non-deterministic. This is important for :ref:`on-the-fly <linkCheckingOnTheFly>` rechecking.
      The downside is that the information may no longer be up-to-date (e.g. the URL will now work,
      but is still displayed as broken).

      As a rule of thumb, use the interval for full checking (e.g. 1 day for once a day checking) and
      multiply that with a factor of 1 to 10 for expiresLow. Add another interval for expiresHigh.

      The interval for expiresLow will be used for full checking via the scheduler.

      .. code-block:: typoscript

         # checking links daily, use 7 as factor:
         # (1 day * 7 * (seconds per day)) - (1 hour * seconds per hour)
         #  1 * 7 * 24*60*60 - 1*60*60
         # the result is "7 days minus 1 hour"
         expires = 601200

   Default
      518400 (6 days)


linkTargetCache.expiresHigh
---------------------------

.. container:: table-row

   Property
      linkTargetCache.expiresHigh

   Data type
      int

   Description
      See :ref:`tsconfigLinkTargetCacheExpires` for description

   Default
      604800 (7 days)


crawlDelay.ms
-------------

.. container:: table-row

   Property
      crawlDelay.ms

   Data type
      int

   Description
      The **minimum** number of milliseconds that must have passed between checking 2 URL for the
      same domain.

      If the required time has already passed since an URL of the same domain was last checked,
      the wait is not performed.

      This helps to prevent that external sites are bombarded with requests from our site.

      .. note::

         Currently, a wait is not performed if URLs are redirected because this is handled
         internally by Guzzle.

      This is a pragmatic approach to make sure that a minimum delay is used when checking URLs
      of the same site. As a site may have multiple domains or several domains will be used by
      the same site, this will not always get the desired result, but it is a "good enough" approach.

      If you increase this, the link checking via scheduler may take a little longer, which should
      not be a problem, if you check regularly. It is recommended to increase but not decrease this.

      This will not be used for :ref:`on-the-fly <linkCheckingOnTheFly>` checking, only for
      checking via the scheduler task.

      .. code-block:: typoscript

         crawlDelay.ms = 10000

   Default
      1000


crawlDelay.nodelay
------------------

.. container:: table-row

   Property
      crawlDelay.nodelay

   Data type
      string

   Description
      Do not use the crawlDelay.ms wait period for these domains


      .. code-block:: typoscript

         crawlDelay.nodelay = example.org,example.com

   Default
      empty


report.docsurl
--------------

.. container:: table-row

   Property
      report.docsurl

   Data type
      string

   Description
      Add a URL to your internal documentation if you have this. This will
      add an "i" button to the broken link report.

      .. code-block:: typoscript

         report.docsurl = https://example.org/typo3/editors/brofix

   Default
      empty

.. _tsconfigMailFomname:

mail.fromname
-------------

.. container:: table-row

   Property
      mail.fromname

   Data type
      string

   Description
      Set the from name of the report mail sent by the scheduler task.

   Default
      Install Tool

      *defaultMailFromName*

.. _tsconfigMailFromemail:

mail.fromemail
--------------

.. container:: table-row

   Property
      mail.fromemail

   Data type
      string

   Description
      Set the from email of the report mail sent by the scheduler task.

   Default
      Install Tool

      *defaultMailFromAddress*

.. _tsconfigMailReplytoname:

mail.replytoname
----------------

.. container:: table-row

   Property
      mail.replytoname

   Data type
      string

   Description
      Set the replyto name of the report mail sent by the cron script.

.. _tsconfigMailReplytoemail:

mail.replytoemail
-----------------

.. container:: table-row

   Property
      mail.replytoemail

   Data type
      string

   Description
      Set the replyto email of the report mail sent by the cron script.

.. _tsconfigMailSubject:

mail.subject
------------

.. container:: table-row

   Property
      mail.subject

   Data type
      string

   Description
      Set the subject of the report mail sent by the cron script.

   Default
      TYPO3 Broken Link Fixer report

Global Configuration
====================

Broken Link Fixer uses the HTTP request library (based on Guzzle) shipped with TYPO3.
Please have a look in the :ref:`Global Configuration <t3coreapi:typo3ConfVars>`,
particularly at the HTTP settings.
