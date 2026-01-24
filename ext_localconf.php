<?php

use Sypets\Brofix\FormEngine\CustomEvaluation\ExcludeLinkTargetsLinkTargetEvaluation;
use TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsProcessCommon;
use TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsProcessShowitem;
use TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsRemoveUnused;

defined('TYPO3') or die();

(function () {
    // -----
    // Fluid
    // -----

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'][901])) {
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'][901] = 'EXT:brofix/Resources/Private/Templates/Email';
    }

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['MAIL']['partialRootPaths'][901])) {
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['partialRootPaths'][901] = 'EXT:brofix/Resources/Private/Partials';
    }

    // --------------------
    // Configure link types
    // --------------------

    if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks'] ?? false)) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks'] = [];
    }

    // link types can be replaced with custom linktypes, custom link types added
    // should correspond with Page TSconfig mod.brofix.linktypes

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks']['db'])) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks']['db'] = \Sypets\Brofix\Linktype\InternalLinktype::class;
    }
    if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks']['file'])) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks']['file'] = \Sypets\Brofix\Linktype\FileLinktype::class;
    }
    if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks']['external'])) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks']['external'] = \Sypets\Brofix\Linktype\ExternalLinktype::class;
    }

    // -------------------------------
    // FormEngine: custom formDataGrup
    // -------------------------------
    // used for checking if fields are editable

    // for link checking, do not perform user permission checks, only check if field is editable
    // permission checks are done when reading records from tx_brofix_broken_links for report
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['brofixFieldShouldBeChecked'] = [
        \TYPO3\CMS\Backend\Form\FormDataProvider\InitializeProcessedTca::class => [],
        \TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseEditRow::class => [
            'depends' => [
                \TYPO3\CMS\Backend\Form\FormDataProvider\InitializeProcessedTca::class,
            ]
        ],
        \TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseRowInitializeNew::class => [
            'depends' => [
                \TYPO3\CMS\Backend\Form\FormDataProvider\PageTsConfig::class,
                \TYPO3\CMS\Backend\Form\FormDataProvider\InitializeProcessedTca::class,
            ],
        ],
        \TYPO3\CMS\Backend\Form\FormDataProvider\DatabasePageLanguageOverlayRows::class => [
            'depends' => [
            ],
        ],
        \TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseLanguageRows::class => [
            'depends' => [
                \TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseRowInitializeNew::class,
            ],
        ],
        \TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseRecordTypeValue::class => [
            'depends' => [
                \TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseLanguageRows::class,
            ],
        ],
        \TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsOverrides::class => [
            'depends' => [
                \TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseRecordTypeValue::class,
            ],
        ],

        \TYPO3\CMS\Backend\Form\FormDataProvider\TcaInlineExpandCollapseState::class => [
            'depends' => [
                \TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseEditRow::class,
                \TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsOverrides::class,
            ],
        ],
        TcaColumnsProcessShowitem::class => [
            'depends' => [
                \TYPO3\CMS\Backend\Form\FormDataProvider\TcaInlineExpandCollapseState::class,
                \TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsProcessPlaceholders::class
            ],
        ],
        TcaColumnsRemoveUnused::class => [
            'depends' => [
                TcaColumnsProcessCommon::class,
                TcaColumnsProcessShowitem::class,
            ],
        ],
    ];

    // add some additional data providers so this works with Flexform checking as well
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['brofixFieldShouldBeCheckedWithFlexform'] = [
        \TYPO3\CMS\Backend\Form\FormDataProvider\InitializeProcessedTca::class => [],
        \TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseEditRow::class => [
            'depends' => [
                \TYPO3\CMS\Backend\Form\FormDataProvider\InitializeProcessedTca::class,
            ]
        ],
        \TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseRowInitializeNew::class => [
            'depends' => [
                \TYPO3\CMS\Backend\Form\FormDataProvider\PageTsConfig::class,
                \TYPO3\CMS\Backend\Form\FormDataProvider\InitializeProcessedTca::class,
            ],
        ],
        \TYPO3\CMS\Backend\Form\FormDataProvider\DatabasePageLanguageOverlayRows::class => [
            'depends' => [
            ],
        ],
        \TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseLanguageRows::class => [
            'depends' => [
                \TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseRowInitializeNew::class,
            ],
        ],
        \TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseRecordTypeValue::class => [
            'depends' => [
                \TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseLanguageRows::class,
            ],
        ],
        \TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsOverrides::class => [
            'depends' => [
                \TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseRecordTypeValue::class,
            ],
        ],

        \TYPO3\CMS\Backend\Form\FormDataProvider\TcaInlineExpandCollapseState::class => [
            'depends' => [
                \TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseEditRow::class,
                \TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsOverrides::class,
            ],
        ],
        TcaColumnsProcessShowitem::class => [
            'depends' => [
                \TYPO3\CMS\Backend\Form\FormDataProvider\TcaInlineExpandCollapseState::class,
                \TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsProcessPlaceholders::class
            ],
        ],
        TcaColumnsRemoveUnused::class => [
            'depends' => [
                TcaColumnsProcessCommon::class,
                TcaColumnsProcessShowitem::class,
            ],
        ],
        // additional

        \TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsProcessCommon::class => [
            'depends' => [
                \TYPO3\CMS\Backend\Form\FormDataProvider\TcaInlineExpandCollapseState::class,
            ],
        ],
        \TYPO3\CMS\Backend\Form\FormDataProvider\TcaFlexPrepare::class => [
            'depends' => [
                \TYPO3\CMS\Backend\Form\FormDataProvider\InitializeProcessedTca::class,
                \TYPO3\CMS\Backend\Form\FormDataProvider\UserTsConfig::class,
                \TYPO3\CMS\Backend\Form\FormDataProvider\PageTsConfigMerged::class,
                \TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsRemoveUnused::class,
                \TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsProcessFieldLabels::class,
                \TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsProcessFieldDescriptions::class,
            ],
        ],
        \TYPO3\CMS\Backend\Form\FormDataProvider\TcaFlexProcess::class => [
            'depends' => [
                \TYPO3\CMS\Backend\Form\FormDataProvider\TcaFlexPrepare::class,
            ],
        ],
        \TYPO3\CMS\Backend\Form\FormDataProvider\EvaluateDisplayConditions::class => [
            'depends' => [
                \TYPO3\CMS\Backend\Form\FormDataProvider\TcaRecordTitle::class,
                // should come after TcaFlexProcess
                \TYPO3\CMS\Backend\Form\FormDataProvider\TcaFlexProcess::class
            ],
        ],
    ];

    // -----
    // hooks
    // -----

    // form input evaluation
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tce']['formevals'][ExcludeLinkTargetsLinkTargetEvaluation::class] = '';

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['Sypets/PageCallouts/Xclass/PageLayoutControllerWithCallouts']['addFlashMessageToPageModule'][] =
        \Sypets\Brofix\Hooks\PageCalloutsHook::class;

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['brofix'] = \Sypets\Brofix\Hooks\DataHandlerHook::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['brofix'] = \Sypets\Brofix\Hooks\DataHandlerHook::class;

    // cache
    // -----

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['brofix'] ??= [];
})();
