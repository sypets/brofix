<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Broken link fixer',
    'description' => '',
    'category' => 'module',
    'author' => 'Sybille Peters',
    'author_email' => 'sypets@gmx.de',
    'author_company' => '',
    'state' => 'beta',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'version' => '2.0.0-dev',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.99-10.9.99',
            'info' => '10.4.99-10.9.99',
            'page_callouts' => '1.0.0-1.90.99'
        ],
        'conflicts' => [],
        'suggests' => [

        ],
    ],
];
