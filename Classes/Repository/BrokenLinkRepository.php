<?php

declare(strict_types=1);

namespace Sypets\Brofix\Repository;

use Doctrine\DBAL\Exception\TableNotFoundException;
use Hoa\File\Link\Link;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Sypets\Brofix\CheckLinks\ExcludeLinkTarget;
use Sypets\Brofix\CheckLinks\LinkTargetResponse\LinkTargetResponse;
use Sypets\Brofix\Configuration\Configuration;
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
 *
 * @todo Make final and change protected methods and properties to private
 */
class BrokenLinkRepository implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected const TABLE = 'tx_brofix_broken_links';

    protected int $maxBindParameters = 0;

    public function __construct(protected ConnectionPool $connectionPool)
    {
        $connection = $connectionPool->getConnectionForTable(static::TABLE);
        $this->maxBindParameters = PlatformInformation::getMaxBindParameters($connection->getDatabasePlatform());
    }

    /**
     * Returns maximum number of pages we can use as parameters in prepared statement
     */
    public function getMaxNumberOfPagesForDbQuery(): int
    {
        return $this->getMaxBindParameters() - 4;
    }

    /**
     * Return information about the maximum number of bound parameters supported on this platform (depends on database
     * server / engine / doctrine/dbal)
     */
    public function getMaxBindParameters(): int
    {
        return $this->maxBindParameters - 4;
    }

    /**
     * Get broken links.
     *
     * Will only return broken links which the current user has edit access to.
     *
     * @param int[] $pageList Pages to check for broken links. If null, do not constrain
     * @param string[] $linkTypes Link types to validate
     * @param array<string,array<string>> $searchFields
     * @param array<array<string>> $orderBy
     * @param BrokenLinkListFilter $filter
     * @param array<mixed> $orderBy
     * @return mixed[]
     *
     * @see LinkTargetResponse
     * @todo Instead of array_chunking the pids, set a hard limit. $max is usually something around 30000. It does not make sense to use more for performance reasons. This
     *       way, it is possible to simplify the function slightly and we do not need to iterate.
     */
    public function getBrokenLinks(
        ?array $pageList,
        array $linkTypes,
        array $searchFields,
        BrokenLinkListFilter $filter,
        Configuration $configuration,
        array $orderBy = []
    ): array {
        $results = [];

        if ($pageList === []) {
            return [];
        }

        /**
         * array_chunk the pids here because otherwise we might get exceptions such as "Too many params in prepared
         * statement". If not using prepared statements, we run into other limit.
         */
        $max = $this->getMaxNumberOfPagesForDbQuery();
        foreach (array_chunk($pageList ?? [1], $max) as $pageIdsChunk) {
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
                    // we  ise record_pageid now instead of record_pid
                    $queryBuilder->expr()->eq(
                        self::TABLE . '.record_pageid',
                        $queryBuilder->quoteIdentifier('pages.uid')
                    )
                );
            if ($pageList) {
                // now use only one field 'record_pageid' instead of record_uid for table pages and record_pid for not table pages
                $queryBuilder
                    ->where(
                        $queryBuilder->expr()->in(
                            self::TABLE . '.record_pageid',
                            $queryBuilder->createNamedParameter($pageIdsChunk, Connection::PARAM_INT_ARRAY)
                        )
                    );
            }

            if ($filter->getUidFilter() != '') {
                $queryBuilder->andWhere(
                    $queryBuilder->expr()->eq(self::TABLE . '.record_uid', $queryBuilder->createNamedParameter($filter->getUidFilter(), Connection::PARAM_INT))
                );
            }

            if ($filter->getTypeFilter() != '') {
                $parts = explode('.', $filter->getTypeFilter());
                $table_name = $parts[0];
                $field = $parts[1] ?? '';
                $queryBuilder->andWhere(
                    $queryBuilder->expr()->eq(
                        self::TABLE . '.table_name',
                        $queryBuilder->createNamedParameter($table_name)
                    )
                );
                if ($field) {
                    $queryBuilder->andWhere(
                        $queryBuilder->expr()->eq(
                            self::TABLE . '.field',
                            $queryBuilder->createNamedParameter($field)
                        )
                    );
                }
            }

            // errorFilter, might be 'custom:13' or 'custom:13|httpErrorCode:404' etc. Several combinations, separated
            // by '|', each combination with <errortype>:<errno>

            $errorFilter = $filter->getErrorFilter();
            $errorConstraintsOr = [];
            if ($errorFilter !== '') {
                $errorCombinations = explode('|', $errorFilter);
                foreach ($errorCombinations as $errorCombination) {
                    $parts = explode(':', $errorCombination);
                    if (count($parts) === 2) {
                        $errorType = $parts[0];
                        $errno = (int)$parts[1];
                        $errorConstraintsOr[] = $queryBuilder->expr()->and(
                            $queryBuilder->expr()->eq(
                                self::TABLE . '.error_type',
                                $queryBuilder->createNamedParameter($errorType)
                            ),
                            $queryBuilder->expr()->eq(
                                self::TABLE . '.errno',
                                $queryBuilder->createNamedParameter($errno, Connection::PARAM_INT)
                            )
                        );
                    } elseif (count($parts) === 1) {
                        $errorType = $parts[0];
                        $errorConstraintsOr[] = $queryBuilder->expr()->eq(
                            self::TABLE . '.error_type',
                            $queryBuilder->createNamedParameter($errorType)
                        );
                    }
                }
            }
            if ($errorConstraintsOr) {
                $queryBuilder->andWhere(
                    $queryBuilder->expr()->or(...$errorConstraintsOr)
                );
            }

            $urlFilter = $filter->getUrlFilter();
            if ($urlFilter != '') {
                switch ($filter->getUrlFilterMatch()) {
                    case 'partial':
                        $urlFilters = explode('|', $filter->getUrlFilter());
                        $urlFilterConstraints =  [];
                        foreach ($urlFilters as $urlFilter) {
                            $urlFilterConstraints[] = $queryBuilder->expr()->like(
                                self::TABLE . '.url',
                                $queryBuilder->createNamedParameter('%' . $queryBuilder->escapeLikeWildcards($urlFilter) . '%')
                            );
                        }
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->or(...$urlFilterConstraints)
                        );
                        break;
                    case 'exact':
                        $urlFilters = explode('|', $filter->getUrlFilter());
                        $urlFilterConstraints =  [];
                        foreach ($urlFilters as $urlFilter) {
                            $urlFilterConstraints[] = $queryBuilder->expr()->eq(
                                self::TABLE . '.url',
                                $queryBuilder->createNamedParameter($urlFilter)
                            );
                        }
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->or(...$urlFilterConstraints)
                        );
                        break;
                    case 'partialnot':
                        $urlFilters = explode('|', $filter->getUrlFilter());
                        $urlFilterConstraints =  [];
                        foreach ($urlFilters as $urlFilter) {
                            $urlFilterConstraints[] = $queryBuilder->expr()->notLike(
                                self::TABLE . '.url',
                                $queryBuilder->createNamedParameter('%' . $queryBuilder->escapeLikeWildcards($urlFilter) . '%')
                            );
                        }
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->and(...$urlFilterConstraints)
                        );
                        break;
                    case 'exactnot':
                        /*
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->neq(
                                self::TABLE . '.url',
                                $queryBuilder->createNamedParameter(mb_substr($urlFilter, 1))
                            )
                        );
                        */
                        $urlFilters = explode('|', $filter->getUrlFilter());
                        $urlFilterConstraints =  [];
                        foreach ($urlFilters as $urlFilter) {
                            $urlFilterConstraints[] = $queryBuilder->expr()->neq(
                                self::TABLE . '.url',
                                $queryBuilder->createNamedParameter($urlFilter)
                            );
                        }
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->and(...$urlFilterConstraints)
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

            if ($configuration->isShowAllLinks()) {
                $checkStatus = $filter->getCheckStatusFilter();
            } else {
                // default is show error only
                $checkStatus = LinkTargetResponse::RESULT_BROKEN;
            }
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
            // now use record_pageid instead of having to check for table and use record_uid for pages and record_pid for others
            ->where(
                $queryBuilder->expr()->eq(
                    self::TABLE . '.record_pageid',
                    $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)
                )
            );

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
        $max = $this->getMaxNumberOfPagesForDbQuery();
        foreach (array_chunk($pageIds, $max) as $pageIdsChunk) {
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
                    $queryBuilder->expr()->in(
                        self::TABLE . '.record_pageid',
                        $queryBuilder->createNamedParameter($pageIdsChunk, Connection::PARAM_INT_ARRAY)
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
            $constraints[] = $queryBuilder->expr()->eq(
                'record_pageid',
                $queryBuilder->createNamedParameter($recordUid, Connection::PARAM_INT)
            );
        } else {
            $constraints[] = $queryBuilder->expr()->eq(
                'record_uid',
                $queryBuilder->createNamedParameter($recordUid, Connection::PARAM_INT)
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
            $constraints[] = $queryBuilder->expr()->eq(
                'record_pageid',
                $queryBuilder->createNamedParameter($recordUid, Connection::PARAM_INT)
            );
        } else {
            $constraints[] = $queryBuilder->expr()->eq(
                'record_uid',
                $queryBuilder->createNamedParameter($recordUid, Connection::PARAM_INT)
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
                    $queryBuilder->createNamedParameter($recordUid, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'table_name',
                    $queryBuilder->createNamedParameter($tableName)
                ),
                $queryBuilder->expr()->lt('tstamp', $queryBuilder->createNamedParameter($time, Connection::PARAM_INT))
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
        $max = $this->getMaxNumberOfPagesForDbQuery();
        foreach (array_chunk($pageIds, $max) as $pageIdsChunk) {
            $queryBuilder = $this->generateQueryBuilder(self::TABLE);

            $queryBuilder->delete(self::TABLE)
                ->where(
                    $queryBuilder->expr()->in(
                        'record_pageid',
                        $queryBuilder->createNamedParameter($pageIdsChunk, Connection::PARAM_INT_ARRAY)
                    ),
                    $queryBuilder->expr()->in(
                        'link_type',
                        $queryBuilder->createNamedParameter($linkTypes, Connection::PARAM_STR_ARRAY)
                    ),
                    $queryBuilder->expr()->lt('tstamp', $queryBuilder->createNamedParameter($time, Connection::PARAM_INT))
                )
                ->executeStatement();
        }
    }

    /**
     * Check if linkTarget is in list of broken links.
     *
     * For performance reasons we use the url_hash, not the URL
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
                    $queryBuilder->expr()->eq('url_hash', $queryBuilder->createNamedParameter(sha1($linkTarget))),
                    $queryBuilder->expr()->eq('link_type', $queryBuilder->createNamedParameter($linkType)),
                    $queryBuilder->expr()->eq('check_status', $queryBuilder->createNamedParameter(LinkTargetResponse::RESULT_BROKEN, Connection::PARAM_INT))
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
                $queryBuilder->createNamedParameter($excludeLinkTargetPid, Connection::PARAM_INT)
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
                    $queryBuilder->createNamedParameter($record['record_uid'], Connection::PARAM_INT)
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
        $record['url_hash'] = sha1($record['url']);
        try {
            $count = (int)$this->connectionPool
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
        $record['url_hash'] = sha1($record['url']);
        try {
            $this->connectionPool
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
        $connectionPool = $this->connectionPool;
        return $connectionPool->getQueryBuilderForTable($table);
    }
}
