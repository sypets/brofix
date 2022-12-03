<?php

declare(strict_types=1);
namespace Sypets\Brofix\Linktype;

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

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class provides Check Internal Links plugin implementation
 */
class InternalLinktype extends AbstractLinktype
{
    /**
     * @var string
     */
    protected const ERROR_TYPE_PAGE = 'page';

    /**
     * @var string
     */
    protected const ERROR_TYPE_CONTENT = 'content';

    /**
     * @var int
     */
    protected const ERROR_ERRNO_DELETED = 1;

    /**
     * @var int
     */
    protected const ERROR_ERRNO_HIDDEN = 2;

    /**
     * @var int
     */
    protected const ERROR_ERRNO_MOVED = 3;

    /**
     * @var int
     */
    protected const ERROR_ERRNO_NOTEXISTING = 4;

    /**
     * Result of the check, if the current page uid is valid or not
     *
     * @var bool
     */
    protected $responsePage = true;

    /**
     * Result of the check, if the current content uid is valid or not
     *
     * @var bool
     */
    protected $responseContent = true;

    /**
     * Checks a given URL + /path/filename.ext for validity
     *
     * @param string $url Url to check as page-id or page-id#anchor (if anchor is present)
     * @param mixed[] $softRefEntry: The soft reference entry which builds the context of that url
     * @param int $flags see LinktypeInterface::checkLink(), not used here
     * @return bool TRUE on success or FALSE on error
     */
    public function checkLink(string $url, array $softRefEntry, int $flags = 0): bool
    {
        $this->initializeErrorParams();

        $contentUid = 0;
        $pageUid = 0;
        $this->responseContent = true;

        // Only check pages records. Content elements will also be checked
        // as we extract the contentUid in the next step.
        if ($softRefEntry) {
            [$table, $uid] = explode(':', $softRefEntry['substr']['recordRef']);
        } else {
            $table = 'pages';
        }
        if (!in_array($table, ['pages', 'tt_content'], true)) {
            return true;
        }
        // Defines the linked page and contentUid (if any).
        if (strpos($url, '#c') !== false) {
            $parts = explode('#c', $url);
            $pageUid = (int)($parts[0]);
            $contentUid = (int)($parts[1]);
        } elseif (
            $table === 'tt_content'
            && strpos($softRefEntry['row'][$softRefEntry['field']], 't3://') === 0
        ) {
            $parsedTypoLinkUrl = @parse_url($softRefEntry['row'][$softRefEntry['field']]);
            if ($parsedTypoLinkUrl['host'] === 'page') {
                parse_str($parsedTypoLinkUrl['query'], $query);
                if (isset($query['uid'])) {
                    $page = (int)$query['uid'];
                    $contentUid = (int)$url;
                }
            }
        } else {
            $pageUid = (int)($url);
        }
        // Check if the linked page is OK
        $this->responsePage = $this->checkPage($pageUid);
        // Check if the linked content element is OK
        if ($contentUid) {
            // Check if the content element is OK
            $this->responseContent = $this->checkContent($pageUid, $contentUid);
        }

        return  $this->responsePage && $this->responseContent;
    }

