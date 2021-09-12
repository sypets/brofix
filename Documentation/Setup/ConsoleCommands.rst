.. include:: /Includes.txt

================
Console commands
================

The commands will use the settings from the TSconfig configuration.

You can run the console command from the command line (or cron) or from
the scheduler, e.g. "Execute console commands", "brofix:checklinks".

.. contents::
   :local:
   :depth: 1

.. _console-command-checkLinks:

brofix:checkLinks
=================

You can use this command to do a full (or partial) check. It is recommended
to regularly run a full check to keep the broken link records up to date.

If invoked without the parameter -p (or --start-pages=) to use as start pages,
the command automatically reads the site configuration and performs a separate
check for each site.

Some of the options override the configuration, most of the options are optional.

Show help:

.. code-block:: shell

   vendor/bin/typo3 brofix:checklinks -h

Dry-run:
   Show what would be performed without doing the link checking and email sending.
   This can be used to check if the configuration is complete.

.. code-block:: shell

   vendor/bin/typo3 brofix:checklinks --dry-run

Example output:

.. code-block:: shell

   Use page ids (from site configuration): 102,1

   Start checking page 102
   =======================

   Configuration: Send mail: true
   Configuration: Email recipients: user@example.org
   Configuration: Email sender (email address): user@example.org
   Configuration: Email sender (name): Sybille
   Configuration: Email template: CheckLinksResults
   Checking start page "second site" [102], depth: infinite
   Dry run is enabled: Do not check and do not send email.

   Start checking page 1
   =====================

   Configuration: Send mail: true
   Configuration: Email recipients: user@example.org
   Configuration: Email sender (email address): user@example.org
   Configuration: Email sender (name): Sybille
   Configuration: Email replyTo (email address): user@example.org
   Configuration: Email replyTo (name): Sybille
   Configuration: Email template: CheckLinksResults
   Checking start page "Congratulations" [1], depth: infinite
   Dry run is enabled: Do not check and do not send email.

Basic usage:
   Perform link checking.

.. code-block:: shell

   vendor/bin/typo3 brofix:checklinks

Execute link checking, send an email to `webmaster@example.org`:

.. code-block:: shell

   vendor/bin/typo3 brofix:checklinks --to webmaster@example.org

.. _console-command-checkLinksInc:

brofix:checkLinksInc
====================

Perform incremental link checking. This will only check records which have
changed since the last check or within a specific timeframe.

.. code-block:: shell

   vendor/bin/typo3 brofix:checklinksInc --time-interval='-1 minute'

This will check all records changed in the last minute. If the command
ran before, the time of the last execution is used (from cache). If this
is longer than 24 hours, 24 hours is used as time interval.

.. important::

   This can be used to make sure the broken link records are up to date -
   additionally to the full checking with
:ref:`brofix:checkLinks <console-command-checkLinks>`.
