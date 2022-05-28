<?php

declare(strict_types=1);

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

namespace Sypets\Brofix\Tests\Functional\Repository;

use Sypets\Brofix\Repository\PagesRepository;
use Sypets\Brofix\Tests\Functional\AbstractFunctionalTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PagesRepositoryTest extends AbstractFunctionalTest
{
    /**
     * @return \Generator<string,array<mixed>>
     */
    public function getPageListReturnsCorrectPagesDataProvider(): \Generator
    {
        yield 'normal page, depth=0' => [
                // input pages
                __DIR__ . '/Fixtures/input_pages.xml',
                // start page
                1,
                // depth
                0,
                // permsClause
                '1=1',
                // considerHidden
                false,
                // excluded pages
                [],
                // expected results
                [1 => 1],
        ];

        yield 'normal page, depth=1' => [
            // input pages
            __DIR__ . '/Fixtures/input_pages.xml',
            // start page
            1,
            // depth
            1,
            // permsClause
            '1=1',
            // considerHidden
            false,
            // excluded pages
            [],
            // expected results
            [1 => 1, 7 => 7, 2 => 2],
        ];

        yield 'normal page, depth=2' => [
            // input pages
            __DIR__ . '/Fixtures/input_pages.xml',
            // start page
            1,
            // depth
            2,
            // permsClause
            '1=1',
            // considerHidden
            false,
            // excluded pages
            [],
            // expected results
            [1 => 1, 2 => 2, 7 => 7, 3 => 3],
        ];

        yield 'normal page, depth=999' => [
            // input pages
            __DIR__ . '/Fixtures/input_pages.xml',
            // start page
            1,
            // depth
            999,
            // permsClause
            '1=1',
            // considerHidden
            false,
            // excluded pages
            [],
            // expected results
            [1 => 1, 2 => 2, 7 => 7, 3 => 3, 4=> 4, 5 => 5, 6 => 6],
        ];

        yield 'subpage of hidden should be returned' => [
            // input pages
            __DIR__ . '/Fixtures/input_pages_hidden.xml',
            // start page
            1,
            // depth
            999,
            // permsClause
            '1=1',
            // considerHidden
            false,
            // excluded pages
            [],
            // expected results
            [1 => 1, 3 => 3],
        ];

        yield 'subpage of hidden + extendToSubpages should NOT be returned' => [
            // input pages
            __DIR__ . '/Fixtures/input_pages_hidden_extend_to_subpages.xml',
            // start page
            1,
            // depth
            999,
            // permsClause
            '1=1',
            // considerHidden
            false,
            // excluded pages
            [],
            // expected results
            [1 => 1],
        ];

        yield 'page with doktype=255 and subpages should not be returned' => [
            // input pages
            __DIR__ . '/Fixtures/input_pages_doktypes.xml',
            // start page
            1,
            // depth
            999,
            // permsClause
            '1=1',
            // considerHidden
            false,
            // excluded pages
            [],
            // expected results
            [1 => 1],
        ];
    }

    /**
     * @param non-empty-string $fixture
     * @param int $startPage
     * @param int $depth
     * @param string $permsClause
     * @param bool $considerHidden
     * @param array<int,int> $excludedPages
     * @param array<int,int> $expectedResult
     *
     * @test
     * @dataProvider getPageListReturnsCorrectPagesDataProvider()
     */
    public function getPageListReturnsCorrectPages(
        string $fixture,
        int $startPage,
        int $depth,
        string $permsClause,
        bool $considerHidden,
        array $excludedPages,
        array $expectedResult
    ): void {
        $doNotCheckPageTypes = [
            6 => 6,
            7 => 7,
            199 => 199,
            255 => 255,
        ];
        $doNotTraversePageTypes = [
            6 => 6,
            199 => 199,
            255 => 255,
        ];

        $this->importDataSet($fixture);
        $pagesRepository = GeneralUtility::makeInstance(PagesRepository::class);
        $results = [];
        $pagesRepository->getPageList(
            $results,
            $startPage,
            $depth,
            $permsClause,
            $considerHidden,
            $excludedPages,
            $doNotCheckPageTypes,
            $doNotTraversePageTypes
        );
        self::assertEquals($expectedResult, $results);
    }
}
