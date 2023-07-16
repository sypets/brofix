.. include:: /Includes.rst.txt

.. _bestPractices:

==============
Best Practices
==============

*  It is recommended to execute a regular check via the
   console command to make sure the broken links are up to date.

*  Reduce the number of false positives for external links by using one of the
   :ref:`counter-measures-external-links`.

*  Be aware of crawling external sites. It is best practice to be "polite"
   and not bombard external sites with excessive requests. You may want to
   limit external checking by using one or more of the following measures:

   *  Increase built-in crawl-delay with Page TSconfig :ref:`tsconfig_crawlDelay_seconds`
   *  Increase link target cache duration: :ref:`tsconfigLinkTargetCacheExpiresLow`
      and :ref:`tsconfigLinkTargetCacheExpiresHigh`
   *  only check specific external links or do not check external links at all,
      see also :ref:`counter-measures-external-links`


.. _counter-measures-external-links:

Counter measures for problems with external links
=================================================

There are (at least) 3 possible counter-measures:

.. rst-class:: bignums-xxl

#. Turn off external link checking entirely

   by removing "external" from Page TSconfig
   :ref:`mod.brofix.linktypes <tsconfigLinktypes>`:

   .. code-block:: typoscript
      :caption: page TSconfig

      mod.brofix.linktypes = db,file

#. Override the ExternalLinktype class

   Alternatively you can
   :ref:`override the ExternalLinktype class <devOverrideExternalLinktype>`
   (in your own extension) and for example check only specific URLs or exclude
   specific URLs or handle only specific error types as errors.

#. "Manage Exclusions"

   The third possibility leaves it up to the editors to exclude specific URLs
   if there are problems: You can give them permission to exclude URLs or domains,
   see :ref:`howItWorksExcludeLinkTargets`.
