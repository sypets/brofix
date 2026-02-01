.. include:: /Includes.rst.txt

.. _changelog:

=========
Changelog
=========

.. important::

   Since version 2.3.0 and higher, we list only the important changes here
   (specifically breaking changes).
   For more changes, please see the respective
   `release notes <https://github.com/sypets/brofix/releases>`__ and
   `commit messages <https://github.com/sypets/brofix/commits/main/>`__
   in the GitHub repository: https://github.com/sypets/brofix

7.0
===

.. important::

   When updating to version >= 7.0.0, you should perform DB schema updates
   and execute upgrade wizard *brofix_copyPidToPageid*!

.. toctree::
   :glob:
   :titlesonly:

   Entries/7.0/*


6.2
===

.. toctree::
   :glob:
   :titlesonly:

   Entries/6.2/*

6.0.0-6.1.x
===========

.. toctree::
   :glob:
   :titlesonly:

   Entries/6.0.0-6.1.x/*

3.2.0
=====

-  Remove page layout action button (due to inconsistencies in usability):
   https://github.com/sypets/brofix/pull/200

-  Add new icon

3.1.0
=====

-  pages with some pages types (e.g. 'recycler' or 'mount point') will not be
   checked. This is configurable, see the configuration options in TSconfig:
   :ref:`tsconfigDoNotCheckContentOnPagesDoktypes`,
   :ref:`tsconfigDoNotCheckPagesDoktypes`
   :ref:`tsconfigDoNotTraversePagesDoktypes`

3.0.0
=====

-  [BREAKING] Move the module from the Info module to its own module. This
   requires changes in the editor configuration: Give the editors permission to
   the "brofix" module.


2.3.0
=====

* Rename branch master => main
* Add module for "Manage Exclusions"

.. note::

   Older, more detailed changes.


2.2.0
=====

**Update to 2.2.0 requires updating the database.**

*  Add support for TYPO3 v11
*  Add crdate to table. This will later make it possible to detect
   new broken links (or broken links recently detected).
*  Add start and stop time to check links email report
*  Change order of settings in check links email report
*  Add additional setting mod.brofix.mail.language to set the
   language of the email report.
*  Do not check records of default language if l18n_cfg is 1 or 3
   ("Hide default language of page")
*  Also consider if records should be checked on page if rechecking
   URL or fields.
*  Optimize external link checking: Do not use extra headers
   Accept-Language and Accept-Encoding by default. This causes problems with
   some websites.
*  Optimize pagination: Do not show pagination controls if there is
   only one page

2.1.1
=====

*  Fix setting of depth=0 via CLI command brofix:checklinks
   (issue:`69 <https://github.com/sypets/brofix/issues/69>`__)
*  Fix fatal error: Exception was thrown on CLI command checklinks if
   replytoemail was set (due to call to not existing function).
   (issue:`66 <https://github.com/sypets/brofix/issues/66>`__)
*  Fix version constraints (in ext_emconf.php)

2.1.0
=====

**!!! Change in SQL:** It is required to do database compare and recheck links.

-  UI optimization: use card layout instead of table for small screens

-  Add editable restrictions: Show only broken links the editor has
   access to.


2.0.2
=====

-  "Check links" button is always available for admins, but deactivated for
   editors by default
-  Change styling for "Last check" field if information is considered "fresh".

2.0.1
=====

-  add "freshness" / "stale" information in "Last check" column in broken
   link report
-  add "Last check" time for the URL as well. Because of the "link target"
   cache, the "last check" information for the field and the URL may differ.
-  Add "Check links" button to report

2.0.0
=====

- Support for TYPO3 9 was dropped
- bugfix: Do not use FlashMessages in DataHandler hook
- several improvements in broken link list
- It is now possible to recheck URLs from the GUI via a button.
  This is configurable (report.recheckButton).
- It is checked if record was edited after last check. In that case the
  broken link information may be "stale" (outdated). This is shown in the
  list along with the time of the last check.
- The last check time of the URLs are shown as well. Since the check status
  is cached, this may differ from the time when the record was last checked.
  While this may be confusing (to show different values), it makes the behaviour
  more transparent.

1.0.4
=====

- Add --send-email and --dry-run option to command controller
- Add more output to command controller
- Fix formatting of floats in email

> 1.0.0
=======

- bug fixing

1.0.0
=====

*Supports TYPO3 9 and 10*

*  Initial version, change extension key to "brofix" (Broken Link Fixer)

GUI: page module

*  Shows message in page module, if broken links on page (depends on ext:page_callouts to add hook)

GUI: RTE

*  Links to hidden or deleted CE are also marked as broken as RTE (as they are also reported as
   broken by brofix)

GUI: broken link report

*  List was decluttered:

   *  Only page title is displayed (not full page path)
   *  short localized error messages are used.
   *  Show a short date format for "last checked" (only hours and minutes, not the date if timestamp is today)

*  Sorting: It is now possible to sort by page / element, content type, URL or error type
*  "Check links" tab was removed
*  All links types are always displayed, no need to check checkboxes
*  In the report, the broken links of the just edited record are displayed in a different color. This makes
   it easier to keep track if jumping back and forth from the edit form to the list of broken links.
*  Show an informational message if no page is selected in the page tree.
*  Reload list immediately, if form was changed. Since there is currently only a select list, it
   does not make sense to have to click a button additionally to changing the value in the select field.

Link checking

*  Use console command instead of scheduler task
*  Previously, all records in the broken links table (of currently to be checked pages) were
   removed at the beginning of the check. This resulted in inconsistent results, especially
   during checks which took longer than just a few minutes. Now, the records are not removed,
   but the existing records are updated (if they already exist) or are inserted (if new). This
   way, the link check results are mostly up-to-date.
*  Crawl delay: A minimum wait time between 2 checks of URLs of the same domain. The crawl delay
   is not used in on-the-fly checking.
*  Link target "cache": External URLs are now stored in a persistent "cache" table. The duration
   (expiration time) is configurable: Thus, on-the-fly checking is faster because the cache is used.
*  Exclude link targets: For the still existing problem of false positives, it is possible to exclude
   URLs (or domains) from link checking. Excluded URLs are treated as valid URLs. URLs can be excluded
   by clicking a button in the link list and then editing the record. Permissions for editors are
   restricted and must explicitly be granted.
*  A timestamp of the last check is added to the broken links table and obsolete records (e.g. belonging
   to a removed page) are removed at the end of the link checking.
*  Links in fields, which are not editable, are no longer checked. Previously, the fields which were configured to
   be checked were always checked, independently of the content type (CType) or page type (doktype).
   However, for some types, content is not relevant such as bodytext for plugins or the URL for normal
   page types. Furthermore, it is not possible to edit these as editor and the editor would get an
   error message. These broken links stayed in the list of broken links and there was no way to remove
   them. Now, only editable fields are checked.
*  Do not check records if in hidden gridelement.


Email report

*  Shows additional statistics, such as number of pages checked, number of links checked, percentage of
   broken links to number of checked links. Especially the percentage of broken links to total number of
   links can be used as an indicator for the "site health".
*  The number of broken links is added to the subject of the email. This way, it is not necessary to click
   on each email to see the most relevant numbers.

Development

*  Setup unit and functional tests (see Build directory)
*  Added .editorconfig
