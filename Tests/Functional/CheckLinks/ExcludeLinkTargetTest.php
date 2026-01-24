<?php

declare(strict_types=1);

namespace Sypets\Brofix\Tests\Functional\CheckLinks;

use Sypets\Brofix\CheckLinks\ExcludeLinkTarget;
use Sypets\Brofix\Tests\Functional\AbstractFunctional;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class ExcludeLinkTargetTest extends AbstractFunctional
{
    protected ExcludeLinkTarget $excludeLinkTarget;

    protected function setUp(): void
    {
        parent::setUp();
        $this->excludeLinkTarget = GeneralUtility::makeInstance(ExcludeLinkTarget::class);
    }

    /**
     * @return array<string,array<mixed>>
     */
    public static function isExcludedDataProvider(): array
    {
        return [
            'URL should be excluded' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_excluded.csv',
                    // URL is excluded
                    'http://localhost/isexcluded',
                    'external',
                    true
                ],
            'URL should not be excluded' =>
                [
                    __DIR__ . '/Fixtures/input_content_with_broken_link_excluded.csv',
                    // domain is not excluded
                    'http://localhost/isnotexcluded/',
                    'external',
                    false
                ],
        ];
    }

    /**
     * @dataProvider isExcludedDataProvider
     *
     * @param non-empty-string $inputFile
     * @param non-empty-string $url
     * @param non-empty-string $linkType
     * @param bool $expectedResult
     */
    public function testIsExcludedChecksUrlIsExcluded(string $inputFile, string $url, string $linkType, bool $expectedResult): void
    {
        /**
         * @todo deprecated  importDataSet Will be removed with core v12 compatible testing-framework. Importing database fixtures based on XML format is discouraged. Switch to CSV format instead.
         * Use method importCSVDataSet() to import such fixture files and assertCSVDataSet() to compare database state with fixture files.
         */
        $this->importCSVDataSet($inputFile);
        $excludeLinkTarget = $this->excludeLinkTarget;
        $excludeLinkTarget->setExcludeLinkTargetsPid($this->configuration->getExcludeLinkTargetStoragePid());
        $result = $excludeLinkTarget->isExcluded($url, $linkType);

        // assert
        self::assertEquals($expectedResult, $result);
    }
}
