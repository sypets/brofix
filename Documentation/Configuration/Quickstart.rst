.. include:: /Includes.txt


.. _configurationQuickstart:

==========
Quickstart
==========

Steps
=====

.. rst-class:: bignums-xxl

#. Editor configuration

   Give your backend users  / user groups permission to the "Info" module. You may want to disable access
   to the Log and TSconfig, see
   :ref:`mod.web_info.menu.function <tsconfig:pageblindingfunctionmenuoptions-webinfo>`.

   Give backend users / user groups permission to the table :sql:`tx_brofix_exclude_link_target`, if they should
   be able to add URLs to the list of URLs not to be checked. (This requires a certain
   amount of prudence and understanding, otherwise this feature may be misused.)

   In this case, you must also set TSconfig :ref:`tsConfigExcludeLinkTargetStoragePid`
   to a page of type system folder, to which the editors have access.

#. Setup page tsconfig

   It is recommended to set this in your site package and have it apply to the entire
   installation. Alternatively, set it in the page TSconfig of the start page of each
   site. See :ref:`tsconfig-example` on this page or at the :ref:`reference <tsconfigRef>`.

#. Setup global configuration

   Look at the minimal configuration below.

   The global configuration can be configured via the backend
   *Settings > Configure Installation-Wide Options* or in the file
   or :file:`typo3conf/LocalConfiguration.php`.

#. Check mail sending

   If an email should be sent on every link check performed via the console
   command, it is a good idea if email sending works.

   Go to Environment > Test Mail Setup

#. Setup the console command brofix:checkLinks

   This will use the settings from the configuration and use all start pages
   that have a site configuration.

   You can run the console command from the command line (or cron).

   .. code-block:: shell

       vendor/bin/typo3 brofix:checklinks --to webmaster@example.org -v

   .. code-block:: shell

      # Use -h to show all parameters:
      vendor/bin/typo3 brofix:checklinks -h

   Or set it up via the scheduler: "Execute console commands > brofix:checklinks".


.. _minimalConfig:

Minimal configuration
=====================

Global configuration
--------------------

The global configuration affects not just brofix but the behaviour of
other extensions as well.

If mod.brofix.mail.sendOnCheckLinks is 1, an email will be sent. You can
configure the following settings to set the recipient address globally (or
you can set it via TSconfig):

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = 'Webmaster';
   $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = 'webmaster@example.org';

----

This determines whether an html mail is sent, a text mail or both:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['MAIL']['format'] = 'both';


----

The template path is already added in this extension in :file:`ext_localconf.php`, but
only if the slot 901 is still free:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'][901]
      = 'EXT:brofix/Resources/Private/Templates/Email';
   $GLOBALS['TYPO3_CONF_VARS']['MAIL']['partialRootPaths'][901]
      = 'EXT:brofix/Resources/Private/Partials';

If not, you need to set this yourself, if a mail should be submitted when
link checking is performed, using the default template in this extension.

Extension configuration
-----------------------

Set the logo used in the Fluid email in the EXT:backend extension configuration:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['backend']['login.loginLogo'] = 'EXT:my_theme/Resources/Public/Images/login-logo.png or //domain.tld/login-logo.png';

.. _tsconfigMinimal:

TSconfig
--------

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
