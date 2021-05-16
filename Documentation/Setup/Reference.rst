.. include:: /Includes.txt


.. _configurationReference:
.. _tsconfigRef:

==================
TSconfig reference
==================

See :ref:`setupQuickstart` for an introduction.

"Optional" means that you do not need to set a value and can use the defaults.

In most cases, you should set values for the following properties (see
:ref:`minimalConfig`):

*  :ref:`tsconfigUserAgent`
*  :ref:`tsConfigExcludeLinkTargetStoragePid`
*  :ref:`tsconfigMailRecipients`
*  :ref:`tsconfigMailFromName`
*  :ref:`tsconfigMailFromEmail`


.. contents::
   :local:
   :depth: 1

.. _tsconfigSearchfields:

searchFields.[table]
====================

*optional*

.. container:: table-row

   Property
      mod.brofix.searchFields.[table]

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

         mod.brofix.searchFields.tt_content = bodytext

      Add checks for news and calendarize events:

      .. code-block:: typoscript

         mod.brofix.searchFields.tx_news_domain_model_news = bodytext
         mod.brofix.searchFields.tx_calendarize_domain_model_event = description

   Default
      .. code-block:: typoscript

         pages = media,url
         tt_content = bodytext,header_link,records


.. _tsconfigexcludeCtype:

excludeCtype
============

*optional*

.. container:: table-row

   Property
      mod.brofix.excludeCtype

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

*optional*

.. container:: table-row

   Property
      mod.brofix.linktypes

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

*optional*

.. container:: table-row

   Property
      mod.brofix.reportHiddenRecords

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

*optional*

.. container:: table-row

   Property
      mod.brofix.depth

   Data type
      int

   Description
      Default depth when checking with console command

   Default
      999 (for infinite)

.. _tsconfigUserAgent:

User-Agent
----------

*required*

