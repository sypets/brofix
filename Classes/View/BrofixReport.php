<?php

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

namespace Sypets\Brofix\View;

use Sypets\Brofix\CheckLinks\ExcludeLinkTarget;
use Sypets\Brofix\Configuration\Configuration;
use Sypets\Brofix\LinkAnalyzer;
use Sypets\Brofix\Linktype\ErrorParams;
use Sypets\Brofix\Linktype\LinktypeInterface;
use Sypets\Brofix\Repository\BrokenLinkRepository;
use Sypets\Brofix\Repository\PagesRepository;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\DocumentTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Info\Controller\InfoModuleController;

/**
 * Module 'Check links' as sub module of Web -> Info
 * @internal
 */
class BrofixReport
{
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
        'field' => [
            ['table_name', 'ASC'],
            ['field', 'ASC'],
        ],
        'field_reverse' => [
            ['table_name', 'DESC'],
            ['field', 'DESC'],
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
        'errortype' => [
            ['link_type', 'ASC'],
            ['url_response', 'ASC']
        ],
        'errortype_reverse' => [
            ['link_type', 'DESC'],
            ['url_response', 'DESC']
        ],
    ];

    protected const ORDER_BY_DEFAULT = 'page';

    /**
     * @var string
     */
    protected $orderBy = self::ORDER_BY_DEFAULT;

    /**
     * @var DocumentTemplate
     */
    protected $doc;

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
        'uid'   => 0,
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
     * @var InfoModuleController Contains a reference to the parent calling object
     */
    protected $pObj;

    /**
     * @var array<string|int>
     */
    protected $pageList;

    /**
     * @var array<string,array<string>>
     */
    protected $searchFields = [];

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
     * @var FlashMessageQueue
     */
    protected $defaultFlashMessageQueue;

    /**
     * @var ModuleTemplate
     */
    protected $moduleTemplate;

    /**
     * @var StandaloneView
     */
    protected $view;

    /**
     * @var Configuration
     */
    protected $configuration;

    public function __construct(
        PagesRepository $pagesRepository = null,
        BrokenLinkRepository $brokenLinkRepository = null,
        ExcludeLinkTarget $excludeLinkTarget = null,
        Configuration $configuration = null,
        FlashMessageService $flashMessageService = null
    ) {
        $this->brokenLinkRepository = $brokenLinkRepository ?: GeneralUtility::makeInstance(BrokenLinkRepository::class);
        $this->pagesRepository = $pagesRepository ?: GeneralUtility::makeInstance(PagesRepository::class);
        $this->excludeLinkTarget = $excludeLinkTarget ?: GeneralUtility::makeInstance(ExcludeLinkTarget::class);
        $this->configuration = $configuration ?: GeneralUtility::makeInstance(Configuration::class);
        $flashMessageService = $flashMessageService ?: GeneralUtility::makeInstance(FlashMessageService::class);
        $this->defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
    }

    /**
     * Init, called from parent object
     *
     * @param InfoModuleController $pObj A reference to the parent (calling) object
     */
    public function init(InfoModuleController $pObj): void
    {
        $this->pObj = $pObj;
        $this->id = (int)GeneralUtility::_GP('id');
        if ($this->id === 0) {
            // @todo work-around, because this->id does not work if "Refresh display" is used
            $this->id = (int)GeneralUtility::_GP('currentPage');
        }
        if ($this->id !== 0) {
            $this->resolveSiteLanguages($this->id);
        }
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $this->moduleTemplate = GeneralUtility::makeInstance(ModuleTemplate::class);
        $this->view = $this->createView('InfoModule');
        if (isset($this->id)) {
            $this->configuration->loadPageTsConfig($this->id);
            $this->currentUserHasPermissionsForExcludeLinkTargetStorage
                = $this->excludeLinkTarget->currentUserHasCreatePermissions(
                    $this->configuration->getExcludeLinkTargetStoragePid()
                );
        }
    }

    protected function createView(string $templateName): StandaloneView
    {
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setLayoutRootPaths(['EXT:brofix/Resources/Private/Layouts']);
        $view->setPartialRootPaths(['EXT:brofix/Resources/Private/Partials']);
        $view->setTemplateRootPaths(['EXT:brofix/Resources/Private/Templates/Backend']);
        $view->setTemplate($templateName);
        $view->assign('currentPage', $this->id);
        $view->assign('depth', $this->depth);
        $view->assign('docsurl', $this->configuration->getTsConfig()['report.']['docsurl'] ?? '');
        $view->assign('showRecheckButton', $this->depth <= $this->configuration->getRecheckButton());
        return $view;
    }

