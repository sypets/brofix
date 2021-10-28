<?php

declare(strict_types=1);

/**
 * Definitions for routes provided by EXT:backend
 * Contains Route to Delete the Selected Exluded Link
 */
return [
    // Delete Exclude Link
    'delete_excluded_links' => [
        'path' => '/deletelinks',
        'target' =>  \Sypets\Brofix\View\ManageExclusions::class . '::deleteExcludedLinks'
    ],
];
