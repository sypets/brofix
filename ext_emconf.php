<?php

/** @phpstan-ignore-next-line */
$EM_CONF[$_EXTKEY] = [
    'title' => 'Broken link fixer',
    'description' => 'Check for broken links and fix them via a backend module (forked from core linkvalidator)',
    'category' => 'module',
    'author' => 'Sybille Peters',
    'author_email' => 'sypets@gmx.de',
    'author_company' => '',
    'state' => 'stable',
    'version' => '6.5.5-dev',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-12.4.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'page_callouts' => '3.0.0-3.9.99'
        ],
    ],
];
