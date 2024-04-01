.. include:: /Includes.rst.txt

.. _configurationReference:

=======================
Configuration Reference
=======================

.. contents::
   :depth: 1
   :local:

Backend user configuration
==========================

Give your backend users  / user groups permission to the "Check Links"
(web_brofix) module.

Give backend users / user groups permission to the table
`tx_brofix_exclude_link_target`, if they should be able to add URLs to the
list of URLs not to be checked. (This requires a certain
amount of prudence and understanding, otherwise this feature may be misused.)

In this case, you must also set TSconfig :ref:`tsConfigExcludeLinkTargetStoragePid`
to a page of type system folder. The editors must have access to this page
(to be able to save records on this page).

.. _globalConfiguation:

Global Configuration
====================

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
=======================

Is handled on separate page: :ref:`extensionConfiguation`.

Tsconfig
========

Is handled on separate page: :ref:`tsconfigRef`.

