<?php

namespace Sypets\Brofix\View;

use Psr\Http\Message\ServerRequestInterface;
use Sypets\Brofix\BackendSession\BackendSession;
use Sypets\Brofix\Filter\Filter;
use Sypets\Brofix\Repository\BrokenLinkRepository;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\PaginationInterface;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Info\Controller\InfoModuleController;

class ManageExclusions
{
    /**
     * @var string
     */
    const MODULE_LANG_FILE = 'LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:';
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
        'hidden' => [
            ['hidden', 'ASC'],
        ],
        'hidden_reverse' => [
            ['hidden', 'DESC'],
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
     * @var string
     */
    protected $orderBy = self::ORDER_BY_DEFAULT;

    /**
     * @var BackendSession
     */
    protected $backendSession;

    /**
     * @var int
     */
    protected $paginationCurrentPage;

    /**
     * @var CharsetConverter
     */
    protected $charsetConverter;

    /**
     * @var LocalizationUtility
     */
    protected $localizationUtility;

    /**
     * @var ModuleTemplate
     */
    protected $moduleTemplate;

    /**
     * @var Filter
     */
    protected $filter;

    /**
     * @var BrokenLinkRepository
     */
    protected $brokenLinkRepository;

    /** @var PaginationInterface|null */
    protected $pagination;

    public function __construct(
        BrokenLinkRepository $brokenLinkRepository = null,
        Filter $filter = null,
        BackendSession $backendSession = null,
        CharsetConverter $charsetConverter = null,
        LocalizationUtility $localizationUtility = null
    ) {
        $this->brokenLinkRepository = $brokenLinkRepository ?: GeneralUtility::makeInstance(BrokenLinkRepository::class);
        $this->filter = $filter ?: GeneralUtility::makeInstance(Filter::class);
        $this->backendSession = $backendSession ?: GeneralUtility::makeInstance(BackendSession::class);
        $this->charsetConverter = $charsetConverter ?? GeneralUtility::makeInstance(CharsetConverter::class);
        $this->localizationUtility = $localizationUtility ?? GeneralUtility::makeInstance(LocalizationUtility::class);
    }

    /**
     * @param array<string,mixed> $additionalQueryParameters
     * @param string $route
     * @return string
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
     */
    protected function constructBackendUri(array $additionalQueryParameters = [], string $route = 'web_info'): string
    {
        $parameters = [
            'orderBy' => $this->orderBy,
        ];
        // if same key, additionalQueryParameters should overwrite parameters
        $parameters = array_merge($parameters, $additionalQueryParameters);

        /**
         * @var UriBuilder $uriBuilder
         */
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $uri = (string)$uriBuilder->buildUriFromRoute($route, $parameters);

        return $uri;
    }

    /**
     * @return StandaloneView
     * Displays the table of the excluded links
     */
    public function createViewForManageExclusionTab(StandaloneView $view, InfoModuleController $pObj): StandaloneView
    {
        // pagination + currentPage
        $this->paginationCurrentPage = (int)(GeneralUtility::_GP('paginationPage') ?? 1);

        $pObj->MOD_SETTINGS['paginationPage'] = $this->paginationCurrentPage;
        $this->getBackendUser()->pushModuleData('web_info', $pObj->MOD_SETTINGS);

        $this->moduleTemplate = GeneralUtility::makeInstance(ModuleTemplate::class);
        $pageRenderer = $this->moduleTemplate->getPageRenderer();
        $pageRenderer->addCssFile('EXT:brofix/Resources/Public/Css/brofix_manage_exclusions.css', 'stylesheet', 'screen');

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
            $this->backendSession->store('filterKey_excludeLinks', new Filter());
        }

        $items = [];
        // build the search filter from the backendSession session
        $searchFilter = new Filter();
        $searchFilter->setExcludeUrlFilter($this->backendSession->get('filterKey_excludeLinks')->getExcludeUrlFilter());
        $searchFilter->setExcludeLinkTypeFilter($this->backendSession->get('filterKey_excludeLinks')->getExcludeLinkTypeFilter());
        $searchFilter->setExcludeReasonFilter($this->backendSession->get('filterKey_excludeLinks')->getExcludeReasonFilter());

        // Get Records from the database
        $brokenLinks = $this->brokenLinkRepository->getExcludedBrokenLinks(self::ORDER_BY_VALUES[$this->orderBy] ?? [], $searchFilter);
        $totalCount = count($brokenLinks);

        if ($brokenLinks) {
            $itemsPerPage = 100;
            $paginator = GeneralUtility::makeInstance(ArrayPaginator::class, $brokenLinks, $this->paginationCurrentPage, $itemsPerPage);
            $this->pagination = GeneralUtility::makeInstance(SimplePagination::class, $paginator);
            foreach ($paginator->getPaginatedItems() as $row) {
                $items[] = $this->renderTableRow($row);
            }
        } else {
            $this->pagination = null;
        }
        $view->assign('totalCount', $totalCount);
        $view->assign('title', 'Excludes Links');
        $view->assign('brokenLinks', $items);
        $view->assign('pagination', $this->pagination);
        $view->assign('paginationPage', $this->paginationCurrentPage ?? 1);

        // Table header
        $sortActions = [];
        foreach (array_keys(self::ORDER_BY_VALUES) as $key) {
            $sortActions[$key] = $this->constructBackendUri(['orderBy' => $key]);
        }
        $view->assign('sortActions', $sortActions);
        $view->assign('tableHeader', $this->getVariablesForTableHeader($sortActions));
        // assign the filters value in the inputs
        $view->assign('excludeUrl_filter', $this->backendSession->get('filterKey_excludeLinks')->getExcludeUrlFilter());
        $view->assign('excludeLinkType_filter', $this->backendSession->get('filterKey_excludeLinks')->getExcludeLinkTypeFilter());
        $view->assign('excludeReason_filter', $this->backendSession->get('filterKey_excludeLinks')->getExcludeReasonFilter());

        return $view;
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
            'hidden',
            'crdate',
            'reason'
        ];

        $tableHeadData = [];

        foreach ($headers as $key) {
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
            if (isset($values['url'])) {
                $tableHeaderHtml[$key]['header'] = sprintf(
                    '<a href="%s" style="text-decoration: underline;">%s</a>',
                    $values['url'],
                    $values['label']
                );
            } else {
                $tableHeaderHtml[$key]['header'] = $values['label'];
            }

            if (($values['icon'] ?? '') !== '') {
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
     * @return LanguageService
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
     * @return BackendUserAuthentication
     */
    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
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
        $excludedLinks = $this->brokenLinkRepository->getExcludedBrokenLinks(self::ORDER_BY_VALUES[$this->orderBy] ?? [], new Filter());

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
        $this->brokenLinkRepository->deleteExcludeLink($urlParam['input']);
        $data = ['result' => ''];
        $response = new Response('php://output', 200);
        $response->getBody()->write(json_encode($data));
        return $response;
    }
}
