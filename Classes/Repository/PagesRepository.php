<?php

declare(strict_types=1);

namespace Sypets\Brofix\Repository;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Handle database queries for table of broken links
 *
 * @internal
 */
class PagesRepository
{
    protected const TABLE = 'pages';

    /**
     * Generates a list of page uids. The start page is $id and this function
     * recursively traverses the page tree and adds all pages to $pageList.
     *
     * This is a helper function for getPageList in this class.
     *
     * @param array <int,int> $pageList
     * @param int $id Start page id
     * @param bool $useStartPage Check and add the page id itself
     * @param int $depth Depth to traverse down the page tree.
     * @param string $permsClause Perms clause
     * @param array<int,int> $excludedPages list of pages to ignore: do not return them, do not traverse into them
     * @param bool $considerHidden Whether to consider hidden pages or not
     * @param array<int,int> $excludedPages
     * @param array<int,int> $doNotCheckPageTypes
     * @param array<int,int> $doNotTraversePageTypes
 *
     * @return array<int,int>
     */
    protected function getAllSubpagesForPage(
        array &$pageList,
        int $id,
        bool $useStartPage,
        int $depth,
        string $permsClause,
        bool $considerHidden = false,
        array $excludedPages = [],
        array $doNotCheckPageTypes = [],
        array $doNotTraversePageTypes = []
    ): array {
        if (!$useStartPage) {
            if ($depth === 0) {
                return $pageList;
            }
            $depth = $depth - 1;
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
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($id, \PDO::PARAM_INT)
                )
            );
        } else {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($id, \PDO::PARAM_INT)
                )
            );
        }
        $result = $queryBuilder->executeQuery();
        while ($row = $result->fetchAssociative()) {
            $id = (int)$row['uid'];
            $isHidden = (bool)$row['hidden'];
            $extendToSubpages = (bool)($row['extendToSubpages'] ?? 0);
            $doktype = (int)($row['doktype'] ?? 1);

            if ((!$isHidden || $considerHidden) && !in_array($id, $excludedPages) && !in_array($doktype, $doNotCheckPageTypes)) {
                $pageList[$id] = $id;
            }
            if ($depth > 0 && (!($isHidden && $extendToSubpages) || $considerHidden) && !in_array($id, $excludedPages)
                && !in_array($doktype, $doNotTraversePageTypes)
            ) {
                $this->getAllSubpagesForPage(
                    $pageList,
                    $id,
                    false,
                    $depth,
                    $permsClause,
                    $considerHidden,
                    $excludedPages,
                    $doNotCheckPageTypes,
                    $doNotTraversePageTypes
                );
            }
        }
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
     * @param int $id
     * @param int $depth
     * @param string $permsClause
     * @param array<int,int> $excludedPages
     * @param bool $considerHidden
     * @param array<int,int> $excludedPages
     * @param array<int,int> $doNotCheckPageTypes
     * @param array<int,int> $doNotTraversePageTypes
     *
     * @return array<int,int>
     */
    public function getPageList(
        array &$pageList,
        int $id,
        int $depth,
        string $permsClause,
        bool $considerHidden = false,
        array $excludedPages = [],
        array $doNotCheckPageTypes = [],
        array $doNotTraversePageTypes = []
    ): array {
        if (in_array($id, $excludedPages)) {
            // do not add page, if in list of excluded pages
            return $pageList;
        }
        $pageList = $this->getAllSubpagesForPage(
            $pageList,
            $id,
            true,
            $depth,
            $permsClause,
            $considerHidden,
            $excludedPages,
            $doNotCheckPageTypes,
            $doNotTraversePageTypes
        );
        $this->getTranslationForPage(
            $pageList,
            $id,
            $permsClause,
            $considerHidden
        );
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
                    $queryBuilder->createNamedParameter($pageInfo['pid'], \PDO::PARAM_INT)
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
     * @param int $currentPage
     * @param string $permsClause
     * @param bool $considerHiddenPages
     * @param int[] $limitToLanguageIds
     * @return array<int,int>
     */
    public function getTranslationForPage(
        array $pageList,
        int $currentPage,
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
            $queryBuilder->expr()->eq(
                'l10n_parent',
                $queryBuilder->createNamedParameter($currentPage, \PDO::PARAM_INT)
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
}
