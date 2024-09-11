<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// Initialize Context Sensitive Help (CSH)
ExtensionManagementUtility::addLLrefForTCAdescr(
    'brofix',
    'EXT:brofix/Resources/Private/Language/Module/locallang_csh.xlf'
);

// BE user settings
// ----------------

$lll = 'LLL:EXT:brofix/Resources/Private/Language/locallang_be_usersettings.xlf';
// make it possible to turn off page_callouts in page module (this is also influenced by extension configuration showPageCalloutBrokenLinksExist)
$GLOBALS['TYPO3_USER_SETTINGS']['columns']['tx_brofix_showPageCalloutBrokenLinksExist'] = [
    'label' => $lll . ':usersettings.pagemodule.showPageCalloutBrokenLinksExist',
    'type' => 'check',
    'default' => '0',
];
ExtensionManagementUtility::addFieldsToUserSettings(
    '--div--;' . $lll . ':usersettings.brofix.tab,tx_brofix_showPageCalloutBrokenLinksExist',
);
