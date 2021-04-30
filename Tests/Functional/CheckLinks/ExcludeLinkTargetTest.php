<?php

declare(strict_types=1);

namespace Sypets\Brofix\Tests\Functional\CheckLinks;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Sypets\Brofix\CheckLinks\ExcludeLinkTarget;
use Sypets\Brofix\Configuration\Configuration;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class ExcludeLinkTargetTest extends FunctionalTestCase
{
    protected $coreExtensionsToLoad = [
        'backend',
        'fluid',
        'info',
        'install'
    ];

    protected $testExtensionsToLoad = [
        'typo3conf/ext/brofix',
        'typo3conf/ext/page_callouts'
    ];

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * Set up for set up the backend user, initialize the language object
     * and creating the Export instance
     */
    protected function setUp(): void
    {
        parent::setUp();

        Bootstrap::initializeLanguageObject();
    }

    /**
     * @throws \Exception
     */
    protected function initializeConfiguration(array $linkTypes = [], array $searchFields = [])
    {
        $this->configuration = GeneralUtility::makeInstance(Configuration::class);
        // load default values
        $this->configuration->loadPageTsConfig(0);
        $this->configuration->overrideTsConfigByString('mod.brofix.linktypesConfig.external.headers.User-Agent = Mozilla/5.0 (compatible; Broken Link Checker; +https://example.org/imprint.html)');
        $this->configuration->overrideTsConfigByString('mod.brofix.searchFields.pages = media,url');
        if ($linkTypes) {
            $this->configuration->setLinkTypes($linkTypes);
        }
        if ($searchFields) {
            $this->configuration->setSearchFields($searchFields);
        }
    }

    public function isExcludedDataProvider(): array
    {
        return [
            'URL should be excluded' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_excluded.xml',
                    'https://example.org',
                    'external',
                    true
                ],
            'URL should not be excluded' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_excluded.xml',
                    'https://example.com',
                    'external',
                    false
                ],
        ];
    }

    /**
     * @test
     * @dataProvider isExcludedDataProvider
     */
    public function isExcludedChecksUrlIsExcluded(string $inputFile, string $url, string $linkType, bool $expectedResult)
    {
        // setup
        $this->importDataSet($inputFile);
        $this->initializeConfiguration();
        $excludeLinkTarget = new ExcludeLinkTarget();
        $excludeLinkTarget->setExcludeLinkTargetsPid($this->configuration->getExcludeLinkTargetStoragePid());
        $result = $excludeLinkTarget->isExcluded($url, $linkType);

        // assert
        self::assertEquals($expectedResult, $result);
    }
}
