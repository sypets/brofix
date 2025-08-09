<?php

declare(strict_types=1);

namespace Sypets\Brofix\Tests\Functional;

use Sypets\Brofix\Configuration\Configuration;
use Sypets\Brofix\LinkAnalyzer;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

class LinkAnalyzerTest extends AbstractFunctional
{
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
    }

    /**
     * @param int $uid
     * @param non-empty-string $fixtureFile
     * @param string $groupFixtureFile
     */
    protected function setupBackendUserAndGroup(int $uid, string $fixtureFile, string $groupFixtureFile = ''): void
    {
        if ($groupFixtureFile) {
            $this->importCSVDataSet($groupFixtureFile);
        }
        $this->backendUserFixture = $fixtureFile;
        $this->setUpBackendUserFromFixture($uid);
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
     * @dataProvider findAllBrokenLinksDataProvider
     *
     * @param non-empty-string $inputFile
     * @param array<string|int> $pidList
     * @param string $expectedOutputFile
     * @throws \TYPO3\TestingFramework\Core\Exception
     */
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
            'Test with one broken link in pages.url and doktype=1. Expected: no broken links' =>
                [
                    __DIR__ . '/Fixtures/input_page_with_broken_link_in_url_doktype_1.csv',
                    $pidList1,
                    __DIR__ . '/Fixtures/expected_output_none.csv'
                ],
            'Test with one broken link in pages.url and doktype=3. Expected: 1 broken link' =>
                [
                    __DIR__ . '/Fixtures/input_page_with_broken_link_in_url_doktype_3.csv',
                    $pidList1,
                    __DIR__ . '/Fixtures/expected_output_page_with_broken_link_url.csv'
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
        $this->importCSVDataSet($inputFile);
        $this->configuration->setLinkTypes($linkTypes);
        $this->configuration->setSearchFields($searchFields);
        $linkAnalyzer = $this->initializeLinkAnalyzer($pidList);
        $linkAnalyzer->generateBrokenLinkRecords($this->request, $linkTypes);

        // assert
        $this->assertCSVDataSet($expectedOutputFile);
    }
}
