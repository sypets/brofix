<?php

declare(strict_types=1);
namespace Sypets\Brofix\Parser;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareTrait;
use Sypets\Brofix\Configuration\Configuration;
use Sypets\Brofix\FormEngine\FieldShouldBeChecked;
use Sypets\Brofix\Linktype\AbstractLinktype;
use Sypets\Brofix\Repository\ContentRepository;
use TYPO3\CMS\Backend\Form\Exception\DatabaseDefaultLanguageException;
use TYPO3\CMS\Backend\Form\FormDataCompiler;
use TYPO3\CMS\Core\DataHandling\SoftReference\SoftReferenceParserFactory;
use TYPO3\CMS\Core\DataHandling\SoftReference\SoftReferenceParserResult;
use TYPO3\CMS\Core\Html\HtmlParser;
use TYPO3\CMS\Core\Http\ServerRequestFactory;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Parse content for links. This class currently also checks if fields, should be parsed based on
 * - whether they are hidden / editable in the backend
 *
 * @todo possibly separate the actual parsing and checking if fields should be parsed
 */
class LinkParser
{
    use LoggerAwareTrait;

    /**
     * @var int
     */
    public const MASK_CONTENT_CHECK_IF_EDITABLE_FIELD = 1;

    /**
     * @var int
     */
    public const MASK_CONTENT_CHECK_IF_RECORD_SHOULD_BE_CHECKED = 2;

    /**
     * @var int
     */
    public const MASK_CONTENT_CHECK_IF_RECORDs_ON_PAGE_SHOULD_BE_CHECKED = 4;

    /**
     * @var int
     */
    public const MASK_CONTENT_CHECK_ALL = 0xff;
    protected FormDataCompiler $formDataCompiler;
    protected Configuration $configuration;
    protected SoftReferenceParserFactory $softReferenceParserFactory;
    protected ContentRepository $contentRepository;

    /**
     * static reference to $this (singleton)
     * @var LinkParser|null
     */
    protected static ?LinkParser $instance = null;

    public function __construct(
        ?SoftReferenceParserFactory $softReferenceParserFactory = null,
        ?ContentRepository $contentRepository = null
    ) {
        if ($softReferenceParserFactory === null) {
            $softReferenceParserFactory = GeneralUtility::makeInstance(SoftReferenceParserFactory::class);
        }
        $this->softReferenceParserFactory = $softReferenceParserFactory;
        if ($contentRepository === null) {
            $contentRepository = GeneralUtility::makeInstance(ContentRepository::class);
        }
        $this->contentRepository = $contentRepository;

        /**
         * @var FieldShouldBeChecked
         */
        $formDataGroup = GeneralUtility::makeInstance(FieldShouldBeChecked::class);
        $this->formDataCompiler = GeneralUtility::makeInstance(FormDataCompiler::class, $formDataGroup);

        self::$instance = $this;
    }

    /**
     * Serves as initialization function to inialize some objects dynamically.
     *
     * @param Configuration $configuration
     * @return LinkParser
     */
    public static function initialize(Configuration $configuration): LinkParser
    {
        if (self::$instance === null) {
            self::$instance = GeneralUtility::makeInstance(LinkParser::class);
        }
        self::$instance->setConfiguration($configuration);
        return self::$instance;
    }

    protected function setConfiguration(Configuration $configuration): void
    {
        $this->configuration = $configuration;
    }

