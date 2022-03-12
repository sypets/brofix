.. include:: /Includes.txt

.. _usage_exclude_link_targets:

====================
Exclude link targets
====================

As described in the :ref:`glossary` we differentiate between the link source
and the link target. The link target is the target of a link, it may be an
external URL or a target page in your TYPO3 site.

"Excluded link targets" (or "exclusions") are targets which have been permanently
excluded from link checking. The reason for doing this is usually that brofix
cannot correctly determine the state of the link target and falsely detects
it as broken ("false positive"). Without any action to prevent this, the "false"
broken links would always appear in the broken link list which severely impedes
working with the broken link list as editor.

A solution for this is to permanently add the link targets to the list of
excluded link targets. Links with this link target will no longer be checked
and the broken links already detected will immediately be removed from the
internal list which brofix uses to display broken links.

Excluding link targets
======================

In the module "Check links" the action button |exclude_link_target_action_image|
"Permanently exclude ..." appears for every broken link record.

This is only available for external link targets by default, but can be
configured differently.

As soon as the button is clicked, an edit dialog appears which makes it
possible to select a "reason" and add additional notes.

Once you press save, all broken link records stored in brofix for this link
target will be removed and the broken links will no longer be displayed in
the list of broken links.

.. important::

   It is recommended to check the link targets in the list regularly, as
   these will no longer be checked by brofix.

.. _usage_exclude_link_target_management:

Manage exclusions module
========================

The management of broken links excluded is done in the module "Manage exclusions"
which is a submodule of "Check Links".

This module allows you to list the excluded link targets using the provided
filters.

The following functionality is available:

*  List and filter the excluded link targets
*  Select exclude link targets and delete them
*  Export excluded link targets as CSV file



.. |exclude_link_target_action_image| image:: ../_images/exclude_link_target.png
