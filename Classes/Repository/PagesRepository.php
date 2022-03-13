<?php

declare(strict_types=1);

namespace Sypets\Brofix\Repository;

use Sypets\Brofix\DoctrineDbalMethodNameHelper;
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
     * @param int $depth Depth to traverse down the page tree.
     * @param string $permsClause Perms clause
     * @param array<int,int> $excludedPages list of pages to ignore: do not return them, do not traverse into them
     * @param bool $considerHidden Whether to consider hidden pages or not
     * @param array<int,int> $excludedPages
     *
     * @return array<int,int>
     */
    protected function getAllSubpagesForPage(array &$pageList, int $id, int $depth, string $permsClause, bool $considerHidden = false, array $excludedPages = []): array
    {
        if ($depth === 0) {
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

        $result = $queryBuilder
            ->select('uid', 'hidden', 'extendToSubpages')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($id, \PDO::PARAM_INT)
                ),
                QueryHelper::stripLogicalOperatorPrefix($permsClause)
            )
            ->execute();

        while ($row = $result->{DoctrineDbalMethodNameHelper::fetchAssociative()}()) {
            $id = (int)$row['uid'];
            $isHidden = (bool)$row['hidden'];
            $extendToSubpages = (bool)($row['extendToSubpages'] ?? 0);

            if ((!$isHidden || $considerHidden) && !in_array($id, $excludedPages)) {
                $pageList[$id] = $id;
            }
            if ($depth > 1 && (!($isHidden && $extendToSubpages) || $considerHidden) && !in_array($id, $excludedPages)) {
                $this->getAllSubpagesForPage(
                    $pageList,
                    $id,
                    $depth - 1,
                    $permsClause,
                    $considerHidden,
                    $excludedPages
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
     * @param array <int,int> $pageList
     * @param int $id
     * @param int $depth
     * @param string $permsClause
     * @param array<int,int> $excludedPages
     * @param bool $considerHidden
     * @param array<int,int> $excludedPages
     *
     * @return array<int,int>
     */
    public function getPageList(array &$pageList, int $id, int $depth, string $permsClause, bool $considerHidden = false, array $excludedPages = []): array
    {
        if (in_array($id, $excludedPages)) {
            // do not add page, if in list of excluded pages
            return $pageList;
        }
        $pageList = $this->getAllSubpagesForPage(
            $pageList,
            $id,
            $depth,
            $permsClause,
            $considerHidden,
            $excludedPages
        );
        // Always add the current page
        $pageList[$id] = $id;
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
        if ($pageInfo['pid'] === 0) {
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
            ->execute()
            ->fetch();

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
            ->execute();

        while ($row = $result->{DoctrineDbalMethodNameHelper::fetchAssociative()}()) {
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
