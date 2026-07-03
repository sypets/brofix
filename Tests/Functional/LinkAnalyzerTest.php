<?php

declare(strict_types=1);

namespace Sypets\Brofix\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Sypets\Brofix\Configuration\Configuration;
use Sypets\Brofix\LinkAnalyzer;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

class LinkAnalyzerTest extends AbstractFunctional
{
    protected $typo3Version;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['LANG'] = $this->getContainer()->get(LanguageServiceFactory::class)->create('default');
        $this->setupBackendUserAndGroup(
            3,
            __DIR__ . '/Repository/Fixtures/be_users.csv',
            __DIR__ . '/Repository/Fixtures/be_groups.csv'
        );
        //Bootstrap::initializeLanguageObject();
        self::initializeLanguageObject();
        $this->typo3Version = $this->get(Typo3Version::class);
    }

    /**
     * @param array<string|int> $pidList
     * @return LinkAnalyzer
     */
    protected function initializeLinkAnalyzer(array $pidList, ?Configuration $configuration = null): LinkAnalyzer
    {
        $linkAnalyzer = $this->get(LinkAnalyzer::class);
        if (!$configuration) {
            $configuration = $this->configuration;
        }
        // @extensionScannerIgnoreLine
        $linkAnalyzer->init($pidList, $this->configuration);
        return $linkAnalyzer;
    }

    /**
     * @return array<string,mixed[]>
     */
    public static function findAllBrokenLinksDataProvider(): array
    {
        $pidList1 = [1];

        return [
            'Test with one broken external link (not existing domain)' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_external.csv',
                    $pidList1,
                    __DIR__ . '/Fixtures/expected_output_content_with_broken_link_external.csv'
                ],
            'Test with one broken page link (not existing page)' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_page.csv',
                    $pidList1,
                    __DIR__ . '/Fixtures/expected_output_content_with_broken_link_page.csv'
                ],
            'Test with one broken file link (not existing file)' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_file.csv',
                    $pidList1,
                    __DIR__ . '/Fixtures/expected_output_content_with_broken_link_file.csv'
                ],
            'Test with several broken external, page and file links' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_links_several.csv',
                    $pidList1,
                    __DIR__ . '/Fixtures/expected_output_content_with_broken_links_several.csv'
                ],
            'Test with several pages with broken external, page and file links' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_links_several_pages.csv',
                    [1, 2],
                    __DIR__ . '/Fixtures/expected_output_content_with_broken_links_several_pages.csv'
                ],
        ];
    }

    /**
     * @param non-empty-string $inputFile
     * @param array<string|int> $pidList
     * @param string $expectedOutputFile
     * @throws \TYPO3\TestingFramework\Core\Exception
     */
    #[DataProvider('findAllBrokenLinksDataProvider')]
    #[Test]
    public function testGenerateBrokenLinkRecordsFindAllBrokenLinks(string $inputFile, array $pidList, string $expectedOutputFile): void
    {
        $linkTypes = ['db', 'file', 'external'];

        // setup
        $this->importCSVDataSet($inputFile);
        $linkAnalyzer = $this->initializeLinkAnalyzer($pidList);

        $linkAnalyzer->generateBrokenLinkRecords($this->request, $linkTypes);

        // assert
        $this->assertCSVDataSet($expectedOutputFile);
    }

    /**
     * @return array<string,mixed[]>
     */
    public static function findFindOnlyFileBrokenLinksDataProvider(): array
    {
        $pidList1 = [1];

        return [
            // Tests with one broken link
            'Test with one broken external link' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_external.csv',
                    $pidList1,
                    __DIR__ . '/Fixtures/expected_output_none.csv'
                ],
            'Test with one broken page link' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_page.csv',
                    $pidList1,
                    __DIR__ . '/Fixtures/expected_output_none.csv'
                ],
            'Test with one broken file link' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_file.csv',
                    $pidList1,
                    __DIR__ . '/Fixtures/expected_output_content_with_broken_link_file.csv'
                ],
        ];
    }

    /**
     * @param non-empty-string $inputFile
     * @param array<string|int> $pidList
     * @param string $expectedOutputFile
     * @throws \TYPO3\TestingFramework\Core\Exception
     */
    #[DataProvider('findFindOnlyFileBrokenLinksDataProvider')]
    #[Test]
    public function testGetLinkStatisticsFindOnlyFileBrokenLinks(string $inputFile, array $pidList, string $expectedOutputFile): void
    {
        $linkTypes = ['file'];

        // setup
        $this->importCSVDataSet($inputFile);
        $this->configuration->setLinkTypes($linkTypes);
        $linkAnalyzer = $this->initializeLinkAnalyzer($pidList);
        $linkAnalyzer->generateBrokenLinkRecords($this->request, $this->configuration->getLinkTypes());

        // assert
        $this->assertCSVDataSet($expectedOutputFile);
    }

    /**
     * @return array<string,mixed[]>
     */
    public static function findFindOnlyPageBrokenLinksDataProvider(): array
    {
        $pidList1 = [1];

        return [
            // Tests with one broken link
            'Test with one broken external link' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_external.csv',
                    $pidList1,
                    __DIR__ . '/Fixtures/expected_output_none.csv'
                ],
            'Test with one broken page link' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_page.csv',
                    $pidList1,
                    __DIR__ . '/Fixtures/expected_output_content_with_broken_link_page.csv'
                ],
            'Test with one broken file link' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_file.csv',
                    $pidList1,
                    __DIR__ . '/Fixtures/expected_output_none.csv'
                ],
        ];
    }

    /**
     * @param non-empty-string $inputFile
     * @param array<string|int> $pidList
     * @param string $expectedOutputFile
     *
     * @throws \TYPO3\TestingFramework\Core\Exception
     */
    #[DataProvider('findFindOnlyPageBrokenLinksDataProvider')]
    #[Test]
    public function testGetLinkStatisticsFindOnlyPageBrokenLinks(string $inputFile, array $pidList, string $expectedOutputFile): void
    {
        $linkTypes = ['db'];

        // setup
        $this->importCSVDataSet($inputFile);
        $this->configuration->setLinkTypes($linkTypes);
        $linkAnalyzer = $this->initializeLinkAnalyzer($pidList);
        $linkAnalyzer->generateBrokenLinkRecords($this->request, $this->configuration->getLinkTypes());

        // assert
        $this->assertCSVDataSet($expectedOutputFile);
    }

    /**
     * @return array<string,mixed[]>
     */
    public static function findFindOnlyExternalBrokenLinksDataProvider(): array
    {
        $pidList1 = [1];

        return [
            // Tests with one broken link
            'Test with one broken external link' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_external.csv',
                    $pidList1,
                    __DIR__ . '/Fixtures/expected_output_content_with_broken_link_external.csv'
                ],
            'Test with one broken page link' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_page.csv',
                    $pidList1,
                    __DIR__ . '/Fixtures/expected_output_none.csv'
                ],
            'Test with one broken file link' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_file.csv',
                    $pidList1,
                    __DIR__ . '/Fixtures/expected_output_none.csv'
                ],
        ];
    }

    /**
     * @param non-empty-string $inputFile
     * @param array<string|int> $pidList
     * @param string $expectedOutputFile
     * @throws \TYPO3\TestingFramework\Core\Exception
     */
    #[DataProvider('findFindOnlyExternalBrokenLinksDataProvider')]
    #[Test]
    public function testGetLinkStatisticsFindOnlyExternalBrokenLinksInBodytext(string $inputFile, array $pidList, string $expectedOutputFile): void
    {
        $linkTypes = ['external'];

        // setup
        $this->importCSVDataSet($inputFile);
        $this->configuration->setLinkTypes($linkTypes);
        $linkAnalyzer = $this->initializeLinkAnalyzer($pidList);
        $linkAnalyzer->generateBrokenLinkRecords($this->request, $this->configuration->getLinkTypes());

        // assert
        $this->assertCSVDataSet($expectedOutputFile);
    }

    /**
     * @return array<string,mixed[]>
     */
    public static function getLinkStatisticsDoNotDetectCorrectLinksAsBrokenDataProvider(): array
    {
        $pidList1 = [1];

        return [
            'Test with one not broken page link' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_not_broken_page_link.csv',
                    $pidList1,
                    __DIR__ . '/Fixtures/expected_output_content_with_not_broken_page_link.csv'
                ],
            'Test with one not broken page link with anchor' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_not_broken_page_link_with_anchor.csv',
                    $pidList1,
                    __DIR__ . '/Fixtures/expected_output_content_with_not_broken_page_link.csv'
                ],
            'Test with one not broken page link with anchor in header_link' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_not_broken_page_link_with_anchor_in_header_link.csv',
                    $pidList1,
                    __DIR__ . '/Fixtures/expected_output_content_with_not_broken_page_link.csv'
                ],
        ];
    }

    /**
     * @param non-empty-string $inputFile
     * @param array<string|int> $pidList
     * @param string $expectedOutputFile
     * @throws \TYPO3\TestingFramework\Core\Exception
     */
    #[DataProvider('getLinkStatisticsDoNotDetectCorrectLinksAsBrokenDataProvider')]
    #[Test]
    public function testGetLinkStatisticsDoNotDetectCorrectLinksAsBroken(string $inputFile, array $pidList, string $expectedOutputFile): void
    {
        // setup
        $this->importCSVDataSet($inputFile);
        $configuration = $this->configuration;
        // make sure no DB entries are created for not broken links
        $configuration->setShowAllLinks(false);
        $linkAnalyzer = $this->initializeLinkAnalyzer($pidList, $configuration);
        $linkAnalyzer->generateBrokenLinkRecords($this->request, $this->configuration->getLinkTypes());

        // assert
        $this->assertCSVDataSet($expectedOutputFile);
    }

    /**
     * @return array<string,mixed[]>
     */
    public static function checkContentByTypeDataProvider(): array
    {
        $pidList1 = [1];

        return [
            'Test with one broken link in tt_content.bodytext and CType=header. Expected: no broken links' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_in_bodytext_type_header.csv',
                    $pidList1,
                    __DIR__ . '/Fixtures/expected_output_none.csv'
                ],
            'Test with one broken link in pages.link and doktype=1. Expected: no broken links' =>
                [
                    __DIR__ . '/Fixtures/input_page_with_broken_link_in_url_doktype_1.csv',
                    $pidList1,
                    __DIR__ . '/Fixtures/expected_output_none.csv'
                ],
            'Test with one broken link in pages.link and doktype=3. Expected: 1 broken link' =>
                [
                    __DIR__ . '/Fixtures/input_page_with_broken_link_in_url_doktype_3.csv',
                    $pidList1,
                    __DIR__ . '/Fixtures/expected_output_page_with_broken_link_url.csv'
                ],
        ];
    }

    /**
     * @param non-empty-string $inputFile
     * @param array<string|int> $pidList
     * @param string $expectedOutputFile
     *
     * @throws \TYPO3\TestingFramework\Core\Exception
     */
    #[DataProvider('checkContentByTypeDataProvider')]
    #[Test]
    public function testGetLinkStatisticsCheckOnlyContentByType(string $inputFile, array $pidList, string $expectedOutputFile): void
    {
        $searchFields = [
            'tt_content' => [
                'bodytext'
            ],
            'pages' => [
                'link'
            ]
        ];

        $linkTypes = [
            'external'
        ];

        /**
         * for version < 14 the field is pages.url, for later versions it is pages.link
         * @see https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/14.0/Breaking-17406-FieldUrlInTablePagesHasBeenRemoved.html
         */
        if ($this->typo3Version->getMajorVersion() < 14) {
            unset($searchFields['pages']['link']);
            $searchFields['pages'][] = 'url';

            if (str_ends_with($inputFile, 'input_page_with_broken_link_in_url_doktype_1.csv')) {
                $inputFile = str_replace('input_page_with_broken_link_in_url_doktype_1.csv', 'input_page_with_broken_link_in_url_doktype_1_v13.csv', $inputFile);
            }
            if (str_ends_with($inputFile, 'input_page_with_broken_link_in_url_doktype_3.csv')) {
                $inputFile = str_replace('input_page_with_broken_link_in_url_doktype_3.csv', 'input_page_with_broken_link_in_url_doktype_3_v13.csv', $inputFile);
            }

            if (str_ends_with($expectedOutputFile, 'expected_output_page_with_broken_link_url.csv')) {
                $expectedOutputFile = str_replace('expected_output_page_with_broken_link_url.csv', 'expected_output_page_with_broken_link_url_v13.csv', $expectedOutputFile);
            }
        }

        // setup
        $this->importCSVDataSet($inputFile);
        $this->configuration->setLinkTypes($linkTypes);
        $this->configuration->setSearchFields($searchFields);
        $linkAnalyzer = $this->initializeLinkAnalyzer($pidList);
        $linkAnalyzer->generateBrokenLinkRecords($this->request, $linkTypes);

        // assert
        $this->assertCSVDataSet($expectedOutputFile);
    }
}
