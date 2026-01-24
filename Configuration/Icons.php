<?php

declare(strict_types=1);
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
return [
    'view-table-min' => [
        
        // Icon provider class
        'provider' => \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        
        // The source SVG for the SvgIconProvider
        'source' => 'EXT:brofix/Resources/Public/Icons/view-table-min.svg',
    ],
    'view-table-complex' => [
        
        // Icon provider class
        'provider' => \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        
        // The source SVG for the SvgIconProvider
        'source' => 'EXT:brofix/Resources/Public/Icons/view-table-complex.svg',
    ],
    'mimetypes-x-exclude-link-target' => [
        'provider' => \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        'source' => 'EXT:brofix/Resources/Public/Icons/mimetypes-x-exclude-link-target.svg',
    ],
];
