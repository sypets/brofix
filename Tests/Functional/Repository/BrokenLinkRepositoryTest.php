<?php

declare(strict_types=1);

namespace Sypets\Brofix\Tests\Functional\Repository;

use Sypets\Brofix\Controller\Filter\BrokenLinkListFilter;
use Sypets\Brofix\LinkAnalyzer;
use Sypets\Brofix\Repository\BrokenLinkRepository;
use Sypets\Brofix\Tests\Functional\AbstractFunctional;

class BrokenLinkRepositoryTest extends AbstractFunctional
{
    protected ?BrokenLinkRepository $brokenLinkRepository = null;

    /**
     * @var array<string,array<mixed>>
     */
    protected static $beusers = [
        'admin' => [
            'fixture' => __DIR__ . '/Fixtures/be_users_admin.csv',
            'uid' => 1,
            'groupFixture' => ''
        ],
        'no group' => [
            'fixture' => __DIR__ . '/Fixtures/be_users.csv',
            'uid' => 2,
            'groupFixture' => ''
        ],
        // write access to pages, tt_content (no non_exclude_fields)
        'group 1' => [
            'fixture' => __DIR__ . '/Fixtures/be_users.csv',
            'uid' => 3,
            'groupFixture' => __DIR__ . '/Fixtures/be_groups.csv'
        ],
        // write access to pages, tt_content, exclude field pages.header_link
        'group 2' => [
            'fixture' => __DIR__ . '/Fixtures/be_users.csv',
            'uid' => 4,
            'groupFixture' => __DIR__ . '/Fixtures/be_groups.csv'
        ],
        // write access to pages, tt_content (restricted to default language)
        'group 3' => [
            'fixture' => __DIR__ . '/Fixtures/be_users.csv',
            'uid' => 5,
            'groupFixture' => __DIR__ . '/Fixtures/be_groups.csv'
        ],
        // group 6: access to all, but restricted via explicit allow to CType=texmedia and text
        'group 6' => [
            'fixture' => __DIR__ . '/Fixtures/be_users.csv',
            'uid' => 6,
            'groupFixture' => __DIR__ . '/Fixtures/be_groups.csv'
        ],

    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->brokenLinkRepository = new BrokenLinkRepository();
    }

