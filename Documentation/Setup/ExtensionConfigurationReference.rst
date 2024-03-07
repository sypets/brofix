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

Set the logo used in the Fluid email in the EXT:backend extension configuration:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['backend']['login.loginLogo'] = 'EXT:my_theme/Resources/Public/Images/login-logo.png or //domain.tld/login-logo.png';


EXT:brofix
==========

.. _extensionConfiguation_brofix_traverseMaxNumberOfPagesInBackend:

EXT:brofix | traverseMaxNumberOfPagesInBackend
----------------------------------------------

*Maximum number of pages to traverse in Backend ...*

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

.. _extensionConfiguation_brofix_combinedErrorNonCheckableMatch:

EXT:brofix | combinedErrorNonCheckableMatch
-------------------------------------------

*Non-checkable match*

If result from link target checking match this, consider the link target (URL)
as non-checkable. This is written to the database table and displayed in the
backend module. It is possible to filter by this status. By default, these
links are not displayed (since the default filter in the backend shows only
broken links).

Currently, these are the known status:

* 1: broken
* 2: ok
* 3: not possible to check ("non-checkable")
* 4: is excluded

This should also improve handling of cloudflare protected sites as these
typically return 403 HTTP status code. The link checking status is no longer
considered broken, it is now considered "not-checkable", since the actual
link check result cannot be obtained.

What kind of results from link checking, make the URL "non-checkable" can
be configured via Exension Configuration "combinedErrorNonCheckableMatch".

This can be either a regular expression (with prefix "regex:" and enclosing
delimeters (e.g. "/"). Or it can be a list of strings, separated by comma.

This is matched against a combination of the link checking result, consisting of:

.. code-block:: text

   <errorType> ":" <errorCode> ":" <exceptionMessage>

To match HTTP status code 401, you could use:

.. code-block:: text

   httpStatusCode:401:

The possible errorTypes and errorCodes can be seen in the class ExternalLinktype
or via the database field tx_brofix_broken_links.url_response.

This is the default value:

.. code-block:: text

   regex:/^(httpStatusCode:(401|403):|libcurlErrno:60:SSL certificate problem: unable to get local issuer certificate)/
