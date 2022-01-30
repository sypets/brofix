<?php

namespace Sypets\Brofix\Controller;

use Psr\Http\Message\ServerRequestInterface;
use Sypets\Brofix\BackendSession\BackendSession;
use Sypets\Brofix\CheckLinks\ExcludeLinkTarget;
use Sypets\Brofix\Configuration\Configuration;
use Sypets\Brofix\Controller\Filter\ManageExclusionsFilter;
use Sypets\Brofix\Repository\ExcludeLinkTargetRepository;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Info\Controller\InfoModuleController;

class ManageExclusionsController extends AbstractInfoController
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

    /**
     * @var CharsetConverter
     */
    protected $charsetConverter;

    /**
     * @var LocalizationUtility
     */
    protected $localizationUtility;

    /**
     * @var ManageExclusionsFilter
     */
    protected $filter;

    /**
     * @var int
     */
    protected $storagePid;

    /**
     * @var ExcludeLinkTargetRepository
     */
    protected $excludeLinkTargetRepository;

    /**
     * Current BE user has access to ExcludeLinkTarget storage. This will
     * be required for each broken link record and should be calculated
     * only once.
     *
     * @var bool
     */
    protected $currentUserHasPermissionsForExcludeLinkTargetStorage = false;

    public function __construct(
        ExcludeLinkTargetRepository $excludeLinkTargetRepository = null,
        ManageExclusionsFilter $filter = null,
        Configuration $configuration = null,
        BackendSession $backendSession = null,
        ModuleTemplate $moduleTemplate = null,
        IconFactory $iconFactory = null,
        ExcludeLinkTarget $excludeLinkTarget = null,
        CharsetConverter $charsetConverter = null,
        LocalizationUtility $localizationUtility = null
    ) {
        $backendSession = $backendSession ?: GeneralUtility::makeInstance(BackendSession::class);
        $configuration = $configuration ?: GeneralUtility::makeInstance(Configuration::class);
        $iconFactory = $iconFactory ?: GeneralUtility::makeInstance(IconFactory::class);
        $moduleTemplate = $moduleTemplate ?: GeneralUtility::makeInstance(ModuleTemplate::class);
        $excludeLinkTarget = $excludeLinkTarget ?: GeneralUtility::makeInstance(ExcludeLinkTarget::class);
        parent::__construct(
            $configuration,
            $backendSession,
            $iconFactory,
            $moduleTemplate,
            $excludeLinkTarget
        );
        $this->excludeLinkTargetRepository = $excludeLinkTargetRepository ?: GeneralUtility::makeInstance(ExcludeLinkTargetRepository::class);
        $this->filter = $filter ?: GeneralUtility::makeInstance(ManageExclusionsFilter::class);
        $this->charsetConverter = $charsetConverter ?? GeneralUtility::makeInstance(CharsetConverter::class);
        $this->localizationUtility = $localizationUtility ?? GeneralUtility::makeInstance(LocalizationUtility::class);
        $this->orderBy = ManageExclusionsController::ORDER_BY_DEFAULT;
    }

    /**
     * Init, called from parent object
     *
     * @param InfoModuleController $pObj A reference to the parent (calling) object
     */
    public function init(InfoModuleController $pObj): void
    {
        $this->pObj = $pObj;
        $this->storagePid = $this->isAdmin() ? -1 : $this->configuration->getExcludeLinkTargetStoragePid();

        $val = GeneralUtility::_GP('id');
        if ($val === null) {
            // work-around, because this->id does not work if "Refresh display" is used
            $val = GeneralUtility::_GP('currentPage');
        }
        if ($val !== null) {
            // @extensionScannerIgnoreLine
            $this->id = (int)$val;
            // @extensionScannerIgnoreLine
            $this->resolveSiteLanguages($this->id);
        } else {
            $this->id = 0;
        }
        $this->view = $this->createView(self::TEMPLATE_NAME);
        if ($this->id !== 0) {
            $this->configuration->loadPageTsConfig($this->id);
            $this->currentUserHasPermissionsForExcludeLinkTargetStorage
                = $this->excludeLinkTarget->currentUserHasCreatePermissions(
                    $this->configuration->getExcludeLinkTargetStoragePid()
                );
        }
    }

    /**
     * Main, called from parent object
     *
     * @return string Module content
     */
    public function main(): string
    {
        $this->getLanguageService()->includeLLFile('EXT:brofix/Resources/Private/Language/Module/locallang.xlf');
        $this->getSettingsFromQueryParameters();
        $this->initializeRenderer();

        $this->initializeExclusionView();
        return $this->view->render();
    }

    /**
     * @return StandaloneView
     * Displays the table of the excluded links
     */
    protected function createView(string $templateName): StandaloneView
    {
        /**
         * @var StandaloneView $view
         */
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setLayoutRootPaths(['EXT:brofix/Resources/Private/Layouts']);
        $view->setPartialRootPaths(['EXT:brofix/Resources/Private/Partials']);
        $view->setTemplateRootPaths(['EXT:brofix/Resources/Private/Templates/Backend']);
        $view->setTemplate($templateName);
        $view->assign('currentPage', $this->id);
        return $view;
    }

    protected function initializeRenderer(): void
    {
        $pageRenderer = $this->moduleTemplate->getPageRenderer();
        $pageRenderer->addCssFile('EXT:brofix/Resources/Public/Css/brofix.css', 'stylesheet', 'screen');
        $pageRenderer->addCssFile('EXT:brofix/Resources/Public/Css/brofix_manage_exclusions.css', 'stylesheet', 'screen');
        // $pageRenderer->loadRequireJsModule('TYPO3/CMS/Brofix/ManageExclusions');
        $pageRenderer->loadRequireJsModule('TYPO3/CMS/Brofix/Brofix');
        $pageRenderer->addInlineLanguageLabelFile('EXT:brofix/Resources/Private/Language/Module/locallang.xlf');
    }

    protected function getSettingsFromQueryParameters(): void
    {
        // pagination + currentPage
        $this->paginationCurrentPage = (int)(GeneralUtility::_GP('paginationPage') ?? 1);

        $this->pObj->MOD_SETTINGS['paginationPage'] = $this->paginationCurrentPage;
        $this->getBackendUser()->pushModuleData('web_info', $this->pObj->MOD_SETTINGS);

        // orderBy
        $this->orderBy = (string)(GeneralUtility::_GP('orderBy') ?? self::ORDER_BY_DEFAULT);

        // store filter parameters in the Filter Object
        $this->filter->setExcludeLinkTypeFilter(GeneralUtility::_GP('excludeLinkType_filter') ?? '');
        $this->filter->setExcludeUrlFilter(GeneralUtility::_GP('excludeUrl_filter') ?? '');
        $this->filter->setExcludeReasonFilter(GeneralUtility::_GP('excludeReason_filter') ?? '');

        // to prevent deleting session, when user sort the records
        if (!is_null(GeneralUtility::_GP('excludeLinkType_filter')) || !is_null(GeneralUtility::_GP('excludeUrl_filter')) || !is_null(GeneralUtility::_GP('excludeReason_filter'))) {
            $this->backendSession->store('filterKey_excludeLinks', $this->filter);
        }
        // create session, if it the first time
        if (is_null($this->backendSession->get('filterKey_excludeLinks'))) {
            $this->backendSession->setStorageKey('filterKey_excludeLinks');
            $this->backendSession->store('filterKey_excludeLinks', new ManageExclusionsFilter());
        }
    }

    /**
     * @return array<mixed>
     */
    public function initializeExclusions(): array
    {
        $items = [];
        // build the search filter from the backendSession session
        $searchFilter = new ManageExclusionsFilter();
        $searchFilter->setExcludeUrlFilter($this->backendSession->get('filterKey_excludeLinks')->getExcludeUrlFilter());
        $searchFilter->setExcludeLinkTypeFilter($this->backendSession->get('filterKey_excludeLinks')->getExcludeLinkTypeFilter());
        $searchFilter->setExcludeReasonFilter($this->backendSession->get('filterKey_excludeLinks')->getExcludeReasonFilter());
        $searchFilter->setExcludeStoragePid($this->storagePid);

        // Get Records from the database
        $exclusions = $this->excludeLinkTargetRepository->getExcludedBrokenLinks(
            $searchFilter,
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
        $this->view->assign('totalCount', $exclusions['totalCount']);
        $this->view->assign('title', 'Excludes Links');
        $this->view->assign('brokenLinks', $exclusions['items']);
        $this->view->assign('pagination', $this->pagination);
        $this->view->assign('paginationPage', $this->paginationCurrentPage ?: 1);
        $this->view->assign('currentPage', $this->id);
        $this->view->assign('hasPermission', $this->currentUserHasPermissionsForExcludeLinkTargetStorage);

        // Table header
        $sortActions = [];
        foreach (array_keys(self::ORDER_BY_VALUES) as $key) {
            $sortActions[$key] = $this->constructBackendUri(['orderBy' => $key]);
        }
        $this->view->assign('sortActions', $sortActions);
        $this->view->assign('tableHeader', $this->getVariablesForTableHeader($sortActions));
        // assign the filters value in the inputs
        $this->view->assign('excludeUrl_filter', $this->backendSession->get('filterKey_excludeLinks')->getExcludeUrlFilter());
        $this->view->assign('excludeLinkType_filter', $this->backendSession->get('filterKey_excludeLinks')->getExcludeLinkTypeFilter());
        $this->view->assign('excludeReason_filter', $this->backendSession->get('filterKey_excludeLinks')->getExcludeReasonFilter());
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
            $tableHeadData[$key]['label'] = $languageService->getLL('columnHeader.' . $key);
            if (isset($sortActions[$key])) {
                // sorting available, add url
                if ($this->orderBy === $key) {
                    $tableHeadData[$key]['url'] = $sortActions[$key . '_reverse'] ?? '';
                } else {
                    $tableHeadData[$key]['url'] = $sortActions[$key] ?? '';
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
        $variables['url'] = $row['linktarget'];
        $variables['link_type'] = $row['link_type'];
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

        fputcsv($fp, $headerCsv, ';', ' - ');

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
     * This Function is delete the selected excluded link
     * @param \Psr\Http\Message\ServerRequestInterface $request
     */
    public function deleteExcludedLinks(ServerRequestInterface $request): Response
    {
        $urlParam = $request->getQueryParams();
        $this->excludeLinkTargetRepository->deleteExcludeLink($urlParam['input']);
        $data = ['result' => ''];
        $response = new Response('php://output', 200);
        $response->getBody()->write(json_encode($data));
        return $response;
    }
}