    /**
     * @return \Generator<string,array<mixed>>
     */
    public static function getLinkCountsForPagesAndLinktypesReturnsCorrectCountForUserDataProvider(): \Generator
    {
        yield 'Admin user should see all broken links' =>
        [
            // backendUser: 1=admin
            self::$beusers['admin'],
            // input file for DB
            __DIR__ . '/Fixtures/input.csv',
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
            self::$beusers['no group'],
            // input file for DB
            __DIR__ . '/Fixtures/input.csv',
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
            self::$beusers['no group'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_2.csv',
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
        yield 'User with permission to pages and to specific tables, but no non_exclude_fields should see 3 of 4 broken links (no links in header_link)' =>
        [
            // backend user
            self::$beusers['group 1'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_3.csv',
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
        yield 'User with permission to pages, specific tables and non_exclude_fields=header_link should see all broken links' =>
        [
            // backend user
            self::$beusers['group 2'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_4.csv',
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
            self::$beusers['group 6'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_6_explicit_allow.csv',
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
            self::$beusers['group 3'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_5.csv',
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
     * @param non-empty-string $inputFile
     * @param array<int,int> $pidList
     * @param array<string,int> $expectedOutput
     * @throws \TYPO3\TestingFramework\Core\Exception
     *
     * @dataProvider getLinkCountsForPagesAndLinktypesReturnsCorrectCountForUserDataProvider
     */
    public function testGetLinkCountsForPagesAndLinktypesReturnsCorrectCountForUser(
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
        $this->setupBackendUserAndGroup($beuser['uid'], $beuser['fixture'], $beuser['groupFixture'] ?? '');
        $this->importCSVDataSet($inputFile);
        $linkAnalyzer = $this->get(LinkAnalyzer::class);
        // @extensionScannerIgnoreLine
        $linkAnalyzer->init($pidList, $this->configuration);
        $linkAnalyzer->generateBrokenLinkRecords($this->request, $linkTypes);

        // get result
        $result = $this->brokenLinkRepository->getLinkCounts(
            $pidList,
            $linkTypes,
            $searchFields
        );

        self::assertEquals($expectedOutput, $result);
    }

    /**
     * @return \Generator<string,array<mixed>>
     */
    public static function getBrokenLinksReturnsCorrectCountForUserDataProvider(): \Generator
    {
        yield 'Admin user should see all broken links' =>
        [
            // backendUser: 1=admin
            self::$beusers['admin'],
            // input file for DB
            __DIR__ . '/Fixtures/input.csv',
            //pids
            [1],
            // count
            4
        ];

        yield 'User with no group should see none' =>
        [
            // backend user
            self::$beusers['no group'],
            // input file for DB
            __DIR__ . '/Fixtures/input.csv',
            //pids
            [1],
            // count
            0
        ];
        yield 'User with permission to pages but not to specific tables should see none' =>
        [
            // backend user
            self::$beusers['no group'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_2.csv',
            //pids
            [1],
            // count
            0
        ];
        yield 'User with permission to pages and to specific tables, but no exclude fields should see 3 of 4 broken links' =>
        [
            // backend user
            self::$beusers['group 1'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_3.csv',
            //pids
            [1],
            // count
            3
        ];
        yield 'User with permission to pages, specific tables and exclude fields should see all broken links' =>
        [
            // backend user
            self::$beusers['group 2'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_4.csv',
            //pids
            [1],
            // count
            4
        ];
        yield 'User has write permission only for Ctype textmedia and text, should see only broken links from textmedia records' =>
        [
            // backend user
            self::$beusers['group 6'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_6_explicit_allow.csv',
            //pids
            [1],
            // count
            1
        ];

        yield 'User has write permission only for default language and should see only 1 of 2 broken links' =>
        [
            // backend user
            self::$beusers['group 3'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_5.csv',
            //pids
            [1],
            // count
            1
        ];
    }

    /**
     * @param array<mixed> $beuser
     * @param non-empty-string $inputFile
     * @param array<int,int> $pidList
     * @param int $expectedCount
     * @throws \TYPO3\TestingFramework\Core\Exception
     * @dataProvider getBrokenLinksReturnsCorrectCountForUserDataProvider
     */
    public function testGetBrokenLinksReturnsCorrectCountForUser(
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
        $this->importCSVDataSet($inputFile);
        $linkAnalyzer = $this->get(LinkAnalyzer::class);
        $linkAnalyzer->init($pidList, $this->configuration);
        $linkAnalyzer->generateBrokenLinkRecords($this->request, $linkTypes);

        $results = $this->brokenLinkRepository->getBrokenLinks(
            $pidList,
            $linkTypes,
            $searchFields,
            new BrokenLinkListFilter(),
            $this->configuration,
            []
        );

        self::assertEquals($expectedCount, count($results));
    }

    /**
     * @return \Generator<string,array<mixed>>
     */
    public static function getBrokenLinksReturnsCorrectValuesForUserDataProvider(): \Generator
    {
        yield 'Admin user should see all broken links' =>
        [
            // backendUser: 1=admin
            self::$beusers['admin'],
            // input file for DB
            __DIR__ . '/Fixtures/input.csv',
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
                    'check_status' => 1,
                    'flexform_field' => '',
                    'flexform_field_label' => '',
                    'error_type' => '',
                    'errno' => 0,
            ],
                [
                    'record_uid' => 2,
                    'record_pid' => 1,
                    'language' => 0,
                    'headline' => '',
                    'field' => 'header_link',
                    'table_name' => 'tt_content',
                    'link_title' => '',
                    'url' => 'http://localhost/iAmInvalid',
                    'link_type' => 'external',
                    'element_type' => 'textmedia',
                    'check_status' => 1,
                    'flexform_field' => '',
                    'flexform_field_label' => '',
                    'error_type' => '',
                    'errno' => 0,
                ],
                [
                    'record_uid' => 3,
                    'record_pid' => 1,
                    'language' => 0,
                    'headline' => '',
                    'field' => 'bodytext',
                    'table_name' => 'tt_content',
                    'link_title' => 'broken link',
                    'url' => 'pages:85',
                    'link_type' => 'db',
                    'element_type' => 'textmedia',
                    'check_status' => 1,
                    'flexform_field' => '',
                    'flexform_field_label' => '',
                    'error_type' => '',
                    'errno' => 0,
                ],
                [
                    'record_uid' => 5,
                    'record_pid' => 1,
                    'language' => 0,
                    'headline' => '',
                    'field' => 'bodytext',
                    'table_name' => 'tt_content',
                    'link_title' => 'broken link',
                    'url' => 'sys_file:88',
                    'link_type' => 'file',
                    'element_type' => 'textmedia',
                    'check_status' => 1,
                    'flexform_field' => '',
                    'flexform_field_label' => '',
                    'error_type' => '',
                    'errno' => 0,
                ],
            ]
        ];

        yield 'User with no group should see none' =>
        [
            // backend user
            self::$beusers['no group'],
            // input file for DB
            __DIR__ . '/Fixtures/input.csv',
            //pids
            [1],
            // expected result:
            []
        ];
        yield 'User with permission to pages but not to specific tables should see none' =>
        [
            // backend user
            self::$beusers['no group'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_2.csv',
            //pids
            [1],
            // expected result:
            []
        ];
        yield 'User with permission to pages and to specific tables, but no exclude fields should see 3 of 4 broken links' =>
        [
            // backend user
            self::$beusers['group 1'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_3.csv',
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
                    'check_status' => 1,
                    'flexform_field' => '',
                    'flexform_field_label' => '',
                    'error_type' => '',
                    'errno' => 0,
                ],
                [
                    'record_uid' => 3,
                    'record_pid' => 1,
                    'language' => 0,
                    'headline' => '',
                    'field' => 'bodytext',
                    'table_name' => 'tt_content',
                    'link_title' => 'broken link',
                    'url' => 'pages:85',
                    'link_type' => 'db',
                    'element_type' => 'textmedia',
                    'check_status' => 1,
                    'flexform_field' => '',
                    'flexform_field_label' => '',
                    'error_type' => '',
                    'errno' => 0,
                ],
                [
                    'record_uid' => 5,
                    'record_pid' => 1,
                    'language' => 0,
                    'headline' => '',
                    'field' => 'bodytext',
                    'table_name' => 'tt_content',
                    'link_title' => 'broken link',
                    'url' => 'sys_file:88',
                    'link_type' => 'file',
                    'element_type' => 'textmedia',
                    'check_status' => 1,
                    'flexform_field' => '',
                    'flexform_field_label' => '',
                    'error_type' => '',
                    'errno' => 0,
                ],
            ]
        ];
        yield 'User with permission to pages, specific tables and exclude fields should see all broken links' =>
        [
            // backend user
            self::$beusers['group 2'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_4.csv',
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
                    'check_status' => 1,
                    'flexform_field' => '',
                    'flexform_field_label' => '',
                    'error_type' => '',
                    'errno' => 0,
                ],
                [
                    'record_uid' => 2,
                    'record_pid' => 1,
                    'language' => 0,
                    'headline' => '',
                    'field' => 'header_link',
                    'table_name' => 'tt_content',
                    'link_title' => '',
                    'url' => 'http://localhost/iAmInvalid',
                    'link_type' => 'external',
                    'element_type' => 'textmedia',
                    'check_status' => 1,
                    'flexform_field' => '',
                    'flexform_field_label' => '',
                    'error_type' => '',
                    'errno' => 0,
                ],
                [
                    'record_uid' => 3,
                    'record_pid' => 1,
                    'language' => 0,
                    'headline' => '',
                    'field' => 'bodytext',
                    'table_name' => 'tt_content',
                    'link_title' => 'broken link',
                    'url' => 'pages:85',
                    'link_type' => 'db',
                    'element_type' => 'textmedia',
                    'check_status' => 1,
                    'flexform_field' => '',
                    'flexform_field_label' => '',
                    'error_type' => '',
                    'errno' => 0,
                ],
                [
                    'record_uid' => 5,
                    'record_pid' => 1,
                    'language' => 0,
                    'headline' => '',
                    'field' => 'bodytext',
                    'table_name' => 'tt_content',
                    'link_title' => 'broken link',
                    'url' => 'sys_file:88',
                    'link_type' => 'file',
                    'element_type' => 'textmedia',
                    'check_status' => 1,
                    'flexform_field' => '',
                    'flexform_field_label' => '',
                    'error_type' => '',
                    'errno' => 0,
                ],
            ]
        ];
        yield 'User has write permission only for Ctype textmedia and text, should see only broken links from textmedia records' =>
        [
            // backend user
            self::$beusers['group 6'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_6_explicit_allow.csv',
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
                    'check_status' => 1,
                    'flexform_field' => '',
                    'flexform_field_label' => '',
                    'error_type' => '',
                    'errno' => 0,
                ],
            ]
        ];

        yield 'User has write permission only for default language and should see only 1 of 2 broken links' =>
        [
            // backend user
            self::$beusers['group 3'],
            // input file for DB
            __DIR__ . '/Fixtures/input_permissions_user_5.csv',
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
                    'check_status' => 1,
                    'flexform_field' => '',
                    'flexform_field_label' => '',
                    'error_type' => '',
                    'errno' => 0,
                ],
            ]
        ];
    }

    /**
     * @param array<mixed> $beuser
     * @param non-empty-string $inputFile
     * @param array<int,int> $pidList
     * @param array<string,mixed> $expectedResult
     * @throws \TYPO3\TestingFramework\Core\Exception
     * @dataProvider getBrokenLinksReturnsCorrectValuesForUserDataProvider
     */
    public function testGetBrokenLinksReturnsCorrectValuesForUser(
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
        $this->importCSVDataSet($inputFile);
        $linkAnalyzer = $this->get(LinkAnalyzer::class);
        $linkAnalyzer->init($pidList, $this->configuration);
        $linkAnalyzer->generateBrokenLinkRecords($this->request, $linkTypes);

        // get results
        $results = $this->brokenLinkRepository->getBrokenLinks(
            $pidList,
            $linkTypes,
            $searchFields,
            new BrokenLinkListFilter(),
            $this->configuration,
            []
        );

        $this->normalizeBrokenLinksResults($expectedResult);
        $this->normalizeBrokenLinksResults($results);
        self::assertEquals($expectedResult, $results);
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
        self::initializeLanguageObject();
    }

    /**
     * Normalize the results
     * - remove fields not in expected result set
     * - always order in the same way
     *
     * @param array<int,array<string,mixed>> $results
     */
    protected function normalizeBrokenLinksResults(array &$results): array
    {
        foreach ($results as &$result) {
            unset($result['url_response']);
            unset($result['uid']);
            unset($result['last_check']);
            unset($result['last_check_url']);
            unset($result['tstamp']);
            unset($result['crdate']);
            unset($result['exclude_link_targets_pid']);
            unset($result['url_hash']);
            if (isset($result['url_checker'])) {
                unset($result['url_checker']);
            }
        }

        return $this->sortBrokenLinksResults($results);
    }

    protected function sortBrokenLinksResults(array &$results): array
    {
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
        return $results;
    }
}