    /**
     * Checks a given page uid for validity
     *
     * @param int $pageUid Page uid to check
     * @return bool TRUE on success or FALSE on error
     */
    protected function checkPage(int $pageUid): bool
    {
        $reportHiddenRecords = $this->configuration->isReportHiddenRecords();

        // Get page ID on which the content element in fact is located
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder
            ->select('uid', 'title', 'deleted', 'hidden', 'starttime', 'endtime')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($pageUid, \PDO::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAssociative();

        $customParams = [];

        /**
         * @var string
         */
        $errorType = '';

        /**
         * @var int
         */
        $errno = 0;

        if ($row) {
            if ($row['deleted'] == '1') {
                $errorType = self::ERROR_TYPE_PAGE;
                $errno = self::ERROR_ERRNO_DELETED;

                $customParams = [
                    'page' => [
                        'title' => $row['title'],
                        'uid'   => $row['uid']
                    ]
                ];
            } elseif ($reportHiddenRecords
                && ($row['hidden'] == '1'
                || $GLOBALS['EXEC_TIME'] < (int)$row['starttime']
                || ($row['endtime'] && (int)$row['endtime'] < $GLOBALS['EXEC_TIME']))
            ) {
                $errorType = self::ERROR_TYPE_PAGE;
                $errno = self::ERROR_ERRNO_HIDDEN;
            }

            if ($errorType !== '') {
                $customParams = [
                    'page' => [
                        'title' => $row['title'],
                        'uid'   => $row['uid']
                    ]
                ];
            }
        } else {
            $errorType = self::ERROR_TYPE_PAGE;
            $errno = self::ERROR_ERRNO_NOTEXISTING;
            $customParams = [
                'page' => [
                    'uid' => $pageUid
                ]
            ];
        }

        if ($customParams) {
            $customParams['page']['errno'] = $errno;
            $this->errorParams->setCustom($customParams);
        }
        $this->errorParams->setErrorType($errorType);
        $this->errorParams->setErrno($errno);

        return $errorType === '';
    }

    /**
     * Checks a given content uid for validity
     *
     * @param int $pageUid Uid of the page to which the link is pointing
     * @param int $contentUid Uid of the content element to check
     * @return bool TRUE on success or FALSE on error
     */
    protected function checkContent(int $pageUid, int $contentUid): bool
    {
        $reportHiddenRecords = $this->configuration->isReportHiddenRecords();

        // Get page ID on which the content element in fact is located
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder
            ->select('uid', 'pid', 'header', 'deleted', 'hidden', 'starttime', 'endtime')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($contentUid, \PDO::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAssociative();
        $this->responseContent = true;

        $customParams = [];

        /**
         * @var string
         */
        $errorType = '';

        /**
         * @var int
         */
        $errno = 0;

        // this content element exists
        if ($row) {
            // page ID on which this CE is in fact located.
            $correctPageID = (int)$row['pid'];
            // Check if the element is on the linked page
            // (The element might have been moved to another page)
            if ($correctPageID !== $pageUid) {
                $errorType = self::ERROR_TYPE_CONTENT;
                $errno = self::ERROR_ERRNO_MOVED;

                $customParams = [
                    'content' => [
                        'uid' => $contentUid,
                        'wrongPage' => $pageUid,
                        'rightPage' => $correctPageID
                    ]
                ];
            } elseif ($row['deleted'] == '1') {
                // The element is located on the page to which the link is pointing
                $errorType = self::ERROR_TYPE_CONTENT;
                $errno = self::ERROR_ERRNO_DELETED;
                $customParams = [
                    'content' => [
                        'title' => $row['header'],
                        'uid'   => $row['uid']
                    ]
                ];
            } elseif ($reportHiddenRecords
                && ($row['hidden'] == '1'
                || $GLOBALS['EXEC_TIME'] < (int)$row['starttime']
                || ($row['endtime'] && (int)$row['endtime'] < $GLOBALS['EXEC_TIME']))
            ) {
                $errorType = self::ERROR_TYPE_CONTENT;
                $errno = self::ERROR_ERRNO_HIDDEN;
                $customParams = [
                    'content' => [
                        'title' => $row['header'],
                        'uid'   => $row['uid']
                    ]
                ];
            }
        } else {
            // The content element does not exist
            $errorType = self::ERROR_TYPE_CONTENT;
            $errno = self::ERROR_ERRNO_NOTEXISTING;
            $customParams = [
                'content' => [
                    'uid' => $contentUid
                ]
            ];
        }

        if ($customParams) {
            $customParams['content']['errno'] = $errno;
            $this->errorParams->addCustom($customParams);
        }
        // error type content should not override error type page - there is only 1 main errorType
        if ($this->errorParams->getErrorType() === '') {
            $this->errorParams->setErrorType($errorType);
        }
        $this->errorParams->setErrno($errno);

        return $errorType === '';
    }

    /**
     * Generates the localized error message from the error params saved from the parsing
     *
     * @param ErrorParams $errorParams All parameters needed for the rendering of the error message
     * @return string Validation error message
     */
    public function getErrorMessage(ErrorParams $errorParams = null): string
    {
        if ($errorParams === null) {
            $errorParams = $this->errorParams;
        }

        $lang = $this->getLanguageService();
        $custom = $errorParams->getCustom();

        if ($custom['page'] ?? false) {
            switch ($custom['page']['errno']) {
                case self::ERROR_ERRNO_DELETED:
                    $errorPage = $lang->getLL('list.report.error.page.deleted');
                    break;
                case self::ERROR_ERRNO_HIDDEN:
                    $errorPage = $lang->getLL('list.report.error.page.notvisible');
                    break;
                default:
                    $errorPage = $lang->getLL('list.report.error.page.notexisting');
            }
        }
        if ($custom['content'] ?? false) {
            switch ($custom['content']['errno'] ?? false) {
                case self::ERROR_ERRNO_DELETED:
                    $errorContent = $lang->getLL('list.report.error.content.deleted');
                    break;
                case self::ERROR_ERRNO_HIDDEN:
                    $errorContent = $lang->getLL('list.report.error.content.notvisible');
                    break;
                case self::ERROR_ERRNO_MOVED:
                    $errorContent = str_replace(
                        [
                            '###title###',
                            '###uid###',
                            '###wrongpage###',
                            '###rightpage###'
                        ],
                        [
                            $custom['content']['title'] ?? '',
                            $custom['content']['uid'] ?? '',
                            $custom['content']['wrongPage'] ?? '',
                            $custom['content']['rightPage'] ?? ''
                        ],
                        $lang->getLL('list.report.error.contentmoved')
                    );
                    break;
                default:
                    $errorContent = $lang->getLL('list.report.error.content.notexisting');
            }
        }
        if (isset($errorPage) && isset($errorContent)) {
            $response = $errorPage . ',' . $errorContent;
        } elseif (isset($errorPage)) {
            $response = $errorPage;
        } elseif (isset($errorContent)) {
            $response = $errorContent;
        } else {
            // This should not happen
            $response = $lang->getLL('list.report.noinformation');
        }
        return $response;
    }

    /**
     * Constructs an URL for browser output
     *
     * @param mixed[] $row Broken link record
     * @return string url
     */
    public function getBrokenUrl(array $row): string
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $pageId = (int)($row['table_name'] === 'pages' ? $row['record_uid'] : $row['record_pid']);

        /**
         * @var Site
         */
        $site = $siteFinder->getSiteByPageId($pageId);

        return (string)($site->getBase() . '/index.php?id=' . $row['url']);
    }

    /**
     * Text to be displayed with the Link as anchor text
     * (not the real anchor text of the Link.
     * @param mixed[] $row
     * @return string
     */
    public function getBrokenLinkText(array $row, array $additionalConfig = null): string
    {
        $pageTitle = $additionalConfig['page']['title'] ?? '';
        $contentTitle = $additionalConfig['content']['title'] ?? '';
        // can be pageid and optionally "#..."
        $elements = explode('#c', $row['url']);
        $pageuid = (int)($elements[0] ?? 0);
        $contentUid = (int)($elements[1] ?? 0);

        $message = $this->getLanguageService()->getLL('list.report.url.page') . ':';
        if ($pageTitle) {
            $message .= (' "' . $pageTitle . '"');
        }
        $message .= (' [' . $pageuid . ']');
        if ($contentUid != 0) {
            $message .=  ', ' . $this->getLanguageService()->getLL('list.report.url.element') . ':';
            if ($contentTitle) {
                $message .= ' "' . $contentTitle . '"';
            }
            $message .= (' [' . $elements[1] . ']');
        }
        return $message;
    }
}
