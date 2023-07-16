.. include:: /Includes.rst.txt

===========
Development
===========

This covers extending Broken Link Fixer via an extension.

Examples
========

.. _devOverrideExternalLinktype:

Override ExternalLinktype
-------------------------

We override the ExternalLinktype class to make some changes in how external link
types are checked:

#.  a specific error type is not treated as error
#.  some specific domains are not checked

.. code-block:: php
   :caption: ext_localconf.php

   use Myvendor\MyExtension\Linktype\MyExternalLinktype;

   $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks']['external'] = MyExternalLinktype::class;


.. code-block:: php
   :caption: Classes/Linktype/MyExternalLinktype.php

   <?php

   declare(strict_types=1);
   namespace Myvendor\MyExtension\Linktype;

   use Sypets\Brofix\Linktype\ErrorParams;
   use Sypets\Brofix\Linktype\ExternalLinktype;

   class ExternalUniolLinktype extends ExternalLinktype
   {
         public function checkLink(string $origUrl, array $softRefEntry, int $flags = 0): bool
         {
               // do some checking here, if $origUrl should get checked ..

               $isValidUrl = parent::checkLink($origUrl, $softRefEntry, $this->flags);
               if (!$isValidUrl) {
                  $exceptionMsg = $this->errorParams->getExceptionMsg();
                  // highly probably certificate chain issue, which should be treated as edge case false positive
                  // curl(60): 'SSL certificate problem: unable to get local issuer certificate'
                  if ($exceptionMsg === 'SSL certificate problem: unable to get local issuer certificate') {
                      return true;
                  }
              }
              return $isValidUrl;
         }
   }
