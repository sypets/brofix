<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:brofix/Resources/Private/Language/Module/locallang_db.xlf:tx_brofix_exclude_link_target',
        'descriptionColumn' => 'notes',
        'label' => 'linktarget',
        'prependAtCopy' => '',
        'hideAtCopy' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'editlock' => 'editlock',
        'type' => 'link_type',
        'typeicon_classes' => [
            'default' => 'mimetypes-x-exclude-link-target'
        ],
        'useColumnsForDefaultValues' => 'link_type',
        'default_sortby' => 'ORDER BY link_type,linktarget',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        // -1 : allow on root page (pid=0) and elsewhere
        'rootLevel' => -1,
        'searchFields' => 'link_type,linktarget'
    ],
    'columns' => [
        'hidden' => [
            'label' => 'LLL:EXT:brofix/Resources/Private/Language/Module/locallang_db.xlf:tx_brofix_exclude_link_target.disabled',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ]
        ],
        'link_type' => [
            'exclude' => false,
            'label' => 'LLL:EXT:brofix/Resources/Private/Language/Module/locallang_db.xlf:tx_brofix_exclude_link_target.link_type',
            'config' => [
                'type' => 'select',
                'default' => 'external',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'LLL:EXT:brofix/Resources/Private/Language/Module/locallang_db.xlf:tx_brofix_exclude_link_target.link_type.external', 'value' => 'external'],
                    ['label' => 'LLL:EXT:brofix/Resources/Private/Language/Module/locallang_db.xlf:tx_brofix_exclude_link_target.link_type.db', 'value' => 'db'],
                    ['label' => 'LLL:EXT:brofix/Resources/Private/Language/Module/locallang_db.xlf:tx_brofix_exclude_link_target.link_type.file', 'value' => 'file'],
                ],
                'fieldWizard' => [
                    'selectIcons' => [
                        'disabled' => false,
                    ],
                ],
                'size' => 1,
                'maxitems' => 1,
            ]
        ],
        'match' => [
            'exclude' => false,
            'label' => 'LLL:EXT:brofix/Resources/Private/Language/Module/locallang_db.xlf:tx_brofix_exclude_link_target.match_by',
            'config' => [
                'type' => 'select',
                'default' => 'exact',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'LLL:EXT:brofix/Resources/Private/Language/Module/locallang_db.xlf:tx_brofix_exclude_link_target.match_by.exact', 'value' => 'exact'],
                    ['label' => 'LLL:EXT:brofix/Resources/Private/Language/Module/locallang_db.xlf:tx_brofix_exclude_link_target.match_by.domain', 'value' => 'domain'],
                ],
                'fieldWizard' => [
                    'selectIcons' => [
                        'disabled' => false,
                    ],
                ],
                'size' => 1,
                'maxitems' => 1,
            ]
        ],
        'linktarget' => [
            'exclude' => false,
            'label' => 'LLL:EXT:brofix/Resources/Private/Language/Module/locallang_db.xlf:tx_brofix_exclude_link_target.link_target',
            'config' => [
                'type' => 'input',
                'placeholder' => 'LLL:EXT:brofix/Resources/Private/Language/Module/locallang_db.xlf:tx_brofix_exclude_link_target.link_target.placeholder',
                'cols' => 30,
                'rows' => 5,
                'eval' => 'trim,' . \Sypets\Brofix\FormEngine\CustomEvaluation\ExcludeLinkTargetsLinkTargetEvaluation::class,
                'required' => true,
            ]
        ],
        'editlock' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:editlock',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
                'items' => [
                    [
                        'label' => '0',
                    ]
                ]
            ]
        ],
        'reason' => [
            'label' => 'LLL:EXT:brofix/Resources/Private/Language/Module/locallang_db.xlf:tx_brofix_exclude_link_target.reason',
            'config' => [
                'type' => 'select',
                'default' => 0,
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => 'LLL:EXT:brofix/Resources/Private/Language/Module/locallang_db.xlf:tx_brofix_exclude_link_target.reason.none',
                        'value' => 0
                    ],
                    [
                        'label' => 'LLL:EXT:brofix/Resources/Private/Language/Module/locallang_db.xlf:tx_brofix_exclude_link_target.reason.noBrokenLink',
                        'value' => 1
                    ]
                ],
            ]
        ],
        'notes' => [
            'label' => 'LLL:EXT:brofix/Resources/Private/Language/Module/locallang_db.xlf:tx_brofix_exclude_link_target.notes',
            'config' => [
                'type' => 'text',
                'default' => '',
                'rows' => 2,
                'cols' => 40,
                'max' => 80,
                'placeholder' => 'LLL:EXT:brofix/Resources/Private/Language/Module/locallang_db.xlf:tx_brofix_exclude_link_target.notes.placeholder',
            ]
        ],
    ],
    'types' => [
        // default
        '0' => [
            'showitem' => 'link_type,linktarget,match,reason,notes,hidden,editlock'
        ],
    ],
    'palettes' => []
];
