<?php

declare(strict_types=1);

namespace Sypets\Brofix\Repository;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Sypets\Brofix\Cache\CacheManager;

/**
 * Handle database queries for table of broken links
 *
 * @internal
 */
class PagesRepository
{
    protected const TABLE = 'pages';

    public function __construct(
        protected CacheManager $cacheManager
    ) {

    }

    /**
     * Generates a list of page uids. The start page is $id and this function
     * recursively traverses the page tree and adds all pages to $pageList.
     *
     * This is a helper function for getPageList in this class.
     *
     * @param array <int,int> $pageList
     * @param array<int,int> $startPages Start page id
     * @param bool $useStartPage Check and add the page id itself
     * @param int $depth Depth to traverse down the page tree.
     * @param string $permsClause Perms clause
     * @param array<int,int> $excludedPages list of pages to ignore: do not return them, do not traverse into them
     * @param bool $considerHidden Whether to consider hidden pages or not
     * @param array<int,int> $excludedPages
     * @param array<int,int> $doNotCheckPageTypes
     * @param array<int,int> $doNotTraversePageTypes
     * @param int $traverseMaxNumberOfPages Maximum number of pages to traverse - hard limit
     *
     * @return array<int,int>
     */
    protected function getAllSubpagesForPage(
        array &$pageList,
        array $startPages,
        bool $useStartPage,
        int $depth,
        string $permsClause,
        bool $considerHidden = false,
        array $excludedPages = [],
        array $doNotCheckPageTypes = [],
        array $doNotTraversePageTypes = [],
        int $traverseMaxNumberOfPages = 0
    ): array {
        if (!$useStartPage) {
            if ($depth === 0) {
                return $pageList;
            }
            $depth = $depth - 1;
        }

        // we abort when limit + 1 is reached so we can determine that limit was reached and surpassed
        if ($traverseMaxNumberOfPages && count($pageList) > $traverseMaxNumberOfPages) {
            return $pageList;
        }

        $queryBuilder = $this->generateQueryBuilder('pages');
        /**
         * @var DeletedRestriction $deletedRestriction
         */
        $deletedRestriction = GeneralUtility::makeInstance(DeletedRestriction::class);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add($deletedRestriction);

        $queryBuilder
            ->select('uid', 'hidden', 'extendToSubpages', 'doktype')
            ->from('pages')
            ->where(
                QueryHelper::stripLogicalOperatorPrefix($permsClause)
            );
        if ($useStartPage) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->in(
                    'uid',
                    $queryBuilder->createNamedParameter($startPages, Connection::PARAM_INT_ARRAY)
                )
            );
        } else {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->in(
                    'pid',
                    $queryBuilder->createNamedParameter($startPages, Connection::PARAM_INT_ARRAY)
                )
            );
        }
        $result = $queryBuilder->executeQuery();
        $subpages = [];
        while ($row = $result->fetchAssociative()) {
            $id = (int)$row['uid'];
            $isHidden = (bool)$row['hidden'];
            $extendToSubpages = (bool)($row['extendToSubpages'] ?? 0);
            $doktype = (int)($row['doktype'] ?? 1);

            if ((!$isHidden || $considerHidden) && !in_array($id, $excludedPages) && !in_array($doktype,
                    $doNotCheckPageTypes)) {
                $pageList[$id] = $id;
            }
            if ($depth > 0 && (!($isHidden && $extendToSubpages) || $considerHidden) && !in_array($id, $excludedPages)
                && !in_array($doktype, $doNotTraversePageTypes)
            ) {
                $subpages[] = $id;
            }
            // we abort when limit + 1 is reached so we can determine that limit was reached and surpassed
            if ($traverseMaxNumberOfPages && count($pageList) > $traverseMaxNumberOfPages) {
                return $pageList;
            }
        }

        $this->getAllSubpagesForPage(
                    $pageList,
                    $subpages,
                    false,
                    $depth,
                    $permsClause,
                    $considerHidden,
                    $excludedPages,
                    $doNotCheckPageTypes,
                    $doNotTraversePageTypes,
                    $traverseMaxNumberOfPages
                );

        return $pageList;
    }

    /**
     * Generates an array of page uids from the page with id $id. Also adds the page $id itself.
     *
     * The collection of the list is done in 3 steps:
     * - Get subpages
     * - Add the page $id itself (check first if it should be added)
     * - Add the translations for all collected page ids
     *
     * Important: Not all checks are performed on the start page.
     *
     * @param array <int,int> $pageList
     * @param array <int,int> $startPages
     * @param int $depth
     * @param string $permsClause
     * @param array<int,int> $excludedPages
     * @param bool $considerHidden
     * @param array<int,int> $excludedPages
     * @param array<int,int> $doNotCheckPageTypes
     * @param array<int,int> $doNotTraversePageTypes
     * @param int $traverseMaxNumberOfPages
     *
     * @return array<int,int>
     */
    public function getPageList(
        array &$pageList,
        array $startPages,
        int $depth,
        string $permsClause,
        bool $considerHidden = false,
        array $excludedPages = [],
        array $doNotCheckPageTypes = [],
        array $doNotTraversePageTypes = [],
        int $traverseMaxNumberOfPages = 0,
        bool $useCache = true
    ): array {
        foreach ($startPages as $key => $startPage) {
            if (in_array($startPage, $excludedPages)) {
                // do not add page, if in list of excluded pages
                uset($startPages[$key]);
            }
        }
        if (!$startPages) {
            return $pageList;
        }

        $hash = null;
        if ($useCache && $depth > 3) {
            if ($this->getBackendUser()->isAdmin()) {
                $username = 'admin';
            } else {
                $username = $this->getBackendUsername();
            }
            $hash = md5(sprintf(
                '%d_%d_%s_%d_%s',
                implode(',', $startPages),
                $depth,
                $permsClause,
                (int)$considerHidden,
                $username
            ));
            $pids = $this->cacheManager->getObject($hash);
            if ($pids !== null) {
                $pageList = array_merge($pageList, $pids);
                return $pageList;
            }
        }

        $pageList = $this->getAllSubpagesForPage(
            $pageList,
            $startPages,
            true,
            $depth,
            $permsClause,
            $considerHidden,
            $excludedPages,
            $doNotCheckPageTypes,
            $doNotTraversePageTypes,
            $traverseMaxNumberOfPages
        );
        $this->getTranslationForPage(
            $pageList,
            $startPages,
            $permsClause,
            $considerHidden
        );

        if ($hash) {
            $this->cacheManager->setObject($hash, $pageList, 7200);
        }

        return $pageList;
    }

    /**
     * Check if rootline contains a hidden page
     *
     * @param mixed[] $pageInfo Array with uid, title, hidden, extendToSubpages from pages table
     * @return bool TRUE if rootline contains a hidden page, FALSE if not
     */
    public function getRootLineIsHidden(array $pageInfo)
    {
        if (($pageInfo['pid'] ?? 0) === 0) {
            return false;
        }

        if ($pageInfo['extendToSubpages'] == 1 && $pageInfo['hidden'] == 1) {
            return true;
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();

        $row = $queryBuilder
            ->select('uid', 'title', 'hidden', 'extendToSubpages')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($pageInfo['pid'], Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAssociative();

        if ($row !== false) {
            return $this->getRootLineIsHidden($row);
        }
        return false;
    }

    /**
     * Add page translations to list of pages
     *
     * @param array <int,int> $pageList
     * @param array <int,int> $startPages
     * @param string $permsClause
     * @param bool $considerHiddenPages
     * @param int[] $limitToLanguageIds
     * @return array<int,int>
     */
    public function getTranslationForPage(
        array $pageList,
        array $startPages,
        string $permsClause,
        bool $considerHiddenPages,
        array $limitToLanguageIds = []
    ): array {
        $queryBuilder = $this->generateQueryBuilder(self::TABLE);
        /**
         * @var HiddenRestriction $hiddenRestriction
         */
        $hiddenRestriction = GeneralUtility::makeInstance(HiddenRestriction::class);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        if (!$considerHiddenPages) {
            $queryBuilder->getRestrictions()->add($hiddenRestriction);
        }
        $constraints = [
            $queryBuilder->expr()->in(
                'l10n_parent',
                $queryBuilder->createNamedParameter($startPages, Connection::PARAM_INT_ARRAY)
            )
        ];
        if (!empty($limitToLanguageIds)) {
            $constraints[] = $queryBuilder->expr()->in(
                'sys_language_uid',
                $queryBuilder->createNamedParameter($limitToLanguageIds, Connection::PARAM_INT_ARRAY)
            );
        }
        if ($permsClause) {
            $constraints[] = QueryHelper::stripLogicalOperatorPrefix($permsClause);
        }

        $result = $queryBuilder
            ->select('uid', 'title', 'hidden')
            ->from(self::TABLE)
            ->where(...$constraints)
            ->executeQuery();

        while ($row = $result->fetchAssociative()) {
            $id = (int)$row['uid'];
            $pageList[$id] = $id;
        }

        return $pageList;
    }

    /**
     * Slightly modified version of BackendUtility::getRecordPath()
     *
     * @param int $uid
     * @param int $titleLimit
     * @return mixed[] returns Page title, rootline
     */
    public function getPagePath(int $uid, int $titleLimit = 0): array
    {
        $title = '';
        $path = '';

        // @todo this is really inefficient, because we only need the title
        $data = BackendUtility::BEgetRootLine($uid, '', true);
        foreach ($data as $record) {
            if ($record['uid'] === 0) {
                continue;
            }
            if ($title == '') {
                $title = GeneralUtility::fixed_lgd_cs(strip_tags($record['title']), $titleLimit)
                    ?: '[' . $record['uid'] . ']';
            }
            $path = '/' . strip_tags($record['title']) . $path;
        }
        return [$title, $path];
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

    protected function isAdmin(): bool
    {
        return $this->getBackendUser()->isAdmin();
    }

    /**
     * @return BackendUserAuthentication|null
     */
    protected function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }

    public function getBackendUsername(): string
    {
        $beUser = $this->getBackendUser();
        if ($beUser) {
            return $beUser->user['username'] ?? '';
        }
        return '';
    }
}
