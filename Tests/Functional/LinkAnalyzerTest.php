<?php

declare(strict_types=1);

namespace Sypets\Brofix\Tests\Functional;

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

use Sypets\Brofix\Configuration\Configuration;
use Sypets\Brofix\LinkAnalyzer;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class LinkAnalyzerTest extends FunctionalTestCase
{
    protected $coreExtensionsToLoad = [
        'backend',
        'fluid',
        'info',
        'install',
        'scheduler'
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

    protected function initializeLinkAnalyzer(array $pidList): LinkAnalyzer
    {
        $linkAnalyzer = new LinkAnalyzer();
        $linkAnalyzer->init(
            $this->configuration->getSearchFields(),
            $pidList,
            $this->configuration->getTsConfig()
        );
        return $linkAnalyzer;
    }

    public function findAllBrokenLinksDataProvider(): array
    {
        $pidList1 = [1];

        return [
            'Test with one broken external link (not existing domain)' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_external.xml',
                    $pidList1,
                    'EXT:brofix/Tests/Functional/Fixtures/expected_output_content_with_broken_link_external.csv'
                ],
            'Test with one broken page link (not existing page)' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_page.xml',
                    $pidList1,
                    'EXT:brofix/Tests/Functional/Fixtures/expected_output_content_with_broken_link_page.csv'
                ],
            'Test with one broken file link (not existing file)' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_file.xml',
                    $pidList1,
                    'EXT:brofix/Tests/Functional/Fixtures/expected_output_content_with_broken_link_file.csv'
                ],
            'Test with several broken external, page and file links' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_links_several.xml',
                    $pidList1,
                    'EXT:brofix/Tests/Functional/Fixtures/expected_output_content_with_broken_links_several.csv'
                ],
            'Test with several pages with broken external, page and file links' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_links_several_pages.xml',
                    [1, 2],
                    'EXT:brofix/Tests/Functional/Fixtures/expected_output_content_with_broken_links_several_pages.csv'
                ],
        ];
    }

    /**
     * @test
     * @dataProvider findAllBrokenLinksDataProvider
     */
    public function generateBrokenLinkRecordsFindAllBrokenLinks(string $inputFile, array $pidList, string $expectedOutputFile)
    {
        // setup
        $this->initializeConfiguration();
        $this->importDataSet($inputFile);
        $linkAnalyzer = $this->initializeLinkAnalyzer($pidList);
        $linkAnalyzer->generateBrokenLinkRecords($this->configuration->getLinkTypes());

        // assert
        $this->assertCSVDataSet($expectedOutputFile);
    }

    public function findFindOnlyFileBrokenLinksDataProvider(): array
    {
        $pidList1 = [1];

        return [
            // Tests with one broken link
            'Test with one broken external link' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_external.xml',
                    $pidList1,
                    'EXT:brofix/Tests/Functional/Fixtures/expected_output_none.csv'
                ],
            'Test with one broken page link' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_page.xml',
                    $pidList1,
                    'EXT:brofix/Tests/Functional/Fixtures/expected_output_none.csv'
                ],
            'Test with one broken file link' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_file.xml',
                    $pidList1,
                    'EXT:brofix/Tests/Functional/Fixtures/expected_output_content_with_broken_link_file.csv'
                ],
        ];
    }

    /**
     * @test
     * @dataProvider findFindOnlyFileBrokenLinksDataProvider
     */
    public function getLinkStatisticsFindOnlyFileBrokenLinks(string $inputFile, array $pidList, string $expectedOutputFile)
    {
        $linkTypes = ['file'];

        // setup
        $this->importDataSet($inputFile);
        $this->initializeConfiguration($linkTypes);
        $linkAnalyzer = $this->initializeLinkAnalyzer($pidList);
        $linkAnalyzer->generateBrokenLinkRecords($this->configuration->getLinkTypes());

        // assert
        $this->assertCSVDataSet($expectedOutputFile);
    }

    public function findFindOnlyPageBrokenLinksDataProvider(): array
    {
        $pidList1 = [1];

        return [
            // Tests with one broken link
            'Test with one broken external link' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_external.xml',
                    $pidList1,
                    'EXT:brofix/Tests/Functional/Fixtures/expected_output_none.csv'
                ],
            'Test with one broken page link' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_page.xml',
                    $pidList1,
                    'EXT:brofix/Tests/Functional/Fixtures/expected_output_content_with_broken_link_page.csv'
                ],
            'Test with one broken file link' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_file.xml',
                    $pidList1,
                    'EXT:brofix/Tests/Functional/Fixtures/expected_output_none.csv'
                ],
        ];
    }

    /**
     * @test
     * @dataProvider findFindOnlyPageBrokenLinksDataProvider
     */
    public function getLinkStatisticsFindOnlyPageBrokenLinks(string $inputFile, array $pidList, string $expectedOutputFile)
    {
        $linkTypes = ['db'];

        // setup
        $this->importDataSet($inputFile);
        $this->initializeConfiguration($linkTypes);
        $linkAnalyzer = $this->initializeLinkAnalyzer($pidList);
        $linkAnalyzer->generateBrokenLinkRecords($this->configuration->getLinkTypes());

        // assert
        $this->assertCSVDataSet($expectedOutputFile);
    }

    public function findFindOnlyExternalBrokenLinksDataProvider(): array
    {
        $pidList1 = [1];

        return [
            // Tests with one broken link
            'Test with one broken external link' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_external.xml',
                    $pidList1,
                    'EXT:brofix/Tests/Functional/Fixtures/expected_output_content_with_broken_link_external.csv'
                ],
            'Test with one broken page link' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_page.xml',
                    $pidList1,
                    'EXT:brofix/Tests/Functional/Fixtures/expected_output_none.csv'
                ],
            'Test with one broken file link' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_file.xml',
                    $pidList1,
                    'EXT:brofix/Tests/Functional/Fixtures/expected_output_none.csv'
                ],
        ];
    }

    /**
     * @test
     * @dataProvider findFindOnlyExternalBrokenLinksDataProvider
     */
    public function getLinkStatisticsFindOnlyExternalBrokenLinksInBodytext(string $inputFile, array $pidList, string $expectedOutputFile)
    {
        $linkTypes = ['external'];

        // setup
        $this->importDataSet($inputFile);
        $this->initializeConfiguration($linkTypes);
        $linkAnalyzer = $this->initializeLinkAnalyzer($pidList);
        $linkAnalyzer->generateBrokenLinkRecords($this->configuration->getLinkTypes());

        // assert
        $this->assertCSVDataSet($expectedOutputFile);
    }

    public function checkContentByTypeDataProvider(): array
    {
        $pidList1 = [1];

        return [
            'Test with one broken link in tt_content.bodytext and CType=header. Expected: no broken links' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_in_bodytext_type_header.xml',
                    $pidList1,
                    'EXT:brofix/Tests/Functional/Fixtures/expected_output_none.csv'
                ],
            'Test with one broken link in pages.url and doktype=1. Expected: no broken links' =>
                [
                    __DIR__ . '/Fixtures/input_page_with_broken_link_in_url_doktype_1.xml',
                    $pidList1,
                    'EXT:brofix/Tests/Functional/Fixtures/expected_output_none.csv'
                ],
            'Test with one broken link in pages.url and doktype=3. Expected: 1 broken link' =>
                [
                    __DIR__ . '/Fixtures/input_page_with_broken_link_in_url_doktype_3.xml',
                    $pidList1,
                    'EXT:brofix/Tests/Functional/Fixtures/expected_output_page_with_broken_link_url.csv'
                ],
        ];
    }

    /**
     * @test
     * @dataProvider checkContentByTypeDataProvider
     */
    public function getLinkStatisticsCheckOnlyContentByType(string $inputFile, array $pidList, string $expectedOutputFile)
    {
        $searchFields = [
            'tt_content' => [
                'bodytext'
            ],
            'pages' => [
                'url'
            ]
        ];

        $linkTypes = [
            'external'
        ];

        // setup
        $this->importDataSet($inputFile);
        $this->initializeConfiguration($linkTypes, $searchFields);
        $linkAnalyzer = $this->initializeLinkAnalyzer($pidList);
        $linkAnalyzer->generateBrokenLinkRecords($linkTypes);

        // assert
        $this->assertCSVDataSet($expectedOutputFile);
    }
}
