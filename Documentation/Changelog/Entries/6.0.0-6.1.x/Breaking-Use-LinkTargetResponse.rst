.. include:: /Includes.rst.txt

====================================================
Breaking - Use LinkTargetResponse (Add check_status)
====================================================

A database field "check_status" was added to the tx_brofix_broken_links
and tx_brofix_link_target_cache tables.
It is now possible to save all links, not just the broken links to
tx_brofix_broken_links (configurable by extension configuration).

For some status codes for external links (e.g. HTTP status code 401 and 403),
the link targets are considered uncheckable - we cannot really know if they
are broken or not. This is stored as separate status and it is possible to
filter by this status in the broken link module.

Currently, these are the known status:

* 1: broken
* 2: ok
* 3: not possible to check
* 4: is excluded

This should also improve handling of cloudflare protected sites as these
typically return 403 HTTP status code. The link checking status is no longer
considered broken, it is now considered "not checkable", since the actual
link check result cannot be obtained.

What kind of results from link checking, make the URL "uncheckable" can
be configured via Exension Configuration "combinedErrorNonCheckableMatch".

This can be either a regular expression (with prefix "regex:" and enclosing
delimeters (e.g. "/"). Or it can be a list of strings, separated by comma.

This is matched against a combination of the link checking result, consisting of:

.. code-block:: text

   <errorType> ":" <errorCode> ":" <exceptionMessage>

To match HTTP status code 401, you could use:

.. code-block:: text

   httpStatusCode:401:

This is the default value:

.. code-block:: text
   :caption:

   regex:/^(httpStatusCode:(401|403):|libcurlErrno:60:SSL certificate problem: unable to get local issuer certificate)/


Impact
======

There were some changes to the database and the LinktypeInterface.
See "Migration" for necessary actions.

Also, there was a change to the backend module: A new filter "Check status:"
was added to filter broken links by status. By default, only broken links
are shown (as before this change).

Migration
=========

Update any custom classes implementing LinktypeInterface to address changes
to the interface. In particular, the :php:`checkLinks()` method will now return
:php:`LinkTargetResult` instead of int.

Also, a database update must be performed to address the changed schema.

The tables tx_brofix_broken_links and tx_brofix_link_target_cache should be
emptied. This can be done by performing the Upgrade wizard "Truncate tables
tx_brofix_broken_links and tx_brofix_link_target_cache".

Some language labels have been added, it is advised to join Crowdin and
contribute translations.

Also, a new select field "Check status" was added to the Broken Link list module:
It is recommended to advise editors about this, but it should not be a problem.
Editors can just use the default selection (only show broken links).
