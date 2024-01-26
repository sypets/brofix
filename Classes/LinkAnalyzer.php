<?php

declare(strict_types=1);

namespace Sypets\Brofix;

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

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Sypets\Brofix\CheckLinks\CheckLinksStatistics;
use Sypets\Brofix\CheckLinks\ExcludeLinkTarget;
use Sypets\Brofix\Configuration\Configuration;
use Sypets\Brofix\Linktype\AbstractLinktype;
use Sypets\Brofix\Parser\LinkParser;
use Sypets\Brofix\Repository\BrokenLinkRepository;
use Sypets\Brofix\Repository\ContentRepository;
use Sypets\Brofix\Repository\PagesRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Handles link checking
 * @internal This class may be heavily refactored in the future!
 */
class LinkAnalyzer implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Array of tables and fields to search for broken links
     *
     * @var array<string,array<string>>
     */
    protected $searchFields = [];

    /**
     * List of page uids (rootline downwards)
     *
     * @var array<string|int>
     */
    protected array $pids = [];

    protected ?Configuration $configuration = null;
    protected BrokenLinkRepository $brokenLinkRepository;
    protected ContentRepository $contentRepository;
    protected PagesRepository $pagesRepository;

    /**
     * @var CheckLinksStatistics|null
     */
    protected ?CheckLinksStatistics $statistics = null;

    protected LinkParser $linkParser;

    public function __construct(
        BrokenLinkRepository $brokenLinkRepository,
        ContentRepository $contentRepository,
        PagesRepository $pagesRepository
    ) {
        $this->getLanguageService()->includeLLFile('EXT:brofix/Resources/Private/Language/Module/locallang.xlf');
        $this->brokenLinkRepository = $brokenLinkRepository;
        $this->contentRepository = $contentRepository;
        $this->pagesRepository = $pagesRepository;
    }

    /**
     * @param array<string|int> $pidList
     * @param Configuration $configuration
     */
    public function init(array $pidList, Configuration $configuration): void
    {
        $this->configuration = $configuration;

        $this->searchFields = $this->configuration->getSearchFields();
        $this->pids = $pidList;
        $this->statistics = new CheckLinksStatistics();
        $this->linkParser = LinkParser::initialize($this->configuration);
    }

    /**
     * Recheck the URL (without using link target cache). If valid, remove existing broken links records.
     * If still invalid, check if link still exists in record. If not, remove from list of broken links.
     *
     * @param string $message
     * @param mixed[] $record
     * @return int Number of broken link records removed
     *
     * @todo make message more flexible, broken link records could get removed or changed
     */
    public function recheckUrl(string &$message, array $record, ServerRequestInterface $request): int
    {
        $message = '';
        $url = $record['url'];
        $linkType = $record['linkType'];
        $table = $record['table'];

        $linktypeObject = $this->configuration->getLinktypeObject($linkType);
        if ($linktypeObject) {
            // get fresh result for URL
            $mode = AbstractLinktype::CHECK_LINK_FLAG_NO_CRAWL_DELAY | AbstractLinktype::CHECK_LINK_FLAG_NO_CACHE
                | AbstractLinktype::CHECK_LINK_FLAG_SYNCHRONOUS;
            $result = $linktypeObject->checkLink($url, [], $mode);
            if ($result === true) {
                // URL is ok, remove broken link records
                $count = $this->brokenLinkRepository->removeBrokenLinksForLinkTarget(
                    $url,
                    $linkType,
                    ExcludeLinkTarget::MATCH_BY_EXACT,
                    -1
                );
                $message = sprintf(
                    $this->getLanguageService()->getLL('list.recheck.url.ok.removed'),
                    $url,
                    $count
                );
                return $count;
            }
            if ($record) {
                // check if url still exists in record
                $uid = (int)$record['uid'];
                $results = [];
                $selectFields = $this->getSelectFields($table, [$record['field']]);
                $row = $this->contentRepository->getRowForUid($uid, $table, $selectFields);
                $this->linkParser->findLinksForRecord(
                    $results,
                    $table,
                    [$record['field']],
                    $row,
                    $request,
                    LinkParser::MASK_CONTENT_CHECK_ALL
                );
                $urls = [];
                foreach ($results[$linkType] ?? [] as $entryValue) {
                    $pageWithAnchor = $entryValue['pageAndAnchor'] ?? '';
                    if (!empty($pageWithAnchor)) {
                        // Page with anchor, e.g. 18#1580
                        $urls[] = $pageWithAnchor;
                    } else {
                        $urls[] = $entryValue['substr']['tokenValue'];
                    }
                }

                if (!in_array($url, $urls)) {
                    // url not in record, remove broken link record
                    $count = $this->brokenLinkRepository->removeForRecordUrl(
                        $record['table'],
                        $uid,
                        $url,
                        $linkType
                    );
                    $message = sprintf(
                        $this->getLanguageService()->getLL('list.recheck.url.notok.removed'),
                        $url,
                        $count
                    );
                    return $count;
                }
            }

            // URL is not ok, update records (error type may have changed)
            $response = [
                    'valid' => false,
                    'errorParams' => $linktypeObject->getErrorParams()->toArray()
                ];
            $brokenLinkRecord = [];
            $brokenLinkRecord['url'] = $url;
            $brokenLinkRecord['url_response'] = json_encode($response) ?: '';
            // last_check reflects time of last check (is now because URL not fetched from cache)
            $brokenLinkRecord['last_check_url'] = \time();
            $brokenLinkRecord['last_check'] = \time();
            $identifier = [
                    'url' => $url,
                    'link_type' => $linkType
                ];
            $count = $this->brokenLinkRepository->updateBrokenLink($brokenLinkRecord, $identifier);
            $message = sprintf(
                $this->getLanguageService()->getLL('list.recheck.url.notok.updated'),
                $url,
                $count
            );
            return $count;
        }
        if ($message === '') {
            $message = sprintf(
                $this->getLanguageService()->getLL('list.recheck.url'),
                $url
            );
        }
        return 0;
    }

    /**
     * Recheck for broken links for one field in table for record.
     *
     * This will not use a crawl delay.
     *
     * This will recheck all URLs in the record using link target cache.
     *
     * @param array<string> $linkTypes
     * @param int $recordUid uid of record to check
     * @param string $table
     * @param string $field
     * @param int $beforeEditedTimestamp - when was record last edited, only recheck if timestamp changed
     * @param bool $checkHidden
     * @return bool return true if checked
     */
    public function recheckLinks(
        string &$message,
        array $linkTypes,
        int $recordUid,
        string $table,
        string $field,
        int $beforeEditedTimestamp,
        ServerRequestInterface $request,
        bool $checkHidden = false
    ): bool {
        $selectFields = $this->getSelectFields($table, [$field]);
        $row = $this->contentRepository->getRowForUid($recordUid, $table, $selectFields, $checkHidden);

        $startTime = \time();

        if (!$row) {
            // missing record: remove existing links
            $message = sprintf($this->getLanguageService()->getLL('list.recheck.message.removed'), $recordUid);
            // remove existing broken links from table
            $this->brokenLinkRepository->removeBrokenLinksForRecordBeforeTime($table, $recordUid, $startTime);
            return true;
        }
        $header = $row['header'] ?: $recordUid;

        if ($beforeEditedTimestamp && isset($row['timestamp']) && $beforeEditedTimestamp >= (int)$row['timestamp']) {
            // if timestamp of record is not after $beforeEditedTimestamp: no need to recheck
            $message = $this->getLanguageService()->getLL('list.recheck.message.notchanged');
            if ($message) {
                $message = sprintf($message, $header);
            }
            return false;
        }
        $resultsLinks = [];
        $this->linkParser->findLinksForRecord(
            $resultsLinks,
            $table,
            [$field],
            $row,
            $request,
            LinkParser::MASK_CONTENT_CHECK_ALL-LinkParser::MASK_CONTENT_CHECK_IF_EDITABLE_FIELD
        );

        if ($resultsLinks) {
            $flags = AbstractLinktype::CHECK_LINK_FLAG_NO_CRAWL_DELAY | AbstractLinktype::CHECK_LINK_FLAG_SYNCHRONOUS;
            // find all broken links for list of links
            $this->checkLinks($resultsLinks, $linkTypes, $flags);
        }
        // remove existing broken links from table
        $this->brokenLinkRepository->removeBrokenLinksForRecordBeforeTime($table, $recordUid, $startTime);
        $message = sprintf($this->getLanguageService()->getLL('list.recheck.message.checked'), $header);
        return true;
    }

    /**
     * @param mixed[] $links
     * @param array<string> $linkTypes
     */
    protected function checkLinks(array $links, array $linkTypes, int $mode = 0): void
    {
        if (!$links) {
            return;
        }

        foreach ($this->configuration->getLinktypeObjects() as $key => $linktypeObject) {
            if (!is_array($links[$key] ?? false) || (!in_array($key, $linkTypes, true))) {
                continue;
            }

            //  Check them
            foreach ($links[$key] as $entryKey => $entryValue) {
                $table = $entryValue['table'];
                $record = [];

                $row = $entryValue['row'];
                $headline = BackendUtility::getProcessedValue(
                    $table,
                    $GLOBALS['TCA'][$table]['ctrl']['label'],
                    $entryValue['row'][$GLOBALS['TCA'][$table]['ctrl']['label']] ?? '',
                    0,
                    false,
                    false,
                    $row['uid'],
                    false
                );
                $headline = trim((string)$headline);

                $record['headline'] = $headline;

                if (isset($GLOBALS['TCA'][$table]['ctrl']['languageField'])
                    && isset($row[$GLOBALS['TCA'][$table]['ctrl']['languageField']])) {
                    $record['language'] = (int)($row[$GLOBALS['TCA'][$table]['ctrl']['languageField']]);
                } else {
                    $record['language'] = -1;
                }

                $record['record_pid'] = (int)$entryValue['row']['pid'];
                $record['record_uid'] = (int)$entryValue['uid'];
                $record['table_name'] = $table;
                $record['link_type'] = $key;
                $record['link_title'] = $entryValue['link_title'] ?? '';
                $record['field'] = $entryValue['field'];
                $typeField = $GLOBALS['TCA'][$table]['ctrl']['type'] ?? false;
                if ($entryValue['row'][$typeField] ?? false) {
                    $record['element_type'] = $entryValue['row'][$typeField];
                }
                $record['exclude_link_targets_pid'] = $this->configuration->getExcludeLinkTargetStoragePid();
                $pageWithAnchor = $entryValue['pageAndAnchor'] ?? '';
                if (!empty($pageWithAnchor)) {
                    // Page with anchor, e.g. 18#1580
                    $url = $pageWithAnchor;
                } else {
                    $url = $entryValue['substr']['tokenValue'] ?? '';
                }
                $record['url'] = (string)$url;

                $this->debug("checkLinks: before checking $url");
                $checkUrl = $linktypeObject->checkLink((string)$url, $entryValue, $mode);
                $this->debug("checkLinks: after checking $url");

                if ($linktypeObject->isExcludeUrl()) {
                    $this->statistics->incrementCountExcludedLinks();
                }

                // Broken link found
                if (!$checkUrl) {
                    $response = [
                        'valid' => false,
                        'errorParams' => $linktypeObject->getErrorParams()->toArray()
                    ];
                    $record['url_response'] = json_encode($response) ?: '';
                    // last_check reflects time of last check (may be older if URL was in cache)
                    $record['last_check_url'] = $linktypeObject->getLastChecked() ?: \time();
                    $record['last_check'] = \time();
                    $this->brokenLinkRepository->insertOrUpdateBrokenLink($record);
                    $this->statistics->incrementCountBrokenLinks();
                } elseif (GeneralUtility::_GP('showalllinks')) {
                    $response = ['valid' => true];
                    $record['url_response'] = json_encode($response) ?: '';
                    $record['last_check_url'] = $linktypeObject->getLastChecked() ?: \time();
                    $record['last_check'] = \time();
                    $this->brokenLinkRepository->insertOrUpdateBrokenLink($record);
                }
            }
        }
    }

    /**
     * Find all supported broken links and store them in tx_brofix_broken_links
     *
     * @param ServerRequestInterface $request
     * @param array<int,string> $linkTypes List of link types to check (corresponds to hook object)
     * @param bool $considerHidden Defines whether to look into hidden fields
     */
    public function generateBrokenLinkRecords(ServerRequestInterface $request, array $linkTypes = [], bool $considerHidden = false): void
    {
        if (empty($linkTypes) || empty($this->pids)) {
            return;
        }

        $checkStart = \time();
        $this->statistics->initialize();
        $this->statistics->setCountPages((int)count($this->pids));

        // Traverse all configured tables
        foreach ($this->searchFields as $table => $fields) {
            // If table is not configured, assume the extension is not installed
            // and therefore no need to check it
            if (!is_array($GLOBALS['TCA'][$table])) {
                continue;
            }

            $max = (int)($this->brokenLinkRepository->getMaxBindParameters() /2 - 4);
            foreach (array_chunk($this->pids, $max)
                     as $pageIdsChunk) {
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getQueryBuilderForTable($table);

                if ($considerHidden) {
                    $queryBuilder->getRestrictions()
                        ->removeAll()
                        ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
                }

                $selectFields = $this->getSelectFields($table, $fields);

                if ($table === 'pages') {
                    $constraints = [
                        $queryBuilder->expr()->in(
                            'uid',
                            $queryBuilder->createNamedParameter($pageIdsChunk, Connection::PARAM_INT_ARRAY)
                        )
                    ];
                } else {
                    // if table is not 'pages', we join with 'pages' table to exclude content elements on pages with
                    // some doktype (e.g. 3 or 4), see Configuration::getDoNotCheckContentOnPagesDoktypes
                    $constraints = [
                        $queryBuilder->expr()->in(
                            $table . '.pid',
                            $queryBuilder->createNamedParameter($pageIdsChunk, Connection::PARAM_INT_ARRAY)
                        )
                    ];
                    $queryBuilder->join(
                        $table,
                        'pages',
                        'p',
                        $queryBuilder->expr()->eq('p.uid', $queryBuilder->quoteIdentifier($table . '.pid'))
                    );
                    foreach ($this->configuration->getDoNotCheckContentOnPagesDoktypes() as $doktype) {
                        $constraints[] = $queryBuilder->expr()->neq(
                            'p.doktype',
                            $queryBuilder->createNamedParameter($doktype, \PDO::PARAM_INT)
                        );
                    }

                    $tmpFields = [];
                    foreach ($selectFields as $field) {
                        $tmpFields[] = $table . '.' . $field . ' AS ' . $field;
                    }
                    // add l18n_cfg to check for option: Hide default language of page
                    $tmpFields[] = 'p.l18n_cfg';
                    $selectFields = $tmpFields;
                }

                $queryBuilder->select(...$selectFields)
                    ->from($table)
                    ->where(
                        ...$constraints
                    );

                $result = $queryBuilder->executeQuery();
                while ($row = $result->fetchAssociative()) {
                    $results = [];

                    if ($this->isRecordsOnPageShouldBeChecked($table, $row) === false) {
                        continue;
                    }
                    $this->linkParser->findLinksForRecord(
                        $results,
                        $table,
                        $fields,
                        $row,
                        $request,
                        LinkParser::MASK_CONTENT_CHECK_ALL - LinkParser::MASK_CONTENT_CHECK_IF_RECORDs_ON_PAGE_SHOULD_BE_CHECKED
                    );
                    $this->statistics->addCountLinks($this->countLinks($results));
                    $this->checkLinks($results, $linkTypes);
                }
            }
        }
        // remove all broken links for pages / linktypes before this check
        $this->brokenLinkRepository->removeAllBrokenLinksForPagesBeforeTime($this->pids, $linkTypes, $checkStart);

        $this->statistics->calculateStats();
    }

    /**
     * Return standard fields which should be selected
     *
     * @param string $table
     * @param array<string> $selectFields
     * @return array<string>
     */
    protected function getSelectFields(string $table, array $selectFields = []): array
    {
        $defaultFields = [
            'uid',
            'pid'
        ];
        if ($GLOBALS['TCA']['tt_content']['ctrl']['versioningWS'] ?? false) {
            $defaultFields[] = 't3ver_wsid';
        }
        if (isset($GLOBALS['TCA'][$table]['ctrl']['label'])) {
            $defaultFields[] = $GLOBALS['TCA'][$table]['ctrl']['label'];
        }
        if (isset($GLOBALS['TCA'][$table]['ctrl']['languageField'])) {
            $defaultFields[] = $GLOBALS['TCA'][$table]['ctrl']['languageField'];
        }
        if ($table === 'tt_content') {
            $defaultFields[] = 'colPos';
            $defaultFields[] = 'CType';
        }
        foreach ($selectFields as $field) {
            // field must have TCA configuration
            if (!isset($GLOBALS['TCA'][$table]['columns'][$field])
                && ($key = array_search($field, $selectFields)) !== false) {
                unset($selectFields[$key]);
            }
        }
        $selectFields = array_merge($defaultFields, $selectFields);

        return $selectFields;
    }

    /**
     * Check if records should be checked by checking the page
     *
     * - is not hidden
     * - is not doktype=3 or 4
     * - is not record of default language and cfg_l18n is 1 or 3
     *
     * @param string $table
     * @param array<mixed> $record
     * @return bool
     *
     * @todo can this already be handledwhen fetching the pagetree in PagesRepository::getPageList
     * e.g. when checking l18n_cfg
     */
    public function isRecordsOnPageShouldBeChecked(string $table, array $record): bool
    {
        if ($table === 'pages') {
            return true;
        }
        $pageUid = $record['pid'] ?? 0;
        if ($pageUid === 0) {
            return false;
        }

        // todo: this is inefficient can we pass these fields for the page in $record (we are joining with pages before)
        // we need pages.(doktype|l18n_cfg)
        // todo: we need also the language, can be determined previously and passed as parameter
        $pageRow = BackendUtility::getRecord('pages', $pageUid, '*', '', false);
        if (!$pageRow) {
            return false;
        }
        $doktype = (int)($pageRow['doktype'] ?? 0);
        if ($doktype === 3 || $doktype === 4) {
            return false;
        }
        $l18nCfg = (int)($pageRow['l18n_cfg'] ?? 0);
        $languageField =  $GLOBALS['TCA'][$table]['ctrl']['languageField'] ?? '';
        $lang = 0;
        if ($languageField) {
            $lang = (int)($record[$languageField] ?? 0);
        }
        if ((($l18nCfg & 1) === 1) && $lang === 0) {
            return false;
        }
        // todo: this is inefficient, we should not need to do this as the page tree has already been fetched
        // however fetching the page list does not check if current start page is subpage of hidden / extendToSubpages
        // should be fixed there
        if ($this->pagesRepository->getRootLineIsHidden($pageRow)) {
            return false;
        }
        return true;
    }

    /**
     * @param mixed[] $links
     * @return int
     */
    protected function countLinks(array $links): int
    {
        $count = 0;
        foreach ($links as $key => $values) {
            $count += count($values);
        }
        return $count;
    }

    public function getStatistics(): CheckLinksStatistics
    {
        return $this->statistics;
    }

    /**
     * @return LanguageService
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    protected function debug(string $message): void
    {
        if ($this->logger) {
            $this->logger->debug($message);
        }
    }

    protected function error(string $message): void
    {
        if ($this->logger) {
            // @extensionScannerIgnoreLine problem with ->error()
            $this->logger->error($message);
        }
    }
}
