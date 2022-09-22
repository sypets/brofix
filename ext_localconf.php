<?php

defined('TYPO3_MODE') or die();

(function () {
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig(
        "@import 'EXT:brofix/Configuration/TsConfig/Page/pagetsconfig.tsconfig'"
    );

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'][901])) {
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'][901] = 'EXT:brofix/Resources/Private/Templates/Email';
    }

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['MAIL']['partialRootPaths'][901])) {
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['partialRootPaths'][901] = 'EXT:brofix/Resources/Private/Partials';
    }

    if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks'] ?? false)) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks'] = [];
    }

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks']['db'])) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks']['db'] = \Sypets\Brofix\Linktype\InternalLinktype::class;
    }
    if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks']['file'])) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks']['file'] = \Sypets\Brofix\Linktype\FileLinktype::class;
    }
    if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks']['external'])) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks']['external'] = \Sypets\Brofix\Linktype\ExternalLinktype::class;
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

    // icons

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
