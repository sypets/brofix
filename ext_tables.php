<?php

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') or die();

$typo3Version = GeneralUtility::makeInstance(Typo3Version::class);

if ($typo3Version->getMajorVersion() < 14) {
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
}
