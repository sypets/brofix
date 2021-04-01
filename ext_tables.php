<?php

defined('TYPO3_MODE') or die();

// Add module
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::insertModuleFunction(
    'web_info',
    \Sypets\Brofix\View\BrofixReport::class,
    null,
    'LLL:EXT:brofix/Resources/Private/Language/locallang.xlf:mod_brofix'
);

// Initialize Context Sensitive Help (CSH)
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr(
    'brofix',
    'EXT:brofix/Resources/Private/Language/Module/locallang_csh.xlf'
);
