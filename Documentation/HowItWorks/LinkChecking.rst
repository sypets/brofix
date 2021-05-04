.. include:: /Includes.txt

.. _linkChecking:

=================
Link checking
=================

This page explains how the link checking is done.

How link checking is done
==========================

The link checking is done via the console command and when returning to the
list of broken links after editing a record.

In general, we try to avoid excessive checking, especially when it comes
to external URLs.

Checking external URLs has the following problems:

*  network traffic is generated
*  external sites may be bombarded with requests in rapid succession -
   in general it is recommended to wait between requests to the same site (crawl delay).
   If external sites get too many requests (in a timeframe), this may even
   result in our site getting blocked.
*  checking an external URL may take a few seconds to complete - redirects
   are followed, which may result in several requests and each single
   request may take several seconds - thus, it is undeterministic. Using this
   mechanism for on-the-fly checking is problematic, because we want to obtain
   the results immediately.

For this reason, the following mechanisms are used:

*  The results of external link checking is cached. This means, if an
   URL is checked more than once before the cache expires, the results
   from the cache are used.
*  A crawl delay is used: If several URLs of one domain is checked, we
   wait at least this amount of time before the next request (this is
   only done when checking via the console command, not for on-the-fly checking).

.. _linkCheckingOnTheFly::

When link checking is done
==========================

1. Via console command, a full link check is performed
2. "on-the-fly" checking: When editing a record via the list of broken links, a
   recheck is performed when returning to the list. This only checks the links in the edited record.

Additionally, if records are deleted or set to deleted=1, the broken link records are
removed immediately.

.. _falsePositives:

False positives
===============

It is a known problem, that the automatic checking does not always yield the correct
result. This is rare but may happen for a handful of different URLs in your site.

As a workaround, it is possible to add a specific URL or specific domain to an exclude
list. In this case, the URL will be treated as if valid. It will no longer show up in
the report. As soon as the URL is excluded, all existing broken link records are removed.
Adding an URL to the exclude list can be conveniently done by clicking on a button in the
list of broken links.


Recommendations
===============

Make sure the link target cache is filled - see :ref:`tsconfigLinkTargetCacheExpires`.

