.. include:: /Includes.rst.txt

.. _commands:

========
Commands
========

If using scheduler, select **Task** :guilabel:`Execute console commands` first.

.. _command_checklinks:

brofix:checklinks
=================

*Check for broken links*

This will use the settings from the TSconfig configuration.
If no start pages are supplied as arguments, all start pages
that have a site configuration are used.

Links will be checked based on the configured :ref:`linktypes <tsconfigLinktypes>`
and :ref:`searchFields <tsconfigSearchfields>` if they have supported TCA
configuration.

After completion, an email is sent (if configured, see also :ref:`command_checklinks_send-email`
below) for each site or start page (see also -p option).

You can run the console command from the command line (or cron) or configure
it in the scheduler (**Task:** :guilabel:`Execute console commands`
| **Schedulable Command:** :guilabel:`brofix:checklinks`).

The following examples show the console commands in a Composer installation.

.. code-block:: shell

   # Use -h to show all parameters:
   vendor/bin/typo3 brofix:checklinks -h


Do not execute link checking, just show what configuration is used:

.. code-block:: shell

    vendor/bin/typo3 brofix:checklinks --dry-run

If everything is already configured via TSconfig, you don't need any arguments:

.. code-block:: shell

    vendor/bin/typo3 brofix:checklinks

Execute link checking, send an email to `webmaster@example.org`:

.. code-block:: shell

    vendor/bin/typo3 brofix:checklinks --to webmaster@example.org

Options
-------

**Not all options are necessarily described here!**

.. _command_checklinks_start-pages:

-p / --start-pages
~~~~~~~~~~~~~~~~~~

**default:** Use site configuration

Can be one or more page ids (separated by comma) to use as start pages. If none
is given, the site configuration is used to determine the start pages. Based
on the start pages and depth, the page tree is traversed to gather all pages
on which broken links will be checked (omitting hidden pages and subpages of
hidden pages with extendToSubpages).

In CLI, use several -x options if more than one, e.g -p 1 -p 2 on the command line.
In the scheduler, seperate several with comma.

.. code-block:: shell

   # Use 1 and 123 as start pages
   vendor/bin/typo3 brofix:checklinks -p 1 -p 2

.. _command_checklinks_depth:

-d / --depth
~~~~~~~~~~~~

**default:** uses TSConfig which has a default of 9999 (infinite)

When traversing the page tree, how deep to go. Overrides TSconfig :ref:`depth
<tsconfigDepth>`. If this option is not given, the TSconfig configuration of the
start page Broken Link Fixer is currently checking is used.

.. code-block:: shell

   # Check only the page given, do not traverse page tree
   vendor/bin/typo3 brofix:checklinks -p 1,123 -d 0

.. _command_checklinks_to:

-t / --to
~~~~~~~~~

**default:** Use TSconfig mail.recipients.
If this is also empty (the default), global configuration is used (see
:ref:`mod.brofix.mail.recipients <tsconfigMailRecipients>` in TSconfig).

Email address of recipient.

.. _command_checklinks_send-email:

-e / --send-email
~~~~~~~~~~~~~~~~~

**default:** auto

Configure whether to send an email when link checking is complete.

If "auto"
is used (the default), this does not override the TSconfig setting. Using the
TSconfig setting makes it possible to configure the setting for each site
individually.

Possible values:

* "**never**" : never send email (previously: 0)
* "**always**": send email (previously: 1)
* "**any**"   : send email if any broken links were found
* "**new**"   : send email if new broken links were found
* "**auto**"  : do not override, TSconfig setting (or default) is used

See also :ref:`TSconfig option mail.sendOnCheckLinks <tsconfigSendOnCheckLinks>`
for description of available values. The TSconfig setting uses "always" by
default.

Example:

.. code-block:: shell

   # send email only if broken links were found
   vendor/bin/typo3 brofix:checklinks -e any

.. _command_checklinks_exclude-uids:

-x / --exclude-uids
~~~~~~~~~~~~~~~~~~~

**default:** none

.. important::

   This will only apply to checking with scheduler / cli. If checking in the
   backend, the pages will still be checked which can lead to inconsistent
   results. Use with care!

Make it possible to omit specific page ids and their subpages when checking.

In CLI, use several -x options if more than one, e.g -x1 -x2 on the command line.
In the scheduler, seperate several with comma.

   # Use 1 and 123 as start pages
   vendor/bin/typo3 brofix:checklinks -p 1,123 -x 55 -x 60
