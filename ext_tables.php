<?php

defined('TYPO3_MODE') or die();

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
    'web',
    'brofix',
    'after:web_info',
    '',
    [
        'routeTarget' => \Sypets\Brofix\Controller\BrofixModuleController::class . '::brofixAction',
        'access' => 'group,user',
        'name' => 'web_brofix',
        'icon' => 'EXT:brofix/Resources/Public/Icons/Extension.svg',
        'labels' => 'LLL:EXT:brofix/Resources/Private/Language/Module/locallang_mod.xlf'
    ]
);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::insertModuleFunction(
    'web_brofix',
    \TYPO3\CMS\Tstemplate\Controller\TypoScriptTemplateConstantEditorModuleFunctionController::class,
    '',
    'test: module function'
);

// info module
// todo: remove Add module to info module
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
