<?php

defined('TYPO3') or die();

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
    'web',
    'brofix',
    'after:info',
    '',
    [
        'routeTarget' => \Sypets\Brofix\Controller\BrofixController::class . '::mainAction',
        'access' => 'user,group',
        'name' => 'web_brofix',
        'path' => '/module/page/link-reports',
        'icon' => 'EXT:brofix/Resources/Public/Icons/Extension.svg',
        'labels' => 'LLL:EXT:brofix/Resources/Private/Language/Module/locallang_mod.xlf',
    ]
);

// Add Info module: Broken link list
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::insertModuleFunction(
    'web_brofix',
    \Sypets\Brofix\Controller\BrokenLinkListController::class,
    null,
    'LLL:EXT:brofix/Resources/Private/Language/locallang.xlf:function.list.header.broken_links'
);

// Add Info module: Manage exclusions
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::insertModuleFunction(
    'web_brofix',
    \Sypets\Brofix\Controller\ManageExclusionsController::class,
    null,
    'LLL:EXT:brofix/Resources/Private/Language/locallang.xlf:function.list.header.manage_exclusions'
);

// Initialize Context Sensitive Help (CSH)
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr(
    'brofix',
    'EXT:brofix/Resources/Private/Language/Module/locallang_csh.xlf'
);
