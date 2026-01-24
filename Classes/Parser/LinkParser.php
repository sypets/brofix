<?php

declare(strict_types=1);
namespace Sypets\Brofix\Parser;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareTrait;
use Sypets\Brofix\Configuration\Configuration;
use Sypets\Brofix\Linktype\AbstractLinktype;
use Sypets\Brofix\Parser\SoftReference\TypolinkRecordTagSoftReferenceParser;
use Sypets\Brofix\Repository\ContentRepository;
use Sypets\Brofix\Util\TcaUtil;
use TYPO3\CMS\Backend\Form\Exception\DatabaseDefaultLanguageException;
use TYPO3\CMS\Backend\Form\FormDataCompiler;
use TYPO3\CMS\Backend\Form\FormDataGroupInterface;
use TYPO3\CMS\Core\DataHandling\SoftReference\SoftReferenceParserFactory;
use TYPO3\CMS\Core\DataHandling\SoftReference\SoftReferenceParserInterface;
use TYPO3\CMS\Core\DataHandling\SoftReference\SoftReferenceParserResult;
use TYPO3\CMS\Core\Html\HtmlParser;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Parse content for links. This class currently also checks if fields, should be parsed based on
 * - whether they are hidden / editable in the backend
 *
 * @internal
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

    protected ?ServerRequestInterface $request;
    protected ?FormDataCompiler $formDataCompiler;
    protected ?FormDataGroupInterface $formDataGroup;
    protected Configuration $configuration;
    protected SoftReferenceParserFactory $softReferenceParserFactory;
    protected ContentRepository $contentRepository;

    /**
     * @var array<mixed> $processedFormData;
     */
    protected array $processedFormData;

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

        /** @phpstan-ignore-next-line */
        $this->formDataGroup = GeneralUtility::makeInstance($this->configuration->getFormDataGroup());
        /** @phpstan-ignore-next-line */
        if ($this->formDataGroup) {
            $this->formDataCompiler = GeneralUtility::makeInstance(FormDataCompiler::class);
        }
    }

    /**
     * Find all supported broken links for a specific record
     *
     * @param mixed[] $results Array of broken links
     * @param string $table Table name of the record
     * @param array<string> $fields Array of fields to check
     * @param mixed[] $record Record to check
     * @param int $checks what checks should be performed. (Default is: all checks enabled)
     * @return array|string[] Return actual fields which will be checked
     * @throws \Throwable
     */
    public function findLinksForRecord(
        array &$results,
        string $table,
        array $fields,
        array $record,
        ServerRequestInterface $request,
        int $checks = self::MASK_CONTENT_CHECK_ALL,
    ): array {
        $this->request = $request;

        $idRecord = (int)($record['uid'] ?? 0);
        try {
            // Put together content of all relevant fields
            /** @var HtmlParser $htmlParser */
            $htmlParser = GeneralUtility::makeInstance(HtmlParser::class);

            if ($checks & self::MASK_CONTENT_CHECK_IF_RECORD_SHOULD_BE_CHECKED) {
                if ($this->isRecordShouldBeChecked($table, $record) === false) {
                    return [];
                }
            }

            $processedFormData = $this->getProcessedFormData($idRecord, $table, $request);

            if ($checks & self::MASK_CONTENT_CHECK_IF_EDITABLE_FIELD && $processedFormData) {
                $fields = $this->getEditableFields($idRecord, $table, $fields, $processedFormData);
            }
            if (!$fields) {
                return [];
            }

            $tableTca = $this->processedFormData ? $this->processedFormData['processedTca'] : $GLOBALS['TCA'][$table];

            // Get all links/references
            foreach ($fields as $field) {
                // use the processedTca to also  get overridden configuration (e.g. columnsOverrides)
                $fieldConfig = $tableTca['columns'][$field]['config'];
                $valueField = htmlspecialchars_decode((string)($record[$field]));
                if ($valueField === '') {
                    continue;
                }
                $type = $fieldConfig['type'] ?? '';
                if ($type === 'flex') {
                    $flexformFields = TcaUtil::getFlexformFieldsWithConfig($table, $field, $record, $fieldConfig);
                    foreach ($flexformFields as $flexformField => $flexformData) {
                        $valueField = htmlspecialchars_decode($flexformData['value'] ?? '');
                        $flexformFieldConfig = $flexformData['config'] ?? [];
                        if (!$valueField || !$flexformFieldConfig || !is_array($flexformFieldConfig)) {
                            continue;
                        }
                        $sofrefParserList = $this->getSoftrefParserListByField($table, $field . '.' . $flexformField, $flexformFieldConfig);

                        foreach ($sofrefParserList as $softReferenceParser) {
                            $parserResult = $softReferenceParser->parse($table, $field, $idRecord, $valueField);
                            if (!$parserResult->hasMatched()) {
                                continue;
                            }
                            if ($softReferenceParser->getParserKey() === 'typolink_tag') {
                                $this->analyzeTypoLinks($parserResult, $results, $htmlParser, $record, $field, $table, $flexformField, $flexformData['label'] ?? '');
                            } else {
                                $this->analyzeLinks($parserResult, $results, $record, $field, $table, $flexformField, $flexformData['label'] ?? '');
                            }
                        }
                    }
                } else {
                    $sofrefParserList = $this->getSoftrefParserListByField($table, $field, $fieldConfig);

                    foreach ($sofrefParserList as $softReferenceParser) {
                        $parserResult = $softReferenceParser->parse($table, $field, $idRecord, $valueField);
                        if (!$parserResult->hasMatched()) {
                            continue;
                        }
                        $parserKey = $softReferenceParser->getParserKey();
                        // ignore found references for this parser
                        // todo: make configurable
                        if ($parserKey === 'rtehtmlarea_images') {
                            continue;
                        }
                        if (in_array($softReferenceParser->getParserKey(), ['typolink_tag', 'typolink_tag_record'])) {
                            $this->analyzeTypoLinks($parserResult, $results, $htmlParser, $record, $field, $table);
                        } else {
                            $this->analyzeLinks($parserResult, $results, $record, $field, $table);
                        }
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
        } catch (\Throwable $e) {
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
        return $fields;
    }

    /**
     * Get list of softref parsers for a particular field.
     *
     * Check if a TCA configured field can contain links and assign the soft reference parser keys
     * - has soft references defined
     * - has type='link'
     *
     * @param string $table
     * @param string $fieldName
     * @param array<mixed> $fieldConfig
     * @return iterable<SoftReferenceParserInterface>
     */
    public function getSoftrefParserListByField(string $table, string $fieldName, array $fieldConfig): iterable
    {
        /**
         * @var array<int,string> $softrefParserKeys
         */
        $softrefParserKeys = [];
        if ($fieldConfig['softref'] ??  false) {
            $softref = explode(',', $fieldConfig['softref']);
        } else {
            $softref = [];
        }
        // if softref are set, use these directly (as in rich text field)
        if ($softref) {
            // e.g. typolink_tag,email[subst],url is exploded into array
            $softrefParserKeys = $softref;

            /**
             * @todo can be removed along with the Extension configuration setting  excludeSoftrefs when core
             *   bug is fixed, see https://forge.typo3.org/issues/97937
             */
            foreach ($this->configuration->getExcludeSoftrefsInFields() as $tableField) {
                if ($tableField != ($table . '.' . $fieldName)) {
                    continue;
                }
                $softrefParserKeys = array_diff($softrefParserKeys, $this->configuration->getExcludeSoftrefs());
            }
        } elseif ($fieldConfig['enableRichtext'] ?? false) {
            $softrefParserKeys = ['typolink_tag'];
        } else {
            $type = $fieldConfig['type'] ?? false;
            switch ($type) {
                case 'link':
                    $softrefParserKeys = ['typolink'];
                    break;
                case 'input':
                    // inputLink is deprecated since version 12
                    if (($fieldConfig['renderType'] ?? '') === 'inputLink') {
                        $softrefParserKeys = ['typolink'];
                    } else {
                        return [];
                    }
                    break;
                default:
                    return [];
            }
        }
        if ($softrefParserKeys === []) {
            return [];
        }

        $softRefParams = ['subst'];
        if (in_array('typolink_tag', $softrefParserKeys)) {
            $softrefParserKeys[] = 'typolink_tag_record';
            $this->softReferenceParserFactory->addParser(
                GeneralUtility::makeInstance(TypolinkRecordTagSoftReferenceParser::class),
                'typolink_tag_record'
            );
        }
        return $this->softReferenceParserFactory->getParsersBySoftRefParserList(implode(',', $softrefParserKeys), $softRefParams);
    }

    /**
     * Find all supported broken links for a specific link list
     *
     * @param SoftReferenceParserResult $parserResult findRef parsed records
     * @param mixed[] $results Array of broken links
     * @param mixed[] $record UID of the current record
     * @param string $field The current field
     * @param string $table The current table
     * @param string $flexformField = ''
     * @param string $flexformFieldLabel = ''
     */
    protected function analyzeLinks(
        SoftReferenceParserResult $parserResult,
        array &$results,
        array $record,
        string $field,
        string $table,
        string $flexformField = '',
        string $flexformFieldLabel = ''
    ): void {
        $foundLinks = [];
        $key = 0;
        $pageKey = 0;
        $type = '';
        $idRecord = 0;
        foreach ($parserResult->getMatchedElements() as $element) {
            $reference = $element['subst'] ?? [];
            $type = '';
            $idRecord = $record['uid'];

            // Type of referenced record

            $referencedRecordType = '';
            if (isset($reference['recordRef']) && strpos($reference['recordRef'], 'pages') !== false) {
                $currentR = $reference;
                // Contains number of the page
                $referencedRecordType = $reference['tokenValue'];
                $wasPage = true;
                $pageKey = $key;
            } elseif (isset($reference['recordRef']) && strpos($reference['recordRef'], 'tt_content') !== false
                && (isset($wasPage) && $wasPage === true)) {
                // if type is ce and previous was page, we extend the page link and disregard the content link
                $foundLinks[$pageKey]['pageAndAnchor'] .= '#c' . $reference['tokenValue'];
                $wasPage = false;
                continue;
            } else {
                $currentR = $reference;
            }

            if (empty($currentR) || !is_array($currentR)) {
                continue;
            }
            $foundLinks[$key] = [
                'substr' => $currentR,
                'pageAndAnchor' => $referencedRecordType,
            ];
        }

        foreach ($foundLinks as $foundLink) {
            $currentR = $foundLink['substr'];
            /** @var AbstractLinktype $linktypeObject */
            foreach ($this->configuration->getLinktypeObjects() as $key => $linktypeObject) {
                $type = $linktypeObject->fetchType($currentR, $type, $key);
                // Store the type that was found
                // This prevents overriding by internal validator
                if (!empty($type)) {
                    $currentR['type'] = $type;
                }
            }
            $key = $table . ':' . $field . ':' . $flexformField . ':' . $idRecord . ':' . $currentR['tokenID'];
            $results[$type][$key]['substr'] = $currentR;
            $results[$type][$key]['row'] = $record;
            $results[$type][$key]['table'] = $table;
            $results[$type][$key]['field'] = $field;
            $results[$type][$key]['flexformField'] = $flexformField;
            $results[$type][$key]['flexformFieldLabel'] = $flexformFieldLabel;
            $results[$type][$key]['uid'] = $idRecord;
            $results[$type][$key]['pageAndAnchor'] = $foundLink['pageAndAnchor'] ?? '';
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
     * @param string $flexformField
     * @param string $flexformFieldLabel
     */
    protected function analyzeTypoLinks(
        SoftReferenceParserResult $parserResult,
        array &$results,
        $htmlParser,
        array $record,
        $field,
        $table,
        string $flexformField = '',
        string $flexformFieldLabel = ''
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
                $reference = $element['subst'] ?? [];
                if (empty($reference['tokenID']) || substr_count($linkTags[$i], $reference['tokenID']) === 0) {
                    continue;
                }

                // Type of referenced record
                if (isset($reference['recordRef']) && strpos($reference['recordRef'], 'pages') !== false) {
                    $currentR = $reference;
                    // Contains number of the page
                    $referencedRecordType = $reference['tokenValue'];
                    $wasPage = true;
                } elseif (isset($reference['recordRef']) && strpos($reference['recordRef'], 'tt_content') !== false
                    && (isset($wasPage) && $wasPage === true)) {
                    $referencedRecordType = $referencedRecordType . '#c' . $reference['tokenValue'];
                    $wasPage = false;
                } else {
                    $currentR = $reference;
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
            $key = $table . ':' . $field . ':' . $flexformField . ':' . $idRecord . ':' . $currentR['tokenID'];
            $results[$type][$key]['substr'] = $currentR;
            $results[$type][$key]['row'] = $record;
            $results[$type][$key]['table'] = $table;
            $results[$type][$key]['field'] = $field;
            $results[$type][$key]['flexformField'] = $flexformField;
            $results[$type][$key]['flexformFieldLabel'] = $flexformFieldLabel;
            $results[$type][$key]['uid'] = $idRecord;
            $results[$type][$key]['link_title'] = $title;
            $results[$type][$key]['pageAndAnchor'] = $referencedRecordType;
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
     * @param int $uid
     * @param string $tablename
     * @return array<mixed>
     */
    public function getProcessedFormData(int $uid, string $tablename, ServerRequestInterface $request): array
    {
        if (!$this->formDataCompiler || !$this->formDataGroup) {
            return [];
        }

        // is hack
        $isAdmin = true;
        // form data processing should be performed as admin, because we do not want to apply permission checks here
        // this should be safe as it is set to admin only for the form processing and not stored
        if (!$GLOBALS['BE_USER']->isAdmin()) {
            $isAdmin = false;
            $GLOBALS['BE_USER']->user['admin'] = 1;
        }

        // check if the field to be checked will be rendered in FormEngine
        // if not, the field should not be checked for broken links because it can't be edited in BE
        $formDataCompilerInput = [
            'tableName' => $tablename,
            'vanillaUid' => $uid,
            'command' => 'edit',
            'request' => $request,
        ];

        $this->processedFormData = $this->formDataCompiler->compile($formDataCompilerInput, $this->formDataGroup);

        // undo hack
        if ($isAdmin === false) {
            $GLOBALS['BE_USER']->user['admin'] = 0;
        }

        return $this->processedFormData;
    }

    /**
     * Return the editable fields of a record (using FormEngine).
     *
     * @param int $uid
     * @param string $tablename
     * @param string[] $fields
     * @param array<mixed> $processedFormData
     * @return string[]
     */
    public function getEditableFields(int $uid, string $tablename, array $fields, array $processedFormData): array
    {
        if ($fields === []) {
            return [];
        }
        $columns = $processedFormData['processedTca']['columns'] ?? [];
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
