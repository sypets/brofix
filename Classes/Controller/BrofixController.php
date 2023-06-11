<?php

declare(strict_types=1);

namespace Sypets\Brofix\Controller;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\PreviewUriBuilder;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Loosely based on InfoModuleController of TYPO3 CMS
 * @internal This class may change without further warnings or increment of major version.
 */
class BrofixController
{
    /**
     * @var array<mixed> Used by client classes.
     */
    public $pageinfo;

    /**
     * The name of the module
     *
     * @var string
     */
    protected $moduleName = 'web_brofix';

    /**
     * ModuleTemplate Container
     *
     * @var ModuleTemplate
     */
    protected $moduleTemplate;

    /**
     * @var StandaloneView
     */
    protected $view;

    /**
     * @var UriBuilder
     */
    protected $uriBuilder;

    /**
     * @var FlashMessageService
     */
    protected $flashMessageService;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var int Value of the GET/POST var 'id'
     */
    protected $id;

    /**
     * A WHERE clause for selection records from the pages table based on read-permissions of the current backend user.
     *
     * @var string
     */
    protected $perms_clause;

    /**
     * The module menu items array. Each key represents a key for which values can range between the items in the array of that key.
     * Written by client classes.
     *
     * @var array<mixed>
     */
    public $MOD_MENU = [
        'function' => []
    ];

    /**
     * Current settings for the keys of the MOD_MENU array
     * Written by client classes.
     *
     * @var array<mixed>
     */
    public $MOD_SETTINGS = [];

    /**
     * Module TSconfig based on PAGE TSconfig / USER TSconfig
     *
     * @var array<mixed>
     */
    protected $modTSconfig;

    /**
     * If type is 'ses' then the data is stored as session-lasting data. This means that it'll be wiped out the next time the user logs in.
     * Can be set from extension classes of this class before the init() function is called.
     *
     * @var string
     */
    protected $modMenu_type = '';

    /**
     * dontValidateList can be used to list variables that should not be checked if their value is found in the MOD_MENU array. Used for dynamically generated menus.
     * Can be set from extension classes of this class before the init() function is called.
     *
     * @var string
     */
    protected $modMenu_dontValidateList = '';

    /**
     * List of default values from $MOD_MENU to set in the output array (only if the values from MOD_MENU are not arrays)
     * Can be set from extension classes of this class before the init() function is called.
     *
     * @var string
     */
    protected $modMenu_setDefaultList = '';

    /**
     * @var array<mixed> Contains module configuration parts from TBE_MODULES_EXT if found
     */
    protected $extClassConf;

    /**
     * Generally used for accumulating the output content of backend modules
     *
     * @var string
     */
    protected $content = '';

    /**
     * May contain an instance of a 'Function menu module' which connects to this backend module.
     *
     * @var object
     */
    protected $extObj;
    protected IconFactory $iconFactory;
    protected PageRenderer $pageRenderer;

    /**
     * Constructor
     */
    public function __construct(
        ModuleTemplate $moduleTemplate,
        UriBuilder $uriBuilder,
        FlashMessageService $flashMessageService,
        ContainerInterface $container,
        IconFactory $iconFactory,
        PageRenderer $pageRenderer
    ) {
        $this->pageRenderer = $pageRenderer;
        $this->iconFactory = $iconFactory;
        $this->moduleTemplate = $moduleTemplate;
        $this->uriBuilder = $uriBuilder;
        $this->flashMessageService = $flashMessageService;
        $this->container = $container;

        $languageService = $this->getLanguageService();
        //$languageService->includeLLFile('EXT:info/Resources/Private/Language/locallang_mod_web_brofix.xlf');
    }

