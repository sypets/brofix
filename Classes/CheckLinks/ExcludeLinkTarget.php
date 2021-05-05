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

namespace Sypets\Brofix\CheckLinks;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class takes care of checking if a link target (URL)
 * should be excluded from checking. The URL is then always
 * handled as if it is valid.
 *
 * @internal
 */
class ExcludeLinkTarget
{
    public const MATCH_BY_EXACT = 'exact';
    public const MATCH_BY_DOMAIN = 'domain';
    public const TABLE = 'tx_brofix_exclude_link_target';

    public const REASON_NONE_GIVEN = 0;
    public const REASON_NO_BROKEN_LINK = 1;

    /**
     * @var int
     */
    protected $excludeLinkTargetsPid = 0;

    public function setExcludeLinkTargetsPid(int $pid): void
    {
        $this->excludeLinkTargetsPid = $pid;
    }

    public function getByUid(int $uid): array
    {
        if ($uid === 0) {
            return [];
        }
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(static::TABLE);
        return $queryBuilder
            ->select('*')
            ->from(static::TABLE)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT))
            )
            ->execute()
            ->fetch();
    }

    /**
     * Check if an URL is in the exclude list
     *
     * @param string $url - check if this URL is in exclude list
     * @param string $linkType
     * @return bool
     */
    public function isExcluded(string $url, string $linkType='external'): bool
    {
        if (!$this->isTableExists()) {
            return false;
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(static::TABLE);

        $matchConstraints = [
            // match by: exact
            $queryBuilder->expr()->andX(
                $queryBuilder->expr()->eq(
                    'linktarget',
                    $queryBuilder->createNamedParameter($url)
                ),
                $queryBuilder->expr()->eq(
                    'match',
                    $queryBuilder->createNamedParameter(self::MATCH_BY_EXACT)
                )
            )
        ];

        $url = html_entity_decode($url);
        $parts = parse_url($url);
        if ($parts['host'] ?? false) {
            // match by: domain
            $matchConstraints[] = $queryBuilder->expr()->andX(
                $queryBuilder->expr()->like(
                    'linktarget',
                    $queryBuilder->createNamedParameter($parts['host'])
                ),
                $queryBuilder->expr()->eq(
                    'match',
                    $queryBuilder->createNamedParameter(self::MATCH_BY_DOMAIN)
                )
            );
        }
        $constraints = [
            $queryBuilder->expr()->eq('link_type', $queryBuilder->createNamedParameter($linkType)),
            $queryBuilder->expr()->orX(...$matchConstraints)
        ];

        if ($this->excludeLinkTargetsPid !== 0) {
            $constraints[] = $queryBuilder->expr()->eq(
                'pid',
                $queryBuilder->createNamedParameter($this->excludeLinkTargetsPid, \PDO::PARAM_INT)
            );
        }

        $count = (int)($queryBuilder
            ->count('uid')
            ->from(static::TABLE)
            ->where(
                ...$constraints
            )
            ->execute()
            ->fetchColumn(0));
        return $count > 0;
    }

    /**
     * Check if current user has permission to create a record for
     * table self::TABLE on page $pageId.
     */
    public function currentUserHasCreatePermissions(int $pageId): bool
    {
        if ($GLOBALS['BE_USER']->isAdmin()) {
            return true;
        }
        if ($GLOBALS['BE_USER']->check('tables_modify', static::TABLE)

        ) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('pages');
            $row = $queryBuilder
                ->select('*')
                ->from('pages')
                ->where(
                    $queryBuilder->expr()->eq(
                        'uid',
                        $queryBuilder->createNamedParameter($pageId, \PDO::PARAM_INT)
                    )
                )
                ->execute()
                ->fetch();

            return $GLOBALS['BE_USER']->doesUserHaveAccess($row, 16);
        }
        return false;
    }

    protected function isTableExists(): bool
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable(static::TABLE);
        if ($connection->getSchemaManager()->tablesExist(static::TABLE)) {
            return true;
        }
        return false;
    }
}
