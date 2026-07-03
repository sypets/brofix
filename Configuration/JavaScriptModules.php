<?php

return [
    'dependencies' => ['core', 'backend'],
    'imports' => [
        '@sypets/brofix/ManageExclusions.js' => 'EXT:brofix/Resources/Public/JavaScript/Backend/Modules/ManageExclusions.js',
        '@sypets/brofix/Brofix.js' => 'EXT:brofix/Resources/Public/JavaScript/Backend/Modules/Brofix.js',
    ],
];