    /**
     * Find all supported broken links for a specific record
     *
     * @param mixed[] $results Array of broken links
     * @param string $table Table name of the record
     * @param array<string> $fields Array of fields to analyze
     * @param mixed[] $record Record to analyze
     * @param int $checks what checks should be performed. (Default is: all checks enabled)
     */
    public function findLinksForRecord(
        array &$results,
        $table,
        array $fields,
        array $record,
        int $checks = self::MASK_CONTENT_CHECK_ALL,
    ): void {

        $idRecord = (int)($record['uid'] ?? 0);
        try {
            // Put together content of all relevant fields
            /** @var HtmlParser $htmlParser */
            $htmlParser = GeneralUtility::makeInstance(HtmlParser::class);

            if ($checks & self::MASK_CONTENT_CHECK_IF_RECORD_SHOULD_BE_CHECKED) {
                if ($this->isRecordShouldBeChecked($table, $record) === false) {
                    return;
                }
            }

            if ($checks & self::MASK_CONTENT_CHECK_IF_EDITABLE_FIELD) {
                $fields = $this->getEditableFields($idRecord, $table, $fields);
            }

            // Get all references
            foreach ($fields as $field) {
                $conf = $GLOBALS['TCA'][$table]['columns'][$field]['config'];
                $valueField = htmlspecialchars_decode((string)($record[$field]));
                if ($valueField === '') {
                    continue;
                }

                // Check if a TCA configured field can contain links and assign the soft reference parser keys
                // - has soft references defined (see TYPO3 Core API document)
                // - has type='link'
                /**
                 * @var array<int,string> $softrefParserKeys
                 */
                $softrefParserKeys = [];
                if (($conf['softref'] ?? false)) {
                    // e.g. typolink_tag,email[subst],url is exploded into array
                    $softrefParserKeys = explode(',', $conf['softref']);
                } else {
                    $type = $conf['type'] ?? false;
                    if ($type === 'link') {
                        $softrefParserKeys = ['typolink'];
                    }
                }
                if ($softrefParserKeys === []) {
                    continue;
                }

                /**
                 * @todo can be removed along the the Extension configuration setting  excludeSoftrefs when core
                 *   bug is fixed, see https://forge.typo3.org/issues/97937
                 */
                foreach ($this->configuration->getExcludeSoftrefsInFields() as $tableField) {
                    if ($tableField != ($table . '.' . $field)) {
                        continue;
                    }
                    $softrefParserKeys = array_diff($softrefParserKeys, $this->configuration->getExcludeSoftrefs());
                }

                $softRefParams = ['subst'];
                foreach ($this->softReferenceParserFactory->getParsersBySoftRefParserList(implode(',', $softrefParserKeys), $softRefParams) as $softReferenceParser) {
                    $parserResult = $softReferenceParser->parse($table, $field, $idRecord, $valueField);
                    if (!$parserResult->hasMatched()) {
                        continue;
                    }
                    if ($softReferenceParser->getParserKey() === 'typolink_tag') {
                        $this->analyzeTypoLinks($parserResult, $results, $htmlParser, $record, $field, $table);
                    } else {
                        $this->analyzeLinks($parserResult, $results, $record, $field, $table);
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
            // log exception with more context and throw again so that errors are not obscured
            // @extensionScannerIgnoreLine problem with ->error()
            $this->error(
                "analyzeRecord: table=$table, uid=$idRecord, exception="
                . $e->getMessage()
                . ' stack trace:'
                . $e->getTraceAsString()
            );
            throw $e;
        }
    }

    /**
     * Find all supported broken links for a specific link list
     *
     * @param SoftReferenceParserResult $parserResult findRef parsed records
     * @param mixed[] $results Array of broken links
     * @param mixed[] $record UID of the current record
     * @param string $field The current field
     * @param string $table The current table
     */
    protected function analyzeLinks(SoftReferenceParserResult $parserResult, array &$results, array $record, string $field, string $table): void
    {
        foreach ($parserResult->getMatchedElements() as $element) {
            $r = $element['subst'];
            $type = '';
            $idRecord = $record['uid'];
            if (empty($r) || !is_array($r)) {
                continue;
            }

            /** @var AbstractLinktype $linktypeObject */
            foreach ($this->configuration->getLinktypeObjects() as $key => $linktypeObject) {
                $type = $linktypeObject->fetchType($r, $type, $key);
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
     * @param SoftReferenceParserResult $parserResult findRef parsed records
     * @param mixed[] $results Array of broken links
     * @param HtmlParser $htmlParser Instance of html parser
     * @param mixed[] $record The current record
     * @param string $field The current field
     * @param string $table The current table
     */
    protected function analyzeTypoLinks(
        SoftReferenceParserResult $parserResult,
        array &$results,
        $htmlParser,
        array $record,
        $field,
        $table
    ): void {
        $currentR = [];
        $linkTags = $htmlParser->splitIntoBlock('a,link', $parserResult->getContent());
        $idRecord = $record['uid'];
        $type = '';
        $title = '';
        $countLinkTags = count($linkTags);
        for ($i = 1; $i < $countLinkTags; $i += 2) {
            $referencedRecordType = '';
            foreach ($parserResult->getMatchedElements() as $element) {
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

            // @todo Should be checked why it could be that $currentR stays empty which breaks further processing with
            //       chained PHP array access errors in hooks fetchType() and the $result[] build lines below. Further
            //       $currentR could be overwritten in the inner loop, thus not checking all elements.
            if (empty($currentR)) {
                continue;
            }

            /** @var AbstractLinktype $linktypeObject */
            foreach ($this->configuration->getLinktypeObjects() as $key => $linktypeObject) {
                $type = $linktypeObject->fetchType($currentR, $type, $key);
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
     * When checking links, there are several criteria for records / fields
     * which should not be checked.
     *
     * These are records / fields which
     * - are not rendered in the FE
     * - excluded from checking
     *
     * @param string $tablename
     * @param array<mixed> $row
     * @return bool
     */
    public function isRecordShouldBeChecked(string $tablename, array $row): bool
    {
        if ($this->isVisibleFrontendRecord($tablename, $row) === false) {
            return false;
        }

        if ($tablename === 'tt_content') {
            $excludedCtypes = $this->configuration->getExcludedCtypes();
            if ($excludedCtypes !== [] && ($row['CType'] ?? false)) {
                if (in_array($row['CType'], $excludedCtypes)) {
                    return false;
                }
            }
        }

        // Check if the element is on WS
        if ($this->configuration->getDoNotCheckLinksOnWorkspace() == true) {
            $workspaceId = (int)($row['t3ver_wsid'] ?? 0);
            if ($workspaceId !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return the editable fields of a record (using FormEngine).
     *
     * @param int $uid
     * @param string $tablename
     * @param string[] $fields
     * @return string[]
     */
    public function getEditableFields(int $uid, string $tablename, array $fields): array
    {
        if ($fields === []) {
            return [];
        }
        // check if the field to be checked will be rendered in FormEngine
        // if not, the field should not be checked for broken links because it can't be edited in BE
        $formDataCompilerInput = [
            'tableName' => $tablename,
            'vanillaUid' => $uid,
            'command' => 'edit',
        ];

        // we need TcaColumnsProcessShowitem
        $formData = $this->formDataCompiler->compile($formDataCompilerInput);
        $columns = $formData['processedTca']['columns'] ?? [];
        if ($columns === []) {
            return [];
        }
        foreach ($fields as $key => $field) {
            if (!isset($columns[$field])) {
                unset($fields[$key]);
            }
        }
        return $fields;
    }

    /**
     * Check if a record is visible in the Frontend. This concerns whether
     * a record has the "hidden" field set, but also considers other factors
     * such as if the element is in a hidden gridelement.
     *
     * This function does not
     *
     * @param string $tablename
     * @param array<mixed> $row
     * @return bool
     */
    public function isVisibleFrontendRecord(string $tablename, array $row): bool
    {
        $uid = (int)($row['uid'] ?? 0);
        if ($row['hidden'] ?? false) {
            return false;
        }
        // if gridelements and in gridelement, check if parent is hidden
        if ($tablename === 'tt_content'
            && ((int)($row['colPos'] ?? 0)) == -1
            && ExtensionManagementUtility::isLoaded('gridelements')
            && $this->contentRepository->isGridElementParentHidden($uid)
        ) {
            return false;
        }
        return true;
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
