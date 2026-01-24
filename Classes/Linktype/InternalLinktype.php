<?php

declare(strict_types=1);
namespace Sypets\Brofix\Linktype;

use Sypets\Brofix\CheckLinks\LinkTargetResponse\LinkTargetResponse;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
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

    protected const ERROR_TYPE_RECORD = 'record';

    protected const ERROR_TYPE_PARSE = 'parse';

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

    protected const ERROR_ERRNO_CONFIGURATION = 5;

    protected const ERROR_ERRNO_TABLE_MISSING = 6;
    public function __construct(private ConnectionPool $connectionPool, private Context $context,
        private readonly SiteFinder $siteFinder)
    {
    }

    /**
     * Checks a given URL + /path/filename.ext for validity
     *
     * @param string $url Url to check. Containers "<table>:<id>". If page id, can also contain anchor, e.g. "<table>:<id#c<anchor>>", legacy: only id means "pages:<id>"
     * @param mixed[] $softRefEntry: The soft reference entry which builds the context of that url
     * @param int $flags see LinktypeInterface::checkLink(), not used here
     * @return LinkTargetResponse
     *
     * @todo change method signature to also return null or make LinkTargetResponce status
     */
    public function checkLink(string $url, array $softRefEntry, int $flags = 0): LinkTargetResponse
    {
        $contentUid = 0;
        $recordUid = 0;

        if (strpos($url, ':') === false) {
            $url = 'pages:' . $url;
        }
        $matches = [];
        $customParams = [];
        if (preg_match('/([^:]*):([0-9]*)(?:#c([0-9]*))?/', $url, $matches) !== 1) {
            return LinkTargetResponse::createInstanceByError(
                self::ERROR_TYPE_PARSE,
                self::ERROR_ERRNO_TABLE_MISSING,
                '',
                '',
                []
            );
        }
        $table = $matches[1];
        $recordUid = (int)($matches[2]);
        $contentUid = (int)($matches[3] ?? 0);
        $identifier = '';
        if ($table !== 'pages') {
            $identifier = $table;
            $table = $softRefEntry['substr']['table'] ?? '';
            if (!$table) {
                $customParams = [
                    'table' => $table,
                    'identifier' => $identifier,
                    'page' => [
                        'uid'   => $recordUid
                    ]
                ];
                return LinkTargetResponse::createInstanceByError(
                    self::ERROR_TYPE_RECORD,
                    self::ERROR_ERRNO_CONFIGURATION,
                    '',
                    '',
                    $customParams
                );
            }
        }
        $customParams = [
            'table' => $table,
            'identifier' => $identifier,
            'page' => [
                'uid'   => $recordUid
            ]
        ];

        // Check if the linked page is OK
        $linkTargetResponse = $this->checkRecord($recordUid, $table, $customParams);
        if ($linkTargetResponse && !$linkTargetResponse->isOk()) {
            return $linkTargetResponse;
        }

        // keep checking
        // Check if the linked content element is OK
        if ($contentUid) {
            // Check if the content element is OK
            $linkTargetResponse = $this->checkContent($recordUid, $contentUid, $customParams);
            if ($linkTargetResponse && !$linkTargetResponse->isOk()) {
                return $linkTargetResponse;
            }
        }

        return  LinkTargetResponse::createInstanceByStatus(LinkTargetResponse::RESULT_OK, 0, $customParams);
    }

    /**
     * Checks a given page uid for validity
     *
     * @param int $pageUid Page uid to check
     * @param array<mixed> $customParams
     * @return LinkTargetResponse return null if ok
     */
    protected function checkRecord(int $pageUid, string $table, array &$customParams): ?LinkTargetResponse
    {
        $reportHiddenRecords = $this->configuration->isReportHiddenRecords();

        // check if table exists
        $connectionPool = $this->connectionPool;
        $connection = $connectionPool->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        if (!in_array($table, $connection->createSchemaManager()->listTableNames())) {
            return LinkTargetResponse::createInstanceByError(
                self::ERROR_TYPE_RECORD,
                self::ERROR_ERRNO_TABLE_MISSING,
                '',
                '',
                $customParams
            );
        }

        // Get page ID on which the content element in fact is located
        $queryBuilder = $connectionPool->getQueryBuilderForTable($table);

        $fields = [
            'uid'
        ];
        $labelField = $GLOBALS['TCA'][$table]['ctrl']['label'] ?? '';
        if ($labelField) {
            $fields[] = $labelField;
        }
        $deletedField = $GLOBALS['TCA'][$table]['ctrl']['delete'] ?? '';
        if ($deletedField) {
            $fields[] = $deletedField;
        }
        $hiddenField = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['disabled'] ?? '';
        if ($hiddenField) {
            $fields[] = $hiddenField;
        }
        $starttimeField = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['starttime'] ?? '';
        if ($starttimeField) {
            $fields[] = $starttimeField;
        }
        $endtimeField = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['endtime'] ?? '';
        if ($endtimeField) {
            $fields[] = $endtimeField;
        }

        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder
            ->select(...$fields)
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($pageUid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAssociative();

        /**
         * @var string
         */
        $errorType = '';

        /**
         * @var int
         */
        $errno = 0;

        if ($row) {
            $customParams['page'] = [
                'title' => $row[$labelField] ?? '',
                'uid'   => $row['uid']
            ];
            if (($row[$deletedField] ?? 0) == '1') {
                return LinkTargetResponse::createInstanceByError(
                    $table === 'pages' ? self::ERROR_TYPE_PAGE : self::ERROR_TYPE_RECORD,
                    self::ERROR_ERRNO_DELETED,
                    '',
                    '',
                    $customParams
                );
            }
            if ($reportHiddenRecords
                && (($row[$hiddenField] ?? 0) == '1'
                || $this->context->getPropertyFromAspect('date', 'timestamp') < (int)($row[$starttimeField] ?? 0)
                || (($row[$endtimeField] ?? false) && (int)$row[$endtimeField] < $this->context->getPropertyFromAspect('date', 'timestamp')))
            ) {
                return LinkTargetResponse::createInstanceByError(
                    $table === 'pages' ? self::ERROR_TYPE_PAGE : self::ERROR_TYPE_RECORD,
                    self::ERROR_ERRNO_HIDDEN,
                    '',
                    '',
                    $customParams
                );
            }
        } else {
            $customParams['page'] = [
               'uid' => $pageUid
            ];
            return LinkTargetResponse::createInstanceByError(
                $table === 'pages' ? self::ERROR_TYPE_PAGE : self::ERROR_TYPE_RECORD,
                self::ERROR_ERRNO_NOTEXISTING,
                '',
                '',
                $customParams
            );
        }
        return null;
    }

    /**
     * Checks a given content uid for validity
     *
     * @param int $pageUid Uid of the page to which the link is pointing
     * @param int $contentUid Uid of the content element to check
     * @param array<mixed> $customParams
     * @return LinkTargetResponse|null null on success
     */
    protected function checkContent(int $pageUid, int $contentUid, array &$customParams): ?LinkTargetResponse
    {
        $reportHiddenRecords = $this->configuration->isReportHiddenRecords();

        // Get page ID on which the content element in fact is located
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder
            ->select('uid', 'pid', 'header', 'deleted', 'hidden', 'starttime', 'endtime')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($contentUid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAssociative();

        // this content element exists
        if ($row) {
            // page ID on which this CE is in fact located.
            $correctPageID = (int)$row['pid'];
            // Check if the element is on the linked page
            // (The element might have been moved to another page)
            if ($correctPageID !== $pageUid) {
                $customParams['content'] = [
                    'uid' => $contentUid,
                    'wrongPage' => $pageUid,
                    'rightPage' => $correctPageID
                ];
                return LinkTargetResponse::createInstanceByError(
                    self::ERROR_TYPE_CONTENT,
                    self::ERROR_ERRNO_MOVED,
                    '',
                    '',
                    $customParams
                );
            }
            if ($row['deleted'] == '1') {
                // The element is located on the page to which the link is pointing
                $customParams['content'] = [
                    'title' => $row['header'],
                    'uid'   => $row['uid']
                ];
                return LinkTargetResponse::createInstanceByError(
                    self::ERROR_TYPE_CONTENT,
                    self::ERROR_ERRNO_DELETED,
                    '',
                    '',
                    $customParams
                );
            }
            if ($reportHiddenRecords
                && ($row['hidden'] == '1'
                || $this->context->getPropertyFromAspect('date', 'timestamp') < (int)$row['starttime']
                || ($row['endtime'] && (int)$row['endtime'] < $this->context->getPropertyFromAspect('date', 'timestamp')))
            ) {
                $customParams['content'] = [
                    'title' => $row['header'],
                    'uid'   => $row['uid']
                ];
                return LinkTargetResponse::createInstanceByError(
                    self::ERROR_TYPE_CONTENT,
                    self::ERROR_ERRNO_HIDDEN,
                    '',
                    '',
                    $customParams
                );
            }
        } else {
            // The content element does not exist
            $customParams['content'] = [
                'uid' => $contentUid
            ];
            return LinkTargetResponse::createInstanceByError(
                self::ERROR_TYPE_CONTENT,
                self::ERROR_ERRNO_NOTEXISTING,
                '',
                '',
                $customParams
            );
        }

        return null;
    }

    /**
     * Generates the localized error message from the error params saved from the parsing
     *
     * @param LinkTargetResponse|null $linkTargetResponse All parameters needed for the rendering of the error message
     * @return string Validation error message
     */
    public function getErrorMessage(?LinkTargetResponse $linkTargetResponse): string
    {
        if ($linkTargetResponse === null) {
            return '';
        }

        $lang = $this->getLanguageService();
        $custom = $linkTargetResponse->getCustom();

        switch ($linkTargetResponse->getErrorType()) {
            case self::ERROR_TYPE_PARSE:
                $errorPage = $lang->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.report.error.other.parse');
                break;
            case self::ERROR_TYPE_RECORD:
                switch ($linkTargetResponse->getErrno()) {
                    case self::ERROR_ERRNO_DELETED:
                        $errorPage = $lang->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.report.error.record.deleted');
                        break;
                    case self::ERROR_ERRNO_HIDDEN:
                        $errorPage = $lang->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.report.error.record.notvisible');
                        break;
                    case self::ERROR_ERRNO_CONFIGURATION:
                    case self::ERROR_ERRNO_TABLE_MISSING:
                        $identifier = $custom['identifier'] ?? '';
                        if ($identifier) {
                            $identifier = $identifier . ' ';
                        }
                        $errorPage = $identifier . $lang->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.report.error.notconfigured');
                        break;
                    default:
                        $errorPage = $lang->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.report.error.record.notexisting');
                }
                break;
            case self::ERROR_TYPE_PAGE:
                switch ($linkTargetResponse->getErrno()) {
                    case self::ERROR_ERRNO_DELETED:
                        $errorPage = $lang->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.report.error.page.deleted');
                        break;
                    case self::ERROR_ERRNO_HIDDEN:
                        $errorPage = $lang->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.report.error.page.notvisible');
                        break;
                    default:
                        $errorPage = $lang->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.report.error.page.notexisting');
                }
                break;
            case self::ERROR_TYPE_CONTENT:
                switch ($linkTargetResponse->getErrno()) {
                    case self::ERROR_ERRNO_DELETED:
                        $errorContent = $lang->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.report.error.content.deleted');
                        break;
                    case self::ERROR_ERRNO_HIDDEN:
                        $errorContent = $lang->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.report.error.content.notvisible');
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
                            $lang->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.report.error.contentmoved')
                        );
                        break;
                    default:
                        $errorContent = $lang->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.report.error.content.notexisting');
                }
                break;
        }
        if (isset($errorPage) && isset($errorContent)) {
            $response = $errorPage . ',' . $errorContent;
        } elseif (isset($errorPage)) {
            $response = $errorPage;
        } elseif (isset($errorContent)) {
            $response = $errorContent;
        } else {
            // This should not happen
            $response = $lang->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.report.noinformation');
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
        $url = $row['url'];
        if (strpos($url, ':') === false) {
            $url = 'pages:' . $url;
        }

        if (preg_match('/([^:]*):([0-9]*)(?:#c([0-9]*))?/', $url, $matches) !== 1) {
            return  '';
        }
        $table = $matches[1];
        $recordUid = (int)($matches[2]);
        $contentUid = (int)($matches[3] ?? 0);

        if ($table !== 'pages' || $recordUid === 0) {
            return '';
        }

        $siteFinder = $this->siteFinder;
        $pageId = (int)($row['table_name'] === 'pages' ? $row['record_uid'] : $row['record_pid']);

        /**
         * @var Site
         */
        $site = $siteFinder->getSiteByPageId($pageId);

        return (string)($site->getBase() . '/index.php?id=' . $recordUid);
    }

    /**
     * Text to be displayed with the Link as anchor text
     * (not the real anchor text of the Link.
     * @param mixed[] $row
     * @param array<mixed>|null $additionalConfig
     * @return string
     */
    public function getBrokenLinkText(array $row, ?array $additionalConfig = null): string
    {
        $pageTitle = $additionalConfig['page']['title'] ?? '';
        $contentTitle = $additionalConfig['content']['title'] ?? '';

        $url = $row['url'];
        if (strpos($url, ':') === false) {
            $url = 'pages:' . $url;
        }

        if (preg_match('/([^:]*):([0-9]*)(?:#c([0-9]*))?/', $url, $matches) !== 1) {
            return  '';
        }
        $table = $matches[1];
        $recordUid = (int)($matches[2]);
        $contentUid = (int)($matches[3] ?? 0);

        if ($table === 'pages') {
            $message = $this->getLanguageService()->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.report.url.page') . ':';
        } else {
            if (isset($additionalConfig['table'])) {
                $table = $additionalConfig['table'];
            }
            $message = $this->getLanguageService()->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.report.url.record');
            if ($table && ($GLOBALS['TCA'][$table]['ctrl']['title'] ?? false)) {
                $message .= sprintf(' "%s"', $this->getLanguageService()->sL($GLOBALS['TCA'][$table]['ctrl']['title']));
            }
            $message .= ':';
        }

        if ($pageTitle) {
            $message .= (' "' . $pageTitle . '"');
        }
        $message .= (' [' . $recordUid . ']');
        if ($contentUid != 0) {
            $message .=  ', ' . $this->getLanguageService()->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.report.url.element') . ':';
            if ($contentTitle) {
                $message .= ' "' . $contentTitle . '"';
            }
            $message .= (' [' . $contentUid . ']');
        }
        return $message;
    }
}
