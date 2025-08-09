<?php

declare(strict_types=1);

namespace Sypets\Brofix\Tests\Functional\Repository;

use Sypets\Brofix\Repository\PagesRepository;
use Sypets\Brofix\Tests\Functional\AbstractFunctional;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PagesRepositoryTest extends AbstractFunctional
{
    /**
     * @return \Generator<string,array<mixed>>
     */
    public static function getPageListReturnsCorrectPagesDataProvider(): \Generator
    {
        yield 'normal page, depth=0' => [
                // input pages
                __DIR__ . '/Fixtures/input_pages.csv',
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
            __DIR__ . '/Fixtures/input_pages.csv',
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
            __DIR__ . '/Fixtures/input_pages.csv',
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
            __DIR__ . '/Fixtures/input_pages.csv',
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
            __DIR__ . '/Fixtures/input_pages_hidden.csv',
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
            __DIR__ . '/Fixtures/input_pages_hidden_extend_to_subpages.csv',
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
            __DIR__ . '/Fixtures/input_pages_doktypes.csv',
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
     * @dataProvider getPageListReturnsCorrectPagesDataProvider()
     */
    public function testGetPageListReturnsCorrectPages(
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

        $this->importCSVDataSet($fixture);
        $pagesRepository = GeneralUtility::makeInstance(PagesRepository::class);
        $results = [];
        $pagesRepository->getPageList(
            $results,
            [$startPage],
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
