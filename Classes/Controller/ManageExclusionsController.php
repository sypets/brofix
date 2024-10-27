<?php

declare(strict_types=1);
namespace Sypets\Brofix\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sypets\Brofix\CheckLinks\ExcludeLinkTarget;
use Sypets\Brofix\Configuration\Configuration;
use Sypets\Brofix\Controller\Filter\ManageExclusionsFilter;
use Sypets\Brofix\Repository\ExcludeLinkTargetRepository;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * @internal This class may change without further warnings or increment of major version.
 */
class ManageExclusionsController extends AbstractBrofixController
{
    /**
     * @var string
     */
    const MODULE_LANG_FILE = 'LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:';

    /** @var string */
    const TEMPLATE_NAME = 'ManageExclusions';

    protected const ORDER_BY_VALUES = [
        'page' => [
            ['pid', 'ASC'],
        ],
        'page_reverse' => [
            ['pid', 'DESC'],
        ],
        'linktarget' => [
            ['linktarget', 'ASC'],
        ],
        'linktarget_reverse' => [
            ['linktarget', 'DESC'],
        ],
        'link_type' => [
            ['link_type', 'ASC'],
        ],
        'link_type_reverse' => [
            ['link_type', 'DESC'],
        ],
        'crdate' => [
            ['crdate', 'ASC'],
        ],
        'crdate_reverse' => [
            ['crdate', 'DESC'],
        ],
        'reason' => [
            ['reason', 'ASC'],
        ],
        'reason_reverse' => [
            ['reason', 'DESC'],
        ],
    ];

    protected const ORDER_BY_DEFAULT = 'linktarget';

    protected CharsetConverter $charsetConverter;

    protected LocalizationUtility $localizationUtility;

    protected ManageExclusionsFilter $filter;

    protected string $action;

    /**
     * @var int
     */
    protected $storagePid;

    /**
     * @var ExcludeLinkTargetRepository
     */
    protected $excludeLinkTargetRepository;

    protected bool $backendUserHasPermissions = false;

    public function __construct(
        ExcludeLinkTargetRepository $excludeLinkTargetRepository = null,
        ManageExclusionsFilter $filter = null,
        ExtensionConfiguration $extensionConfiguration = null,
        ModuleTemplateFactory $moduleTemplateFactory,
        IconFactory $iconFactory = null,
        ExcludeLinkTarget $excludeLinkTarget = null,
        CharsetConverter $charsetConverter = null,
        LocalizationUtility $localizationUtility = null,
        PageRenderer $pageRenderer = null
    ) {
        $this->pageRenderer = $pageRenderer ?: GeneralUtility::makeInstance(PageRenderer::class);
        $iconFactory = $iconFactory ?: GeneralUtility::makeInstance(IconFactory::class);
        $moduleTemplateFactory = $moduleTemplateFactory;
        $excludeLinkTarget = $excludeLinkTarget ?: GeneralUtility::makeInstance(ExcludeLinkTarget::class);
        $this->excludeLinkTargetRepository = $excludeLinkTargetRepository ?: GeneralUtility::makeInstance(ExcludeLinkTargetRepository::class);
        $this->filter = $filter ?: GeneralUtility::makeInstance(ManageExclusionsFilter::class);
        $this->charsetConverter = $charsetConverter ?? GeneralUtility::makeInstance(CharsetConverter::class);
        $this->localizationUtility = $localizationUtility ?? GeneralUtility::makeInstance(LocalizationUtility::class);
        $this->orderBy = ManageExclusionsController::ORDER_BY_DEFAULT;

        $extensionConfiguration = $extensionConfiguration ?: GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $extConfArray  = $extensionConfiguration->get('brofix') ?: [];
        $configuration = GeneralUtility::makeInstance(Configuration::class, $extConfArray);

        parent::__construct(
            $configuration,
            $iconFactory,
            $moduleTemplateFactory,
            $excludeLinkTarget
        );
    }

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->moduleData = $request->getAttribute('moduleData');

        $this->initialize($request);
        $this->initializeTemplate($request);

