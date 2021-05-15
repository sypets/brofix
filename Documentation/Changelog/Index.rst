.. include:: /Includes.txt

.. _changelog:

=========
Changelog
=========

2.0.0
=====

- Support for TYPO3 9 was dropped
- bugfix: Do not use FlashMessages in DataHandler hook
- several improvements in broken link list, including behaviour
  of rechecking URLs and displaying information about last check


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
