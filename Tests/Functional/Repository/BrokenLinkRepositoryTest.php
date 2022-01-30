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

use Sypets\Brofix\Controller\Filter\BrokenLinkListFilter;
use Sypets\Brofix\LinkAnalyzer;
use Sypets\Brofix\Repository\BrokenLinkRepository;
use Sypets\Brofix\Tests\Functional\AbstractFunctionalTest;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BrokenLinkRepositoryTest extends AbstractFunctionalTest
{
    /**
     * @var array<string,array<mixed>>
     */
    protected $beusers = [
        'admin' => [
            'fixture' => 'PACKAGE:typo3/testing-framework/Resources/Core/Functional/Fixtures/be_users.xml',
            'uid' => 1,
            'groupFixture' => ''
        ],
        'no group' => [
            'fixture' => 'EXT:brofix/Tests/Functional/Repository/Fixtures/be_users.xml',
            'uid' => 2,
            'groupFixture' => ''
        ],
        // write access to pages, tt_content
        'group 1' => [
            'fixture' => 'EXT:brofix/Tests/Functional/Repository/Fixtures/be_users.xml',
            'uid' => 3,
            'groupFixture' => 'EXT:brofix/Tests/Functional/Repository/Fixtures/be_groups.xml'
        ],
        // write access to pages, tt_content, exclude field pages.header_link
        'group 2' => [
            'fixture' => 'EXT:brofix/Tests/Functional/Repository/Fixtures/be_users.xml',
            'uid' => 4,
            'groupFixture' => 'EXT:brofix/Tests/Functional/Repository/Fixtures/be_groups.xml'
        ],
        // write access to pages, tt_content (restricted to default language)
        'group 3' => [
            'fixture' => 'EXT:brofix/Tests/Functional/Repository/Fixtures/be_users.xml',
            'uid' => 5,
            'groupFixture' => 'EXT:brofix/Tests/Functional/Repository/Fixtures/be_groups.xml'
        ],
        // group 6: access to all, but restricted via explicit allow to CType=texmedia and text
        'group 6' => [
            'fixture' => 'EXT:brofix/Tests/Functional/Repository/Fixtures/be_users.xml',
            'uid' => 6,
            'groupFixture' => 'EXT:brofix/Tests/Functional/Repository/Fixtures/be_groups.xml'
        ],

    ];

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['TYPO3_CONF_VARS']['BE']['explicitADmode'] = 'explicitAllow';
    }

    /**
     * @return \Generator<string,array<mixed>>
     */
    public function getLinkCountsForPagesAndLinktypesReturnsCorrectCountForUserDataProvider(): \Generator
    {
        yield 'Admin user should see all broken links' =>
        [
            // backendUser: 1=admin
            $this->beusers['admin'],
            // input file for DB
            __DIR__ . '/Fixtures/input.xml',
            //pids
            [1],
            // expected result:
            [
                'db' => 1,
                'file' => 1,
                'external' => 2,
                'total' => 4,
            ]
        ];
        yield 'User with no group should see none' =>
        [
            // backend user
            $this->beusers['no group'],
            // input file for DB
            __DIR__ . '/Fixtures/input.xml',
            //pids
            [1],
            // expected result:
            [
                'total' => 0,
                'db' => 0,
                'file' => 0,
                'external' => 0,

            ]
        ];
        yield 'User with permission to pages but not to specific tables should see none' =>
        [
            // backend user
            $this->beusers['no group'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_2.xml',
            //pids
            [1],
            // expected result:
            [
                'total' => 0,
                'db' => 0,
                'file' => 0,
                'external' => 0,
            ]
        ];
        yield 'User with permission to pages and to specific tables, but no exclude fields should see 3 of 4 broken links' =>
        [
            // backend user
            $this->beusers['group 1'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_3.xml',
            //pids
            [1],
            // expected result:
            [
                'db' => 1,
                'file' => 1,
                'external' => 1,
                'total' => 3
            ]
        ];
        yield 'User with permission to pages, specific tables and exclude fields should see all broken links' =>
        [
            // backend user
            $this->beusers['group 2'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_4.xml',
            //pids
            [1],
            // expected result:
            [
                'db' => 1,
                'file' => 1,
                'external' => 2,
                'total' => 4
            ]
        ];
        yield 'User has write permission only for Ctype textmedia and text, should see only broken links from textmedia records' =>
        [
            // backend user
            $this->beusers['group 6'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_6_explicit_allow.xml',
            //pids
            [1],
            // expected result:
            [
                'external' => 1,
                'db' => 0,
                'file' => 0,
                'total' => 1,
            ]
        ];

        yield 'User has write permission only for default language and should see only 1 of 2 broken links' =>
        [
            // backend user
            $this->beusers['group 3'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_5.xml',
            //pids
            [1],
            // expected result:
            [
                'external' => 1,
                'db' => 0,
                'file' => 0,
                'total' => 1
            ]
        ];
    }

    /**
     * @param array<mixed> $beuser
     * @param string $inputFile
     * @param array<int,int> $pidList
     * @param array<string,int> $expectedOutput
     * @throws \TYPO3\TestingFramework\Core\Exception
     *
     * @test
     * @dataProvider getLinkCountsForPagesAndLinktypesReturnsCorrectCountForUserDataProvider
     */
    public function getLinkCountsForPagesAndLinktypesReturnsCorrectCountForUser(
        array $beuser,
        string $inputFile,
        array $pidList,
        array $expectedOutput
    ): void {
        // setup
        $searchFields = [
            'pages' => ['media', 'url', 'canonical_link'],
            'tt_content' => ['bodytext', 'header_link', 'records']
        ];
        $linkTypes = ['db', 'file', 'external'];
        $this->configuration->setSearchFields($searchFields);
        $this->configuration->setLinkTypes($linkTypes);
        $this->setupBackendUserAndGroup($beuser['uid'], $beuser['fixture'], $beuser['groupFixture']);
        $this->importDataSet($inputFile);
        $brokenLinksRepository = GeneralUtility::makeInstance(BrokenLinkRepository::class);
        $linkAnalyzer = GeneralUtility::makeInstance(LinkAnalyzer::class, $brokenLinksRepository);
        // @extensionScannerIgnoreLine
        $linkAnalyzer->init($pidList, $this->configuration);
        $linkAnalyzer->generateBrokenLinkRecords($linkTypes);

        // get result
        $result = $brokenLinksRepository->getLinkCounts(
            $pidList,
            $linkTypes,
            $searchFields
        );

        self::assertEquals($expectedOutput, $result);
    }

    /**
     * @return \Generator<string,array<mixed>>
     */
    public function getBrokenLinksReturnsCorrectCountForUserDataProvider(): \Generator
    {
        yield 'Admin user should see all broken links' =>
        [
            // backendUser: 1=admin
            $this->beusers['admin'],
            // input file for DB
            __DIR__ . '/Fixtures/input.xml',
            //pids
            [1],
            // count
            4
        ];

        yield 'User with no group should see none' =>
        [
            // backend user
            $this->beusers['no group'],
            // input file for DB
            __DIR__ . '/Fixtures/input.xml',
            //pids
            [1],
            // count
            0
        ];
        yield 'User with permission to pages but not to specific tables should see none' =>
        [
            // backend user
            $this->beusers['no group'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_2.xml',
            //pids
            [1],
            // count
            0
        ];
        yield 'User with permission to pages and to specific tables, but no exclude fields should see 3 of 4 broken links' =>
        [
            // backend user
            $this->beusers['group 1'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_3.xml',
            //pids
            [1],
            // count
            3
        ];
        yield 'User with permission to pages, specific tables and exclude fields should see all broken links' =>
        [
            // backend user
            $this->beusers['group 2'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_4.xml',
            //pids
            [1],
            // count
            4
        ];
        yield 'User has write permission only for Ctype textmedia and text, should see only broken links from textmedia records' =>
        [
            // backend user
            $this->beusers['group 6'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_6_explicit_allow.xml',
            //pids
            [1],
            // count
            1
        ];

        yield 'User has write permission only for default language and should see only 1 of 2 broken links' =>
        [
            // backend user
            $this->beusers['group 3'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_5.xml',
            //pids
            [1],
            // count
            1
        ];
    }

    /**
     * @param array<mixed> $beuser
     * @param string $inputFile
     * @param array<int,int> $pidList
     * @param int $expectedCount
     * @throws \TYPO3\TestingFramework\Core\Exception
     * @test
     * @dataProvider getBrokenLinksReturnsCorrectCountForUserDataProvider
     */
    public function getBrokenLinksReturnsCorrectCountForUser(
        array $beuser,
        string $inputFile,
        array $pidList,
        int $expectedCount
    ): void {
        // setup
        $searchFields = [
            'pages' => ['media', 'url', 'canonical_link'],
            'tt_content' => ['bodytext', 'header_link', 'records']
        ];
        $linkTypes = ['db', 'file', 'external'];
        $this->configuration->setSearchFields($searchFields);
        $this->configuration->setLinkTypes($linkTypes);
        $this->setupBackendUserAndGroup($beuser['uid'], $beuser['fixture'], $beuser['groupFixture']);
        $this->importDataSet($inputFile);
        $brokenLinksRepository = GeneralUtility::makeInstance(BrokenLinkRepository::class);
        $linkAnalyzer = GeneralUtility::makeInstance(LinkAnalyzer::class, $brokenLinksRepository);
        $linkAnalyzer->init($pidList, $this->configuration);
        $linkAnalyzer->generateBrokenLinkRecords($linkTypes);

        $results = $brokenLinksRepository->getBrokenLinks(
            $pidList,
            $linkTypes,
            $searchFields,
            new BrokenLinkListFilter(),
            []
        );

        self::assertEquals($expectedCount, count($results));
    }

    /**
     * @return \Generator<string,array<mixed>>
     */
    public function getBrokenLinksReturnsCorrectValuesForUserDataProvider(): \Generator
    {
        yield 'Admin user should see all broken links' =>
        [
            // backendUser: 1=admin
            $this->beusers['admin'],
            // input file for DB
            __DIR__ . '/Fixtures/input.xml',
            //pids
            [1],
            // expected result:
            [
                [
                    'record_uid' => 1,
                    'record_pid' => 1,
                    'language' => 0,
                    'headline' => '',
                    'field' => 'bodytext',
                    'table_name' => 'tt_content',
                    'link_title' => 'link',
                    'url' => 'http://localhost/iAmInvalid',
                    'link_type' => 'external',
                    'element_type' => 'textmedia',
                ],
                [
                    'record_uid' => 2,
                    'record_pid' => 1,
                    'language' => 0,
                    'headline' => '',
                    'field' => 'header_link',
                    'table_name' => 'tt_content',
                    'link_title' => null,
                    'url' => 'http://localhost/iAmInvalid',
                    'link_type' => 'external',
                    'element_type' => 'textmedia',
                ],
                [
                    'record_uid' => 3,
                    'record_pid' => 1,
                    'language' => 0,
                    'headline' => '',
                    'field' => 'bodytext',
                    'table_name' => 'tt_content',
                    'link_title' => 'broken link',
                    'url' => '85',
                    'link_type' => 'db',
                    'element_type' => 'textmedia',
                ],
                [
                    'record_uid' => 5,
                    'record_pid' => 1,
                    'language' => 0,
                    'headline' => '',
                    'field' => 'bodytext',
                    'table_name' => 'tt_content',
                    'link_title' => 'broken link',
                    'url' => 'file:88',
                    'link_type' => 'file',
                    'element_type' => 'textmedia',
                ],
            ]
        ];

        yield 'User with no group should see none' =>
        [
            // backend user
            $this->beusers['no group'],
            // input file for DB
            __DIR__ . '/Fixtures/input.xml',
            //pids
            [1],
            // expected result:
            []
        ];
        yield 'User with permission to pages but not to specific tables should see none' =>
        [
            // backend user
            $this->beusers['no group'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_2.xml',
            //pids
            [1],
            // expected result:
            []
        ];
        yield 'User with permission to pages and to specific tables, but no exclude fields should see 3 of 4 broken links' =>
        [
            // backend user
            $this->beusers['group 1'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_3.xml',
            //pids
            [1],
            // expected result:
            [
                [
                    'record_uid' => 1,
                    'record_pid' => 1,
                    'language' => 0,
                    'headline' => '',
                    'field' => 'bodytext',
                    'table_name' => 'tt_content',
                    'link_title' => 'link',
                    'url' => 'http://localhost/iAmInvalid',
                    'link_type' => 'external',
                    'element_type' => 'textmedia',
                ],
                [
                    'record_uid' => 3,
                    'record_pid' => 1,
                    'language' => 0,
                    'headline' => '',
                    'field' => 'bodytext',
                    'table_name' => 'tt_content',
                    'link_title' => 'broken link',
                    'url' => '85',
                    'link_type' => 'db',
                    'element_type' => 'textmedia',
                ],
                [
                    'record_uid' => 5,
                    'record_pid' => 1,
                    'language' => 0,
                    'headline' => '',
                    'field' => 'bodytext',
                    'table_name' => 'tt_content',
                    'link_title' => 'broken link',
                    'url' => 'file:88',
                    'link_type' => 'file',
                    'element_type' => 'textmedia',
                ],
            ]
        ];
        yield 'User with permission to pages, specific tables and exclude fields should see all broken links' =>
        [
            // backend user
            $this->beusers['group 2'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_4.xml',
            //pids
            [1],
            // expected result:
            [
                [
                    'record_uid' => 1,
                    'record_pid' => 1,
                    'language' => 0,
                    'headline' => '',
                    'field' => 'bodytext',
                    'table_name' => 'tt_content',
                    'link_title' => 'link',
                    'url' => 'http://localhost/iAmInvalid',
                    'link_type' => 'external',
                    'element_type' => 'textmedia',
                ],
                [
                    'record_uid' => 2,
                    'record_pid' => 1,
                    'language' => 0,
                    'headline' => '',
                    'field' => 'header_link',
                    'table_name' => 'tt_content',
                    'link_title' => null,
                    'url' => 'http://localhost/iAmInvalid',
                    'link_type' => 'external',
                    'element_type' => 'textmedia',
                ],
                [
                    'record_uid' => 3,
                    'record_pid' => 1,
                    'language' => 0,
                    'headline' => '',
                    'field' => 'bodytext',
                    'table_name' => 'tt_content',
                    'link_title' => 'broken link',
                    'url' => '85',
                    'link_type' => 'db',
                    'element_type' => 'textmedia',
                ],
                [
                    'record_uid' => 5,
                    'record_pid' => 1,
                    'language' => 0,
                    'headline' => '',
                    'field' => 'bodytext',
                    'table_name' => 'tt_content',
                    'link_title' => 'broken link',
                    'url' => 'file:88',
                    'link_type' => 'file',
                    'element_type' => 'textmedia',
                ],
            ]
        ];
        yield 'User has write permission only for Ctype textmedia and text, should see only broken links from textmedia records' =>
        [
            // backend user
            $this->beusers['group 6'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_6_explicit_allow.xml',
            //pids
            [1],
            // expected result:
            [
                [
                    'record_uid' => 1,
                    'record_pid' => 1,
                    'language' => 0,
                    'headline' => '',
                    'field' => 'bodytext',
                    'table_name' => 'tt_content',
                    'link_title' => 'link',
                    'url' => 'http://localhost/iAmInvalid',
                    'link_type' => 'external',
                    'element_type' => 'textmedia',
                ],
            ]
        ];

        yield 'User has write permission only for default language and should see only 1 of 2 broken links' =>
        [
            // backend user
            $this->beusers['group 3'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_5.xml',
            //pids
            [1],
            // expected result:
            [
                [
                    'record_uid' => 1,
                    'record_pid' => 1,
                    'language' => 0,
                    'headline' => '',
                    'field' => 'bodytext',
                    'table_name' => 'tt_content',
                    'link_title' => 'link',
                    'url' => 'http://localhost/iAmInvalid',
                    'link_type' => 'external',
                    'element_type' => 'textmedia',
                ],
            ]
        ];
    }

    /**
     * @param array<mixed> $beuser
     * @param string $inputFile
     * @param array<int,int> $pidList
     * @param array<string,mixed> $expectedResult
     * @throws \TYPO3\TestingFramework\Core\Exception
     * @test
     * @dataProvider getBrokenLinksReturnsCorrectValuesForUserDataProvider
     */
    public function getBrokenLinksReturnsCorrectValuesForUser(
        array $beuser,
        string $inputFile,
        array $pidList,
        array $expectedResult
    ): void {

        // setup
        $searchFields = [
            'pages' => ['media', 'url', 'canonical_link'],
            'tt_content' => ['bodytext', 'header_link', 'records']
        ];
        $linkTypes = ['db', 'file', 'external'];
        $this->configuration->setSearchFields($searchFields);
        $this->configuration->setLinkTypes($linkTypes);
        $this->setupBackendUserAndGroup($beuser['uid'], $beuser['fixture'], $beuser['groupFixture']);
        $this->importDataSet($inputFile);
        $brokenLinksRepository = GeneralUtility::makeInstance(BrokenLinkRepository::class);
        $linkAnalyzer = GeneralUtility::makeInstance(LinkAnalyzer::class, $brokenLinksRepository);
        $linkAnalyzer->init($pidList, $this->configuration);
        $linkAnalyzer->generateBrokenLinkRecords($linkTypes);

        // get results
        $results = $brokenLinksRepository->getBrokenLinks(
            $pidList,
            $linkTypes,
            $searchFields,
            new BrokenLinkListFilter(),
            []
        );

        $this->normalizeBrokenLinksResults($expectedResult);
        $this->normalizeBrokenLinksResults($results);
        self::assertEquals($expectedResult, $results);
    }

    protected function setupBackendUserAndGroup(int $uid, string $fixtureFile, string $groupFixtureFile): void
    {
        if ($groupFixtureFile) {
            $this->importDataSet($groupFixtureFile);
        }
        $this->backendUserFixture = $fixtureFile;
        $this->setUpBackendUserFromFixture($uid);
        Bootstrap::initializeLanguageObject();
    }

    /**
     * Normalize the results
     * - remove fields not in expected result set
     * - always order in the same way
     *
     * @param array<int,array<string,mixed>> $results
     */
    protected function normalizeBrokenLinksResults(array &$results): void
    {
        foreach ($results as &$result) {
            unset($result['url_response']);
            unset($result['uid']);
            unset($result['last_check']);
            unset($result['last_check_url']);
            unset($result['tstamp']);
            unset($result['crdate']);
            unset($result['exclude_link_targets_pid']);
        }

        usort($results, function ($a, $b) {
            $result = strcmp($a['table_name'], $b['table_name']);
            if ($result !== 0) {
                return $result;
            }
            if ($a['language'] !== $b['language']) {
                return ($a['language'] < $b['language']) ? -1 : 1;
            }
            return ($a['record_uid'] < $b['record_uid']) ? -1 : 1;
        });
    }
}
