.. include:: /Includes.txt

.. _glossary:

========
Glossary
========

.. _glossaryFalsePositives:

false positives
===============

These are URL which were falsely detected as broken. They are valid
URLs which Broken Link Fixer detects as broken.

.. _glossaryOnTheFlyChecking:

on-the-fly checking
===================

"On-the-fly" checking means almost immediate link checking as soon as
the record is saved. This is in contrast to periodic link checking via
the console command.

.. _glossaryStaleLinks:

stale links
===========

These are links, where the broken link status is "stale", meaning it may be
outdated. For example the broken link is still shown in the list
while the record has already been updated and the broken link fixed.

.. _glossaryLinkSource:

link source
===========

You can think of a link as a connection between 2 points, the link source
and the link target.

The **link source**, is where the link is defined, e.g. in the text of
a content element.


.. code-block:: text

   Link Source ----->  Link target

Understanding link source and link target can be helpful to understand
how Broken Link Fixer works. Some things affect the link target (the URL),
such as link target excluding.

link target
===========

The **link target** is where the link points to. This is usually an URL,
such as `http://example.org/example`. It can also be a page or a file.
