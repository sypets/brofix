<?php

declare(strict_types=1);

namespace Sypets\Brofix\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * ContentRepository for tt_content and other tables
 */
class ContentRepository
{
    protected const TABLE = 'tt_content';

    /**
     * @param int $uid
     * @param string $table
     * @param array<string> $fields
     * @param bool $checkHidden
     * @return array<mixed>
     *
     * @todo additional fields added here should be added in LinkAnalyzer::getSelectFields()
     */
    public function getRowForUid(int $uid, string $table, array $fields, bool $checkHidden = false): array
    {
        // get all links for $record / $table / $field combination
        $queryBuilder = $this->generateQueryBuilder($table);
        if ($checkHidden) {
            $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);
        }

        if (isset($GLOBALS['TCA'][$table]['ctrl']['tstamp'])) {
            $fields[] = $GLOBALS['TCA'][$table]['ctrl']['tstamp'] . ' AS timestamp';
        }
        if (isset($GLOBALS['TCA'][$table]['ctrl']['label'])) {
            $fields[] = $GLOBALS['TCA'][$table]['ctrl']['label'] . ' AS header';
        }

        $result = $queryBuilder->select(...$fields)
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAssociative();
        if (!is_array($result)) {
            $result = [];
        }
        return $result;
    }

    /**
     * !!! Should already be checked if gridelemens is installed!
     *
     * @param int $uid
     * @return bool
     */
    public function isGridElementParentHidden(int $uid): bool
    {
        /**
         * @var DeletedRestriction
         */
        $deletedRestriction = GeneralUtility::makeInstance(DeletedRestriction::class);
        $queryBuilder = $this->generateQueryBuilder();
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add($deletedRestriction);

        $parentId = (int)$queryBuilder
            ->select('tx_gridelements_container')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchOne();

        /**
         * @var DeletedRestriction
         */
        $deletedRestriction = GeneralUtility::makeInstance(DeletedRestriction::class);
        $queryBuilder = $this->generateQueryBuilder(self::TABLE);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add($deletedRestriction);

        return (bool)$queryBuilder
            ->select('hidden')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($parentId, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchOne();
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
