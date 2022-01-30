<?php

defined('TYPO3_MODE') or die();

// Add Info module: Broken link list
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::insertModuleFunction(
    'web_info',
    \Sypets\Brofix\Controller\BrokenLinkListController::class,
    null,
    'LLL:EXT:brofix/Resources/Private/Language/locallang.xlf:function.list.header.broken_links'
);

// Add Info module: Manage exclusions
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::insertModuleFunction(
    'web_info',
    \Sypets\Brofix\Controller\ManageExclusionsController::class,
    null,
    'LLL:EXT:brofix/Resources/Private/Language/locallang.xlf:function.list.header.manage_exclusions'
);

// Initialize Context Sensitive Help (CSH)
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr(
    'brofix',
    'EXT:brofix/Resources/Private/Language/Module/locallang_csh.xlf'
);
