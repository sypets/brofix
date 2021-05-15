.. include:: /Includes.txt


.. _configurationReference:
.. _tsconfigRef:

==================
TSconfig reference
==================

See :ref:`setupQuickstart` for an introduction.

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


   Example
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


.. _tsconfigDepth:

depth
-----

**optional**

.. container:: table-row

   Property
      depth

   Data type
      int

   Description
      Default depth when checking with console command

   Default
      999 (for infinite)

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

      .. code-block:: tsconfig

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
      Timeout for HTTP request.

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
      this is handled as broken link.

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
      The pid of the storage folder which contains the excluded link target
      records. If you want to enable editors to add URLs to list of excluded
      URLs, you must change this (it must be != 0).

      Create a central folder to store the excluded URLs or create one for each
      site.

      .. hint::

         Excluded link targets (=URLs) are treated as valid URLs. This can be
         used for the **rare** case that an URL is detected as broken, but is
         not broken. This may be the case for some sites which require login
         credentials, but also for common sites where the automatic link
         checking mechanism yields false results.

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
      Allowed link types which can be excluded. By default, it is only possible
      to exclude external URLs. If you would like to make this available for
      page links to, add db, e.g.

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
      When the link target cache expires in seconds. Whenever an external URL
      is checked or rechecked, the link target cache is used. Once the cache
      expires, the URL must be checked again.

      The value means that the information for external URLs is retained for
      that time without having to access the external site. Making a request
      to the external site may take several seconds and is non-deterministic.
      This is important for :ref:`on-the-fly <linkCheckingOnTheFly>` rechecking.
      The downside is that the information may no longer be up-to-date (e.g.
      the URL will now work, but is still displayed as broken).

      2 different values are used for expiresLow and expiresHigh so that the
      target will usually not expire on on-the-fly checking which would lead
      to delays.

      As a rule of thumb, use the interval for full checking (e.g. 1 day for
      once a day checking) and multiply that with a factor of 1 to 10 for
      expiresLow. Add another interval for expiresHigh.

      The interval for expiresLow will be used for full checking via the
      console command.

      .. code-block:: typoscript

         # checking links daily, use 7 as factor:
         #  1 day * 7 * (seconds per day)
         #  1 * 7 * 24*60*60
         linkTargetCache.expiresLow = 604800
         #  1 * 8 * 24*60*60
         linkTargetCache.expiresHigh = 691200

   Default
      604800 (7 days)


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
      691200 (8 days)


crawlDelay.seconds
------------------

.. container:: table-row

   Property
      crawlDelay.seconds

   Data type
      int

   Description
      The **minimum** number of seconds that must have passed between
      checking 2 URL for the same domain.

      If the required time has already passed since an URL of the same domain
      was last checked, the wait is not performed.

      This helps to prevent that external sites are bombarded with requests from
      our site.

      .. note::

         Currently, a wait is not performed for every URL if URLs are redirected
         because this is handled internally by Guzzle.

      This is a pragmatic approach to make sure that a minimum delay is used
      when checking URLs of the same site. As a site may have multiple domains
      or several domains may be used by the same site, this will not always get
      the desired result, but it is a "good enough" approach.

      This will not be used for :ref:`on-the-fly <linkCheckingOnTheFly>`
      checking, only for checking via the console command task.

      .. code-block:: typoscript

         crawlDelay.seconds = 10

   Default
      5


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

report.recheckButton
--------------------

.. container:: table-row

   Property
      mod.brofix.report.recheckButton

   Data type
      int

   Description
      Whether to show button for rechecking links.

      Deactivate the button:

      .. code-block:: typoscript

         mod.brofix.report.recheckButton = -1

      Only check if depth=0 (current page) is selected:

      .. code-block:: typoscript

         mod.brofix.report.recheckButton = 0

      Disable the page in User TSconfig (for a user or group):

      .. code-block:: typoscript

         page.mod.brofix.report.recheckButton = 0

      If the current depth  <= recheckButton, the button will be displayed.
      This makes it possible to not only control whether rechecking is
      possible, but also the depth

   Default
      999 (always show button)


.. _tsconfigSendOnCheckLinks:

mail.sendOnCheckLinks
---------------------

.. container:: table-row

   Property
      mail.sendOnCheckLinks

   Data type
      int

   Description
      Enable setting an email when the brofix:checkLinks console command
      is excecuted. This can be overridden via the command line arguments.

   Default
      0


.. _tsconfigMailRecipients:

mail.recipients
---------------

*required*

.. container:: table-row

   Property
      mail.recipients

   Data type
      string

   Description
      Set the recipient email address(es) of the report mail sent by the
      console command. Can be several, separated by comma.

      Use only email addresses, not a format like `Sender2 <sender2@example.org>`

   Example
      .. code-block:: tsconfig

         mod.brofix.mail.recipients = sender@example.org

   Default
      This is empty by default.
      :php:`$GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName']` and
      :php:`$GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress']`
      is used if this is empty.



.. _tsconfigMailFrom:

mail.fromname
-------------

*required*

.. container:: table-row

   Property
      mail.from

   Data type
      string

   Description
      Set the from name of the report mail sent by the console command.

   Example
      .. code-block:: tsconfig

         mod.brofix.mail.from = Sender

   Default
      This is empty by default.
      :php:`$GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName']`
      is used if this is empty.

mail.fromemail
--------------

*required*

.. container:: table-row

   Property
      mail.from

   Data type
      string

   Description
      Set the from email of the report mail sent by the console command.

   Example
      .. code-block:: tsconfig

         mod.brofix.mail.from = sender@example.org

   Default
      This is empty by default.
      :php:`$GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromEmail']`
      is used if this is empty.



.. _tsconfigMailReplyto:

mail.replytoemail
----------------

*optional*

.. container:: table-row

   Property
      mail.replytoemail

   Data type
      string

   Description
      Set the replyto email of the report mail sent by the cron script.

mail.replytoname
----------------

*optional*

.. container:: table-row

   Property
      mail.replytoma,e

   Data type
      string

   Description
      Set the replyto name of the report mail sent by the cron script.


.. _tsconfigMailSubject:

mail.subject
------------

*optional*

If this is not set explicitly, a subject will be auto-generated.

.. container:: table-row

   Property
      mail.subject

   Data type
      string

   Description
      Set the subject of the report mail.

   Default
      empty, auto-generated

.. _tsconfigMailTemplate:

mail.template
-------------

*optional*

Always uses the default template CheckLinksResults if not supplied.

.. container:: table-row

   Property
      mail.template

   Data type
      string

   Description
      Set the template name of the report mail. If
      $GLOBALS['TYPO3_CONF_VARS']['MAIL']['format'] equals 'both',
      CheckLinksResults.html and CheckLinksResults.txt must exist.

   Default
      empty, auto-generated

