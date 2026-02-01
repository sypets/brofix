<?php

declare(strict_types=1);

namespace Sypets\Brofix\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sypets\Brofix\CheckLinks\ExcludeLinkTarget;
use Sypets\Brofix\CheckLinks\LinkTargetResponse\LinkTargetResponse;
use Sypets\Brofix\Configuration\Configuration;
use Sypets\Brofix\Controller\Filter\BrokenLinkListFilter;
use Sypets\Brofix\LinkAnalyzer;
use Sypets\Brofix\Linktype\LinktypeInterface;
use Sypets\Brofix\Repository\BrokenLinkRepository;
use Sypets\Brofix\Repository\PagesRepository;
use Sypets\Brofix\Util\StringUtil;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Backend Module 'Check links'
 *
 * @internal This class may change without further warnings or increment of major version.
 *
 * v12: changelogs
 * - New backend module registration API: https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/12.0/Feature-96733-NewBackendModuleRegistrationAPI.html
 * - Introduce Module data object: https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/12.0/Feature-96895-IntroduceModuleDataObject.html
 */
class BrokenLinkListController extends AbstractBrofixController
{
    protected const MODULE_NAME = 'web_brofix_broken_links';

    protected const DEFAULT_ORDER_BY = 'page';
    protected const DEFAULT_DEPTH = 0;
    public const VIEW_MODE_VALUE_MIN = 'view_table_min';
    public const VIEW_MODE_VALUE_COMPLEX = 'view_table_complex';
    public const DEFAULT_VIEW_MODE_VALUE = self::VIEW_MODE_VALUE_COMPLEX;

    protected const ORDER_BY_VALUES = [
        'page' => [
            // ['record_pid', 'ASC'],
            ['record_pageid', 'ASC'],
            ['language', 'ASC'],
            ['record_uid', 'ASC'],
        ],
        'page_reverse' => [
            //['record_pid', 'DESC'],
            ['record_pageiid', 'DESC'],
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
            ['check_status', 'ASC'],
            ['error_type', 'ASC'],
            ['errno', 'ASC']
        ],
        'error_reverse' => [
            ['check_status', 'DESC'],
            ['error_type', 'DESC'],
            ['errno', 'DESC']
        ],
    ];

    /**
     * Information about the current page record
     *
     * @var mixed[]
     */
    protected $pageRecord = [];

    /**
     * Link validation class
     *
     * @var LinkAnalyzer
     */
    protected $linkAnalyzer;

    /**
     * @var BrokenLinkListFilter|null
     */
    protected $filter;

    /**
     * @var array<string>
     */
    protected array $linkTypes = [];

    /**
     * Depth for the recursive traversal of pages for the link validation
     * For "Report" tab.
     * @var int
     */
    protected int $depth = self::DEFAULT_DEPTH;

    protected string $viewMode = self::DEFAULT_VIEW_MODE_VALUE;

    /**
     * @var array<string,mixed>
     */
    protected array $pageinfo = [];

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
     * @var IconFactory
     */
    protected $iconFactory;

    /**
     * @var array<string|int>|null
     */
    protected $pageList;

    /**
     * @var FlashMessageQueue<FlashMessage>
     */
    protected $defaultFlashMessageQueue;

    protected bool $backendUserHasPermissionsForBrokenLinklist = false;
    protected bool $backendUserHasPermissionsForExcludes = false;

