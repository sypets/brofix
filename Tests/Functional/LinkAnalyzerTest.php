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

use Sypets\Brofix\LinkAnalyzer;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class LinkAnalyzerTest extends AbstractFunctional
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['LANG'] = $this->getContainer()->get(LanguageServiceFactory::class)->create('default');
    }

    /**
     * @param array<string|int> $pidList
     * @return LinkAnalyzer
     */
    protected function initializeLinkAnalyzer(array $pidList): LinkAnalyzer
    {
        $linkAnalyzer = GeneralUtility::makeInstance(LinkAnalyzer::class);
        // @extensionScannerIgnoreLine
        $linkAnalyzer->init($pidList, $this->configuration);
        return $linkAnalyzer;
    }

    /**
     * @return array<string,mixed[]>
     */
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
     * @dataProvider findAllBrokenLinksDataProvider
     *
     * @param non-empty-string $inputFile
     * @param array<string|int> $pidList
     * @param string $expectedOutputFile
     * @throws \TYPO3\TestingFramework\Core\Exception
     */
    public function testGenerateBrokenLinkRecordsFindAllBrokenLinks(string $inputFile, array $pidList, string $expectedOutputFile): void
    {
        // setup
        $this->importDataSet($inputFile);
        $linkAnalyzer = $this->initializeLinkAnalyzer($pidList);
        $linkAnalyzer->generateBrokenLinkRecords($this->configuration->getLinkTypes());

        // assert
        $this->assertCSVDataSet($expectedOutputFile);
    }

    /**
     * @return array<string,mixed[]>
     */
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
     * @dataProvider findFindOnlyFileBrokenLinksDataProvider
     *
     * @param non-empty-string $inputFile
     * @param array<string|int> $pidList
     * @param string $expectedOutputFile
     * @throws \TYPO3\TestingFramework\Core\Exception
     */
    public function testGetLinkStatisticsFindOnlyFileBrokenLinks(string $inputFile, array $pidList, string $expectedOutputFile): void
    {
        $linkTypes = ['file'];

        // setup
        $this->importDataSet($inputFile);
        $this->configuration->setLinkTypes($linkTypes);
        $linkAnalyzer = $this->initializeLinkAnalyzer($pidList);
        $linkAnalyzer->generateBrokenLinkRecords($this->configuration->getLinkTypes());

        // assert
        $this->assertCSVDataSet($expectedOutputFile);
    }

    /**
     * @return array<string,mixed[]>
     */
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
     * @dataProvider findFindOnlyPageBrokenLinksDataProvider
     *
     * @param non-empty-string $inputFile
     * @param array<string|int> $pidList
     * @param string $expectedOutputFile
     *
     * @throws \TYPO3\TestingFramework\Core\Exception
     */
    public function testGetLinkStatisticsFindOnlyPageBrokenLinks(string $inputFile, array $pidList, string $expectedOutputFile): void
    {
        $linkTypes = ['db'];

        // setup
        $this->importDataSet($inputFile);
        $this->configuration->setLinkTypes($linkTypes);
        $linkAnalyzer = $this->initializeLinkAnalyzer($pidList);
        $linkAnalyzer->generateBrokenLinkRecords( $this->configuration->getLinkTypes());

        // assert
        $this->assertCSVDataSet($expectedOutputFile);
    }

    /**
     * @return array<string,mixed[]>
     */
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
     * @dataProvider findFindOnlyExternalBrokenLinksDataProvider
     *
     * @param non-empty-string $inputFile
     * @param array<string|int> $pidList
     * @param string $expectedOutputFile
     * @throws \TYPO3\TestingFramework\Core\Exception
     */
    public function testGetLinkStatisticsFindOnlyExternalBrokenLinksInBodytext(string $inputFile, array $pidList, string $expectedOutputFile): void
    {
        $linkTypes = ['external'];

        // setup
        $this->importDataSet($inputFile);
        $this->configuration->setLinkTypes($linkTypes);
        $linkAnalyzer = $this->initializeLinkAnalyzer($pidList);
        $linkAnalyzer->generateBrokenLinkRecords( $this->configuration->getLinkTypes());

        // assert
        $this->assertCSVDataSet($expectedOutputFile);
    }

    /**
     * @return array<string,mixed[]>
     */
    public function getLinkStatisticsDoNotDetectCorrectLinksAsBrokenDataProvider(): array
    {
        $pidList1 = [1];

        return [
            'Test with one not broken page link' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_not_broken_page_link.xml',
                    $pidList1,
                    'EXT:brofix/Tests/Functional/Fixtures/expected_output_content_with_not_broken_page_link.csv'
                ],
            'Test with one not broken page link with anchor' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_not_broken_page_link_with_anchor.xml',
                    $pidList1,
                    'EXT:brofix/Tests/Functional/Fixtures/expected_output_content_with_not_broken_page_link.csv'
                ],
            'Test with one not broken page link with anchor in header_link' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_not_broken_page_link_with_anchor_in_header_link.xml',
                    $pidList1,
                    'EXT:brofix/Tests/Functional/Fixtures/expected_output_content_with_not_broken_page_link.csv'
                ],
        ];
    }

    /**
     * @dataProvider getLinkStatisticsDoNotDetectCorrectLinksAsBrokenDataProvider
     *
     * @param non-empty-string $inputFile
     * @param array<string|int> $pidList
     * @param string $expectedOutputFile
     * @throws \TYPO3\TestingFramework\Core\Exception
     */
    public function testGetLinkStatisticsDoNotDetectCorrectLinksAsBroken(string $inputFile, array $pidList, string $expectedOutputFile): void
    {
        // setup
        $this->importDataSet($inputFile);
        $linkAnalyzer = $this->initializeLinkAnalyzer($pidList);
        $linkAnalyzer->generateBrokenLinkRecords( $this->configuration->getLinkTypes());

        // assert
        $this->assertCSVDataSet($expectedOutputFile);
    }

    /**
     * @return array<string,mixed[]>
     */
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
     * @dataProvider checkContentByTypeDataProvider
     *
     * @param non-empty-string $inputFile
     * @param array<string|int> $pidList
     * @param string $expectedOutputFile
     *
     * @throws \TYPO3\TestingFramework\Core\Exception
     */
    public function testGetLinkStatisticsCheckOnlyContentByType(string $inputFile, array $pidList, string $expectedOutputFile): void
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
        $this->configuration->setLinkTypes($linkTypes);
        $this->configuration->setSearchFields($searchFields);
        $linkAnalyzer = $this->initializeLinkAnalyzer($pidList);
        $linkAnalyzer->generateBrokenLinkRecords( $linkTypes);

        // assert
        $this->assertCSVDataSet($expectedOutputFile);
    }
}
