.. include:: /Includes.txt


.. _configurationQuickstart:

==========
Quickstart
==========

.. rst-class:: bignums-xxl

#. Editor configuration

   Give your backend users  / user groups permission to the "Info" module. You may want to disable access
   to the Log and TSconfig, see
   :ref:`mod.web_info.menu.function <tsconfig:pageblindingfunctionmenuoptions-webinfo>`.

   Give backend users / user groups permission to the table :sql:`tx_brofix_exclude_link_target`, if they should
   be able to add URLs to the list of URLs not to be checked. (This requires a certain
   amount of prudence and understanding, otherwise this feature may be misused.)

   In this case, you must also set TSconfig :ref:`tsConfigExcludeLinkTargetStoragePid`
   to a page of type system folder, to which the editors have access.

#. Setup a scheduler task "Broken link fixer"

   Setup a task for each site in your installation. Use depth "infinite" and specify an email
   for the report. Either set the email settings in "Overwrite TSconfig" or in the page TSconfig
   (see next step).

   .. code-block:: typoscript

      mod.brofix.mail.fromname = TYPO3 Administrator
      mod.brofix.mail.fromemail = sybille.peters@uol.de
      mod.brofix.mail.replytoname = Sybille
      mod.brofix.mail.replytoemail = sybille.peters@uol.de
      mod.brofix.mail.subject = TYPO3 Broken Link Fixer report - uolminimal10

#. Set page tsconfig

   It is recommended to set this in your site package and have it apply to the entire
   installation. Alternatively, set it in the page TSconfig of the start page of each
   site. See :ref:`tsconfig-example` for an example.