        $this->getSettingsFromQueryParameters();
        $this->initializePageRenderer();
        if ($this->backendUserHasPermissions) {
            return $this->mainAction($this->moduleTemplate);
        }
        $this->moduleTemplate->addFlashMessage(
            $this->getLanguageService()->sL(self::MODULE_LANG_FILE . ':no.access'),
            $this->getLanguageService()->sL(self::MODULE_LANG_FILE . ':no.access.title'),
            ContextualFeedbackSeverity::ERROR
        );
        return $this->emptyAction($this->moduleTemplate);
    }

    protected function initialize(ServerRequestInterface $request): void
    {
        $this->getLanguageService()->includeLLFile('EXT:brofix/Resources/Private/Language/Module/locallang.xlf');
        $this->id = (int)($request->getParsedBody()['id'] ?? $request->getQueryParams()['id'] ?? 0);
        $this->storagePid = $this->isAdmin() ? -1 : $this->configuration->getExcludeLinkTargetStoragePid();
        $this->resolveSiteLanguages($this->id);

        if ($this->id) {
            $this->configuration->loadPageTsConfig($this->id);
            $this->backendUserHasPermissions
                = $this->excludeLinkTarget->currentUserHasCreatePermissions(
                    $this->configuration->getExcludeLinkTargetStoragePid()
                );
        } else {
            $this->backendUserHasPermissions = false;
        }
    }

    protected function initializeTemplate(ServerRequestInterface $request): void
    {
        $this->moduleTemplate = $this->moduleTemplateFactory->create($request);
        $this->moduleTemplate->makeDocHeaderModuleMenu(['id' => $this->id]);

        $this->moduleTemplate->assign('currentPage', $this->id);
    }

    protected function initializePageRenderer(): void
    {
        $pageRenderer = $this->pageRenderer;
        $pageRenderer->addCssFile('EXT:brofix/Resources/Public/Css/brofix.css', 'stylesheet', 'screen');
        $pageRenderer->addCssFile('EXT:brofix/Resources/Public/Css/brofix_manage_exclusions.css', 'stylesheet', 'screen');
        $this->pageRenderer->loadJavaScriptModule('@sypets/brofix/ManageExclusions.js');
        $pageRenderer->addInlineLanguageLabelFile('EXT:brofix/Resources/Private/Language/Module/locallang.xlf');
    }

    protected function getSettingsFromQueryParameters(): void
    {
        $this->action = $this->moduleData->get('action');

        $this->paginationCurrentPage = (int)$this->moduleData->get('paginationPage', '1');
        $this->orderBy = $this->moduleData->get('orderBy', self::ORDER_BY_DEFAULT);

        // store filter parameters in the Filter Object
        $this->filter = new ManageExclusionsFilter();
        $this->filter->setExcludeLinkTypeFilter($this->moduleData->get('excludeLinkType_filter', ''));
        $this->filter->setExcludeUrlFilter($this->moduleData->get('excludeUrl_filter', ''));
        $this->filter->setExcludeReasonFilter($this->moduleData->get('excludeReason_filter', ''));
    }

    protected function mainAction(ModuleTemplate $view): ResponseInterface
    {
        $this->initializeExclusionView();
        return $view->renderResponse('Backend/ManageExclusions');
    }

    protected function emptyAction(ModuleTemplate $view): ResponseInterface
    {
        return $view->renderResponse('Backend/ManageExclusions');
    }

    /**
     * @return array<mixed>
     */
    protected function initializeExclusions(): array
    {
        $items = [];

        $this->filter->setExcludeStoragePid($this->storagePid);

        // Get Records from the database
        $exclusions = $this->excludeLinkTargetRepository->getExcludedBrokenLinks(
            $this->filter,
            self::ORDER_BY_VALUES[$this->orderBy] ?? []
        );

        if ($exclusions) {
            $itemsPerPage = 100;
            $paginator = GeneralUtility::makeInstance(
                ArrayPaginator::class,
                $exclusions,
                $this->paginationCurrentPage,
                $itemsPerPage
            );
            $this->pagination = GeneralUtility::makeInstance(SimplePagination::class, $paginator);
            foreach ($paginator->getPaginatedItems() as $row) {
                $items[] = $this->renderTableRow($row);
            }
        } else {
            $this->pagination = null;
        }
        return [
            'items' => $items,
             'totalCount' => count($exclusions)
         ];
    }

    protected function initializeExclusionView(): void
    {
        $exclusions = $this->initializeExclusions();
        $this->moduleTemplate->assign('totalCount', $exclusions['totalCount']);
        $this->moduleTemplate->assign('title', 'Excludes Links');
        $this->moduleTemplate->assign('brokenLinks', $exclusions['items']);
        $this->moduleTemplate->assign('pagination', $this->pagination);
        $this->moduleTemplate->assign('paginationPage', $this->paginationCurrentPage ?: 1);
        $this->moduleTemplate->assign('currentPage', $this->id);
        $this->moduleTemplate->assign('hasPermission', $this->backendUserHasPermissions);

        // Table header
        $sortActions = [];
        foreach (array_keys(self::ORDER_BY_VALUES) as $key) {
            $sortActions[$key] = $this->constructBackendUri(['orderBy' => $key]);
        }
        $this->moduleTemplate->assign('sortActions', $sortActions);
        $this->moduleTemplate->assign('tableHeader', $this->getVariablesForTableHeader($sortActions));

        $this->moduleTemplate->assign('excludeUrl_filter', $this->filter->getExcludeUrlFilter());
        $this->moduleTemplate->assign('excludeLinkType_filter', $this->filter->getExcludeLinkTypeFilter());
        $this->moduleTemplate->assign('excludeReason_filter', $this->filter->getExcludeReasonFilter());
    }

    /**
     * Sets variables for the Fluid Template of the table with the Excluded Links
     * @param array<string,string> $sortActions
     * @return mixed[] variables
     */
    protected function getVariablesForTableHeader(array $sortActions): array
    {
        $languageService = $this->getLanguageService();

        $headers = [
            'page',
            'linktarget',
            'link_type',
            'crdate',
            'reason'
        ];

        $tableHeadData = [];

        foreach ($headers as $key) {
            $tableHeadData[$key] = [
                'label' => '',
                'url'   => '',
                'icon'  => '',
            ];
            $tableHeadData[$key]['label'] = $languageService->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:columnHeader.' . $key);
            if (isset($sortActions[$key])) {
                // sorting available, add url
                if ($this->orderBy === $key) {
                    $tableHeadData[$key]['url'] = $sortActions[$key . '_reverse'];
                } else {
                    $tableHeadData[$key]['url'] = $sortActions[$key];
                }

                // add icon only if this is the selected sort order
                if ($this->orderBy === $key) {
                    $tableHeadData[$key]['icon'] = 'status-status-sorting-asc';
                } elseif ($this->orderBy === $key . '_reverse') {
                    $tableHeadData[$key]['icon'] = 'status-status-sorting-desc';
                }
            }
        }
        $tableHeaderHtml = [];
        foreach ($tableHeadData as $key => $values) {
            if ($values['url'] !== '') {
                $tableHeaderHtml[$key]['header'] = sprintf(
                    '<a href="%s" style="text-decoration: underline;">%s</a>',
                    $values['url'],
                    $values['label']
                );
            } else {
                $tableHeaderHtml[$key]['header'] = $values['label'];
            }

            if ($values['icon'] !== '') {
                $tableHeaderHtml[$key]['icon'] = $values['icon'];
            }
        }
        return $tableHeaderHtml;
    }

    /**
     * Displays one line of the excluded links table
     *
     * @param array<mixed> $row Name of database table
     * @return array<mixed> HTML of the rendered row
     */
    protected function renderTableRow(array $row): array
    {
        $variables['page'] = $row['pid'];

        $linktarget_url = $linktarget_text = $row['linktarget'] ?? '';
        $linkType = $row['link_type'] ?? 'external';
        $match = $row['match'] ?? 'exact';

        if ($match === 'domain' && $linkType === 'external' && strpos($linktarget_url, 'http') !== 0) {
            $linktarget_url = 'https://' . $linktarget_url;
        }

        $variables['linktarget_url'] = $linktarget_url;
        $variables['linktarget_text'] = $linktarget_text;
        $variables['link_type'] = $linkType;
        // Reformating excluding link date
        $excludeDate = date($GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'], $row['crdate']);
        $excludeTime = date($GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'], $row['crdate']);
        $variables['exclude_date'] = $excludeDate . ' ' . $excludeTime;
        $variables['hidden'] = $row['hidden'];
        $variables['reason'] = $row['reason'];
        $variables['notes'] = $row['notes'];
        $variables['uid'] = $row['uid'];
        return $variables;
    }

    /**
     * This Function to Generate an array for CSV Header file
     *
     * @param array<string> $itemsArray
     *
     * @return array<string>
     */
    protected function generateCsvHeaderArray(array $itemsArray): array
    {
        $headerCsv = [];
        $count = count($itemsArray);
        for ($i = 0; $i < $count; $i++) {
            $headerCsv[] = $this->charsetConverter->conv($itemsArray[$i], 'utf-8', 'iso-8859-15');
        }
        return $headerCsv;
    }

    /**
     * This Function to Generate the CSV file for the excluded links records
     */
    public function exportExcludedLinks(): Response
    {
        //Initialize Response and prepare the CSV output file
        $format = 'csv';
        $title = 'excluded-links' . date('Y-m-d_H-i');
        $filename = $title . '.' . $format;

        $response = new Response(
            'php://output',
            200,
            ['Content-Type' => 'text/csv; charset=utf-8',
                'Content-Description' => 'File transfer',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]
        );

        //Implement Array Contains Key of the Lang File To regenerate an Array For CSV Header
        $LangArrayHeader = ['Page', 'Link target', 'Link type', 'isHidden', 'Excluding date', 'Reason'];

        //CSV HEADERS Using Translate File and respecting UTF-8 Charset for Special Char
        $headerCsv = $this->generateCsvHeaderArray($LangArrayHeader);

        //Render Excluded Links
        $excludedLinks = $this->excludeLinkTargetRepository->getExcludedBrokenLinks(new ManageExclusionsFilter(), self::ORDER_BY_VALUES[$this->orderBy] ?? []);

        //Open File Based on Function Php To start Write inside the file CSV
        $fp = fopen('php://output', 'wb');

        fputcsv($fp, $headerCsv, ';', '"');

        foreach ($excludedLinks as $key => $item) {
            //Fill Array of Excluded Links by Data
            $arrayData = [];
            $arrayData[] = $item['pid'];
            $arrayData[] = $item['linktarget'];
            $arrayData[] = $item['link_type'];
            if ($item['hidden'] == 1) {
                $arrayData[] = $this->localizationUtility->translate(Self::MODULE_LANG_FILE . 'records.isHidden');
            } else {
                $arrayData[] = $this->localizationUtility->translate(Self::MODULE_LANG_FILE . 'records.isNotHidden');
            }
            $arrayData[] = date('Y-m-d - H-i', $item['crdate']);
            if ($item['reason'] == 1) {
                $arrayData[] = $this->localizationUtility->translate(Self::MODULE_LANG_FILE . 'records.reason2');
            } else {
                $arrayData[] = $this->localizationUtility->translate(Self::MODULE_LANG_FILE . 'records.reason1');
            }
            //Write Inside the CSV File
            fputcsv($fp, $arrayData, ';', '"');
        }
        fclose($fp);

        return $response;
    }

    /**
     * Deletes the selected excluded link
     * @param ServerRequestInterface $request
     */
    public function deleteExcludedLinks(ServerRequestInterface $request): Response
    {
        $data = [
            'result' => '',
            'affectedRows' => 0,

        ];

        $urlParam = $request->getQueryParams();
        $deleteIds = $urlParam['input'] ?? [];
        if (!is_array($deleteIds)) {
            $response = new Response('php://output', 200);

            $response->getBody()->write(json_encode($data));
            return $response;
        }
        foreach ($deleteIds as $key => $value) {
            $deleteIds[$key] = (int)$value;
        }

        $affectedRows = $this->excludeLinkTargetRepository->deleteExcludeLink($deleteIds);
        $data['affectedRows'] = $affectedRows;
        $response = new Response('php://output', 200);
        $response->getBody()->write(json_encode($data));
        return $response;
    }
}
