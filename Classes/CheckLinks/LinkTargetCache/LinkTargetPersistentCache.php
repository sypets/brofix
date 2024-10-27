<?php

declare(strict_types=1);
namespace Sypets\Brofix\CheckLinks\LinkTargetCache;

use Sypets\Brofix\CheckLinks\LinkTargetResponse\LinkTargetResponse;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class implements a persistent link target cache using
 * a database table.
 * @internal
 */
class LinkTargetPersistentCache extends AbstractLinkTargetCache
{
    protected const TABLE = 'tx_brofix_link_target_cache';

    const CHECK_STATUS_NONE = 0;
    const CHECK_STATUS_OK = 1;
    const CHECK_STATUS_ERROR = 2;

    /**
     * Check if url exists in link cache (and is not expired)
     */
    public function hasEntryForUrl(string $linkTarget, string $linkType, bool $useExpire = true, int $expire = 0): bool
    {
        $queryBuilder = $this->generateQueryBuilder();

        $constraints = [
            $queryBuilder->expr()->eq('url', $queryBuilder->createNamedParameter($linkTarget)),
            $queryBuilder->expr()->eq('link_type', $queryBuilder->createNamedParameter($linkType)),
        ];

        if ($useExpire) {
            $expire = $expire ?: $this->expire;
            $constraints[] = $queryBuilder->expr()->neq(
                'last_check',
                $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
            );
            $constraints[] = $queryBuilder->expr()->gt(
                'last_check',
                $queryBuilder->createNamedParameter(\time()-$expire, Connection::PARAM_INT)
            );
        }

        return (int)$queryBuilder
            ->count('uid')
            ->from(static::TABLE)
            ->where(...$constraints)
            ->executeQuery()
            ->fetchOne() > 0;
    }

    /**
     * Get result of link check
     *
     * @param string $linkTarget
     * @param string $linkType
     * @param int $expire (optional, default is 0, in that case uses $this->expire)
     * @return LinkTargetResponse|null
     */
    public function getUrlResponseForUrl(string $linkTarget, string $linkType, int $expire = 0): ?LinkTargetResponse
    {
        $expire = $expire ?: $this->expire;
        $queryBuilder = $this->generateQueryBuilder();
        $queryBuilder
            ->select('url_response', 'last_check')
            ->from(static::TABLE)
            ->where(
                $queryBuilder->expr()->eq('url', $queryBuilder->createNamedParameter($linkTarget)),
                $queryBuilder->expr()->eq('link_type', $queryBuilder->createNamedParameter($linkType)),
                $queryBuilder->expr()->neq('last_check', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->gt('last_check', $queryBuilder->createNamedParameter(\time()-$expire, Connection::PARAM_INT))
            );
        $row = $queryBuilder
            ->executeQuery()
            ->fetchAssociative();
        if (!$row) {
            return null;
        }
        return LinkTargetResponse::createInstanceFromJson($row['url_response']);
    }

    /**
     * Insert result / update existing result
     * @param string $linkTarget
     * @param string $linkType
     * @param LinkTargetResponse $linkTargetResponse
     */
    public function setResult(string $linkTarget, string $linkType, LinkTargetResponse $linkTargetResponse): void
    {
        if ($this->hasEntryForUrl($linkTarget, $linkType, false)) {
            $this->update($linkTarget, $linkType, $linkTargetResponse);
        } else {
            $this->insert($linkTarget, $linkType, $linkTargetResponse);
        }
    }

    /**
     * @param string $linkTarget
     * @param string $linkType
     * @param LinkTargetResponse $linkTargetResponse
     */
    protected function insert(string $linkTarget, string $linkType, LinkTargetResponse $linkTargetResponse): void
    {
        $queryBuilder = $this->generateQueryBuilder();
        $queryBuilder
            ->insert(static::TABLE)
            ->values(
                [
                    'url' => $linkTarget,
                    'link_type' => $linkType,
                    'url_response' => $linkTargetResponse->toJson(),
                    'check_status' => $linkTargetResponse->getStatus(),
                    'last_check' => \time()
                ]
            )
            ->executeStatement();
    }

    protected function update(string $linkTarget, string $linkType, LinkTargetResponse $linkTargetResponse): void
    {
        $queryBuilder = $this->generateQueryBuilder();
        $queryBuilder
            ->update(static::TABLE)
            ->where(
                $queryBuilder->expr()->eq('url', $queryBuilder->createNamedParameter($linkTarget)),
                $queryBuilder->expr()->eq('link_type', $queryBuilder->createNamedParameter($linkType))
            )
            ->set('url_response', $linkTargetResponse->toJson())
            ->set('check_status', (string)$linkTargetResponse->getStatus())
            ->set('last_check', (string)\time())
            ->executeStatement();
    }

    public function remove(string $linkTarget, string $linkType): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(static::TABLE);
        $queryBuilder
            ->delete(static::TABLE)
            ->where(
                $queryBuilder->expr()->eq('url', $queryBuilder->createNamedParameter($linkTarget)),
                $queryBuilder->expr()->eq('link_type', $queryBuilder->createNamedParameter($linkType))
            )
            ->executeStatement();
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
