<?php

// @todo
//declare(strict_types=1);

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

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Sypets\Brofix\CheckLinks\CheckLinksStatistics;
use Sypets\Brofix\CheckLinks\ExcludeLinkTarget;
use Sypets\Brofix\Configuration\Configuration;
use Sypets\Brofix\FormEngine\FieldShouldBeChecked;
use Sypets\Brofix\Linktype\AbstractLinktype;
use Sypets\Brofix\Repository\BrokenLinkRepository;
use Sypets\Brofix\Repository\ContentRepository;
use TYPO3\CMS\Backend\Form\Exception\DatabaseDefaultLanguageException;
use TYPO3\CMS\Backend\Form\FormDataCompiler;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Html\HtmlParser;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;

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
     * @var array
     */
    protected $searchFields = [];

    /**
     * List of page uids (rootline downwards)
     *
     * @var array
     */
    protected $pids = [];

    /**
     * Array for hooks for own checks
     *
     * @var \Sypets\Brofix\Linktype\AbstractLinktype[]
     */
    protected $hookObjectsArr = [];

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var BrokenLinkRepository
     */
    protected $brokenLinkRepository;

    /**
     * @var ContentRepository
     */
    protected $contentRepository;

    /**
     * @var FormDataCompiler
     */
    protected $formDataCompiler;

    /**
     * Statistics
     *
     * @var array
     *
     * @deprecated
     */
    protected $stats;

    /**
     * @var CheckLinksStatistics
     */
    protected $statistics;

    /**
     * Fill hookObjectsArr with different link types and possible XClasses.
     */
    public function __construct(
        BrokenLinkRepository $brokenLinkRepository = null,
        ContentRepository $contentRepository = null
    ) {
        $this->getLanguageService()->includeLLFile('EXT:brofix/Resources/Private/Language/Module/locallang.xlf');
        $this->brokenLinkRepository = $brokenLinkRepository ?: GeneralUtility::makeInstance(BrokenLinkRepository::class);
        $this->contentRepository = $contentRepository ?: GeneralUtility::makeInstance(ContentRepository::class);

        // Hook to handle own checks
        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks'] ?? [] as $key => $className) {
            $this->hookObjectsArr[$key] = GeneralUtility::makeInstance($className);
        }
        $formDataGroup = GeneralUtility::makeInstance(FieldShouldBeChecked::class);
        $this->formDataCompiler = GeneralUtility::makeInstance(FormDataCompiler::class, $formDataGroup);
    }

    public function init(array $searchField, array $pidList, Configuration $configuration = null): void
    {
        $this->configuration = $configuration ?: GeneralUtility::makeInstance(Configuration::class);
        $this->searchFields = $searchField ?: $this->configuration->getSearchFields();
        $this->pids = $pidList;
        $this->statistics = new CheckLinksStatistics();

        foreach ($this->hookObjectsArr as $key => $value) {
            $value->setConfiguration($this->configuration);
        }
    }

    /**
     * Recheck the URL (without using link target cache). If valid, remove existing broken links records.
     * If still invalid, check if link still exists in record. If not, remove from list of broken links.
     *
     * @param string $message
     * @param array $record
     * @return int Number of broken link records removed
     *
     * @todo make message more flexible, broken link records could get removed or changed
     */
    public function recheckUrl(string &$message, array $record): int
    {
        $message = '';
        $url = $record['url'];
        $linkType = $record['linkType'];
        if ($this->hookObjectsArr[$linkType] ?? false) {
            $hookObj = $this->hookObjectsArr[$linkType];
            // get fresh result for URL
            $mode = AbstractLinktype::CHECK_LINK_FLAG_NO_CRAWL_DELAY | AbstractLinktype::CHECK_LINK_FLAG_NO_CACHE
                | AbstractLinktype::CHECK_LINK_FLAG_SYNCHRONOUS;
            $result = $hookObj->checkLink($url, [], $mode);
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
                $selectFields = $this->getSelectFields($record['table'], [$record['field']]);
                $row = $this->contentRepository->getRowForUid($uid, $record['table'], $selectFields);
                $this->findLinksForRecord(
                    $results,
                    $record['table'],
                    [$record['field']],
                    $row,
                    true
                );
                $urls = [];
                foreach ($results[$linkType] ?? [] as $entryValue) {
                    $pageWithAnchor = $entryValue['pageAndAnchor'];
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
                    'errorParams' => $hookObj->getErrorParams()->toArray()
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
     * @param array $linkTypes
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
        bool $checkHidden = false
    ): bool {
        $selectFields = $this->getSelectFields($table, [$field]);
        $row = $this->contentRepository->getRowForUid($recordUid, $table, $selectFields, $checkHidden);

        $header = $row['header'] ?: $recordUid;
        if (!$row) {
            // missing record: remove existing links
            $message = sprintf($this->getLanguageService()->getLL('list.recheck.message.removed'), $header);
            return true;
        }
        if ($beforeEditedTimestamp && isset($row['timestamp']) && $beforeEditedTimestamp >= (int)$row['timestamp']) {
            // if timestamp of record is not after $beforeEditedTimestamp: no need to recheck
            $message = $this->getLanguageService()->getLL('list.recheck.message.notchanged');
            if ($message) {
                $message = sprintf($message, $header);
            }
            return false;
        }
        $resultsLinks = [];
        $this->findLinksForRecord($resultsLinks, $table, [$field], $row, false);
        $startTime = \time();
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
     * @param array $links
     * @param array<int,string> $linkTypes
     */
    protected function checkLinks(array $links, array $linkTypes, int $mode = 0): void
    {
        foreach ($this->hookObjectsArr as $key => $hookObj) {
            if (!is_array($links[$key]) || (!in_array($key, $linkTypes, true))) {
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
                $headline = trim($headline);

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
                $record['link_title'] = $entryValue['link_title'];
                $record['field'] = $entryValue['field'];
                $record['exclude_link_targets_pid'] = $this->configuration->getExcludeLinkTargetStoragePid();
                $pageWithAnchor = $entryValue['pageAndAnchor'];
                if (!empty($pageWithAnchor)) {
                    // Page with anchor, e.g. 18#1580
                    $url = $pageWithAnchor;
                } else {
                    $url = $entryValue['substr']['tokenValue'];
                }
                $record['url'] = $url;

                $this->debug("checkLinks: before checking $url");
                $checkUrl = $hookObj->checkLink($url, $entryValue, $mode);
                $this->debug("checkLinks: after checking $url");

                if ($hookObj->isExcludeUrl()) {
                    $this->statistics->incrementCountExcludedLinks();
                }

                // Broken link found
                if (!$checkUrl) {
                    $response = [
                        'valid' => false,
                        'errorParams' => $hookObj->getErrorParams()->toArray()
                    ];
                    $record['url_response'] = json_encode($response) ?: '';
                    // last_check reflects time of last check (may be older if URL was in cache)
                    $record['last_check_url'] = $hookObj->getLastChecked() ?: \time();
                    $record['last_check'] = \time();
                    $this->brokenLinkRepository->insertOrUpdateBrokenLink($record);
                    $this->statistics->incrementCountBrokenLinks();
                } elseif (GeneralUtility::_GP('showalllinks')) {
                    $response = ['valid' => true];
                    $record['url_response'] = json_encode($response) ?: '';
                    $record['last_check_url'] = $hookObj->getLastChecked() ?: \time();
                    $record['last_check'] = \time();
                    $this->brokenLinkRepository->insertOrUpdateBrokenLink($record);
                }
            }
        }
    }

    /**
     * Find all supported broken links and store them in tx_brofix_broken_links
     *
     * @param array<int,string> $linkTypes List of link types to check (corresponds to hook object)
     * @param bool $considerHidden Defines whether to look into hidden fields
     */
    public function generateBrokenLinkRecords(array $linkTypes = [], $considerHidden = false): void
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

                // move db query to ContentRepository, unify handling of selectFields in getSelectFields as 'AS' below
                if ($table === 'pages') {
                    $constraints = [
                        $queryBuilder->expr()->in(
                            'uid',
                            $queryBuilder->createNamedParameter($pageIdsChunk, Connection::PARAM_INT_ARRAY)
                        )
                    ];
                } else {
                    // if table is not 'pages', we join with 'pages' table to exclude content elements on pages with doktype=3 or 4
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
                    $constraints[] = $queryBuilder->expr()->neq('p.doktype', $queryBuilder->createNamedParameter(3, \PDO::PARAM_INT));
                    $constraints[] =$queryBuilder->expr()->neq('p.doktype', $queryBuilder->createNamedParameter(4, \PDO::PARAM_INT));
                    if ($table === 'tt_content') {
                        $excludedCtypes = $this->configuration->getExcludedCtypes();
                        if ($excludedCtypes !== []) {
                            $constraints[] =$queryBuilder->expr()->notIn('CType', $queryBuilder->createNamedParameter($excludedCtypes, Connection::PARAM_STR_ARRAY));
                        }
                    }
                    $tmpFields = [];
                    foreach ($selectFields as $field) {
                        $tmpFields[] = $table . '.' . $field . ' AS ' . $field;
                    }
                    $selectFields = $tmpFields;
                }

                $queryBuilder->select(...$selectFields)
                    ->from($table)
                    ->where(
                        ...$constraints
                    );

                $result = $queryBuilder
                    ->execute();

                while ($row = $result->fetch()) {
                    $results = [];
                    $this->findLinksForRecord($results, $table, $fields, $row);
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
     */
    protected function getSelectFields(string $table, array $selectFields = []): array
    {
        $defaultFields = [
            'uid',
            'pid'
        ];
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
        return array_merge($defaultFields, $selectFields);
    }

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
     * Find all supported broken links for a specific record
     *
     * @param array $results Array of broken links
     * @param string $table Table name of the record
     * @param array $fields Array of fields to analyze
     * @param array $record Record to analyze
     * @param bool $checkIfEditable should check if field is editable, this can be skipped if the information
     *        is already available (for performance reasons)
     */
    public function findLinksForRecord(
        array &$results,
        $table,
        array $fields,
        array $record,
        bool $checkIfEditable = true
    ): void {
        $idRecord = (int)($record['uid'] ?? 0);
        try {
            list($results, $record) = $this->emitBeforeAnalyzeRecordSignal($results, $record, $table, $fields);

            // Put together content of all relevant fields
            /** @var HtmlParser $htmlParser */
            $htmlParser = GeneralUtility::makeInstance(HtmlParser::class);

            if ($checkIfEditable) {
                // check if the field to be checked will be rendered in FormEngine
                // if not, the field should not be checked for broken links because it can't be edited in BE
                $formDataCompilerInput = [
                    'tableName' => $table,
                    'vanillaUid' => $idRecord,
                    'command' => 'edit',
                ];
                // we need TcaColumnsProcessShowitem
                $formData = $this->formDataCompiler->compile($formDataCompilerInput);
                $columns = $formData['processedTca']['columns'] ?? [];

                // if gridelements and in gridelement, check if parent is hidden
                if ($table === 'tt_content'
                    && ((int)($record['colPos'] ?? 0)) == -1
                    && ExtensionManagementUtility::isLoaded('gridelements')
                    && $this->contentRepository->isGridElementParentHidden($idRecord)
                ) {
                    return;
                }
            }

            // Get all references
            foreach ($fields as $field) {
                if ($checkIfEditable && !isset($columns[$field])) {
                    continue;
                }

                $conf = $GLOBALS['TCA'][$table]['columns'][$field]['config'];
                $valueField = htmlspecialchars_decode((string)($record[$field]));

                // Check if a TCA configured field has soft references defined (see TYPO3 Core API document)
                if (!$conf['softref'] || (string)$valueField === '') {
                    continue;
                }

                // Explode the list of soft references/parameters
                $softRefs = BackendUtility::explodeSoftRefParserList($conf['softref']);
                if ($softRefs === false) {
                    continue;
                }

                // Traverse soft references
                foreach ($softRefs as $spKey => $spParams) {
                    /** @var \TYPO3\CMS\Core\Database\SoftReferenceIndex $softRefObj */
                    $softRefObj = BackendUtility::softRefParserObj($spKey);

                    // If there is an object returned...
                    if (!is_object($softRefObj)) {
                        continue;
                    }
                    $softRefParams = $spParams;
                    if (!is_array($softRefParams)) {
                        // set subst such that findRef will return substitutes for urls, emails etc
                        $softRefParams = ['subst' => true];
                    }

                    // Do processing
                    $resultArray = $softRefObj->findRef($table, $field, $idRecord, $valueField, $spKey, $softRefParams);
                    if (empty($resultArray['elements'])) {
                        continue;
                    }

                    if ($spKey === 'typolink_tag') {
                        $this->analyzeTypoLinks($resultArray, $results, $htmlParser, $record, $field, $table);
                    } else {
                        $this->analyzeLinks($resultArray, $results, $record, $field, $table);
                    }
                }
            }
        } catch (DatabaseDefaultLanguageException $e) {
            // @extensionScannerIgnoreLine problem with ->error()
            $this->error(
                "analyzeRecord: table=$table, uid=$idRecord, DatabaseDefaultLanguageException:"
                . $e->getMessage()
                . ' stack trace:'
                . $e->getTraceAsString()
            );
        } catch (\Exception | \Throwable $e) {
            // @extensionScannerIgnoreLine problem with ->error()
            $this->error(
                "analyzeRecord: table=$table, uid=$idRecord, exception="
                . $e->getMessage()
                . ' stack trace:'
                . $e->getTraceAsString()
            );
        }
    }

    /**
     * Find all supported broken links for a specific link list
     *
     * @param array $resultArray findRef parsed records
     * @param array $results Array of broken links
     * @param array $record UID of the current record
     * @param string $field The current field
     * @param string $table The current table
     */
    protected function analyzeLinks(array $resultArray, array &$results, array $record, string $field, string $table): void
    {
        foreach ($resultArray['elements'] as $element) {
            $r = $element['subst'];
            $type = '';
            $idRecord = $record['uid'];
            if (empty($r)) {
                continue;
            }

            /** @var \Sypets\Brofix\Linktype\AbstractLinktype $hookObj */
            foreach ($this->hookObjectsArr as $keyArr => $hookObj) {
                $type = $hookObj->fetchType($r, $type, $keyArr);
                // Store the type that was found
                // This prevents overriding by internal validator
                if (!empty($type)) {
                    $r['type'] = $type;
                }
            }
            $results[$type][$table . ':' . $field . ':' . $idRecord . ':' . $r['tokenID']]['substr'] = $r;
            $results[$type][$table . ':' . $field . ':' . $idRecord . ':' . $r['tokenID']]['row'] = $record;
            $results[$type][$table . ':' . $field . ':' . $idRecord . ':' . $r['tokenID']]['table'] = $table;
            $results[$type][$table . ':' . $field . ':' . $idRecord . ':' . $r['tokenID']]['field'] = $field;
            $results[$type][$table . ':' . $field . ':' . $idRecord . ':' . $r['tokenID']]['uid'] = $idRecord;
        }
    }

    /**
     * Find all supported broken links for a specific typoLink
     *
     * @param array $resultArray findRef parsed records
     * @param array $results Array of broken links
     * @param HtmlParser $htmlParser Instance of html parser
     * @param array $record The current record
     * @param string $field The current field
     * @param string $table The current table
     */
    protected function analyzeTypoLinks(
        array $resultArray,
        array &$results,
        $htmlParser,
        array $record,
        $field,
        $table
    ): void {
        $currentR = [];
        $linkTags = $htmlParser->splitIntoBlock('a,link', $resultArray['content']);
        $idRecord = $record['uid'];
        $type = '';
        $title = '';
        $countLinkTags = count($linkTags);
        for ($i = 1; $i < $countLinkTags; $i += 2) {
            $referencedRecordType = '';
            foreach ($resultArray['elements'] as $element) {
                $type = '';
                $r = $element['subst'];
                if (empty($r['tokenID']) || substr_count($linkTags[$i], $r['tokenID']) === 0) {
                    continue;
                }

                // Type of referenced record
                if (isset($r['recordRef']) && strpos($r['recordRef'], 'pages') !== false) {
                    $currentR = $r;
                    // Contains number of the page
                    $referencedRecordType = $r['tokenValue'];
                    $wasPage = true;
                } elseif (isset($r['recordRef']) && strpos($r['recordRef'], 'tt_content') !== false
                    && (isset($wasPage) && $wasPage === true)) {
                    $referencedRecordType = $referencedRecordType . '#c' . $r['tokenValue'];
                    $wasPage = false;
                } else {
                    $currentR = $r;
                }
                $title = strip_tags($linkTags[$i]);
            }
            /** @var \Sypets\Brofix\Linktype\AbstractLinktype $hookObj */
            foreach ($this->hookObjectsArr as $keyArr => $hookObj) {
                $type = $hookObj->fetchType($currentR, $type, $keyArr);
                // Store the type that was found
                // This prevents overriding by internal validator
                if (!empty($type)) {
                    $currentR['type'] = $type;
                }
            }
            $results[$type][$table . ':' . $field . ':' . $idRecord . ':' . $currentR['tokenID']]['substr'] = $currentR;
            $results[$type][$table . ':' . $field . ':' . $idRecord . ':' . $currentR['tokenID']]['row'] = $record;
            $results[$type][$table . ':' . $field . ':' . $idRecord . ':' . $currentR['tokenID']]['table'] = $table;
            $results[$type][$table . ':' . $field . ':' . $idRecord . ':' . $currentR['tokenID']]['field'] = $field;
            $results[$type][$table . ':' . $field . ':' . $idRecord . ':' . $currentR['tokenID']]['uid'] = $idRecord;
            $results[$type][$table . ':' . $field . ':' . $idRecord . ':' . $currentR['tokenID']]['link_title'] = $title;
            $results[$type][$table . ':' . $field . ':' . $idRecord . ':' . $currentR['tokenID']]['pageAndAnchor'] = $referencedRecordType;
        }
    }

    /**
     * Emits a signal before the record is analyzed
     *
     * @param array $results Array of broken links
     * @param array $record Record to analyze
     * @param string $table Table name of the record
     * @param array $fields Array of fields to analyze
     * @return array
     *
     * @todo convert to PSR-14 events, signal/slot is deprecated
     * @deprecated signal/slot is deprecated
     */
    protected function emitBeforeAnalyzeRecordSignal($results, $record, $table, $fields): array
    {
        return $this->getSignalSlotDispatcher()->dispatch(
            self::class,
            'beforeAnalyzeRecord',
            [$results, $record, $table, $fields, $this]
        );
    }

    /**
     * @return Dispatcher
     *
     * @todo convert to PSR-14 events, signal/slot is deprecated
     * @deprecated signal/slot is deprecated

     */
    protected function getSignalSlotDispatcher()
    {
        return $this->getObjectManager()->get(Dispatcher::class);
    }

    /**
     * @return ObjectManager
     *
     * @deprecated
     */
    protected function getObjectManager(): ObjectManager
    {
        return GeneralUtility::makeInstance(ObjectManager::class);
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
        $this->logger->debug($message);
    }

    protected function error(string $message): void
    {
        // @extensionScannerIgnoreLine problem with ->error()
        $this->logger->error($message);
    }
}
