<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// BE user settings
// ----------------

$lll = 'LLL:EXT:brofix/Resources/Private/Language/locallang_be_usersettings.xlf';
/**
 * is moved to Configuration/TCA/Overrides/be_users.php for TYPO3 >= v14
 * @todo Remove here when support for TYPO3 v13 is dropped
 */
// directly access extension configuration so class does not need to be initialized
$showPageCalloutBrokenLinksExist = (int)($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['brofix']['showPageCalloutBrokenLinksExist'] ?? 1);
if ($showPageCalloutBrokenLinksExist === 1) {
    // BE user settings
    // ----------------

    $lll = 'LLL:EXT:brofix/Resources/Private/Language/locallang_be_usersettings.xlf';
    // make it possible to turn off page_callouts in page module (this is also influenced by extension configuration showPageCalloutBrokenLinksExist)
    $GLOBALS['TYPO3_USER_SETTINGS']['columns']['tx_brofix_showPageCalloutBrokenLinksExist'] = [
        'label' => $lll . ':usersettings.pagemodule.showPageCalloutBrokenLinksExist',
        'type' => 'check',
        'default' => '1',
    ];
    ExtensionManagementUtility::addFieldsToUserSettings(
        '--div--;' . $lll . ':usersettings.brofix.tab,tx_brofix_showPageCalloutBrokenLinksExist',
    );
}

