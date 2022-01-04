<?php

declare(strict_types=1);
namespace Sypets\Brofix\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sypets\Brofix\BackendSession\BackendSession;
use Sypets\Brofix\CheckLinks\ExcludeLinkTarget;
use Sypets\Brofix\Configuration\Configuration;
use Sypets\Brofix\Filter\Filter;
use Sypets\Brofix\LinkAnalyzer;
use Sypets\Brofix\Linktype\ErrorParams;
use Sypets\Brofix\Linktype\LinktypeInterface;
use Sypets\Brofix\Repository\BrokenLinkRepository;
use Sypets\Brofix\Repository\PagesRepository;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\PaginationInterface;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class BrokenLinksController
{
    /**
     * @var array<mixed> Used by client classes.
     */
    public $pageinfo = [];

    /**
     * @var string
     */
    protected $perms_clause;


    /**
     * @var ServerRequestInterface
     */
    protected $request;


    protected const ORDER_BY_VALUES = [
        // there is an inaccuracy here because for table 'pages', the page is record_uid and
        // record_pid is actually the parent page, while for tables != 'pages' record_pid
        // -> this means records on the page and record on the content will not necessarily be
        //    grouped together
        // contains the page id
        // todo: add actual page id to table instead of record_pid
        'page' => [
            ['record_pid', 'ASC'],
            ['language', 'ASC'],
            ['record_uid', 'ASC'],
        ],
        'page_reverse' => [
            ['record_pid', 'DESC'],
            ['language', 'DESC'],
            ['record_uid', 'DESC'],
        ],
        'type' => [
            ['table_name', 'ASC'],
            ['field', 'ASC'],
        ],
        'type_reverse' => [
            ['table_name', 'DESC'],
            ['field', 'DESC'],
        ],
        'last_check' => [
            ['last_check', 'ASC'],
        ],
        'last_check_reverse' => [
            ['last_check', 'DESC'],
        ],
        // add by Mee
        'last_check_url' => [
            ['last_check_url', 'ASC'],
        ],
        'last_check_url_reverse' => [
            ['last_check_url', 'DESC'],
        ],
        'url' => [
            ['link_type', 'ASC'],
            ['url', 'ASC'],
        ],
        'url_reverse' => [
            ['link_type', 'DESC'],
            ['url', 'DESC'],
        ],
        // sort by error type
        'error' => [
            ['link_type', 'ASC'],
            ['url_response', 'ASC']
        ],
        'error_reverse' => [
            ['link_type', 'DESC'],
            ['url_response', 'DESC']
        ],
    ];

    protected const ORDER_BY_DEFAULT = 'page';

    /**
     * The module menu items array. Each key represents a key for which values can range between the items in the array of that key.
     * Written by client classes.
     *
     * @var array<string,mixed>
     */
    public $MOD_MENU = [
        'function' => []
    ];

    /**
     * Current settings for the keys of the MOD_MENU array, used in client classes
     *
     * @var array<mixed>
     */
    public $MOD_SETTINGS = [];

    /**
     * @var string
     */
    protected $orderBy = BrokenLinksController::ORDER_BY_DEFAULT;

    /**
     * @var int
     */
    protected $paginationCurrentPage;

    /**
     * Information about the current page record
     *
     * @var mixed[]
     */
    protected $pageRecord = [];

    /**
     * Information, if the module is accessible for the current user or not
     *
     * @var bool
     */
    protected $isAccessibleForCurrentUser = false;

    /**
     * Current BE user has access to ExcludeLinkTarget storage. This will
     * be required for each broken link record and should be calculated
     * only once.
     *
     * @var bool
     */
    protected $currentUserHasPermissionsForExcludeLinkTargetStorage = false;

    /**
     * Link validation class
     *
     * @var LinkAnalyzer
     */
    protected $linkAnalyzer;

    /**
     * @var BackendSession
     */
    protected $backendSession;

    /**
     * @var Filter
     */
    protected $filter;

    /**
     * @var array<string>
     */
    protected $linkTypes = [];

    /**
     * Depth for the recursive traversal of pages for the link validation
     * For "Report" tab.
     *
     * -1 means not initialized
     *
     * @var int
     */
    protected $depth = -1;

    /**
     * @var string
     */
    protected $route = '';

    /**
     * @var string
     */
    protected $token = '';

    /**
     * @var string
     */
    protected $action = '';

    /**
     * Information for last edited record
     * @var mixed[]
     */
    protected $currentRecord = [
        'uid' => 0,
        'table' => '',
        'field' => '',
        'currentTime' => 0,
        'url' => '',
        'linkType' => ''
    ];

    /**
     * @var LinktypeInterface[]
     */
    protected $hookObjectsArr = [];

    /**
     * Contains site languages for this page ID
     *
     * @var SiteLanguage[]
     */
    protected $siteLanguages = [];

    /**
     * @var int Value of the GET/POST var 'id'
     */
    protected $id;

    /**
     * @var array<string|int>
     */
    protected $pageList;

    /**
     * @var BrokenLinkRepository
     */
    protected $brokenLinkRepository;

    /**
     * @var PagesRepository
     */
    protected $pagesRepository;

    /**
     * @var ExcludeLinkTarget
     */
    protected $excludeLinkTarget;

    /**
     * @var FlashMessageQueue<mixed>
     */
    protected $defaultFlashMessageQueue;

    /**
     * @var Configuration
     */
    protected $configuration;

    /** @var PaginationInterface|null */
    protected $pagination;

    /**
     * @var UriBuilder $uriBuilder
     */
    protected $uriBuilder;

    public function __construct(
        PagesRepository $pagesRepository = null,
        BrokenLinkRepository $brokenLinkRepository = null,
        ExcludeLinkTarget $excludeLinkTarget = null,
        Configuration $configuration = null,
        FlashMessageService $flashMessageService = null,
        BackendSession $backendSession = null,
        IconFactory $iconFactory = null,
        ModuleTemplate $moduleTemplate = null,
        UriBuilder $uriBuilder = null
    ) {
        $this->brokenLinkRepository = $brokenLinkRepository ?: GeneralUtility::makeInstance(BrokenLinkRepository::class);
        $this->pagesRepository = $pagesRepository ?: GeneralUtility::makeInstance(PagesRepository::class);
        $this->excludeLinkTarget = $excludeLinkTarget ?: GeneralUtility::makeInstance(ExcludeLinkTarget::class);
        $this->configuration = $configuration ?: GeneralUtility::makeInstance(Configuration::class);
        $flashMessageService = $flashMessageService ?: GeneralUtility::makeInstance(FlashMessageService::class);
        $this->defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $this->backendSession = $backendSession ?: GeneralUtility::makeInstance(BackendSession::class);
        $this->iconFactory = $iconFactory ?: GeneralUtility::makeInstance(IconFactory::class);
        $this->moduleTemplate = $moduleTemplate ?: GeneralUtility::makeInstance(ModuleTemplate::class);
        $this->getLanguageService()->includeLLFile('EXT:brofix/Resources/Private/Language/Module/locallang.xlf');
        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks'] ?? [] as $linkType => $className) {
            $this->hookObjectsArr[$linkType] = GeneralUtility::makeInstance($className);
        }
        $this->uriBuilder = $uriBuilder ?: GeneralUtility::makeInstance(UriBuilder::class);
    }

    /**
     * Injects the request object for the current request, and renders the overview of all redirects
     *
     * @param ServerRequestInterface $request the current request
     * @return ResponseInterface the response with the content
     */
    public function brokenlinksAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;
        $this->initializeValues();
        $this->getSettingsFromQueryParameters();
        $this->initialize();
        $this->initializeLinkAnalyzer();
        $this->view = $this->initializeView('Brokenlinks');

        $this->renderContentMenu('brokenlinks');

        if ($this->action === 'manageexclusions') {
            $this->createFlashMessage(
                'check exclusions',
                '',
                FlashMessage::OK
            );
        }

        if ($this->action === 'updateLinkList') {
            $this->linkAnalyzer->generateBrokenLinkRecords($this->configuration->getLinkTypes());
            // todo: localize this
            $this->createFlashMessage(
                $this->getLanguageService()->getLL('list.status.check.done'),
                '',
                FlashMessage::OK
            );
        }

        if ($this->action === 'recheckUrl') {
            $message = '';
            $count = $this->linkAnalyzer->recheckUrl($message, $this->currentRecord);
            if ($count > 0) {
                $this->moduleTemplate->addFlashMessage(
                    $message,
                    $this->getLanguageService()->getLL('list.recheck.url.title'),
                    FlashMessage::OK
                );
            } else {
                $this->moduleTemplate->addFlashMessage(
                    $message,
                    $this->getLanguageService()->getLL('list.recheck.url.title'),
                    FlashMessage::OK
                );
            }
        } elseif ($this->action === 'editField') {
            $message = '';
            // recheck broken links for last edited reccord
            $this->linkAnalyzer->recheckLinks(
                $message,
                $this->linkTypes,
                (int)$this->currentRecord['uid'],
                $this->currentRecord['table'],
                $this->currentRecord['field'],
                (int)($this->currentRecord['currentTime'] ?? 0),
                $this->configuration->isCheckHidden()
            );
            if ($message) {
                $this->moduleTemplate->addFlashMessage(
                    $message,
                    $this->getLanguageService()->getLL('list.recheck.links.title'),
                    FlashMessage::OK
                );
            }
        }

        $pageTitle = $this->pageRecord ? BackendUtility::getRecordTitle('pages', $this->pageRecord) : '';
        $this->view->assign('title', $pageTitle);
        //$this->view->assign('content', $this->renderContent());
        //return $this->view->render();
        $this->createViewForBrokenLinksTab($this->view);

        $this->moduleTemplate->setContent($this->view->render());
        return new HtmlResponse($this->moduleTemplate->renderContent());
    }

    protected function initializeView(string $templateName): StandaloneView
    {
        /**
         * @var StandaloneView $view
         */
        /*
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setLayoutRootPaths(['EXT:brofix/Resources/Private/Layouts']);
        $view->setPartialRootPaths(['EXT:brofix/Resources/Private/Partials']);
        $view->setTemplateRootPaths(['EXT:brofix/Resources/Private/Templates/Backend']);
        */
        $view->setTemplate($templateName);
        $view->assign('currentPage', $this->id);
        $view->assign('depth', $this->depth);
        $view->assign('docsurl', $this->configuration->getTsConfig()['report.']['docsurl'] ?? '');
        $view->assign(
            'showRecheckButton',
            $this->getBackendUser()->isAdmin() || $this->depth <= $this->configuration->getRecheckButton()
        );
        return $view;
    }

    protected function initializeValues(): void
    {
        $this->perms_clause = $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW);
        $this->MOD_SETTINGS = BackendUtility::getModuleData(
            $this->MOD_MENU,
            GeneralUtility::_GP('SET'),
            'web_brofix',
            '',
            '',
            ''
        );

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

        if ($this->id !== 0) {
            $this->pageinfo = BackendUtility::readPageAccess($this->id, $this->perms_clause);
            $this->configuration->loadPageTsConfig($this->id);
            $this->currentUserHasPermissionsForExcludeLinkTargetStorage
                = $this->excludeLinkTarget->currentUserHasCreatePermissions(
                    $this->configuration->getExcludeLinkTargetStoragePid()
                );
        }
    }

    /**
     * Checks for incoming GET/POST parameters to update the module settings
     */
    protected function getSettingsFromQueryParameters(): void
    {
        $this->currentRecord = [];
        /**
         * @var bool $resetPagination
         */
        $resetPagination = false;

        // get information for last edited record
        $this->currentRecord['uid'] = GeneralUtility::_GP('current_record_uid') ?? 0;
        $this->currentRecord['table'] = GeneralUtility::_GP('current_record_table') ?? '';
        $this->currentRecord['field'] = GeneralUtility::_GP('current_record_field') ?? '';
        $this->currentRecord['currentTime'] = GeneralUtility::_GP('current_record_currentTime') ?? 0;
        $this->currentRecord['url'] = urldecode(GeneralUtility::_GP('current_record_url') ?? '');
        $this->currentRecord['linkType'] = GeneralUtility::_GP('current_record_linkType') ?? '';

        // get searchLevel (number of levels of pages to check / show results)
        $depth = GeneralUtility::_GP('depth');

        // store filter parameters in the Filter Object
        $this->filter = new Filter();
        $this->filter->setUidFilter(GeneralUtility::_GP('uid_searchFilter') ?? '');

        $this->filter->setUrlFilter(GeneralUtility::_GP('url_searchFilter') ?? '');

        $this->filter->setTitleFilter(GeneralUtility::_GP('title_searchFilter') ?? '');

        // to prevent deleting session, when user sort the records
        if (!is_null(GeneralUtility::_GP('url_searchFilter')) || !is_null(GeneralUtility::_GP('title_searchFilter')) || !is_null(GeneralUtility::_GP('uid_searchFilter'))) {
            $this->backendSession->store('filterKey', $this->filter);
        }

        // create session, if it the first time
        if (is_null($this->backendSession->get('filterKey'))) {
            $this->backendSession->setStorageKey('filterKey');
            $this->backendSession->store('filterKey', new Filter());
        }

        /**
         * @var int $previousDepth
         */
        $previousDepth = (int)($this->MOD_SETTINGS['depth'] ?? 0);
        if (is_null($depth)) {
            // not set, set to stored value or 0 (default)
            $this->depth = $previousDepth;
        } else {
            $this->depth = (int)$depth;
            if ($this->depth !== $previousDepth) {
                $resetPagination = true;
            }
        }
        $this->MOD_SETTINGS['depth'] = $this->depth;

        $this->route = GeneralUtility::_GP('route') ?? '';
        $this->token = GeneralUtility::_GP('token') ?? '';
        $this->action = GeneralUtility::_GP('action') ?? '';

        if (GeneralUtility::_GP('updateLinkList') ?? '') {
            $this->action = 'updateLinkList';
        }

        // orderBy
        $this->orderBy = (string)(GeneralUtility::_GP('orderBy')
            ?: ($this->MOD_SETTINGS['orderBy'] ?? BrokenLinksController::ORDER_BY_DEFAULT));
        if ($this->orderBy != ($this->MOD_SETTINGS['orderBy'] ?? BrokenLinksController::ORDER_BY_DEFAULT)) {
            $resetPagination = true;
        }
        $this->MOD_SETTINGS['orderBy'] = $this->orderBy;

        $this->linkTypes = [];
        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks'] ?? [] as $linkType => $value) {
            $linkTypes = $this->configuration->getLinkTypes();
            // Compile list of all available types. Used for checking with button "Check Links".
            if (in_array($linkType, $linkTypes)) {
                $this->linkTypes[] = $linkType;
            }
        }

        // pagination + currentPage
        $lastCurrentPage = (int)($this->MOD_SETTINGS['currentPage'] ?? 0);
        if (($this->id !== $lastCurrentPage) || $resetPagination) {
            // pagination (reset pagination if page id changed!)
            $this->paginationCurrentPage = 1;
        } else {
            $this->paginationCurrentPage = (int)(GeneralUtility::_GP('paginationPage')
                ?: ($this->MOD_SETTINGS['paginationPage'] ?? 1));
        }
        $this->MOD_SETTINGS['currentPage'] = $this->id;
        $this->MOD_SETTINGS['paginationPage'] = $this->paginationCurrentPage;

        // save settings
        $this->getBackendUser()->pushModuleData('web_brofix', $this->MOD_SETTINGS);
    }

    /**
     * Initializes the Module
     */
    protected function initialize(): void
    {
        $this->pageRecord = BackendUtility::readPageAccess(
            $this->id,
            $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW)
        );
        if (($this->id && is_array($this->pageRecord)) || (!$this->id && $this->getBackendUser()->isAdmin())) {
            $this->isAccessibleForCurrentUser = true;
        }
        // Don't access in workspace
        if ($this->getBackendUser()->workspace !== 0) {
            $this->isAccessibleForCurrentUser = false;
        }

        $pageRenderer = $this->moduleTemplate->getPageRenderer();
        $pageRenderer->addCssFile('EXT:brofix/Resources/Public/Css/brofix.css', 'stylesheet', 'screen');
        $pageRenderer->loadRequireJsModule('TYPO3/CMS/Brofix/Brofix');
        $pageRenderer->addInlineLanguageLabelFile('EXT:brofix/Resources/Private/Language/Module/locallang.xlf');
    }

    /**
     * Updates the table of stored broken links
     */
    protected function initializeLinkAnalyzer(): void
    {
        $permsClause = (string)$this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW);
        if ($this->id) {
            $this->pageList = $this->pagesRepository->getPageList($this->id, $this->depth,
                $permsClause, $this->configuration->isCheckHidden());
        } else {
            $this->pageList = [];
        }
        $this->linkAnalyzer = GeneralUtility::makeInstance(LinkAnalyzer::class);
        $this->linkAnalyzer->init($this->pageList, $this->configuration);
    }

    /**
     * Displays the table of broken links or a note if there were no broken links
     *
     * @return StandaloneView
     */
    protected function createViewForBrokenLinksTab(StandaloneView $view): StandaloneView
    {
        //$view = $this->initializeView('ReportTab');
        $view->assign('depth', $this->depth);

        $items = [];
        $totalCount = 0;
        // todo: do we need to check rootline for hidden? Was already checked in checking for broken links!
        // @extensionScannerIgnoreLine problem with getRootLineIsHidden
        $rootLineHidden = $this->pagesRepository->getRootLineIsHidden($this->pageinfo);
        if ($this->id > 0 && (!$rootLineHidden || $this->configuration->isCheckHidden())) {
            // build the search filter from the backendSession session
            $searchFilter = new Filter();
            $searchFilter->setUrlFilter($this->backendSession->get('filterKey')->getUrlFilter());
            $searchFilter->setUidFilter($this->backendSession->get('filterKey')->getUidFilter());
            $searchFilter->setTitleFilter($this->backendSession->get('filterKey')->getTitleFilter());

            $brokenLinks = $this->brokenLinkRepository->getBrokenLinks(
                $this->pageList,
                $this->linkTypes,
                $this->configuration->getSearchFields(),
                $searchFilter,
                self::ORDER_BY_VALUES[$this->orderBy] ?? []
            );
            if ($brokenLinks) {
                $totalCount = count($brokenLinks);

                $itemsPerPage = 100;
                $paginator = GeneralUtility::makeInstance(ArrayPaginator::class, $brokenLinks, $this->paginationCurrentPage, $itemsPerPage);
                $this->pagination = GeneralUtility::makeInstance(SimplePagination::class, $paginator);
                // move end
                foreach ($paginator->getPaginatedItems() as $row) {
                    $items[] = $this->renderTableRow($row['table_name'], $row);
                }
            }
        } else {
            $this->pagination = null;
        }
        $view->assign('totalCount', $totalCount);
        // send the search filters to the view
        $view->assign('url_filter', $this->backendSession->get('filterKey')->getUrlFilter());
        $view->assign('uid_filter', $this->backendSession->get('filterKey')->getUidFilter());
        $view->assign('title_filter', $this->backendSession->get('filterKey')->getTitleFilter());
        if ($this->id === 0) {
            $this->createFlashMessagesForRootPage();
        } elseif (empty($items)) {
            $this->createFlashMessagesForNoBrokenLinks();
        }
        $view->assign('brokenLinks', $items);

        $view->assign('pagination', $this->pagination);
        $view->assign('orderBy', $this->orderBy);
        $view->assign('paginationPage', $this->paginationCurrentPage ?: 1);
        $sortActions = [];

        foreach (array_keys(self::ORDER_BY_VALUES) as $key) {
            $sortActions[$key] = $this->constructBackendUri(['orderBy' => $key]);
        }
        $view->assign('sortActions', $sortActions);

        // Table header
        $view->assign('tableHeader', $this->getVariablesForTableHeader($sortActions));

        return $view;
    }

    /**
     * Used when there are no broken links found.
     */
    protected function createFlashMessagesForNoBrokenLinks(): void
    {
        $this->createFlashMessage(
            $this->getLanguageService()->getLL(
                $this->depth > 0 ? 'list.no.broken.links' : 'list.no.broken.links.this.page'
            ),
            '',
            FlashMessage::OK
        );
    }

    protected function createFlashMessagesForRootPage(): void
    {
        $this->createFlashMessage($this->getLanguageService()->getLL('list.rootpage'));
    }

    /**
     * Generic convenience function for creating and enqueing a flash message
     *
     * @param string $message
     * @param string $title
     * @param int $type
     * @throws \TYPO3\CMS\Core\Exception
     */
    protected function createFlashMessage(string $message, string $title = '', int $type = FlashMessage::INFO): void
    {
        /**
         * @var FlashMessage $flashMessage
         */
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $title,
            $message,
            $type,
            false
        );
        /**
         * @var FlashMessageService $flashMessageService
         */
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier('brofix');
        $defaultFlashMessageQueue->enqueue($flashMessage);
    }

    /**
     * Sets variables for the Fluid Template of the table with the broken links
     * @param array<string,string> $sortActions
     * @return mixed[] variables
     */
    protected function getVariablesForTableHeader(array $sortActions): array
    {
        $languageService = $this->getLanguageService();

        $headers = [
            'page',
            'element',
            'type',
            'last_check',
            'url',
            'error',
            'last_check_url',
            'action'
        ];

        $tableHeadData = [];

        foreach ($headers as $key) {
            $tableHeadData[$key] = [
                'label' => '',
                'url'   => '',
                'icon'  => '',
            ];
            $tableHeadData[$key]['label'] = $languageService->getLL('list.tableHead.' . $key);
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
     * Displays one line of the broken links table
     *
     * @param string $table Name of database table
     * @param mixed[] $row Record row to be processed
     * @return mixed[] HTML of the rendered row
     */
    protected function renderTableRow($table, array $row): array
    {
        $languageService = $this->getLanguageService();
        $variables = [];
        // Restore the linktype object
        $hookObj = $this->hookObjectsArr[$row['link_type']];
        $isAdmin = $this->isAdmin();

        // Construct link to edit the content element
        $backUriEditField = $this->constructBackendUri(
            [
                'action' => 'editField',
                // add record_uid as query parameter for rechecking after edit
                'current_record_uid' => $row['record_uid'],
                'current_record_table' => $row['table_name'],
                'current_record_field' => $row['field'],
                'current_record_currentTime' => $GLOBALS['EXEC_TIME'],
            ]
        );

        /**
         * @var UriBuilder $uriBuilder
         */
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $variables['editUrl'] = (string)$uriBuilder->buildUriFromRoute('record_edit', [
            'edit' => [
                $table => [
                    $row['record_uid'] => 'edit'
                ]
            ],
            'columnsOnly' => $row['field'],
            'returnUrl' => $backUriEditField
        ]);

        // construct URL to recheck the URL
        $variables['recheckUrl'] = $this->constructBackendUri(
            [
                'action' => 'recheckUrl',
                // add url information
                'current_record_url' => urlencode($row['url']),
                'current_record_linkType' => $row['link_type'],
                // add record_uid as query parameter for rechecking after edit
                'current_record_uid' => $row['record_uid'],
                'current_record_table' => $row['table_name'],
                'current_record_field' => $row['field'],
                'current_record_currentTime' => $GLOBALS['EXEC_TIME'],
            ]
        );

        $variables['lastChecked'] = 0;
        // check if current record was recently checked
        if (isset($this->currentRecord['uid']) && isset($this->currentRecord['table']) && isset($this->currentRecord['field'])
            && $this->action === 'editField'
            && $row['record_uid'] == $this->currentRecord['uid']
            && $row['table_name'] === $this->currentRecord['table']
            && $row['field'] === $this->currentRecord['field']
        ) {
            $variables['lastChecked'] = 1;
        }

        // check if current URL was recently checked
        if ($this->action === 'recheckUrl'
            && isset($this->currentRecord['url'])
            && $this->currentRecord['url'] === $row['url']
            && isset($this->currentRecord['linkType'])
            && $this->currentRecord['linkType'] === $row['link_type']
        ) {
            $variables['lastChecked'] = 1;
        }

        $excludeLinkTargetStoragePid = $this->configuration->getExcludeLinkTargetStoragePid();
        // show exclude link target button
        if (in_array($row['link_type'] ?? 'empty', $this->configuration->getExcludeLinkTargetAllowedTypes())
            && $this->currentUserHasPermissionsForExcludeLinkTargetStorage
        ) {
            $returnUrl = $this->constructBackendUri();
            $excludeUrl = (string)$uriBuilder->buildUriFromRoute('record_edit', [
                'edit' => [
                    'tx_brofix_exclude_link_target' => [
                        $excludeLinkTargetStoragePid => 'new',
                    ],
                ],
                'defVals' => [
                    'tx_brofix_exclude_link_target' => [
                        'link_type' => $row['link_type'] ?? 'external',
                        'linktarget' => $row['url'],
                    ],
                ],
                'returnUrl' => $returnUrl
            ]);
            $variables['excludeUrl'] = $excludeUrl;
        }

        // column "Element"
        $variables['elementHeadline'] = htmlspecialchars($row['headline']);
        $variables['record_uid'] = $row['record_uid'];
        $variables['elementIcon'] = $this->iconFactory->getIconForRecord($table, $row, Icon::SIZE_SMALL)->render();

        // langIcon
        if (isset($row['language']) && $row['language'] != -1 && isset($this->siteLanguages[(int)($row['language'])])) {
            $variables['langIcon'] = $this->siteLanguages[(int)($row['language'])]->getFlagIdentifier();
        }

        // Element Type + Field
        if ($isAdmin) {
            $variables['table'] = $table;
            $variables['field'] = $row['field'] ?? '';
        }
        $variables['elementType'] = $this->getLanguageSplitLabel($GLOBALS['TCA'][$table]['ctrl']['title'] ?? '');
        // Get the language label for the field from TCA
        $fieldName = '';
        if ($GLOBALS['TCA'][$table]['columns'][$row['field']]['label']) {
            $fieldName = $languageService->sL($GLOBALS['TCA'][$table]['columns'][$row['field']]['label']);
            // Crop colon from end if present
            if (substr($fieldName, -1, 1) === ':') {
                $fieldName = substr($fieldName, 0, strlen($fieldName) - 1);
            }
        }
        $variables['fieldName'] = !empty($fieldName) ? $fieldName : $row['field'];

        // page title / uid / path
        $pageId = (int)($table === 'pages' ? $row['record_uid'] : $row['record_pid']);
        $variables['pageId'] = $pageId;
        $path = $this->pagesRepository->getPagePath($pageId, 50);
        $variables['path'] = $path[1];
        $variables['pagetitle'] = $path[0] ?? '';

        // error message
        $response = $response = json_decode($row['url_response'], true);
        $errorParams = new ErrorParams($response['errorParams']);
        if ($response['valid']) {
            $linkMessage = '<span class="valid">' . htmlspecialchars($languageService->getLL('list.msg.ok')) . '</span>';
        } else {
            $linkMessage = sprintf(
                '<span class="error" title="%s">%s</span>',
                nl2br(
                    htmlspecialchars(
                        $errorParams->getExceptionMsg(),
                        ENT_QUOTES,
                        'UTF-8',
                        false
                    )
                ),
                nl2br(
                // Encode for output
                    htmlspecialchars(
                        $hookObj->getErrorMessage($errorParams),
                        ENT_QUOTES,
                        'UTF-8',
                        false
                    )
                )
            );
        }
        $variables['linkmessage'] = $linkMessage;

        // link / URL
        $variables['linktarget'] = $hookObj->getBrokenUrl($row);
        if (isset($row['link_title']) && $variables['linktarget'] !== $row['link_title']) {
            $variables['link_title'] = htmlspecialchars($row['link_title']);
        } else {
            $variables['link_title'] = '';
        }
        $variables['linktext'] = $hookObj->getBrokenLinkText($row, $errorParams->getCustom());

        // last check of record
        $currentDate = date($GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'], \time());
        $lastcheckDate = date($GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'], $row['last_check']);
        $lastCheckTime = date($GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'], $row['last_check']);
        $variables['lastcheck'] = (($currentDate != $lastcheckDate) ? $lastcheckDate . ' ' : '') . $lastCheckTime;

        // last check of URL
        $lastcheckDateUrl = date($GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'], $row['last_check_url']);
        $lastCheckTimeUrl = date($GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'], $row['last_check_url']);
        $variables['lastcheck_url'] = (($currentDate != $lastcheckDateUrl) ? $lastcheckDateUrl . ' ' : '') . $lastCheckTimeUrl;

        // determine if check is fresh or stale
        $tstamp_field = $GLOBALS['TCA'][$row['table_name']]['ctrl']['tstamp'] ?? '';
        $variables['freshness'] = 'unknown';
        if ($tstamp_field) {
            $result = BackendUtility::getRecord($row['table_name'], $row['record_uid'], $tstamp_field);
            $tstamp = (int)($result['tstamp'] ?? 0);
            $last_check = (int)($row['last_check']);

            if ($tstamp > $last_check) {
                $variables['freshness'] = 'stale';
            } else {
                $variables['freshness'] = 'fresh';
            }
        }
        return $variables;
    }

    protected function isShowRecordIds(): bool
    {
        return true;
    }

    protected function getLanguageSplitLabel(string $label): string
    {
        static $languageCache = [];

        if (isset($languageCache[$label])) {
            return $languageCache[$label];
        }

        $text = $this->getLanguageService()->sL($label);
        if ($text) {
            $languageCache[$label] = $text;
            return $text;
        }
        return '';
    }

    /**
     * Reused from PageLayoutView::resolveSiteLanguages()
     *
     * Fetch the site language objects for the given $pageId and store it in $this->siteLanguages
     *
     * @param int $pageId
     * @throws SiteNotFoundException
     */
    protected function resolveSiteLanguages(int $pageId): void
    {
        /**
         * @var SiteMatcher $siteMatcher
         */
        $siteMatcher = GeneralUtility::makeInstance(SiteMatcher::class);
        /**
         * @var SiteInterface $site
         */
        $site = $siteMatcher->matchByPageId($pageId);
        $this->siteLanguages = $site->getAvailableLanguages($this->getBackendUser(), true, $pageId);
    }

    /**
     * @return LanguageService
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    protected function isAdmin(): bool
    {
        return $this->getBackendUser()->isAdmin();
    }

    /**
     * @return BackendUserAuthentication
     */
    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
