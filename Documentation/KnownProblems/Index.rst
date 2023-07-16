.. include:: /Includes.rst.txt

.. _known-problems:

==============
Known problems
==============

The most relevant known problems currently concern only "external broken links".
You can turn off external link checking entirely or use one of the other
:ref:`counter measures <counter-measures-external-links>`.

.. _usagePitfallsFalsePositives:

False positives
===============

The main problem with external links are
:ref:`false positives <glossaryFalsePositives>` where the automatic link
checking will report a problem even though the URL is ok.

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
cache is not a problem and considerably speeds up link checking. Also it reduces
network traffic and load on external servers.

Because of the above mentioned problem of the false positives, it is a good
idea anyway to verify the result by loading the URL in the browser.

Once you work more with Broken Link Fixer and fixing the links in your site,
you will get a better judgement when checking an external URL yourself is
necessary.


.. |exclude_link_target_action_image| image:: ../_images/exclude_link_target.png

.. |recheck_url_action_image| image:: ../_images/recheck_url_action.png

Issues
======

For more known problems see the list of issues:

* https://github.com/sypets/brofix/issues
