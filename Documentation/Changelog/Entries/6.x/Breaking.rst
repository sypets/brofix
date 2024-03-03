.. include:: /Includes.rst.txt

===========================
Breaking - Add check_status
===========================

A database field "check_status" was added to the tx_brofix_broken_links table.
It is now possible to save all links, not just the broken links to this table
(configurable by extension configuration).

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

Impact
======

If any custom linktypes implementing LinktypeInterface were created, these
must be changed.

A new field check_status was added to the table tx_brofix_broken_links.

Migration
=========

Update any custom classes implementing LinktypeInterface to address changes
to the interface. In particular, the :php:`checkLinks()` method will now return
:php:`LinkTargetResult` instead of int.

Also, a database update must be performed to address the changed schema.

The tables tx_brofix_broken_links and tx_brofix_link_target_cache should be
emptied. It is necessary to check all links.

Some language labels have been added, it is advised to join Crowdin and
contribute translations.

Also, a new select field "Check status" was added to the Broken Link list module:
It is recommended to advise editors about this, but it should not be a problem.
Editors can just use the default selection (only show broken links).