    /**
     * Checks for incoming GET/POST parameters to update the module settings
     */
    protected function getSettingsFromQueryParameters(): void
    {
        $this->currentRecord = [];

        // get information for last edited record
        $this->currentRecord['uid'] = GeneralUtility::_GP('current_record_uid') ?? 0;
        $this->currentRecord['table'] = GeneralUtility::_GP('current_record_table') ?? '';
        $this->currentRecord['field'] = GeneralUtility::_GP('current_record_field') ?? '';
        $this->currentRecord['currentTime'] = GeneralUtility::_GP('current_record_currentTime') ?? 0;
        $this->currentRecord['url'] = urldecode(GeneralUtility::_GP('current_record_url') ?? '');
        $this->currentRecord['linkType'] = GeneralUtility::_GP('current_record_linkType') ?? '';

        // get searchLevel (number of levels of pages to check / show results)
        $depth = GeneralUtility::_GP('depth');
        if (is_null($depth)) {
            // not set, set to stored value or 0 (default)
            $this->depth = (int)($this->pObj->MOD_SETTINGS['depth'] ?? 0);
        } else {
            $this->depth = (int)$depth;
        }
        $this->pObj->MOD_SETTINGS['depth'] = $this->depth;

        $this->route =  GeneralUtility::_GP('route') ?? '';
        $this->token = GeneralUtility::_GP('token') ?? '';
        $this->action = GeneralUtility::_GP('action') ?? '';

        if (GeneralUtility::_GP('updateLinkList') ?? '') {
            $this->action = 'updateLinkList';
        }

        $this->orderBy = (string)(GeneralUtility::_GP('orderBy')
            ?: ($this->pObj->MOD_SETTINGS['orderBy'] ?? self::ORDER_BY_DEFAULT));
        $this->pObj->MOD_SETTINGS['orderBy'] = $this->orderBy;

        $this->linkTypes = [];
        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks'] ?? [] as $linkType => $value) {
            $linkTypes = $this->configuration->getLinkTypes();
            // Compile list of all available types. Used for checking with button "Check Links".
            if (in_array($linkType, $linkTypes)) {
                $this->linkTypes[] = $linkType;
            }
        }