.. container:: table-row

   Property
      mod.brofix.linktypesConfig.external.headers.User-Agent

   Data type
      string

   Description
      This is what is sent as "User-Agent" to the external site when
      checking external URLs. It should contain a working URL with
      contact information.

      .. code-block:: tsconfig

         User-Agent = Mozilla/5.0 (compatible; Mysite LinkChecker/1.1; +https://mysite.com/imprint.html

   Default
      If not set, a default is automatically generated using the email address from Global Configuration
      :php:`$GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress']`.

Accept
------

*optional*

.. container:: table-row

   Property
      mod.brofix.linktypesConfig.external.headers.Accept

   Data type
      string

   Description
      HTTP request header "Accept". It is recommended to leave the default value and not change this.

   Default
      */*

Accept-Language
---------------

*optional*

.. container:: table-row

   Property
      mod.brofix.linktypesConfig.external.headers.Accept

   Data type
      string

   Description
      HTTP request header. It is recommended to leave the default value and not change this.

   Default
      *


Accept-Encoding
---------------

*optional*

.. container:: table-row

   Property
      mod.brofix.linktypesConfig.external.headers.Accept-Encoding

   Data type
      string

   Description
      HTTP request header. It is recommended to leave the default value and not change this.

   Default
      *

timeout
-------

*optional*

.. container:: table-row

   Property
      mod.brofix.linktypesConfig.external.timeout

   Data type
      int

   Description
      Timeout for HTTP request.

   Default
      10

redirects
---------

*optional*

.. container:: table-row

   Property
      mod.brofix.linktypesConfig.external.redirects

   Data type
      int

   Description
      Number of redirects to follow. If more redirects are necessary to reach
      the destination final URL, this is handled as broken link.

   Default
      5

.. _tsConfigExcludeLinkTargetStoragePid:

excludeLinkTarget.storagePid
------------------------------

*required* (if "exclude URL" functionality should be available for non-admin
editors)

.. container:: table-row

   Property
      mod.brofix.excludeLinkTarget.storagePid

   Data type
      int

   Description
      The pid of the storage folder which contains the excluded link target
      records. If you want to enable editors to add URLs to list of excluded
      URLs, you must change this (it must be != 0).

      Create a central folder to store the excluded URLs or create one for each
      site.

      .. important::

         The storage pid is stored along with the broken link records. If
         you change this value, you should start a complete recheck of broken
         links to get this updated.

      Excluded link targets (=URLs) are treated as valid URLs. This can be
      used for the **rare** case that an URL is detected as broken, but is
      not broken. This may be the case for some sites which require login
      credentials, but also for common sites where the automatic link
      checking mechanism yields false results.

   Default
      0


excludeLinkTarget.allowed
-------------------------

*optional*

.. container:: table-row

   Property
      mod.brofix.excludeLinkTarget.allowed

   Data type
      string

   Description
      Allowed link types which can be excluded. By default, it is only possible
      to exclude external URLs. If you would like to make this available for
      page links too, add additional link types, e.g.

      .. code-block:: typoscript

         allowed = external,db

      You can set it to empty to disable the "exclude URL" functionality:

      .. code-block:: typoscript

         allowed =

   Default
      external


.. _tsconfigLinkTargetCacheExpires:

linkTargetCache.expiresLow
--------------------------

*optional*

.. container:: table-row

   Property
      mod.brofix.linkTargetCache.expiresLow

   Data type
      int

   Description
      When the link target cache expires in seconds. Whenever an external URL
      is checked or rechecked, the link target cache is used. Once the cache
      expires, the URL must be checked again.

      The value means that the information for external URLs is retained for
      that time without having to access the external site.

      2 different values are used for expiresLow and expiresHigh so that the
      target will usually not expire during the on-the-fly checking which would
      lead to delays.

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

*optional*

.. container:: table-row

   Property
      mod.brofix.linkTargetCache.expiresHigh

   Data type
      int

   Description
      See :ref:`tsconfigLinkTargetCacheExpires` for description

   Default
      691200 (8 days)


crawlDelay.seconds
------------------

*optional*

.. container:: table-row

   Property
      mod.brofix.crawlDelay.seconds

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

*optional*

.. container:: table-row

   Property
      mod.brofix.crawlDelay.nodelay

   Data type
      string

   Description
      Do not use the crawlDelay.seconds wait period for these domains


      .. code-block:: typoscript

         crawlDelay.nodelay = example.org,example.com

   Default
      empty


report.docsurl
--------------

*optional*

.. container:: table-row

   Property
      mod.brofix.report.docsurl

   Data type
      string

   Description
      Add a documentation URL. This will add an "i" button to the broken link
      report with a link to the documentation.

      Add a link to the official documentation:

      .. code-block:: typoscript

         report.docsurl = https://docs.typo3.org/p/sypets/brofix/master/en-us/Index.html

   Default
      empty

report.recheckButton
--------------------

*optional*

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

*optional*

.. container:: table-row

   Property
      mod.brofix.mail.sendOnCheckLinks

   Data type
      int

   Description
      Enable sending an email when the brofix:checkLinks console command
      is excecuted. This can be overridden via command line arguments (`-e`).

   Default
      1


.. _tsconfigMailRecipients:

mail.recipients
---------------

*required*

.. container:: table-row

   Property
      mod.brofix.mail.recipients

   Data type
      string

   Description
      Set the recipient email address(es) of the report mail sent by the
      console command. Can be several, separated by comma.

   Example
      .. code-block:: tsconfig

         mod.brofix.mail.recipients = sender@example.org

   Default
      This is empty by default.
      :php:`$GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName']` and
      :php:`$GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress']`
      is used if this is empty.


.. _tsconfigMailFromName:

mail.fromname
-------------

*required* (unless set in
:php:`$GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName']`)

.. container:: table-row

   Property
      mod.brofix.mail.from

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

.. _tsconfigMailFromEmail:

mail.fromemail
--------------

*required* (unless set in
:php:`$GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromEmail']`)

.. container:: table-row

   Property
      mod.brofix.mail.from

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
      mod.brofix.mail.replytoemail

   Data type
      string

   Description
      Set the replyto email of the report mail sent by the cron script.

   Default
      Empty

mail.replytoname
----------------

*optional*

.. container:: table-row

   Property
      mod.brofix.mail.replytoma,e

   Data type
      string

   Description
      Set the replyto name of the report mail sent by the cron script.

   Default
      Empty

.. _tsconfigMailSubject:

mail.subject
------------

*optional*

If this is not set explicitly, a subject will be auto-generated.

.. container:: table-row

   Property
      mod.brofix.mail.subject

   Data type
      string

   Description
      Set the subject of the report mail.

   Default
      Empty, auto-generated

.. _tsconfigMailTemplate:

mail.template
-------------

*optional*

Always uses the default template CheckLinksResults if not supplied.

.. container:: table-row

   Property
      mod.brofix.mail.template

   Data type
      string

   Description
      Set the template name of the report mail. If
      $GLOBALS['TYPO3_CONF_VARS']['MAIL']['format'] equals 'both',
      CheckLinksResults.html and CheckLinksResults.txt must exist.

   Default
      CheckLinksResults

