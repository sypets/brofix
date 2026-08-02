.. include:: /Includes.rst.txt

===========================================
Feature - Make page callouts configurable
===========================================

*since verion 6.1.0, changed again in 8.0.1*

If `EXT:page_callouts <https://extensions.typo3.org/extension/page_callouts>`__
is installed, information is displayed in the page module, if broken links exists.

Since this has a small performance impact, is not really necessary if broken
links are fixed regularly.

This is now configurable via:

*  extension configuration: "Show message in page module if broken links exist
   on page" *[showPageCalloutBrokenLinksExist]* (default: "depends on user settings")
*  user settings: "Show message in page module if broken links exist on page"
   *[tx_brofix_showPageCalloutBrokenLinksExist]* in tab "Broken links" (default: on),
   only available if extension configuration showPageCalloutBrokenLinksExist is
   set to "depends on user settings".

The information is **only** displayed if extension configuration is set to "Always"
or "depends on user settings" and the user settings is active.
Additionally, page_callouts mubst be installed.

Migration
=========

No migration necessary. It might make sense to inform the BE users about this.

