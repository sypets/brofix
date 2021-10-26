.. include:: /Includes.txt

.. _usagePitfalls:

==============
Usage pitfalls
==============

This page covers some known problems and situations which may cause
difficulties for beginners.


.. _usagePitfallsFalsePositives:

False positives
===============

The result of the automatic checking is not always 100% accurate.

In some rare cases, a link is displayed as broken, but the URL can be opened
in the browser and is ok. We refer to this as "false positives".

This may not affect you, but there are some known URLs which cannot be
automatically checked correctly.

Additionally, it may occur for

* URLs which require a login or are access restricted (e.g. by IP). For
  these, an error code 401 or 403 will usually be shown.
* temporary errors, e.g. network problems, timeout when loading the URL
  or a 500 error (which indicates a problem on the site which is usually
  temporary).

**Recommendation:**

*  For temporary problems (e.g. timeout): check the URL in the browser. If the
   URL cannot be loaded in the browser as well, wait a few days or contact the
   administrator of the remote site. If the URL is generally ok, but "flaky"
   (the result may fluctuate), you may want to replace the link with something
   more reliable or exclude this URL (see next item).
*  if the URL can be loaded in the browser and is ok, press "Recheck URL".
   |recheck_url_action_image| If the link
   is still reported as broken, exclude the link by clicking the "Exclude"
   button |exclude_link_target_action_image| and save.

.. important::

   It is often possible to load pages in the browser and the error is not
   immediately visible. This is sometimes the case for "Page not found (404)"
   errors. If you look more closely, you may see a "Page not found" text or
   something similar on the page or in the title.

   If you can verify the **HTTP status code** in the browser, this is a good
   indicator. Pages that load ok, should have the status code **200**.

   For example, you can use "Developer tools" in Chrome or Firefox and
   select the "Network" tab or install a browser extension for this.


.. _usagePitfallsLinkTargetCache:

Link target cache
=================

The result for external URLs is stored in the link target cache. This is an
internal storage, which saves the last result of the check. This way it is
not always necessary to recheck the external URL. The cache has an expiration
date, by default this is one week.

Because of this, the displayed information may not be up to date. Clicking
the "Recheck URL" button |recheck_url_action_image| will always refresh the
information.

Since the status of external URLs will not change very often, the link target
cache is not a problem and considerably speeds up link checking.

Because of the above mentioned problem of the false positives, it is a good
idea anyway to verify the result by loading the URL in the browser.

Once you work more with Broken Link Fixer and fixing the links in your site,
you will get a better judgement when checking an external URL yourself is
necessary.


.. |exclude_link_target_action_image| image:: ../_images/exclude_link_target.png

.. |recheck_url_action_image| image:: ../_images/recheck_url_action.png
