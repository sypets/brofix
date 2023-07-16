.. include:: /Includes.rst.txt

.. _commands:

========
Commands
========

.. _command_checklinks:

checklinks
==========

This will use the settings from the TSconfig configuration.
If no start pages are supplied as arguments, all start pages
that have a site configuration are used.

You can run the console command from the command line (or cron) or configure
it in the scheduler ("Execute console commands > brofix:checklinks").

.. code-block:: shell

   # Use -h to show all parameters:
   vendor/bin/typo3 brofix:checklinks -h


Do not execute link checking, just show what configuration is used:

.. code-block:: shell

    vendor/bin/typo3 brofix:checklinks --dry-run

If everything is already configured, you don't need any arguments:

.. code-block:: shell

    vendor/bin/typo3 brofix:checklinks

Execute link checking, send an email to `webmaster@example.org`:

.. code-block:: shell

    vendor/bin/typo3 brofix:checklinks --to webmaster@example.org

