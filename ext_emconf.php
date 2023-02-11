<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Broken link fixer',
    'description' => 'Check for broken links and fix them via a backend module (forked from core linkvalidator)',
    'category' => 'module',
    'author' => 'Sybille Peters',
    'author_email' => 'sypets@gmx.de',
    'author_company' => '',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'version' => '4.2.6-dev',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.10-11.9.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'page_callouts' => '3.0.0-3.9.99'
        ],
    ],
];
