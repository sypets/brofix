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
use Sypets\Brofix\Tests\Functional\AbstractFunctional;

class ExcludeLinkTargetTest extends AbstractFunctional
{
    /**
     * @return array<string,mixed[]>
     */
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
     * @dataProvider isExcludedDataProvider
     *
     * @param non-empty-string $inputFile
     * @param non-empty-string $url
     * @param non-empty-string $linkType
     * @param bool $expectedResult
     */
    public function testIsExcludedChecksUrlIsExcluded(string $inputFile, string $url, string $linkType, bool $expectedResult): void
    {
        // setup
        $this->importDataSet($inputFile);
        $excludeLinkTarget = new ExcludeLinkTarget();
        $excludeLinkTarget->setExcludeLinkTargetsPid($this->configuration->getExcludeLinkTargetStoragePid());
        $result = $excludeLinkTarget->isExcluded($url, $linkType);

        // assert
        self::assertEquals($expectedResult, $result);
    }
}
