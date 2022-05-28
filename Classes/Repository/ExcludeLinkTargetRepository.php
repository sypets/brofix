<?php

declare(strict_types=1);

namespace Sypets\Brofix\Repository;

use Sypets\Brofix\Controller\Filter\ManageExclusionsFilter;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ExcludeLinkTargetRepository
{
    protected const TABLE = 'tx_brofix_exclude_link_target';

    /**
     * Get Excluded links.
     *
     *
     * @param array<array<string>> $orderBy
     * @param ManageExclusionsFilter $filter
     * @return mixed[]
     */
    public function getExcludedBrokenLinks(ManageExclusionsFilter $filter, array $orderBy = []): array
    {
        $results = [];

        $queryBuilder = $this->generateQueryBuilder(self::TABLE);

        $queryBuilder
            ->select(self::TABLE . '.*')
            ->from(self::TABLE)
            ->join(
                self::TABLE,
                'pages',
                'pages',
                // @todo record_pid is not always page id
                $queryBuilder->expr()->eq(self::TABLE . '.pid', $queryBuilder->quoteIdentifier('pages.uid'))
            )
            // SQL Filter
            ->andWhere(
                $queryBuilder->expr()->like(
                    self::TABLE . '.linktarget',
                    $queryBuilder->createNamedParameter('%' . $queryBuilder->escapeLikeWildcards($filter->getExcludeUrlFilter()) . '%')
                )
            )
            ->andWhere(
                $queryBuilder->expr()->like(
                    self::TABLE . '.link_type',
                    $queryBuilder->createNamedParameter('%' . $queryBuilder->escapeLikeWildcards($filter->getExcludeLinkTypeFilter()) . '%')
                )
            );
        $storagePid = $filter->getExcludeStoragePid();
        if ($storagePid !== -1) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq(self::TABLE . '.pid', $queryBuilder->createNamedParameter($storagePid, \PDO::PARAM_INT))
            );
        }
        if ($filter->getExcludeReasonFilter() != '') {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq(self::TABLE . '.reason', $queryBuilder->createNamedParameter($filter->getExcludeReasonFilter(), \PDO::PARAM_INT))
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

        $results = array_merge($results, $queryBuilder->executeQuery()->fetchAllAssociative());
        return $results;
    }

    /**
     * Delete Exclude Link
     * @param array<int> $uids
     */
    public function deleteExcludeLink(array $uids): void
    {
        foreach ($uids as $uid) {
            $queryBuilder = $this->generateQueryBuilder(self::TABLE);
            $affectedRows = $queryBuilder
                ->delete(self::TABLE)
                ->where(
                    $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT))
                )
                ->executeStatement();
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
