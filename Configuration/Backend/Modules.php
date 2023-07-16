<?php

declare(strict_types=1);
// see https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/12.0/Feature-96733-NewBackendModuleRegistrationAPI.html

use Sypets\Brofix\Controller\BrokenLinkListController;
use Sypets\Brofix\Controller\ManageExclusionsController;

return [
    'web_brofix' => [
        'parent' => 'web',
        'position' => ['after' => 'info'],
        'access' => 'user',
        'workspaces' => 'live',
        'path' => '/module/web/brofix',
        // todo, register icon 'EXT:brofix/Resources/Public/Icons/Extension.svg'
        //'iconIdentifier' => 'module-example',
        'icon' => 'EXT:brofix/Resources/Public/Icons/Extension.svg',
        'navigationComponent' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
        'labels' => [
            'title' => 'LLL:EXT:brofix/Resources/Private/Language/locallang.xlf:mod_brofix',
        ]
    ],
    'web_brofix_broken_links' => [
        'parent' => 'web_brofix',
        'access' => 'user',
        'workspaces' => 'live',
        'path' => '/module/web/brofix/brokenlinks',
        'icon' => 'EXT:brofix/Resources/Public/Icons/Extension.svg',
        //'navigationComponent' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
        'labels' => [
            'title' => 'LLL:EXT:brofix/Resources/Private/Language/locallang.xlf:mod_brofix',
        ],
        'routes' => [
            '_default' => [
                'target' => BrokenLinkListController::class . '::handleRequest',
            ],
        ],

        /*
         *
         * moduleData
         * The allowed module data properties and their default value. Module data are the module specific settings of a backend user.
         * The properties, defined in the registration, can be overwritten in a request via GET or POST. For more information about the usage of this option,
         * see the corresponding changelog.
         *  https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/12.0/Feature-96895-IntroduceModuleDataObject.html
         */
        // It's still possible to store and retrieve arbitrary module data. The definition of moduleData in the module
        // registration only defines, which properties can be overwritten in a request (with GET / POST).
        'moduleData' => [
             'action' => 'report',
             'currentPage' => '0',
             'depth' => 'undefined',
             'orderBy' => 'page',
             'paginationPage' => '1',
             'viewMode' => 'view_table_complex',
             'uid_searchFilter' => '',
             'linktype_searchFilter' => 'all',
             'url_searchFilter' => 'all',
             'url_match_searchFilter' => 'partial',
             'current_record_uid' => '',
             'current_record_table' => '',
             'current_record_field' => '',
             'current_record_currentTime' => '',
             'current_record_url' => '',
             'current_record_linkType' => '',
         ],

    ],
    'web_brofix_manage_exclusions' => [
        'parent' => 'web_brofix',
        'access' => 'user',
        'workspaces' => 'live',
        'path' => '/module/web/brofix/manageexclusions',
        'icon' => 'EXT:brofix/Resources/Public/Icons/Extension.svg',
        'navigationComponent' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
        'labels' => [
            'title' => 'LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:ManageExclusions',
        ],
        'routes' => [
            '_default' => [
                'target' => ManageExclusionsController::class . '::handleRequest',
            ],
        ],
        'moduleData' => [
            'action' => 'report',
            // todo: drop either id or currentPage?
            'currentPage' => '0',
            'orderBy' => 'linktarget',
            'paginationPage' => '1',
            'excludeLinkType_filter' => '',
            'excludeUrl_filter' => '',
            'excludeReason_filter' => '',
        ],
    ]

];
