<?php

declare(strict_types=1);

namespace Sypets\Brofix\CheckLinks;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
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

        $queryBuilder = $this->generateQueryBuilder();

        $matchConstraints = [
            // match by: exact
            $queryBuilder->expr()->and(
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
            $matchConstraints[] = $queryBuilder->expr()->and(
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
            $queryBuilder->expr()->or(...$matchConstraints)
        ];

        if ($this->excludeLinkTargetsPid !== 0) {
            $constraints[] = $queryBuilder->expr()->eq(
                'pid',
                $queryBuilder->createNamedParameter($this->excludeLinkTargetsPid, Connection::PARAM_INT)
            );
        }

        $count = (int)($queryBuilder
            ->count('uid')
            ->from(static::TABLE)
            ->where(
                ...$constraints
            )
            ->executeQuery()
            ->fetchOne());
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
            $queryBuilder = $this->generateQueryBuilder('pages');
            $row = $queryBuilder
                ->select('*')
                ->from('pages')
                ->where(
                    $queryBuilder->expr()->eq(
                        'uid',
                        $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)
                    )
                )
                ->executeQuery()
                ->fetchAssociative();

            return $GLOBALS['BE_USER']->doesUserHaveAccess($row, 16);
        }
        return false;
    }

    protected function isTableExists(): bool
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable(static::TABLE);
        if ($connection->createSchemaManager()->tablesExist([static::TABLE])) {
            return true;
        }
        return false;
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
