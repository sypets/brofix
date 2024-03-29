.. include:: /Includes.rst.txt

=======================================================
Feature - More configuration options for sending emails
=======================================================

There is an option "send-email" in the command / scheduler task which determined
if an email should be sent when the link checking is complete. There are now
more options which also make it possible to send an email only when broken
links were found and also only when new broken links were found.

The old values (0, 1, -1) are still supported and are mapped to the new values.

* "**never**" : never send email (previously: 0)
* "**always**": send email (previously: 1)
* "**any**"   : send email if any broken links were found
* "**new**"   : send email if new broken links were found
* "**auto**"  : do not override, use :ref:`TSconfig mail.sendOnCheckLinks <tsconfigSendOnCheckLinks>`

If "auto" is used, the TSconfig will be used which makes it possible to configure
this for each site individually.

Migration
=========

As the old values will still work, no change is necessary, but it is recommended
to use the new string values instead of the old numeric values.

Info
====

* :ref:`TSconfig mail.sendOnCheckLinks <tsconfigSendOnCheckLinks>`
* :ref:`Command / scheduler option send-email <command_checklinks_send-email>`