    /**
     * Initializes the backend module by setting internal variables, initializing the menu.
     */
    protected function init(): void
    {
        $this->id = (int)GeneralUtility::_GP('id');
        $this->perms_clause = $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW);
        $this->menuConfig();
        $this->handleExternalFunctionValue();
    }

    /**
     * Initialize module header etc and call extObjContent function
     */
    protected function main(): void
    {
        $languageService = $this->getLanguageService();
        $backendUser = $this->getBackendUser();

        // The page will show only if there is a valid page and if this page
        // may be viewed by the user
        $this->pageinfo = BackendUtility::readPageAccess($this->id, $this->perms_clause);
        if ($this->pageinfo) {
            $this->moduleTemplate->getDocHeaderComponent()->setMetaInformation($this->pageinfo);
        }
        $access = is_array($this->pageinfo);
        if (($this->id && $access) || ($backendUser->isAdmin() && !$this->id)) {
            if ($backendUser->isAdmin() && !$this->id) {
                $this->pageinfo = ['title' => '[root-level]', 'uid' => 0, 'pid' => 0];
            }
            // JavaScript
            $this->moduleTemplate->addJavaScriptCode(
                'WebFuncInLineJS',
                'if (top.fsMod) top.fsMod.recentIds["web"] = ' . (int)$this->id . ';
				'
            );
            // Setting up the context sensitive menu:
            $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/ContextMenu');

            $this->view = $this->getFluidTemplateObject();
            $this->view->assign('moduleName', (string)$this->uriBuilder->buildUriFromRoute($this->moduleName));
            $this->view->assign('functionMenuModuleContent', $this->getExtObjContent());
            // Setting up the buttons and markers for doc header
            $this->createButtonsOnButtonBar();
            $this->generateMenu();
            $this->content .= $this->view->render();
        } else {
            // If no access or if ID == zero
            $this->content = $this->moduleTemplate->header($languageService->getLL('title'));
        }
    }

    /**
     * Injects the request object for the current request or subrequest
     * Then checks for module functions that have hooked in, and renders menu etc.
     *
     * @param ServerRequestInterface $request the current request
     * @return ResponseInterface the response with the content
     */
    public function mainAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->init();
        // Checking for first level external objects
        $this->checkExtObj();

        $this->main();

        $this->moduleTemplate->setContent($this->content);
        return new HtmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * Create the panel of buttons for submitting the form or otherwise perform operations.
     */
    protected function createButtonsOnButtonBar(): void
    {
        $languageService = $this->getLanguageService();
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        // create "View page" button
        $previewDataAttributes = PreviewUriBuilder::create((int)$this->pageinfo['uid'])
            ->withRootLine(BackendUtility::BEgetRootLine($this->pageinfo['uid']))
            ->buildDispatcherDataAttributes();
        $viewButton = $buttonBar->makeLinkButton()
            ->setHref('#')
            ->setDataAttributes($previewDataAttributes ?? [])
            ->setTitle($languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.showPage'))
            ->setIcon($this->iconFactory->getIcon(
                'actions-view-page',
                Icon::SIZE_SMALL
            ));
        $buttonBar->addButton($viewButton, ButtonBar::BUTTON_POSITION_LEFT, 1);
    }

    /**
     * Generate the ModuleMenu
     */
    protected function generateMenu(): void
    {
        $menu = $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $menu->setIdentifier('WebBrofixJumpMenu');
        foreach ($this->MOD_MENU['function'] as $controller => $title) {
            $item = $menu
                ->makeMenuItem()
                ->setHref(
                    (string)$this->uriBuilder->buildUriFromRoute(
                        $this->moduleName,
                        [
                            'id' => $this->id,
                            'SET' => [
                                'function' => $controller
                            ]
                        ]
                    )
                )
                ->setTitle($title);
            if ($controller === $this->MOD_SETTINGS['function']) {
                $item->setActive(true);
            }
            $menu->addMenuItem($item);
        }
        $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
    }

    /**
     * returns a new standalone view, shorthand function
     *
     * @return StandaloneView
     */
    protected function getFluidTemplateObject(): StandaloneView
    {
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        /*
        $view->setLayoutRootPaths([GeneralUtility::getFileAbsFileName('EXT:brofix/Resources/Private/Layouts')]);
        $view->setPartialRootPaths([GeneralUtility::getFileAbsFileName('EXT:brofix/Resources/Private/Partials')]);
        $view->setTemplateRootPaths([GeneralUtility::getFileAbsFileName('EXT:brofix/Resources/Private/Templates')]);

        */
        //

        $view->setLayoutRootPaths([GeneralUtility::getFileAbsFileName('EXT:brofix/Resources/Private/Layouts')]);
        $view->setPartialRootPaths([GeneralUtility::getFileAbsFileName('EXT:brofix/Resources/Private/Partials')]);
        $view->setTemplateRootPaths([GeneralUtility::getFileAbsFileName('EXT:brofix/Resources/Private/Templates')]);
        $view->setTemplatePathAndFilename(GeneralUtility::getFileAbsFileName('EXT:brofix/Resources/Private/Templates/Backend/Brofix.html'));
        //$view->setTemplatePathAndFilename(GeneralUtility::getFileAbsFileName('EXT:info/Resources/Private/Templates/Main.html'));

        $view->getRequest()->setControllerExtensionName('brofix');
        return $view;
    }

    /**
     * Initializes the internal MOD_MENU array setting and unsetting items based on various conditions. It also merges in external menu items from the global array TBE_MODULES_EXT (see mergeExternalItems())
     * Then MOD_SETTINGS array is cleaned up (see \TYPO3\CMS\Backend\Utility\BackendUtility::getModuleData()) so it contains only valid values. It's also updated with any SET[] values submitted.
     * Also loads the modTSconfig internal variable.
     */
    protected function menuConfig(): void
    {
        // Page / user TSconfig settings and blinding of menu-items
        $this->modTSconfig['properties'] = BackendUtility::getPagesTSconfig($this->id)['mod.']['web_brofix.'] ?? [];
        $this->MOD_MENU['function'] = $this->mergeExternalItems('web_brofix', 'function', $this->MOD_MENU['function']);
        $blindActions = $this->modTSconfig['properties']['menu.']['function.'] ?? [];
        foreach ($blindActions as $key => $value) {
            if (!$value && array_key_exists($key, $this->MOD_MENU['function'])) {
                unset($this->MOD_MENU['function'][$key]);
            }
        }
        $this->MOD_SETTINGS = BackendUtility::getModuleData($this->MOD_MENU, GeneralUtility::_GP('SET'), 'web_brofix', $this->modMenu_type, $this->modMenu_dontValidateList, $this->modMenu_setDefaultList);
    }

    /**
     * Merges menu items from global array $TBE_MODULES_EXT
     *
     * @param string $modName Module name for which to find value
     * @param string $menuKey Menu key, eg. 'function' for the function menu.
     * @param array<mixed> $menuArr The part of a MOD_MENU array to work on.
     * @return array<mixed> Modified array part.
     * @internal
     */
    protected function mergeExternalItems($modName, $menuKey, $menuArr): array
    {
        $mergeArray = $GLOBALS['TBE_MODULES_EXT'][$modName]['MOD_MENU'][$menuKey];
        if (is_array($mergeArray)) {
            $backendUser = $this->getBackendUser();
            foreach ($mergeArray as $k => $v) {
                if (((string)$v['ws'] === '' || $backendUser->workspace === 0 && GeneralUtility::inList($v['ws'], 'online'))
                    || $backendUser->workspace === -1 && GeneralUtility::inList($v['ws'], 'offline')
                    || $backendUser->workspace > 0 && GeneralUtility::inList($v['ws'], 'custom')
                ) {
                    $menuArr[$k] = $this->getLanguageService()->sL($v['title']);
                }
            }
        }
        return $menuArr;
    }

    /**
     * Loads $this->extClassConf with the configuration for the CURRENT function of the menu.
     *
     * @param string $MM_key The key to MOD_MENU for which to fetch configuration. 'function' is default since it is first and foremost used to get information per "extension object" (I think that is what its called)
     * @param string $MS_value The value-key to fetch from the config array. If NULL (default) MOD_SETTINGS[$MM_key] will be used. This is useful if you want to force another function than the one defined in MOD_SETTINGS[function]. Call this in init() function of your Script Class: handleExternalFunctionValue('function', $forcedSubModKey)
     */
    protected function handleExternalFunctionValue($MM_key = 'function', $MS_value = null): void
    {
        if ($MS_value === null) {
            $MS_value = $this->MOD_SETTINGS[$MM_key];
        }
        $this->extClassConf = $this->getExternalItemConfig('web_brofix', $MM_key, $MS_value);
    }

    /**
     * Returns configuration values from the global variable $TBE_MODULES_EXT for the module given.
     * For example if the module is named "web_brofix" and the "function" key ($menuKey) of MOD_SETTINGS is "stat" ($value) then you will have the values
     * of $TBE_MODULES_EXT['webbrofix']['MOD_MENU']['function']['stat'] returned.
     *
     * @param string $modName Module name
     * @param string $menuKey Menu key, eg. "function" for the function menu. See $this->MOD_MENU
     * @param string $value Optionally the value-key to fetch from the array that would otherwise have been returned if this value was not set. Look source...
     * @return array<mixed> The value from the TBE_MODULES_EXT array.
     */
    protected function getExternalItemConfig($modName, $menuKey, $value = ''): array
    {
        if (isset($GLOBALS['TBE_MODULES_EXT'][$modName])) {
            return (string)$value !== ''
                ? $GLOBALS['TBE_MODULES_EXT'][$modName]['MOD_MENU'][$menuKey][$value]
                : $GLOBALS['TBE_MODULES_EXT'][$modName]['MOD_MENU'][$menuKey];
        }
        return [];
    }

    /**
     * Creates an instance of the class found in $this->extClassConf['name'] in $this->extObj if any (this should hold three keys, "name", "path" and "title" if a "Function menu module" tries to connect...)
     * This value in extClassConf might be set by an extension (in an ext_tables/ext_localconf file) which thus "connects" to a module.
     * The array $this->extClassConf is set in handleExternalFunctionValue() based on the value of MOD_SETTINGS[function]
     * If an instance is created it is initiated with $this passed as value and $this->extClassConf as second argument. Further the $this->MOD_SETTING is cleaned up again after calling the init function.
     */
    protected function checkExtObj(): void
    {
        if (is_array($this->extClassConf) && $this->extClassConf['name']) {
            if ($this->container->has($this->extClassConf['name'])) {
                $this->extObj = $this->container->get($this->extClassConf['name']);
            } else {
                $this->extObj = GeneralUtility::makeInstance($this->extClassConf['name']);
            }
            if (is_callable([$this->extObj, 'init'])) {
                $this->extObj->init($this);
            }
            // Re-write:
            $this->MOD_SETTINGS = BackendUtility::getModuleData($this->MOD_MENU, GeneralUtility::_GP('SET'), 'web_brofix', $this->modMenu_type, $this->modMenu_dontValidateList, $this->modMenu_setDefaultList);
        }
    }

    /**
     * Calls the 'main' function inside the "Function menu module" if present
     */
    protected function extObjContent(): void
    {
        if ($this->extObj === null) {
            $languageService = $this->getLanguageService();
            $flashMessage = GeneralUtility::makeInstance(
                FlashMessage::class,
                $languageService->sL('LLL:EXT:backend/Resources/Private/Language/locallang.xlf:no_modules_registered'),
                $languageService->getLL('title'),
                AbstractMessage::ERROR
            );
            /** @var FlashMessageQueue $defaultFlashMessageQueue */
            $defaultFlashMessageQueue = $this->flashMessageService->getMessageQueueByIdentifier();
            $defaultFlashMessageQueue->enqueue($flashMessage);
        } else {
            if (is_callable([$this->extObj, 'main'])) {
                $main = $this->extObj->main();
                if ($main instanceof ResponseInterface) {
                    $stream = $main->getBody();
                    $stream->rewind();
                    $main = $stream->getContents();
                }
                $this->content .= $main;
            }
        }
    }

    /**
     * Return the content of the 'main' function inside the "Function menu module" if present
     *
     * @return string
     */
    protected function getExtObjContent(): string
    {
        $savedContent = $this->content;
        $this->content = '';
        $this->extObjContent();
        $newContent = $this->content;
        $this->content = $savedContent;
        return $newContent;
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
}
