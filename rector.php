<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\PostRector\Rector\NameImportingPostRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\ValueObject\PhpVersion;
use Ssch\TYPO3Rector\CodeQuality\General\ConvertImplicitVariablesToExplicitGlobalsRector;
use Ssch\TYPO3Rector\CodeQuality\General\ExtEmConfRector;
use Ssch\TYPO3Rector\Configuration\Typo3Option;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;
use Ssch\TYPO3Rector\Set\Typo3SetList;

/**
 * After update rector/rector or ssch/typo3-rector, possibly initialize this file again, in order to get updated code:
 * php .Build/bin/typo3-init
 * @see https://github.com/sabbelasichon/typo3-rector
 *
 * require-dev:
 * - ssch/typo3-rector
 *
 * Currently checks for complicance with TYPO3 v12
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/Build',
        __DIR__ . '/Classes',
        __DIR__ . '/Configuration',
        __DIR__ . '/Tests',
        __DIR__ . '/ext_emconf.php',
        __DIR__ . '/ext_localconf.php',
        __DIR__ . '/ext_tables.php',
    ])
    // uncomment to reach your current PHP version
    // ->withPhpSets()
    ->withPhpVersion(PhpVersion::PHP_81)
    ->withSets([
        Typo3SetList::CODE_QUALITY,
        Typo3SetList::GENERAL,
        Typo3LevelSetList::UP_TO_TYPO3_12,
        // To migrate to Doctrine Dbal 4, uncomment the following line
        //\Rector\Doctrine\Set\DoctrineSetList::DOCTRINE_DBAL_40,
    ])
    // To have a better analysis from PHPStan, we teach it here some more things
    ->withPHPStanConfigs([Typo3Option::PHPSTAN_FOR_RECTOR_PATH])
    ->withRules([
        AddVoidReturnTypeWhereNoReturnRector::class,
        ConvertImplicitVariablesToExplicitGlobalsRector::class,
    ])
    ->withConfiguredRule(ExtEmConfRector::class, [
        // TYPO3 v12: PHP 8.1-8.4
        ExtEmConfRector::PHP_VERSION_CONSTRAINT => '8.1.0-8.4.99',
        ExtEmConfRector::TYPO3_VERSION_CONSTRAINT => '12.4.0-12.4.99',
        ExtEmConfRector::ADDITIONAL_VALUES_TO_BE_REMOVED => [],
    ])
    // If you use withImportNames(), you should consider excluding some TYPO3 files.
    ->withSkip([
        // @see https://github.com/sabbelasichon/typo3-rector/issues/2536
        __DIR__ . '/**/Configuration/ExtensionBuilder/*',
        NameImportingPostRector::class => [
            'ext_localconf.php', // This line can be removed since TYPO3 11.4, see https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/11.4/Important-94280-MoveContentsOfExtPhpIntoLocalScopes.html
            'ext_tables.php', // This line can be removed since TYPO3 11.4, see https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/11.4/Important-94280-MoveContentsOfExtPhpIntoLocalScopes.html
            'ClassAliasMap.php',
        ]
    ])
;