    public function __construct(
        protected PagesRepository $pagesRepository,
        protected BrokenLinkRepository $brokenLinkRepository,
        // has property in parent class, is passed to parent object via constructor!
        ExcludeLinkTarget $excludeLinkTarget,
        protected FlashMessageService $flashMessageService,
        // has property in parent class, is passed to parent object via constructor!
        ModuleTemplateFactory $moduleTemplateFactory,
        // has property in parent class, is passed to parent object via constructor!
        IconFactory $iconFactory,
        // only needed in constructur
        ExtensionConfiguration $extensionConfiguration,
        // has property in parent class
        PageRenderer $pageRenderer,
        protected Context $context,
        protected readonly UriBuilder $uriBuilder
    ) {
        $this->defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $this->orderBy = BrokenLinkListController::DEFAULT_ORDER_BY;

        $extConfArray  = $extensionConfiguration->get('brofix') ?: [];
        $configuration = GeneralUtility::makeInstance(Configuration::class, $extConfArray);
        $this->pageRenderer = $pageRenderer;

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

        $this->initializePageRenderer();
        $this->initializeLinkAnalyzer();

        $this->action = $this->moduleData->get('action');

        if ($this->action === 'report') {
            $this->resetModuleData();
            return $this->mainAction($this->moduleTemplate);
        }

        if ($this->action === 'checklinks') {
            // todo set considerHidden
            $considerHidden = false;
            $this->linkAnalyzer->generateBrokenLinkRecords(
                $request,
                $this->configuration->getLinkTypes(),
                $considerHidden
            );
            $this->createFlashMessage(
                $this->getLanguageService()->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.status.check.done'),
                '',
                ContextualFeedbackSeverity::OK
            );
            $this->resetModuleData();
            return $this->mainAction($this->moduleTemplate);
        }

        if ($this->action === 'recheckUrl') {
            $message = '';
            $count = $this->linkAnalyzer->recheckUrl($message, $this->currentRecord, $request);
            if ($count > 0) {
                $this->moduleTemplate->addFlashMessage(
                    $message,
                    $this->getLanguageService()->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.recheck.url.title'),
                    ContextualFeedbackSeverity::OK
                );
            } else {
                $this->moduleTemplate->addFlashMessage(
                    $message,
                    $this->getLanguageService()->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.recheck.url.title'),
                    ContextualFeedbackSeverity::OK
                );
            }
            $this->resetModuleData();

            return $this->mainAction($this->moduleTemplate);
        }
        if ($this->action === 'editField') {
            $message = '';
            // recheck broken links for last edited reccord
            //$this->linkAnalyzer->recheckLinks(
            $this->linkAnalyzer->recheckRecord(
                $message,
                $this->linkTypes,
                (int)$this->currentRecord['uid'],
                $this->currentRecord['table'],
                $this->currentRecord['field'],
                (int)($this->currentRecord['currentTime'] ?? 0),
                $request,
                $this->configuration->isCheckHidden()
            );
            if ($message) {
                $this->moduleTemplate->addFlashMessage(
                    $message,
                    $this->getLanguageService()->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.recheck.links.title'),
                    ContextualFeedbackSeverity::OK
                );
            }
            $this->resetModuleData(false);
            return $this->mainAction($this->moduleTemplate);
        }
        return $this->mainAction($this->moduleTemplate);
    }

    protected function initializeTemplate(ServerRequestInterface $request): void
    {
        $this->moduleTemplate = $this->moduleTemplateFactory->create($request);
        $this->moduleTemplate->makeDocHeaderModuleMenu(['id' => $this->id]);

        $this->moduleTemplate->assign('currentPage', $this->id);
        $this->moduleTemplate->assign('depth', $this->depth);
        $this->moduleTemplate->assign('docsurl', $this->configuration->getDocsUrl());
        $this->moduleTemplate->assign(
            'showRecheckButton',
            // only show recheck button if limit to pages or mount points
            $this->filter->getHowtotraverse() !== BrokenLinkListFilter::HOW_TO_TRAVERSE_ALL
            && (
                $this->getBackendUser()->isAdmin()
                || $this->depth <= $this->configuration->getRecheckButton()
            )
        );
        $this->moduleTemplate->assign('isAdmin', $this->getBackendUser()->isAdmin());
    }

    /**
     * Create tabs to split the report and the checkLink functions
     */
    protected function renderContent(): void
    {
        if (!$this->backendUserHasPermissionsForBrokenLinklist) {
            // If no access or if ID == zero
            $this->moduleTemplate->addFlashMessage(
                $this->getLanguageService()->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:no.access'),
                $this->getLanguageService()->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:no.access.title'),
                ContextualFeedbackSeverity::ERROR
            );
            return;
        }

        $this->initializeViewForBrokenLinks();
    }

