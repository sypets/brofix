<?php

declare(strict_types=1);

namespace Sypets\Brofix\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
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
     * @return mixed[]
     *
     * @todo additional fields added here should be added in LinkAnalyzer::getSelectFields()
     */
    public function getRowForUid(int $uid, string $table, array $fields, bool $checkHidden = false): array
    {
        // get all links for $record / $table / $field combination
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($table);
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
            ->execute()
            ->fetch();
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
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $parentId = (int)$queryBuilder
            ->select('tx_gridelements_container')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)
                )
            )
            ->execute()
            ->fetchColumn(0);

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return (bool)$queryBuilder
            ->select('hidden')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($parentId, \PDO::PARAM_INT)
                )
            )
            ->execute()
            ->fetchColumn(0);
    }
}
