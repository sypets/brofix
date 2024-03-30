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

Options
-------

Not all options are described here!

.. _command_checklinks_send-email:

-e / --send-email
~~~~~~~~~~~~~~~~~

Configure whether to send an email when link checking is complete. If "auto"
is used (the default), this does not override the TSconfig setting. The
TSconfig setting uses "always" by default. See
:ref:`TSconfig option mail.sendOnCheckLinks <tsconfigSendOnCheckLinks>`
for description of available values.

Example:

.. code-block:: shell

   # send email only if broken links were found
   vendor/bin/typo3 brofix:checklinks -e any
