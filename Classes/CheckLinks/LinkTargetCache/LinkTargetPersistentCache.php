<?php

declare(strict_types=1);
namespace Sypets\Brofix\CheckLinks\LinkTargetCache;

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

use Sypets\Brofix\DoctrineDbalMethodNameHelper;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class implements a persistent link target cache using
 * a database table.
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
                $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)
            );
            $constraints[] = $queryBuilder->expr()->gt(
                'last_check',
                $queryBuilder->createNamedParameter(\time()-$expire, \PDO::PARAM_INT)
            );
        }

        return $queryBuilder
            ->count('uid')
            ->from(static::TABLE)
            ->where(...$constraints)
            ->execute()
            ->{DoctrineDbalMethodNameHelper::fetchOne()}() ? true : false;
    }

    /**
     * Get result of link check
     *
     * @param string $linkTarget
     * @param string $linkType
     * @param int $expire (optional, default is 0, in that case uses $this->expire)
     * @return mixed[] returns URL response as array or
     *   empty array if no entry
     */
    public function getUrlResponseForUrl(string $linkTarget, string $linkType, int $expire = 0): array
    {
        $expire = $expire ?: $this->expire;
        $queryBuilder = $this->generateQueryBuilder();
        $queryBuilder
            ->select('url_response', 'last_check')
            ->from(static::TABLE)
            ->where(
                $queryBuilder->expr()->eq('url', $queryBuilder->createNamedParameter($linkTarget)),
                $queryBuilder->expr()->eq('link_type', $queryBuilder->createNamedParameter($linkType)),
                $queryBuilder->expr()->neq('last_check', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                $queryBuilder->expr()->gt('last_check', $queryBuilder->createNamedParameter(\time()-$expire, \PDO::PARAM_INT))
            );
        $row = $queryBuilder
            ->execute()
            ->{DoctrineDbalMethodNameHelper::fetchAssociative()}();
        if (!$row) {
            return [];
        }
        $urlResponse = json_decode($row['url_response'], true);
        $urlResponse['lastChecked'] = (int)$row['last_check'];
        return $urlResponse;
    }

    /**
     * Insert result / update existing result
     * @param string $linkTarget
     * @param string $linkType
     * @param mixed[] $urlResponse
     */
    public function setResult(string $linkTarget, string $linkType, array $urlResponse): void
    {
        $checkStatus = $urlResponse['valid'] ? self::CHECK_STATUS_OK : self::CHECK_STATUS_ERROR;
        if ($this->hasEntryForUrl($linkTarget, $linkType, false)) {
            $this->update($linkTarget, $linkType, $urlResponse, $checkStatus);
        } else {
            $this->insert($linkTarget, $linkType, $urlResponse, $checkStatus);
        }
    }

    /**
     * @param string $linkTarget
     * @param string $linkType
     * @param mixed[] $urlResponse
     * @param int $checkStatus
     */
    protected function insert(string $linkTarget, string $linkType, array $urlResponse, int $checkStatus): void
    {
        $queryBuilder = $this->generateQueryBuilder();
        $queryBuilder
            ->insert(static::TABLE)
            ->values(
                [
                    'url' => $linkTarget,
                    'link_type' => $linkType,
                    'url_response' => \json_encode($urlResponse),
                    'check_status' => $checkStatus,
                    'last_check' => \time()
                ]
            )
            ->execute();
    }

    /**
     * @param string $linkTarget
     * @param string $linkType
     * @param mixed[] $urlResponse
     * @param int $checkStatus
     */
    protected function update(string $linkTarget, string $linkType, array $urlResponse, int $checkStatus): void
    {
        $queryBuilder = $this->generateQueryBuilder();
        $queryBuilder
            ->update(static::TABLE)
            ->where(
                $queryBuilder->expr()->eq('url', $queryBuilder->createNamedParameter($linkTarget)),
                $queryBuilder->expr()->eq('link_type', $queryBuilder->createNamedParameter($linkType))
            )
            ->set('url_response', \json_encode($urlResponse))
            ->set('check_status', (string)$checkStatus)
            ->set('last_check', (string)\time())
            ->execute();
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
            ->execute();
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
