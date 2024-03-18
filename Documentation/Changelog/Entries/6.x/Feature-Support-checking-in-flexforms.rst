.. include:: /Includes.rst.txt

=======================================
Feature - Support checking in Flexforms
=======================================

**This feature can be used and has been tested, but should be considered
experimental until further notice!**

Since Flexforms consist of nested fields, checking these kind of fields needed
modified functionality. It is now possible to also check Flexforms for
broken links.

Implementation
==============

Which Flexform fields are visible is determined by the fields defined in the
Flexform XML schema, just as is the case for other fields. When the values
are written to the database field (e.g. tt_content.pi_flexform), this may
include older fields which will no longer be displayed. However, if we get
the schema from the processed TCA, we process only the fields which would be
displayed in the Backend.

How do we determine which fields should be checked and how?

We use the type (and other TCA configuration in the Flexform schema) and only
parse fields which have a type which might include links, e.g.

*  if the "softref" field is set, we get the list of softref parsers from this
   field
*  if "enableRichtext" is set (but softref not), we use the "typolink_tag" parser
   key
*  type "link" and type "input" with "renderType" "inputLink" use the "typolink"
   softref parser key
*  more field types (such as "file") will be supported in the future

Using this new feature
======================

#. Add your Flexform fields to the search fields, for example:

   .. code-block:: typoscript

      mod.brofix.searchFields.tt_content = bodytext,header_link,records,pi_flexform

#. Check your fields in your Flexform configuration, to make sure, you are
   using field configuration which will be checked by brofix (see the
   "Implementation" section), such as type ":ref:`link <t3tca:columns-link>"
   (since TYPO3 v12) and set the correct ":ref:`softref <t3tca:tca_property_softref>".

#. Check your links

Caveats
=======

This new feature comes with some caveats:

#. It is not possible to edit the field with the broken link directly: When
   clicking the edit button in the broken link list, an edit dialog is opened
   for all fields in the flexform. For non-Flexform fields, the edit dialog
   will show only the affected field. The advantage of showing only the affected
   field is that it is easier to find the broken link, especially in non-RTE
   fields where the broken link is not highlighted. (The reason for this caveat
   is that it is not possible currently with core functionality using the
   record_edit route.)

#. It is not possible to specify directly which fields in the Flexform will
   be checked. The fields which are checked is derived directly form the field
   configuration (type, renderType, enableRichtext and softref).

Some of these caveats may be addressed in future releases.

Combination with extensions
===========================

dce
---

This new feature was tested with EXT:dce, but a problem is found. If patch
from the PR is applied, it should work:

*  issue: https://github.com/a-r-m-i-n/dce/issues/102
*  PR: https://github.com/a-r-m-i-n/dce/pull/103
