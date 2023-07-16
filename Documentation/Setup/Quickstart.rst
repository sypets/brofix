.. include:: /Includes.rst.txt

.. _setupQuickstart:

================
Setup quickstart
================


.. rst-class:: bignums-xxl


#. Setup :ref:`minimalConfig`

   Also see the :ref:`configurationReference`, for more configuration options

#. Check mail sending

   If an email should be sent on every link check performed via the console
   command, it is a good idea to check if email sending is setup correctly
   and works. (Sending a mail is optional).

   Go to Environment > Test Mail Setup

#. Setup the console command :ref:`brofix:checkLinks <command_checklinks>`


.. _minimalConfig:

Minimal configuration
=====================

.. _tsconfigMinimal:

Page TSconfig
-------------

.. code-block:: typoscript

   # email recipients
   mod.brofix.mail.recipients = recipient@example.org

   # Add contact information here, such as an email address or a URL which contains an email address
   mod.brofix.linktypesConfig.external.headers.User-Agent =  Mozilla/5.0 (compatible; Site link checker; +https://gratesturff.com/imprint.html)

   # pid of a page of type folder - this is where the exclude link target
   # records are stored
   mod.brofix.excludeLinkTarget.storagePid = 20

