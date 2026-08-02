<?php

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$typo3Version = GeneralUtility::makeInstance(Typo3Version::class);
// directly access extension configuration so class does not need to be initialized
$showPageCalloutBrokenLinksExist = (int)($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['showPageCalloutBrokenLinksExist'] ?? 1);
if ($showPageCalloutBrokenLinksExist === 1 && $typo3Version->getMajorVersion() >= 14) {
    /**
     * @see https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/14.2/Deprecation-108843-ExtensionManagementUtilityAddFieldsToUserSettings.html
     */
    $lll = 'LLL:EXT:brofix/Resources/Private/Language/locallang_be_usersettings.xlf';
    ExtensionManagementUtility::addUserSetting(
        'tx_brofix_showPageCalloutBrokenLinksExist',
        [
            'label' => $lll . ':usersettings.pagemodule.showPageCalloutBrokenLinksExist',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => '1',
            ],
        ],
        //'after:emailMeAtLogin'
    );
}
