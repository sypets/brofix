.. confval, see https://docs.typo3.org/m/typo3/docs-how-to-document/main/en-us/WritingReST/Reference/Code/Confval.html
.. additional fields can be defined in Settins.cfg

.. include:: /Includes.rst.txt

.. _extensionConfiguation:

=======================
Extension configuration
=======================

Extension configuration is used for global settings which should be the same
for the entire TYPO3 installation.

It is
configured in the backend, via :guilabel:`Settings | Extension Configuation`
or using the file :file:`settings.php`.

EXT:backend
===========

.. _extensionConfiguation_backend_loginLogo:

EXT:backend | login.loginLogo
-----------------------------

*Logo*

:guilabel:`Login` tab

default:
   empty

Set the logo used in the Fluid email in the EXT:backend extension configuration:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['backend']['login.loginLogo'] = 'EXT:my_theme/Resources/Public/Images/login-logo.png or //domain.tld/login-logo.png';


EXT:brofix
==========

.. _extensionConfiguation_brofix_linkTargetCacheExpiresLow:

EXT:brofix | linkTargetCacheExpiresLow
--------------------------------------

*External link target cache (in seconds) for checking*

:guilabel:`Checking` tab

default:
   0 (means use TSconfig value :ref:`tsconfigLinkTargetCacheExpiresLow`)
available values:
   any integer value

For a description see the TSconfig option :ref:`tsconfigLinkTargetCacheExpiresLow`.

.. _extensionConfiguation_brofix_linkTargetCacheExpiresHigh:

EXT:brofix | linkTargetCacheExpiresHigh
--------------------------------------

*External link target cache (in seconds) for checking*

:guilabel:`Checking` tab

default:
   0 (means use TSconfig value :ref:`tsconfigLinkTargetCacheExpiresHigh`)
available values:
   any integer value

This should be a slightly higher value than :ref:`extensionConfiguation_brofix_linkTargetCacheExpiresLow`
or 0.

For a description see the TSconfig option :ref:`tsconfigLinkTargetCacheExpiresHigh`.

.. _extensionConfiguation_brofix_excludeSoftrefs:

EXT:brofix | excludeSoftrefs
----------------------------

*Do not use these softreference parsers (comma separated list) when parsing content*

:guilabel:`Checking` tab

* default: url
* available values: any softref parser keys, separated by comma

This is a workaround for a TYPO3 core bug, see see https://forge.typo3.org/issues/97937

.. _extensionConfiguation_brofix_excludeSoftrefsInFields:

EXT:brofix | excludeSoftrefsInFields
----------------------------

*In which fields should excludeSoftrefs apply*

:guilabel:`Checking` tab

default:
   "tt_content.bodytext"
available values:
   any softref parser keys, separated by comma

This is a workaround for a TYPO3 core bug, see see https://forge.typo3.org/issues/97937

Usually, you will want to apply this in any rich text fields where link tags
are used.

.. _extensionConfiguation_tcaProcessing:

EXT:brofix | tcaProcessing
--------------------------

*Perform TCA processing*

:guilabel:`Checking` tab

default:
   "default"
available values:
   "default" | "full"

Changes how the TCA processing is done. The default setting may not work
for some configurations and especially for Flexforms. In that case, it should
be set to "full". This setting is still experimental, so it is not on by
default.

This setting results in 2 changes:

1. Use of the FormDataGroup
2. If the entire row is fetched for TCA processing. If "full" is on, the entire row is fetched.
   If the value is "default", only the fields defined in "searchFields" are fetched, in addition
   to some fields such as type, relevant fields for language evaluation and header.

By default, one of the following class names to use as FormDataGroup for TCA
processing will be used based on the value of tcaProcessing:

* "default": Sypets\Brofix\FormEngine\FieldShouldBeChecked
* "full": Sypets\Brofix\FormEngine\FieldShouldBeCheckedWithFlexform

.. _extensionConfiguation_overrideFormDataGroup:

EXT:brofix | overrideFormDataGroup
----------------------------------

*Override FormDataGroup for processing TCA*

:guilabel:`Checking` tab

default:
   "" (empty, which means the default FormDataGroup based on tcaProcessing is used)
available values:
   any valid class name which implements FormDataGroupInterface as fully qualified class name, for example Myvendor\Myextension\FormEngine\MyFormdatagroup

Changes how the TCA processing is done.

.. _extensionConfiguation_brofix_traverseMaxNumberOfPagesInBackend:

EXT:brofix | traverseMaxNumberOfPagesInBackend
----------------------------------------------

*Maximum number of pages to traverse in Backend ...*

:guilabel:`Backend` tab

default:
   1000
available values:
   any number, 0 turns feature off

Set the maximum number of pages traversed in the backend module.
This should be limited so that loading the broken link list in the backend
does not feel sluggish and slow. A good rule of thumb is to always keep the
time required to load a page in the Backend always under 1 second. Depending
on the performance of your site, you should use a limit such as 1000 (thousand).

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['brofix']['traverseMaxNumberOfPagesInBackend'] = 1000;

.. note::

   Remember that even though pagination is applied, Broken Link Fixer will
   always traverse through all subpages of the current page (unless the level
   is restricted in the form). The traversing of the pages is not cached and
   may cause considerable delays.
