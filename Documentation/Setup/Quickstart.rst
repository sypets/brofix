.. include:: /Includes.rst.txt


.. _setupQuickstart:

================
Setup quickstart
================

Steps
=====

.. rst-class:: bignums-xxl

#. Editor configuration

   Give your backend users  / user groups permission to the "Check Links"
   (web_brofix) module.

   Give backend users / user groups permission to the table
   `tx_brofix_exclude_link_target`, if they should be able to add URLs to the
   list of URLs not to be checked. (This requires a certain
   amount of prudence and understanding, otherwise this feature may be misused.)

   In this case, you must also set TSconfig :ref:`tsConfigExcludeLinkTargetStoragePid`
   to a page of type system folder. The editors must have access to this page
   (to be able to save records on this page).

#. Setup page tsconfig

   It is recommended to set this in your site package and have it apply to the entire
   installation. Alternatively, set it in the page TSconfig of the start page of each
   site. See :ref:`tsconfigMinimal` on this page for a minimal setup or in
   :ref:`tsconfigRef` for a complete reference.

#. Setup Global Configuration

   Look at the minimal :ref:`global configuration <globalConfiguation>` below.

   The global configuration can be configured via the backend
   *Settings > Configure Installation-Wide Options* or in the file
   or :file:`typo3conf/LocalConfiguration.php`.

#. Setup Extension Configuration

   The section :ref:`extensionConfiguation` lists some basic settings.

#. Check mail sending

   If an email should be sent on every link check performed via the console
   command, it is a good idea to check if email sending is setup correctly
   and works. (Sending a mail is optional).

   Go to Environment > Test Mail Setup

#. Setup the console command `brofix:checkLinks`

   This will use the settings from the TSconfig configuration.
   If no start pages are supplied as arguments, all start pages
   that have a site configuration are used.

   You can run the console command from the command line (or cron).

   Do not execute link checking, just show what configuration is used:

   .. code-block:: shell

       vendor/bin/typo3 brofix:checklinks --dry-run

   If everything is already configured, you don't need any arguments:

   .. code-block:: shell

       vendor/bin/typo3 brofix:checklinks

   Execute link checking, send an email to `webmaster@example.org`:

   .. code-block:: shell

       vendor/bin/typo3 brofix:checklinks --to webmaster@example.org

   .. code-block:: shell

      # Use -h to show all parameters:
      vendor/bin/typo3 brofix:checklinks -h

   Or set it up via the scheduler: "Execute console commands > brofix:checklinks".


.. _minimalConfig:

Minimal configuration
=====================

.. _globalConfiguation:

Global configuration
--------------------

The global configuration affects not just brofix but the behaviour of
other extensions as well.

If mod.brofix.mail.sendOnCheckLinks is 1, an email will be sent. You
can override this in the console command. If an email should be sent,
you should configure the recipient and sender address.

You can configure the following settings to set the from address globally (or
you can set it specifically for brofix via :ref:`TSconfig <tsconfigMailFromEmail>`):

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = 'Webmaster';
   $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = 'webmaster@example.org';

----

This determines whether an html mail is sent, a text mail or both:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['MAIL']['format'] = 'both';


----

The template path is already added in this extension in :file:`ext_localconf.php`,
but only if the slot 901 is still free:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'][901]
      = 'EXT:brofix/Resources/Private/Templates/Email';
   $GLOBALS['TYPO3_CONF_VARS']['MAIL']['partialRootPaths'][901]
      = 'EXT:brofix/Resources/Private/Partials';

If not, you need to set this yourself, if a mail should be submitted when
link checking is performed, using the default template in this extension.

.. _extensionConfiguation:

Extension configuration
-----------------------

Set the logo used in the Fluid email in the EXT:backend extension configuration:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['backend']['login.loginLogo'] = 'EXT:my_theme/Resources/Public/Images/login-logo.png or //domain.tld/login-logo.png';

Optional: Set the maximum number of pages traversed in the backend module.
This should be limited so that loading the broken link list in the backend
does not feel sluggish and slow. A good rule of thumb is to always keep this
under 1s. Depending on the performance of your site, you should use a limit
such as 10000 (ten thousand).

.. note::

   Remember that even though pagination is applied, Broken Link Fixer will
   always traverse through all subpages of the current page (unless the level
   is restricted in the form). The traversing of the pages is not cached and
   may cause considerable delays.



.. _tsconfigMinimal:

Page TSconfig
-------------

.. code-block:: typoscript

   # email recipients
   mod.brofix.mail.recipients = recipient@example.org

   # Add contact information here, such as an email address or a URL which contains an email address
   mod.brofix.linktypesConfig.external.headers.User-Agent =  Mozilla/5.0 (compatible; Site link checker; +https://gratesturff.com/imprint.html)

   # pid of a page of type folder - this is where the exclude link target
   # records are stored
   mod.brofix.excludeLinkTarget.storagePid = 20

   # you may want to exclude the domains of your own site from crawl delay
   # this means no crawl delay will be used for these domains
   # It is not recommended to do this for external domains
   mod.brofix.crawlDelay.nodelay = example.org,example.com