        // save settings
        $this->getBackendUser()->pushModuleData('web_info', $this->pObj->MOD_SETTINGS);
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
            'id' => $this->id,
            'depth' => $this->depth,
            'orderBy' => $this->orderBy
        ];

        // if same key, additionalQueryParameters should overwrite parameters
        $parameters = array_merge($parameters, $additionalQueryParameters);

        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $uri = (string)$uriBuilder->buildUriFromRoute($route, $parameters);

        return $uri;
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
        $this->initialize();

        if ($this->action === 'updateLinkList') {
            $this->linkAnalyzer->generateBrokenLinkRecords($this->configuration->getLinkTypes());
            // todo: localize this
            $this->createFlashMessage($this->getLanguageService()->getLL('list.status.check.done'), '', FlashMessage::OK);
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
        $this->view->assign('content', $this->renderContent());
        return $this->view->render();
    }

    /**
     * Create tabs to split the report and the checkLink functions
     */
    protected function renderContent(): string
    {
        if (!$this->isAccessibleForCurrentUser) {
            // If no access or if ID == zero
            $this->moduleTemplate->addFlashMessage(
                $this->getLanguageService()->getLL('no.access'),
                $this->getLanguageService()->getLL('no.access.title'),
                FlashMessage::ERROR
            );
            return '';
        }

        $reportsTabView = $this->createViewForBrokenLinksTab();
        $menuItems = [
            0 => [
                'label' => $this->getLanguageService()->getLL('Report'),
                'content' => $reportsTabView->render()
            ],
        ];

        return $this->moduleTemplate->getDynamicTabMenu($menuItems, 'report-brofix');
    }

    /**
     * Initializes the Module
     */
    protected function initialize(): void
    {
        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks'] ?? [] as $linkType => $className) {
            $this->hookObjectsArr[$linkType] = GeneralUtility::makeInstance($className);
        }

        $this->pageRecord = BackendUtility::readPageAccess($this->id, $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW));
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

        $this->initializeLinkAnalyzer();
    }

    /**
     * Updates the table of stored broken links
     */
    protected function initializeLinkAnalyzer(): void
    {
        $considerHidden = $this->configuration->isCheckHidden();
        $depth = $this->depth;
        $permsClause = (string)$this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW);
        if ($this->id !== 0) {
            $this->pageList = $this->pagesRepository->getPageList($this->id, $depth, $permsClause, $considerHidden);
        } else {
            $this->pageList = [];
        }
        $this->linkAnalyzer = GeneralUtility::makeInstance(LinkAnalyzer::class);
        $this->linkAnalyzer->init($this->searchFields, $this->pageList, $this->configuration);
    }

    /**
     * Displays the table of broken links or a note if there were no broken links
     *
     * @return StandaloneView
     */
    protected function createViewForBrokenLinksTab(): StandaloneView
    {
        $view = $this->createView('ReportTab');
        $view->assign('depth', $this->depth);
        // Table header
        $view->assignMultiple($this->getVariablesForTableHeader());

        $linkTypes = $this->linkTypes;
        $items = [];
        $totalCount = 0;
        // todo: do we need to check rootline for hidden? Was already checked in checking for broken links!
        // @extensionScannerIgnoreLine problem with getRootLineIsHidden
        $rootLineHidden = $this->pagesRepository->getRootLineIsHidden($this->pObj->pageinfo);
        if ($this->id > 0 && (!$rootLineHidden || $this->configuration->isCheckHidden())) {
            $brokenLinks = $this->brokenLinkRepository->getBrokenLinks(
                $this->pageList,
                // todo: do we need to pass this, was already handled when checking links?
                $linkTypes,
                self::ORDER_BY_VALUES[$this->orderBy] ?? []
            );
            if ($brokenLinks) {
                $totalCount =  count($brokenLinks);
            }
            foreach ($brokenLinks as $row) {
                $items[] = $this->renderTableRow($row['table_name'], $row);
            }
        }
        $view->assign('totalCount', $totalCount);
        if ($this->id === 0) {
            $this->createFlashMessagesForRootPage();
        } elseif (empty($items)) {
            $this->createFlashMessagesForNoBrokenLinks();
        }
        $view->assign('brokenLinks', $items);
        $view->assign('orderBy', $this->orderBy);
        $sortActions = [];

        foreach (array_keys(self::ORDER_BY_VALUES) as $key) {
            $sortActions[$key] = $this->constructBackendUri(['orderBy' => $key]);
        }
        $view->assign('sortActions', $sortActions);
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
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $title,
            $message,
            $type,
            false
        );
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier('brofix');
        $defaultFlashMessageQueue->enqueue($flashMessage);
    }

    /**
     * Sets variables for the Fluid Template of the table with the broken links
     * @return mixed[] variables
     */
    protected function getVariablesForTableHeader(): array
    {
        $languageService = $this->getLanguageService();
        $variables = [
            'tableheadPath' => $languageService->getLL('list.tableHead.page'),
            'tableheadElement' => $languageService->getLL('list.tableHead.element'),
            //'tableheadElementType' => $languageService->getLL('list.tableHead.element.type'),
            //'tableheadElementField' => $languageService->getLL('list.tableHead.element.field'),
            //'tableheadHeadlink' => $languageService->getLL('list.tableHead.headlink'),
            'tableheadLinktarget' => $languageService->getLL('list.tableHead.linktarget'),
            'tableheadLinkmessage' => $languageService->getLL('list.tableHead.linkmessage'),
            'tableheadLastcheck' => $languageService->getLL('list.tableHead.lastCheck'),
        ];

        // Add CSH to the header of each column
        foreach ($variables as $column => $label) {
            $variables[$column] = BackendUtility::wrapInHelp('brofix', $column, $label);
        }
        return $variables;
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
        $variables['elementType'] = $this->getLanguageSplitLabel($GLOBALS['TCA'][$table]['ctrl']['title']);
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
        $path = $this->getPagePath($pageId, 50);
        $variables['path'] = $path[1];
        $variables['pagetitle'] =  $path[0] ?? '';

        // error message
        $response = $response = json_decode($row['url_response'], true);
        $errorParams = new ErrorParams($response['errorParams']);
        if ($response['valid']) {
            $linkMessage = '<span class="valid">' . htmlspecialchars($languageService->getLL('list.msg.ok')) . '</span>';
        } else {
            $linkMessage = '<span class="error">'
                . nl2br(
                // Encode for output
                    htmlspecialchars(
                        $hookObj->getErrorMessage($errorParams),
                        ENT_QUOTES,
                        'UTF-8',
                        false
                    )
                )
                . '</span>';
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
        $currentDate =  date($GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'], \time());
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

    /**
     * Slightly modified version of BackendUtility::getRecordPath()
     *
     * @param int $uid
     * @param int $titleLimit
     * @return mixed[] returns Page title, rootline
     */
    protected function getPagePath(int $uid, int $titleLimit = 0): array
    {
        $title = '';
        $path = '';

        // @todo this is really inefficient, because we only need the title
        $data = BackendUtility::BEgetRootLine($uid, '', true);
        foreach ($data as $record) {
            if ($record['uid'] === 0) {
                continue;
            }
            if ($title == '') {
                $title = GeneralUtility::fixed_lgd_cs(strip_tags($record['title']), $titleLimit)
                    ?? '[' . $record['uid'] . ']';
            }
            $path = '/' . strip_tags($record['title']) . $path;
        }
        return [$title, $path];
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
        $site = GeneralUtility::makeInstance(SiteMatcher::class)->matchByPageId($pageId);
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
