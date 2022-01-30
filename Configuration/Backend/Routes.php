<?php

declare(strict_types=1);

/**
 * Definitions for routes provided by EXT:backend
 * Contains Route to Export Lists of Excluded Links
 */
return [
    //Backend Route link To Export Excluded Links
    'export-excluded_links' => [
        'path' => '/export-excluded_links',
        'referrer' => 'required,refresh-empty',
        'target' =>  \Sypets\Brofix\Controller\ManageExclusionsController::class . '::exportExcludedLinks'
    ],
];
