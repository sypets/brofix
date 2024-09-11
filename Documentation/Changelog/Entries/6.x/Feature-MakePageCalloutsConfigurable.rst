.. include:: /Includes.rst.txt

===========================================
Feature - Make page callouts configurable
===========================================

*since verion 6.1.0*

If `EXT:page_callouts <https://extensions.typo3.org/extension/page_callouts>`__
is installed, information is displayed in the page module, if broken links exists.

Since this has a small performance impact, is not really necessary if broken
links are fixed regularly etc., this is now configurable via:

*  extension configuration: "Show message in page module if broken links exist on page" *[showPageCalloutBrokenLinksExist]* (default: on)
*  user settings: "Show message in page module if broken links exist on page"
   *[tx_brofix_showPageCalloutBrokenLinksExist]* in tab "Broken links" (default: on)

The information is **only** displayed if extension configuration is set to true,
the user settings is active and page_callouts is installed (and of course, if
broken links exist on that page).

Migration
=========

No migration necessary. It might make sense to inform the BE users about this.

