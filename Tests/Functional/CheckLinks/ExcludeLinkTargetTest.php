<?php

declare(strict_types=1);

namespace Sypets\Brofix\Tests\Functional\CheckLinks;

use Sypets\Brofix\CheckLinks\ExcludeLinkTarget;
use Sypets\Brofix\Tests\Functional\AbstractFunctional;

class ExcludeLinkTargetTest extends AbstractFunctional
{
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
        // $this->importDataSet($inputFile);
        $this->importCSVDataSet($inputFile);
        $excludeLinkTarget = new ExcludeLinkTarget();
        $excludeLinkTarget->setExcludeLinkTargetsPid($this->configuration->getExcludeLinkTargetStoragePid());
        $result = $excludeLinkTarget->isExcluded($url, $linkType);

        // assert
        self::assertEquals($expectedResult, $result);
    }
}
