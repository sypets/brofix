<?php

declare(strict_types=1);

namespace Sypets\Brofix\Repository;

use Doctrine\DBAL\Exception\TableNotFoundException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Sypets\Brofix\CheckLinks\ExcludeLinkTarget;
use Sypets\Brofix\CheckLinks\LinkTargetResponse\LinkTargetResponse;
use Sypets\Brofix\Controller\Filter\BrokenLinkListFilter;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Platform\PlatformInformation;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Handle database queries for table of broken links
 *
 * @internal
 */
class BrokenLinkRepository implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected const TABLE = 'tx_brofix_broken_links';

    /**
     * @var int
     */
    protected $maxBindParameters;

    public function __construct()
    {
        /**
         * @var ConnectionPool $connectionPool
         */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection = $connectionPool->getConnectionForTable(static::TABLE);
        $this->maxBindParameters = PlatformInformation::getMaxBindParameters($connection->getDatabasePlatform());
    }

    public function getMaxBindParameters(): int
    {
        return $this->maxBindParameters;
    }

    /**
     * Get broken links.
     *
     * Will only return broken links which the current user has edit access to.
     *
     * @param int[] $pageList Pages to check for broken links
     * @param string[] $linkTypes Link types to validate
     * @param array<string,array<string>> $searchFields
     * @param array<array<string>> $orderBy
     * @param BrokenLinkListFilter $filter
     * @param array<mixed> $orderBy
     * @return mixed[]
     *
     * @see LinkTargetResponse
     */
    public function getBrokenLinks(
        array $pageList,
        array $linkTypes,
        array $searchFields,
        BrokenLinkListFilter $filter,
        array $orderBy = []
    ): array {
        $results = [];
        $max = (int)($this->getMaxBindParameters() /2 - 4);
        foreach (array_chunk($pageList, $max)
                 as $pageIdsChunk) {
            $queryBuilder = $this->generateQueryBuilder(self::TABLE);

            if (!$GLOBALS['BE_USER']->isAdmin()) {
                /**
                 * @var EditableRestriction $editableRestriction
                 */
                $editableRestriction = GeneralUtility::makeInstance(EditableRestriction::class, $searchFields, $queryBuilder);
                $queryBuilder->getRestrictions()
                    ->add($editableRestriction);
            }

            $queryBuilder
                ->select(self::TABLE . '.*')
                ->from(self::TABLE)
                ->join(
                    self::TABLE,
                    'pages',
                    'pages',
                    // @todo record_pid is not always page id
                    $queryBuilder->expr()->eq(self::TABLE . '.record_pid', $queryBuilder->quoteIdentifier('pages.uid'))
                )
                ->where(
                    $queryBuilder->expr()->or(
                        $queryBuilder->expr()->and(
                            $queryBuilder->expr()->in(
                                self::TABLE . '.record_uid',
                                $queryBuilder->createNamedParameter($pageIdsChunk, Connection::PARAM_INT_ARRAY)
                            ),
                            $queryBuilder->expr()->eq('table_name', $queryBuilder->createNamedParameter('pages'))
                        ),
                        $queryBuilder->expr()->and(
                            $queryBuilder->expr()->in(
                                self::TABLE . '.record_pid',
                                $queryBuilder->createNamedParameter($pageIdsChunk, Connection::PARAM_INT_ARRAY)
                            ),
                            $queryBuilder->expr()->neq('table_name', $queryBuilder->createNamedParameter('pages'))
                        )
                    )
                );

            if ($filter->getUidFilter() != '') {
                $queryBuilder->andWhere(
                    $queryBuilder->expr()->eq(self::TABLE . '.record_uid', $queryBuilder->createNamedParameter($filter->getUidFilter(), \PDO::PARAM_INT))
                );
            }
            $urlFilter = $filter->getUrlFilter();
            if ($urlFilter != '') {
                switch ($filter->getUrlFilterMatch()) {
                    case 'partial':
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->like(
                                self::TABLE . '.url',
                                $queryBuilder->createNamedParameter('%' . $queryBuilder->escapeLikeWildcards($filter->getUrlFilter()) . '%')
                            )
                        );
                        break;
                    case 'exact':
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->eq(
                                self::TABLE . '.url',
                                $queryBuilder->createNamedParameter($urlFilter)
                            )
                        );
                        break;
                    case 'partialnot':
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->notLike(
                                self::TABLE . '.url',
                                $queryBuilder->createNamedParameter('%' . $queryBuilder->escapeLikeWildcards($filter->getUrlFilter()) . '%')
                            )
                        );
                        break;
                    case 'exactnot':
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->neq(
                                self::TABLE . '.url',
                                $queryBuilder->createNamedParameter(mb_substr($urlFilter, 1))
                            )
                        );
                        break;
                }
            }
            $linktypeFilter = $filter->getLinkTypeFilter() ?: 'all';
            if ($linktypeFilter != 'all') {
                $queryBuilder->andWhere(
                    $queryBuilder->expr()->eq(self::TABLE . '.link_type', $queryBuilder->createNamedParameter($linktypeFilter))
                );
            }

            $checkStatus = $filter->getCheckStatusFilter();
            if ($checkStatus !== LinkTargetResponse::RESULT_ALL) {
                $queryBuilder->andWhere(
                    $queryBuilder->expr()->eq(self::TABLE . '.check_status', $queryBuilder->createNamedParameter($checkStatus, Connection::PARAM_INT))
                );
            }

            if ($orderBy !== []) {
                $values = array_shift($orderBy);
                if ($values && is_array($values) && count($values) === 2) {
                    $queryBuilder->orderBy($values[0], $values[1]);
                    foreach ($orderBy as $values) {
                        if (!is_array($values) || count($values) != 2) {
                            break;
                        }
                        $queryBuilder->addOrderBy(self::TABLE . '.' . $values[0], $values[1]);
                    }
                }
            }

            if (!empty($linkTypes)) {
                $queryBuilder->andWhere(
                    $queryBuilder->expr()->in(
                        self::TABLE . '.link_type',
                        $queryBuilder->createNamedParameter($linkTypes, Connection::PARAM_STR_ARRAY)
                    )
                );
            }

            $results = array_merge($results, $queryBuilder->executeQuery()->fetchAllAssociative());
        }
        return $results;
    }

    /**
     * Check if current page has broken links editable by user
     *
     * @param int $pageId
     * @param bool $withEditableByUser if true, only count broken links for records editable by user
     * @return bool
     *
     * @todo use $withEditableByUser
     */
    public function hasPageBrokenLinks(int $pageId, bool $withEditableByUser = true): bool
    {
        $count = $this->getLinkCountForPage($pageId, $withEditableByUser, LinkTargetResponse::RESULT_BROKEN);
        return $count !== 0;
    }

    /**
     * Check if current page has broken links editable by user
     *
     * @param int $pageId
     * @param bool $withEditableByUser if true, only count broken links for records editable by user
     * @param int $withStatus (or -1 for all)
     * @return int
     *
     * @todo $withEditableByUser is not used
     */
    public function getLinkCountForPage(int $pageId, bool $withEditableByUser = true, int $withStatus = LinkTargetResponse::RESULT_BROKEN): int
    {
        $queryBuilder = $this->generateQueryBuilder(self::TABLE);

        $stmt = $queryBuilder
            ->count('uid')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->or(
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq(
                        self::TABLE . '.record_uid',
                        $queryBuilder->createNamedParameter($pageId, \PDO::PARAM_INT)
                    ),
                    $queryBuilder->expr()->eq('table_name', $queryBuilder->createNamedParameter('pages'))
                ),
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq(
                        self::TABLE . '.record_pid',
                        $queryBuilder->createNamedParameter($pageId, \PDO::PARAM_INT)
                    ),
                    $queryBuilder->expr()->neq('table_name', $queryBuilder->createNamedParameter('pages'))
                )
            ));

        if ($withStatus !== -1) {
            $stmt->andWhere($queryBuilder->expr()->eq('check_status', $withStatus));
        }

        return (int)$stmt->executeQuery()
            ->fetchOne();
    }

    /**
     * Fill a marker array with the number of links found in a list of pages
     *
     * @param array<string|int> $pageIds page uids
     * @param array<string> $linkTypes
     * @param array<string,array<string>> $searchFields
     * @return mixed[] array with the number of links found
     *
     * @todo is currently not used, use for statistics
     */
    public function getLinkCounts(array $pageIds, array $linkTypes = [], array $searchFields = []): array
    {
        $markerArray = [];
        $max = (int)($this->getMaxBindParameters() /2 - 4);
        foreach (array_chunk($pageIds, $max)
                 as $pageIdsChunk) {
            $queryBuilder = $this->generateQueryBuilder(self::TABLE);
            $queryBuilder->getRestrictions()->removeAll();

            if (!$GLOBALS['BE_USER']->isAdmin()) {
                /**
                 * @var EditableRestriction $editableRestriction
                 */
                $editableRestriction = GeneralUtility::makeInstance(EditableRestriction::class, $searchFields, $queryBuilder);
                $queryBuilder->getRestrictions()
                    ->add($editableRestriction);
            }

            $result = $queryBuilder->select(self::TABLE . '.link_type')
                ->addSelectLiteral($queryBuilder->expr()->count(self::TABLE . '.uid', 'nbBrokenLinks'))
                ->from(self::TABLE)
                ->join(
                    self::TABLE,
                    'pages',
                    'pages',
                    // @todo record_pid is not always page id
                    $queryBuilder->expr()->eq(self::TABLE . '.record_pid', $queryBuilder->quoteIdentifier('pages.uid'))
                )
                ->where(
                    $queryBuilder->expr()->or(
                        $queryBuilder->expr()->and(
                            $queryBuilder->expr()->in(
                                self::TABLE . '.record_uid',
                                $queryBuilder->createNamedParameter($pageIdsChunk, Connection::PARAM_INT_ARRAY)
                            ),
                            $queryBuilder->expr()->eq(self::TABLE . '.table_name', $queryBuilder->createNamedParameter('pages'))
                        ),
                        $queryBuilder->expr()->and(
                            $queryBuilder->expr()->in(
                                self::TABLE . '.record_pid',
                                $queryBuilder->createNamedParameter($pageIdsChunk, Connection::PARAM_INT_ARRAY)
                            ),
                            $queryBuilder->expr()->neq(self::TABLE . '.table_name', $queryBuilder->createNamedParameter('pages'))
                        )
                    )
                )
                ->groupBy(self::TABLE . '.link_type')
                ->executeQuery();

            while ($row = $result->fetchAssociative()) {
                if (!isset($markerArray[$row['link_type']])) {
                    $markerArray[$row['link_type']] = 0;
                }
                $markerArray[$row['link_type']] += (int)($row['nbBrokenLinks']);
                if (!isset($markerArray['total'])) {
                    $markerArray['total'] = 0;
                }
                $markerArray['total'] += (int)($row['nbBrokenLinks']);
            }
        }
        if ($linkTypes) {
            // fill missing values
            foreach ($linkTypes as $linkType) {
                if (!isset($markerArray[$linkType])) {
                    $markerArray[$linkType] = 0;
                }
                if (!isset($markerArray['total'])) {
                    $markerArray['total'] = 0;
                }
            }
        }
        return $markerArray;
    }

    public function removeBrokenLinksForRecord(string $tableName, int $recordUid): int
    {
        $queryBuilder = $this->generateQueryBuilder(static::TABLE);
        $constraints = [];

        if ($tableName === 'pages') {
            $constraints[] = $queryBuilder->expr()->or(
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq(
                        'record_uid',
                        $queryBuilder->createNamedParameter($recordUid, \PDO::PARAM_INT)
                    ),
                    $queryBuilder->expr()->eq(
                        'table_name',
                        $queryBuilder->createNamedParameter('pages')
                    )
                ),
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq(
                        'record_pid',
                        $queryBuilder->createNamedParameter($recordUid, \PDO::PARAM_INT)
                    ),
                    $queryBuilder->expr()->neq(
                        'table_name',
                        $queryBuilder->createNamedParameter('pages')
                    )
                )
            );
        } else {
            $constraints[] = $queryBuilder->expr()->eq(
                'record_uid',
                $queryBuilder->createNamedParameter($recordUid, \PDO::PARAM_INT)
            );
            $constraints[] = $queryBuilder->expr()->eq(
                'table_name',
                $queryBuilder->createNamedParameter($tableName)
            );
        }

        return $queryBuilder->delete(static::TABLE)
            ->where(
                ...$constraints
            )
            ->executeStatement();
    }

    public function removeForRecordUrl(string $tableName, int $recordUid, string $url, string $linkType): int
    {
        $queryBuilder = $this->generateQueryBuilder(static::TABLE);

        if ($tableName === 'pages') {
            $constraints[] = $queryBuilder->expr()->or(
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq(
                        'record_uid',
                        $queryBuilder->createNamedParameter($recordUid, \PDO::PARAM_INT)
                    ),
                    $queryBuilder->expr()->eq(
                        'table_name',
                        $queryBuilder->createNamedParameter('pages')
                    )
                ),
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq(
                        'record_pid',
                        $queryBuilder->createNamedParameter($recordUid, \PDO::PARAM_INT)
                    ),
                    $queryBuilder->expr()->neq(
                        'table_name',
                        $queryBuilder->createNamedParameter('pages')
                    )
                )
            );
        } else {
            $constraints[] = $queryBuilder->expr()->eq(
                'record_uid',
                $queryBuilder->createNamedParameter($recordUid, \PDO::PARAM_INT)
            );
            $constraints[] = $queryBuilder->expr()->eq(
                'table_name',
                $queryBuilder->createNamedParameter($tableName)
            );
        }
        $constraints[] = $queryBuilder->expr()->like(
            'url',
            $queryBuilder->createNamedParameter($url)
        );
        $constraints[] = $queryBuilder->expr()->like(
            'link_type',
            $queryBuilder->createNamedParameter($linkType)
        );

        return $queryBuilder->delete(static::TABLE)
            ->where(
                ...$constraints
            )
            ->executeStatement();
    }

    public function removeBrokenLinksForRecordBeforeTime(string $tableName, int $recordUid, int $time): void
    {
        $queryBuilder = $this->generateQueryBuilder(static::TABLE);

        $queryBuilder->delete(static::TABLE)
            ->where(
                $queryBuilder->expr()->eq(
                    'record_uid',
                    $queryBuilder->createNamedParameter($recordUid, \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'table_name',
                    $queryBuilder->createNamedParameter($tableName)
                ),
                $queryBuilder->expr()->lt('tstamp', $queryBuilder->createNamedParameter($time, \PDO::PARAM_INT))
            )
            ->executeStatement();
    }

    /**
     * Remove all broken link records in list of broken links for these pages and
     * link types.
     *
     * @param array<int|string> $pageIds
     * @param array<string> $linkTypes
     * @param int $time
     */
    public function removeAllBrokenLinksForPagesBeforeTime(array $pageIds, array $linkTypes, int $time): void
    {
        $max = (int)($this->getMaxBindParameters() /2 - 4);
        foreach (array_chunk($pageIds, $max)
                 as $pageIdsChunk) {
            $queryBuilder = $this->generateQueryBuilder(self::TABLE);

            $queryBuilder->delete(self::TABLE)
                ->where(
                    $queryBuilder->expr()->or(
                        $queryBuilder->expr()->and(
                            $queryBuilder->expr()->in(
                                'record_uid',
                                $queryBuilder->createNamedParameter($pageIdsChunk, Connection::PARAM_INT_ARRAY)
                            ),
                            $queryBuilder->expr()->eq('table_name', $queryBuilder->createNamedParameter('pages'))
                        ),
                        $queryBuilder->expr()->and(
                            $queryBuilder->expr()->in(
                                'record_pid',
                                $queryBuilder->createNamedParameter($pageIdsChunk, Connection::PARAM_INT_ARRAY)
                            ),
                            $queryBuilder->expr()->neq(
                                'table_name',
                                $queryBuilder->createNamedParameter('pages')
                            )
                        )
                    ),
                    $queryBuilder->expr()->in(
                        'link_type',
                        $queryBuilder->createNamedParameter($linkTypes, Connection::PARAM_STR_ARRAY)
                    ),
                    $queryBuilder->expr()->lt('tstamp', $queryBuilder->createNamedParameter($time, \PDO::PARAM_INT))
                )
                ->executeStatement();
        }
    }

    /**
     * Check if linkTarget is in list of broken links.
     *
     * @param string $linkTarget Url to check for. Can be a URL (for external links)
     *   a page uid (for db links), a file reference (for file links), etc.
     * @return bool is the link target a broken link
     */
    public function isLinkTargetBrokenLink(string $linkTarget, string $linkType): bool
    {
        try {
            $queryBuilder = $this->generateQueryBuilder(static::TABLE);
            $queryBuilder
                ->count('uid')
                ->from(static::TABLE)
                ->where(
                    $queryBuilder->expr()->eq('url', $queryBuilder->createNamedParameter($linkTarget)),
                    $queryBuilder->expr()->eq('link_type', $queryBuilder->createNamedParameter($linkType)),
                    $queryBuilder->expr()->eq('check_status', $queryBuilder->createNamedParameter(LinkTargetResponse::RESULT_BROKEN, \PDO::PARAM_INT))
                );
            return (bool)$queryBuilder
                ->executeQuery()
                ->fetchOne();
        } catch (TableNotFoundException $e) {
            return false;
        }
    }

    /**
     * Remove all broken links that match a link target.
     *
     * @param string $linkTarget
     * @param string $linkType
     * @param string $matchBy
     * @param int $excludeLinkTargetPid Storage pid of excluded link targets, -1 means to not consider the pid
     * @return int
     */
    public function removeBrokenLinksForLinkTarget(
        string $linkTarget,
        string $linkType = 'external',
        string $matchBy = ExcludeLinkTarget::MATCH_BY_EXACT,
        int $excludeLinkTargetPid = -1
    ): int {
        $queryBuilder = $this->generateQueryBuilder(static::TABLE);

        $constraints = [];

        if ($matchBy === ExcludeLinkTarget::MATCH_BY_EXACT) {
            $constraints[] = $queryBuilder->expr()->eq(
                'url',
                $queryBuilder->createNamedParameter($linkTarget)
            );
        } elseif ($matchBy === ExcludeLinkTarget::MATCH_BY_DOMAIN) {
            $constraints[] = $queryBuilder->expr()->or(
                $queryBuilder->expr()->like(
                    'url',
                    $queryBuilder->createNamedParameter('%://' . $linkTarget . '/%')
                ),
                $queryBuilder->expr()->like(
                    'url',
                    $queryBuilder->createNamedParameter('%://' . $linkTarget)
                )
            );
        } else {
            return 0;
        }

        $constraints[] = $queryBuilder->expr()->eq(
            'link_type',
            $queryBuilder->createNamedParameter($linkType)
        );

        if ($excludeLinkTargetPid !== -1) {
            $constraints[] = $queryBuilder->expr()->eq(
                'exclude_link_targets_pid',
                $queryBuilder->createNamedParameter($excludeLinkTargetPid, \PDO::PARAM_INT)
            );
        }

        return (int)$queryBuilder->delete(static::TABLE)
            ->where(...$constraints)
            ->executeStatement();
    }

    /**
     * Update existing record or insert new
     *
     * @param mixed[] $record
     * @return bool Returns true if new record was inserted
     */
    public function insertOrUpdateBrokenLink(array $record): bool
    {
        $queryBuilder = $this->generateQueryBuilder(self::TABLE);
        $count = (int)$queryBuilder->count('uid')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq(
                    'record_uid',
                    $queryBuilder->createNamedParameter($record['record_uid'], \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'table_name',
                    $queryBuilder->createNamedParameter($record['table_name'])
                ),
                $queryBuilder->expr()->eq(
                    'field',
                    $queryBuilder->createNamedParameter($record['field'])
                ),
                $queryBuilder->expr()->eq(
                    'url',
                    $queryBuilder->createNamedParameter($record['url'])
                )
            )
            ->executeQuery()
            ->fetchOne();
        if ($count > 0) {
            $identifier = [
                'record_uid' => $record['record_uid'],
                'table_name' => $record['table_name'],
                'field' => $record['field'],
                'url' => $record['url']
            ];
            $this->updateBrokenLink($record, $identifier);
        } else {
            $this->insertBrokenLink($record);
            return true;
        }
        return false;
    }

    /**
     * @param array<string,mixed> $record What to update: key => value pairs
     * @param array<string,mixed> $identifier Update criteria (where-statement): key => value pairs
     * @return int
     * @see \TYPO3\CMS\Core\Database\Connection::update()
     */
    public function updateBrokenLink(array $record, array $identifier): int
    {
        $count = 0;
        $record['tstamp'] = \time();
        try {
            $count = (int)GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable(static::TABLE)
                ->update(self::TABLE, $record, $identifier);
        } catch (\Exception $e) {
            // we catch exception here and log as error
            // @extensionScannerIgnoreLine problem with ->error()
            $this->logger->error(
                'insertBrokenLink: url_response='
                . ($record['url_response'] ?? '')
                . ', exception message=' . $e->getMessage()
                . ', stack trace=' . $e->getTraceAsString()
            );
        }
        return $count;
    }

    /**
     * Insert new record
     *
     * @param mixed[] $record
     */
    public function insertBrokenLink(array $record): void
    {
        $record['tstamp'] = \time();
        $record['crdate'] = \time();
        try {
            GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable(static::TABLE)
                ->insert(self::TABLE, $record);
        } catch (\Exception $e) {
            // we catch exception here and log as error
            // @extensionScannerIgnoreLine problem with ->error()
            $this->logger->error(
                'insertBrokenLink: url_response='
                . ($record['url_response'] ?? '')
                . ', exception message=' . $e->getMessage()
                . ', stack trace=' . $e->getTraceAsString()
            );
        }
    }

    protected function generateQueryBuilder(string $table = ''): QueryBuilder
    {
        if ($table === '') {
            $table = static::TABLE;
        }
        /**
         * @var ConnectionPool $connectionPool
         */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        return $connectionPool->getQueryBuilderForTable($table);
    }
}
