<?php

use Sypets\Brofix\FormEngine\CustomEvaluation\ExcludeLinkTargetsLinkTargetEvaluation;
use TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsProcessCommon;
use TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsProcessRecordTitle;
use TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsProcessShowitem;
use TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsRemoveUnused;

defined('TYPO3') or die();

(function () {
    // ------------------
    // load page TSconfig
    // ------------------

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig(
        "@import 'EXT:brofix/Configuration/TsConfig/Page/pagetsconfig.tsconfig'"
    );

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
    ];

    if (isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['tcaDatabaseRecord'])) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['brofixFieldShouldBeChecked'] =
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['tcaDatabaseRecord'];

        // todo: find better solution
        // hack: remove TcaColumnsProcessRecordTitle from provider list because this might write additional fields
        //  to the list columnsToProcess and processedTcaColumns which should not be displayed in the BE
        if (isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['brofixFieldShouldBeChecked'][TcaColumnsProcessRecordTitle::class])) {
            unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['brofixFieldShouldBeChecked'][TcaColumnsProcessRecordTitle::class]);
        }

        // remove TcaText
        // it might call brofixFieldShouldBeChecked
        if (isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['brofixFieldShouldBeChecked'][TcaText::class])) {
            unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['brofixFieldShouldBeChecked'][TcaText::class]);
        }
    } else {
        // legacy: worked but did not include flexform and some other providers
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
    }

    // -----
    // hooks
    // -----

    // form input evaluation
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tce']['formevals'][ExcludeLinkTargetsLinkTargetEvaluation::class] = '';

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['Sypets/PageCallouts/Xclass/PageLayoutControllerWithCallouts']['addFlashMessageToPageModule'][] =
        \Sypets\Brofix\Hooks\PageCalloutsHook::class;

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['brofix'] = \Sypets\Brofix\Hooks\DataHandlerHook::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['brofix'] = \Sypets\Brofix\Hooks\DataHandlerHook::class;

    \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class)
        ->registerIcon(
            'mimetypes-x-exclude-link-target',
            \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
            ['source' => 'EXT:brofix/Resources/Public/Icons/mimetypes-x-exclude-link-target.svg']
        );

    // -----
    // icons
    // -----

    $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
        \TYPO3\CMS\Core\Imaging\IconRegistry::class
    );
    $iconRegistry->registerIcon(
        'view-table-min',
        \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        ['source' => 'EXT:brofix/Resources/Public/Icons/view-table-min.svg']
    );
    $iconRegistry->registerIcon(
        'view-table-complex',
        \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        ['source' => 'EXT:brofix/Resources/Public/Icons/view-table-complex.svg']
    );
})();
