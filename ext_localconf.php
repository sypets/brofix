<?php

defined('TYPO3_MODE') or die();

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig(
    "@import 'EXT:brofix/Configuration/TsConfig/Page/pagetsconfig.tsconfig'"
);

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\Sypets\Brofix\Task\ValidatorTask::class] = [
    'extension' => 'brofix',
    'title' => 'LLL:EXT:brofix/Resources/Private/Language/locallang.xlf:tasks.validate.name',
    'description' => 'LLL:EXT:brofix/Resources/Private/Language/locallang.xlf:tasks.validate.description',
    'additionalFields' => \Sypets\Brofix\Task\ValidatorTaskAdditionalFieldProvider::class
];

if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks'])) {
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks'] = [];
}

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks']['db'] = \Sypets\Brofix\Linktype\InternalLinktype::class;
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks']['file'] = \Sypets\Brofix\Linktype\FileLinktype::class;
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks']['external'] = \Sypets\Brofix\Linktype\ExternalLinktype::class;

// XCLASS: Use only in v9, v10 is using EventListener for BrokenLinkAnalysisEvent
if (((int)(\TYPO3\CMS\Core\Utility\GeneralUtility::intExplode('.', \TYPO3\CMS\Core\Utility\VersionNumberUtility::getCurrentTypo3Version())[0])) < 10) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][TYPO3\CMS\Core\Html\RteHtmlParser::class] = [
        'className' => Sypets\Brofix\Xclass\RteHtmlParserWithBrokenLinkHook::class
    ];
}

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
    \TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsProcessShowitem::class => [
        'depends' => [
            \TYPO3\CMS\Backend\Form\FormDataProvider\TcaInlineExpandCollapseState::class,
            \TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsProcessPlaceholders::class
        ],
    ],
    \TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsRemoveUnused::class => [
        'depends' => [
            \TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsProcessCommon::class,
            \TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsProcessShowitem::class,
        ],
    ],
];

// -----
// hooks
// -----
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