    protected function initialize(ServerRequestInterface $request): void
    {
        $backendUser = $this->getBackendUser();
        $this->id = (int)($request->getParsedBody()['id'] ?? $request->getQueryParams()['id'] ?? 0);

        if ($this->id !== 0) {
            $this->resolveSiteLanguages($this->id);
            $this->pageinfo = BackendUtility::readPageAccess($this->id, $backendUser->getPagePermsClause(Permission::PAGE_SHOW));
            $this->configuration->loadPageTsConfig($this->id);

            $this->pageRecord = BackendUtility::readPageAccess(
                $this->id,
                $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW)
            );
        }
        $this->getLanguageService()->includeLLFile('EXT:brofix/Resources/Private/Language/Module/locallang.xlf');
        $this->getSettingsFromQueryParameters($request);
        $this->initializeLinkTypes();

        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks'] ?? [] as $linkType => $className) {
            /**
             * @var LinktypeInterface $this->hookObjectsArr[$linkType]
             * @phpstan-ignore-next-line Ignore next line because of dynamic type for $className
             */
            $this->hookObjectsArr[$linkType] = GeneralUtility::makeInstance($className);
        }

        $this->backendUserHasPermissionsForBrokenLinklist = false;
        if (($this->id && is_array($this->pageRecord)) || (!$this->id && $this->getBackendUser()->isAdmin())) {
            $this->backendUserHasPermissionsForBrokenLinklist = true;
        }
        // Don't access in workspace
        if ($this->getBackendUser()->workspace !== 0) {
            $this->backendUserHasPermissionsForBrokenLinklist = false;
        }
        $this->backendUserHasPermissionsForExcludes =
            $this->excludeLinkTarget->currentUserHasCreatePermissions(
                $this->configuration->getExcludeLinkTargetStoragePid()
            );
    }

    protected function initializeLinkTypes(): void
    {
        $this->linkTypes = [];
        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks'] ?? [] as $linkType => $value) {
            $linkTypes = $this->configuration->getLinkTypes();
            // Compile list of all available types. Used for checking with button "Check Links".
            if (in_array($linkType, $linkTypes)) {
                $this->linkTypes[] = $linkType;
            }
        }
    }

    protected function initializePageRenderer(): void
    {
        $this->pageRenderer->addCssFile('EXT:brofix/Resources/Public/Css/brofix.css', 'stylesheet', 'screen');
        $this->pageRenderer->loadJavaScriptModule('@sypets/brofix/Brofix.js');
        $this->pageRenderer->addInlineLanguageLabelFile('EXT:brofix/Resources/Private/Language/Module/locallang.xlf');
    }

    /**
     * Injects the request object for the current request or subrequest
     * Then checks for module functions that have hooked in, and renders menu etc.
     */
    public function mainAction(ModuleTemplate $view): ResponseInterface
    {
        $this->renderContent();
        return $view->renderResponse('Backend/BrokenLinkList');
    }

    /**
     * Checks for incoming GET/POST parameters to update the module settings
     */
    protected function getSettingsFromQueryParameters(ServerRequestInterface $request): void
    {
        // get information for last edited record
        $this->currentRecord = [];
        $this->currentRecord['uid'] = (string)$this->moduleData->get('current_record_uid', '');
        $this->currentRecord['table'] = (string)$this->moduleData->get('current_record_table', '');
        $this->currentRecord['field'] = (string)$this->moduleData->get('current_record_field', '');
        $this->currentRecord['currentTime'] = (string)$this->moduleData->get('current_record_currentTime', '');
        $this->currentRecord['url'] = urldecode((string)$this->moduleData->get('current_record_url', ''));
        $this->currentRecord['linkType'] = (string)$this->moduleData->get('current_record_linkType', '');

        $this->depth = (int)$this->moduleData->get('depth', self::DEFAULT_DEPTH);
        $this->orderBy = $this->moduleData->get('orderBy', BrokenLinkListController::DEFAULT_ORDER_BY);
        if ($this->configuration->isEnableSelectViewControl()) {
            $this->viewMode = $this->moduleData->get('viewMode', self::DEFAULT_VIEW_MODE_VALUE);
        } else {
            $this->viewMode = self::VIEW_MODE_VALUE_COMPLEX;
        }

        $this->filter = BrokenLinkListFilter::getInstanceFromModuleData($this->moduleData);

        $queryParams = $request->getQueryParams();
        $this->route = $queryParams['route'] ?? '';
        $this->token = $queryParams['token'] ?? '';
        $paginationPage = $this->moduleData->get('paginationPage');
        if ($paginationPage !== null) {
            $this->paginationCurrentPage = (int)$paginationPage;
        } else {
            $this->paginationCurrentPage = 1;
        }
    }

    /**
     * @param array<string,mixed> $additionalQueryParameters
     * @param string $route
     * @return string
     * @throws RouteNotFoundException
     */
    protected function constructBackendUri(array $additionalQueryParameters = [], string $route = 'web_brofix'): string
    {
        $parameters = [
            'id' => $this->id,
            'depth' => $this->depth,
            'orderBy' => $this->orderBy,
            'paginationPage', $this->paginationCurrentPage
        ];

        // if same key, additionalQueryParameters should overwrite parameters
        $parameters = array_merge($parameters, $additionalQueryParameters);

        /**
         * @var UriBuilder $uriBuilder
         */
        $uriBuilder = $this->uriBuilder;
        $uri = (string)$uriBuilder->buildUriFromRoute($route, $parameters);

        return $uri;
    }

    /**
     * Updates the table of stored broken links
     */
    protected function initializeLinkAnalyzer(): void
    {
        if ($this->configuration->isEnableHowToTraverseControl()) {
            $howtotraverse = $this->filter->getHowtotraverse();
        } else {
            $howtotraverse = BrokenLinkListFilter::HOW_TO_TRAVERSE_PAGES;
        }
        switch ($howtotraverse) {
            case BrokenLinkListFilter::HOW_TO_TRAVERSE_PAGES:
                $considerHidden = $this->configuration->isCheckHidden();
                $permsClause = $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW);
                if ($this->id !== 0) {
                    $this->pageList = [];

                    // pagetree cache is on if pagetree cache is enabled in extension.
                    $usePageTreeCache = $this->configuration->isUseCacheForPageList();
                    if ($usePageTreeCache) {
                        // If pagetree cache is enabled and pagetree cache button is enabled, must also check if on in filter
                        if ($this->configuration->isEnableCacheForPageListButton()) {
                            $usePageTreeCache = $this->filter->isUsePagetreeCache();
                        }
                    }

                    $this->pagesRepository->getPageList(
                        $this->pageList,
                        [$this->id],
                        $this->depth,
                        $permsClause,
                        $considerHidden,
                        [],
                        $this->configuration->getDoNotCheckPagesDoktypes(),
                        $this->configuration->getDoNotTraversePagesDoktypes(),
                        $this->configuration->getTraverseMaxNumberOfPagesInBackend(),
                        $usePageTreeCache
                    );
                } else {
                    $this->pageList = [];
                }
                break;
            case BrokenLinkListFilter::HOW_TO_TRAVERSE_ALL:
                if ($this->isAdmin()) {
                    $this->pageList = null;
                    break;
                }
                // no break
            case BrokenLinkListFilter::HOW_TO_TRAVERSE_ALLMOUNTPOINTS:
                // get mountpoints
                $startPids = $this->getAllowedDbMounts();
                if ($startPids) {
                    $considerHidden = $this->configuration->isCheckHidden();
                    $permsClause = $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW);
                    $this->pageList = [];

                    // pagetree cache is on if pagetree cache is enabled in extension.
                    $usePageTreeCache = $this->configuration->isUseCacheForPageList();
                    if ($usePageTreeCache) {
                        // If pagetree cache is enabled and pagetree cache button is enabled, must also check if on in filter
                        if ($this->configuration->isEnableCacheForPageListButton()) {
                            $usePageTreeCache = $this->filter->isUsePagetreeCache();
                        }
                    }

                    $this->pagesRepository->getPageList(
                        $this->pageList,
                        $startPids,
                        $this->depth,
                        $permsClause,
                        $considerHidden,
                        [],
                        $this->configuration->getDoNotCheckPagesDoktypes(),
                        $this->configuration->getDoNotTraversePagesDoktypes(),
                        $this->configuration->getTraverseMaxNumberOfPagesInBackend(),
                        $usePageTreeCache
                    );
                }
        }
        $this->linkAnalyzer = GeneralUtility::makeInstance(LinkAnalyzer::class);
        $this->linkAnalyzer->init($this->pageList, $this->configuration);
    }

    /**
     * Displays the table of broken links or a note if there were no broken links
     */
    protected function initializeViewForBrokenLinks(): void
    {
        $this->moduleTemplate->assign('depth', $this->depth);

        $items = [];
        $totalCount = 0;

        $shouldShow = true;
        $howToTraverse = $this->filter->getHowtotraverse();
        if ($howToTraverse === BrokenLinkListFilter::HOW_TO_TRAVERSE_PAGES) {
            if ($this->id <= 0) {
                $shouldShow = false;
            } else {
                // todo: do we need to check rootline for hidden? Was already checked in checking for broken links!
                // @extensionScannerIgnoreLine problem with getRootLineIsHidden
                $rootLineHidden = $this->pagesRepository->getRootLineIsHidden($this->pageinfo);
                if ($rootLineHidden && !$this->configuration->isCheckHidden()) {
                    $shouldShow = false;
                }
            }
        }

        if ($shouldShow) {
            /**
             * @todo Currently, we fetch all and then paginate. We would like to optimize this to fetch only the broken
             *       links for one page. However, this would make it necessary to first fetch the total amount, which
             *       is similarly complicated to getBrokenLinks because of the array_chunking the pids. Possibly, set
             *       a hard limit to the number of pages and do away with array_chunking.
             *       There is already traverseMaxNumberOfPagesInBackend extension configuration which would have to
             *       effectively be set to a hard limit corresponding to (int)(BrokenLinkRepository::getMaxBindParameters() /2 - 4);
            */
            $brokenLinks = $this->brokenLinkRepository->getBrokenLinks(
                $this->pageList,
                $this->linkTypes,
                $this->configuration->getSearchFields(),
                $this->filter,
                $this->configuration,
                self::ORDER_BY_VALUES[$this->orderBy] ?? []
            );
            if ($brokenLinks) {
                $totalCount = count($brokenLinks);

                $itemsPerPage = 100;
                if (($this->paginationCurrentPage - 1) * $itemsPerPage >= $totalCount) {
                    $this->resetPagination();
                }
                $paginator = GeneralUtility::makeInstance(ArrayPaginator::class, $brokenLinks, $this->paginationCurrentPage, $itemsPerPage);
                $this->pagination = GeneralUtility::makeInstance(SimplePagination::class, $paginator);
                // move end
                foreach ($paginator->getPaginatedItems() as $row) {
                    $items[] = $this->renderTableRow($row['table_name'], $row);
                }
                $this->moduleTemplate->assign('listUri', $this->constructBackendUri());
            }
            if ($howToTraverse !== BrokenLinkListFilter::HOW_TO_TRAVERSE_ALL
                && $this->configuration->getTraverseMaxNumberOfPagesInBackend()
                && is_countable($this->pageList)
                && count($this->pageList) >= $this->configuration->getTraverseMaxNumberOfPagesInBackend()) {
                $this->createFlashMessage(
                    sprintf(
                        $this->getLanguageService()->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.report.warning.max_limit_pages_reached')
                            ?: 'The limit of %s number of pages was reached. Some broken links may not be displayed. To see more broken links for further subpages, go to a subpage of this page.',
                        $this->configuration->getTraverseMaxNumberOfPagesInBackend()
                    ),
                    $this->getLanguageService()->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.report.warning.max_limit_pages_reached.title') ?: 'Limit for maximum number of pages reached',
                    ContextualFeedbackSeverity::WARNING
                );
            }
        } else {
            $this->pagination = null;
        }
        $this->moduleTemplate->assign('totalCount', $totalCount);
        $this->moduleTemplate->assign('filter', $this->filter);
        $this->moduleTemplate->assign('viewMode', $this->viewMode);
        if ($this->id === 0) {
            $this->createFlashMessagesForRootPage();
        } elseif (empty($items)) {
            $this->createFlashMessagesForNoBrokenLinks();
        }
        $this->moduleTemplate->assign('brokenLinks', $items);
        $linktypes = array_merge(['all' => 'all'], $this->linkTypes);
        if (count($linktypes) > 2) {
            $this->moduleTemplate->assign('linktypes', $linktypes);
        }

        $this->moduleTemplate->assign('pagination', $this->pagination);
        $this->moduleTemplate->assign('orderBy', $this->orderBy);
        $this->moduleTemplate->assign('paginationPage', $this->paginationCurrentPage ?: 1);

        // todo: only pass configuration
        $this->moduleTemplate->assign('showPageLayoutButton', $this->configuration->isShowPageLayoutButton());

        $this->moduleTemplate->assign('configuration', $this->configuration);

        $sortActions = [];

        foreach (array_keys(self::ORDER_BY_VALUES) as $key) {
            $sortActions[$key] = $this->constructBackendUri(['orderBy' => $key]);
        }
        $this->moduleTemplate->assign('sortActions', $sortActions);

        // Table header
        $this->moduleTemplate->assign('tableHeader', $this->getVariablesForTableHeader($sortActions));
    }

    /**
     * Used when there are no broken links found.
     */
    protected function createFlashMessagesForNoBrokenLinks(): void
    {
        $status = ContextualFeedbackSeverity::OK;
        if ($this->filter->isFilter()) {
            $status = ContextualFeedbackSeverity::WARNING;
            $message = $this->getLanguageService()->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.no.broken.links.filter')
                ?: 'No broken links found if current filter is applied!';
        } elseif ($this->depth === 0) {
            $message = $this->getLanguageService()->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.no.broken.links.this.page')
                ?: 'No broken links on this page!';
            $message .= ' ' . $this->getLanguageService()->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:message.choose.higher.level');
            $status = ContextualFeedbackSeverity::INFO;
        } elseif ($this->depth > 0 && $this->depth < BrokenLinkListFilter::PAGE_DEPTH_INFINITE) {
            $message = $this->getLanguageService()->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.no.broken.links.current.level')
                ?: 'No broken links for current level';
            $message .= ' (' . $this->depth . ').';
            $message .= ' ' . $this->getLanguageService()->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:message.choose.higher.level');
            $status = ContextualFeedbackSeverity::INFO;
        } else {
            $message = $this->getLanguageService()->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.no.broken.links.level.infinite')
                ?: $this->getLanguageService()->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.no.broken.links')
                ?: 'No broken links on this page and its subpages!';
        }
        $this->createFlashMessage(
            $message,
            '',
            $status
        );
    }

    protected function createFlashMessagesForRootPage(): void
    {
        $this->createFlashMessage($this->getLanguageService()->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.rootpage'));
    }

    /**
     * Generic convenience function for creating and enqueing a flash message
     *
     * @param string $message
     * @param string $title
     * @param int|value-of<ContextualFeedbackSeverity>|ContextualFeedbackSeverity $severity
     * @throws Exception
     */
    protected function createFlashMessage(string $message, string $title = '', $severity = ContextualFeedbackSeverity::INFO): void
    {
        /**
         * @var FlashMessage $flashMessage
         */
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $message,
            $title,
            $severity,
            false
        );
        /**
         * @var FlashMessageService $flashMessageService
         */
        $flashMessageService = $this->flashMessageService;
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
            'last_check_record',
            'linktext',
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
            if ($key === 'last_check_record') {
                $part1 = $languageService->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.tableHead.last_check.part1');
                $part2 = $languageService->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.tableHead.last_check.part2.record');
                if ($part1 && $part2) {
                    $tableHeadData[$key]['label'] = $part1
                        . '<br/>'
                        . $part2;
                } else {
                    // fallback: use older language label
                    $tableHeadData[$key]['label'] =
                        $languageService->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.tableHead.last_check');
                }
            } elseif ($key === 'last_check_url') {
                $part1 = $languageService->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.tableHead.last_check.part1');
                $part2 = $languageService->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.tableHead.last_check.part2.url');
                if ($part1 && $part2) {
                    $tableHeadData[$key]['label'] = $part1
                        . '<br/>'
                        . $part2;
                } else {
                    // fallback: use older language label
                    $tableHeadData[$key]['label'] =
                        $languageService->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.tableHead.last_check_url')
                        ?: $languageService->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.tableHead.last_check');
                }
            } else {
                $tableHeadData[$key]['label'] = $languageService->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.tableHead.' . $key);
            }
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
        $linkTargetResponse = LinkTargetResponse::createInstanceFromJson($row['url_response']);
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
                'current_record_currentTime' => $this->context->getPropertyFromAspect('date', 'timestamp'),
            ]
        );

        /**
         * @var UriBuilder $uriBuilder
         */
        $uriBuilder = $this->uriBuilder;
        $showEditButtons = $this->configuration->getShowEditButtons();
        $editUrlParameters = [
            'edit' => [
                $table => [
                    $row['record_uid'] => 'edit',
                ],
            ],
            'returnUrl' => $backUriEditField,
        ];
        if ($showEditButtons === 'both' || $showEditButtons === 'full') {
            // Construct link to edit the full record
            $variables['editUrlFull'] = (string)$uriBuilder->buildUriFromRoute('record_edit', $editUrlParameters);
        }
        if ($showEditButtons === 'both' || $showEditButtons === 'field') {
            // Construct link to edit the field
            $editUrlParameters['columnsOnly'] = $row['field'];
            $variables['editUrlField'] = (string)$uriBuilder->buildUriFromRoute('record_edit', $editUrlParameters);
        }

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
                'current_record_currentTime' => $this->context->getPropertyFromAspect('date', 'timestamp'),
            ]
        );

        $variables['lastChecked'] = 0;
        // check if current record was recently checked
        if (isset($this->currentRecord['uid']) && isset($this->currentRecord['table']) && isset($this->currentRecord['field'])
            //&& $this->action === 'editField'
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
            && $this->backendUserHasPermissionsForExcludes
            && !$linkTargetResponse->isExcluded()
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
        if (isset($row['language'])) {
            $lang = (int)$row['language'];
            if ($lang != -1 && isset($this->siteLanguages[$lang])) {
                $variables['langIcon'] = $this->siteLanguages[$lang]->getFlagIdentifier();
            }
            $variables['lang'] = $lang;
        }

        // Element Type + Field
        if ($isAdmin) {
            $variables['table'] = $table;
            $variables['field'] = $row['field'] ?? '';
        }
        if ($GLOBALS['TCA'][$table]['ctrl']['title'] ?? false) {
            try {
                $variables['elementType'] = $this->getLanguageService()->sL($GLOBALS['TCA'][$table]['ctrl']['title']);
            } catch (\Throwable $e) {
                // todo: log ERROR
                $variables['elementType'] = '';
            }
        } else {
            // todo: log warning
            $variables['elementType'] = '';
        }
        // Get the language label for the field from TCA
        $fieldName = '';
        if ($GLOBALS['TCA'][$table]['columns'][$row['field']]['label'] ?? false) {
            try {
                $fieldName = $languageService->sL($GLOBALS['TCA'][$table]['columns'][$row['field']]['label']);
            } catch (\Throwable $e) {
                // todo: log ERROR
                $fieldName = '';
            }
            // Crop colon from end if present
            if ($fieldName && substr($fieldName, -1, 1) === ':') {
                $fieldName = substr($fieldName, 0, strlen($fieldName) - 1);
            }
        }
        $variables['fieldName'] = !empty($fieldName) ? $fieldName : $row['field'];
        // flexform field label
        if ($row['flexform_field_label'] ?? '') {
            $flexformLabel = $languageService->sL($row['flexform_field_label']);
            if ($flexformLabel) {
                $variables['fieldName'] = $flexformLabel;
            }
        }

        // page title / uid / path
        $pageId = (int)($row['record_pageid'] ?? 0);

        /**
         * @todo remove this part, record_pageid should always contain page id in the futuer
         */
        // legacy BEGIN (fallback for missing record_pageid)
        if ($pageId === 0) {
            $pageId = (int)($table === 'pages' ? $row['record_uid'] : $row['record_pid']);
        }
        // legacy END

        $variables['pageId'] = $pageId;
        $path = $this->pagesRepository->getPagePath($pageId, 50);
        $variables['path'] = $path[1];
        $variables['pagetitle'] = $path[0] ?? '';

        // error message
        switch ($linkTargetResponse->getStatus()) {
            case LinkTargetResponse::RESULT_BROKEN:
                $linkMessage = sprintf(
                    '<span class="error" title="%s">%s</span>',
                    nl2br(
                        htmlspecialchars(
                            $linkTargetResponse->getExceptionMessage(),
                            ENT_QUOTES,
                            'UTF-8',
                            false
                        )
                    ),
                    nl2br(
                        // Encode for output
                        htmlspecialchars(
                            $hookObj->getErrorMessage($linkTargetResponse),
                            ENT_QUOTES,
                            'UTF-8',
                            false
                        )
                    )
                );
                break;
            case LinkTargetResponse::RESULT_OK:
                $linkMessage = '<span class="valid">' . htmlspecialchars($languageService->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.msg.ok')) . '</span>';
                break;

            case LinkTargetResponse::RESULT_CANNOT_CHECK:
                // todo add language label
                $linkMessage = sprintf(
                    '<span class="status-cannot-check">%s</span>',
                    htmlspecialchars($languageService->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.msg.status.cannot_check')) ?: 'Cannot check URL'
                );
                if ($linkTargetResponse->getReasonCannotCheck()) {
                    $linkMessage .= ':' . $linkTargetResponse->getReasonCannotCheck();
                }
                $linkMessage .= ': ' . sprintf(
                    '<span class="error" title="%s">%s</span>',
                    nl2br(
                        htmlspecialchars(
                            $linkTargetResponse->getExceptionMessage(),
                            ENT_QUOTES,
                            'UTF-8',
                            false
                        )
                    ),
                    nl2br(
                        // Encode for output
                        htmlspecialchars(
                            $hookObj->getErrorMessage($linkTargetResponse),
                            ENT_QUOTES,
                            'UTF-8',
                            false
                        )
                    )
                );
                break;

            case LinkTargetResponse::RESULT_EXCLUDED:
                // todo add language label
                $linkMessage = sprintf(
                    '<span class="status-excluded">%s</span>',
                    htmlspecialchars($languageService->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.msg.status.excluded')) ?: 'URL is excluded, will not be checked'
                );
                break;
            default:
                // todo add language label
                $linkMessage = sprintf(
                    '<span class="status-unknown">%s</span>',
                    htmlspecialchars($languageService->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.msg.status.unknown')) ?: 'Unknown status'
                );
                break;
        }
        if (($row['url_checker'] ?? false)
        && ($this->configuration->isShowUrlCheckerOn()
            ||
            ($this->configuration->isShowUrlCheckerAdminOnly() && $this->getBackendUser()->isAdmin()))
        ) {
            $linkMessage .= ' (' . $row['url_checker'] . ')';
        }
        $variables['linkmessage'] = $linkMessage;
        $variables['status'] = $linkTargetResponse->getStatus();

        // link / URL
        $variables['linktarget'] = $hookObj->getBrokenUrl($row);
        $variables['effectiveUrl'] = $linkTargetResponse->getEffectiveUrl();
        $variables['redirectCount'] = $linkTargetResponse->getRedirectCount();
        $variables['orig_linktarget'] = $row['url'];
        if ($this->filter->getUrlFilter() == $variables['orig_linktarget']
            && $this->filter->getUrlFilterMatch() === 'exact'
        ) {
            // filter already active for this URL, offer to deactivate filter
            $variables['encoded_linktarget'] = '';
        } else {
            $variables['encoded_linktarget'] = urlencode($variables['orig_linktarget']);
        }
        if (isset($row['link_title']) && $variables['linktarget'] !== $row['link_title']) {
            $variables['link_title'] = htmlspecialchars($row['link_title']);
        } else {
            $variables['link_title'] = '';
        }
        $variables['linktext'] = $hookObj->getBrokenLinkText($row, $linkTargetResponse->getCustom());

        // error
        /** @phpstan-ignore-next-line  */
        if ($row['error_type'] ?? false && $row['errno'] ?? false) {
            $variables['error'] = $row['error_type'] . ':' . $row['errno'];
        }

        // last check of record
        // show the oldest last_check, either for the record or for the link target
        $variables['lastcheck_combined'] = StringUtil::formatTimestampAsString($row['last_check'] < $row['last_check_url'] ? $row['last_check'] : $row['last_check_url']);
        $variables['last_check'] = StringUtil::formatTimestampAsString($row['last_check']);
        $variables['last_check_url'] = StringUtil::formatTimestampAsString($row['last_check_url']);

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

    protected function resetPagination(int $pageNr = 1): void
    {
        $this->paginationCurrentPage = $pageNr;
    }

    protected function resetModuleData(bool $resetCurrentRecord = true): void
    {
        $persist = false;
        if ($this->moduleData->get('current_record_uid')) {
            $this->moduleData->set('current_record_uid', '');
            $this->moduleData->set('current_record_table', '');
            $this->moduleData->set('current_record_field', '');
            $this->moduleData->set('current_record_currentTime', '');
            $this->moduleData->set('current_record_url', '');
            $this->moduleData->set('current_record_linkType', '');
            if ($resetCurrentRecord) {
                $this->currentRecord = [
                    'uid' => 0,
                    'table' => '',
                    'field' => '',
                    'currentTime' => 0,
                    'url' => '',
                    'linkType' => ''
                ];
            }
            $persist = true;
        }
        if ($this->moduleData->get('action', 'report') !== 'report') {
            $this->moduleData->set('action', 'report');
            $this->action = 'report';
            $persist = true;
        }

        if ($persist) {
            $this->getBackendUser()->pushModuleData(self::MODULE_NAME, $this->moduleData->toArray());
        }
    }

    /**
     * @return int[]
     */
    public function getAllowedDbMounts(): array
    {
        $dbMounts = (int)($this->getBackendUser()->uc['pageTree_temporaryMountPoint'] ?? 0);
        if (!$dbMounts) {
            $dbMounts = array_map(intval(...), $this->getBackendUser()->returnWebmounts());

            $dbMounts = array_unique($dbMounts);
        } else {
            $dbMounts = [$dbMounts];
        }

        return $dbMounts;
    }
}
