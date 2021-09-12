<?php

declare(strict_types=1);

namespace Sypets\Brofix\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\DataHandling\History\RecordHistoryStore;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SysHistoryRepository
{
    protected const TABLE = 'sys_history';

    public function __construct()
    {
        /**
         * @var ConnectionPool $connectionPool
         */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection = $connectionPool->getConnectionForTable(static::TABLE);
    }

    /**
     * @param array<int,string> $tables
     * @param int $timestamp
     * @return array<int,array<string,string>>
     */
    public function getLastChangedRecords(array $tables, int $timestamp): array
    {
        $queryBuilder = $this->generateQueryBuilder(self::TABLE);
        return $queryBuilder
            ->select('tablename', 'recuid', 'history_data')
            ->from(self::TABLE)
            ->where(
                // removing broken link records for deleted records is already handled in DataHandler
                // so we do not consider changes with ACTION_DELETE
                $queryBuilder->expr()->neq(
                    'actiontype',
                    $queryBuilder->createNamedParameter(RecordHistoryStore::ACTION_DELETE, \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->in('tablename', $queryBuilder->createNamedParameter($tables, Connection::PARAM_STR_ARRAY)),
                $queryBuilder->expr()->gt(
                    'tstamp',
                    $queryBuilder->createNamedParameter($timestamp, \PDO::PARAM_INT)
                )
            )
            ->execute()
            ->fetchAll();
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
