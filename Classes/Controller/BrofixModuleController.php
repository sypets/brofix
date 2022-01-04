<?php

declare(strict_types=1);
namespace Sypets\Brofix\Controller;


use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class BrofixModuleController
{
    /**
     * The name of the module
     *
     * @var string
     */
    protected const MODULE_NAME = 'web_ts';

    /**
     * @var ServerRequestInterface
     */
    protected $request;

    /**
     * @var string
     */
    protected $action;

    /**
     * ModuleTemplate object
     *
     * @var ModuleTemplate
     */
    protected $moduleTemplate;

    /**
     * @var StandaloneView
     */
    protected $view;

    /**
     * @var IconFactory
     */
    protected $iconFactory;


    public function __construct(IconFactory $iconFactory,
        ModuleTemplate $moduleTemplate,
        UriBuilder $uriBuilder
    )
    {
        $this->iconFactory = $iconFactory;
        $this->moduleTemplate = $moduleTemplate;
        $this->uriBuilder = $uriBuilder;
    }

    public function brofixAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;
        $queryParams = $request->getQueryParams();
        /** @var string $action */
        $action = (string)($queryParams['action'] ?? 'brokenlinks');

        switch ($action) {
            case 'brokenlinks':
                break;

            case 'manageexclusions':
                break;
        }

        $this->view = $this->initializeView();
        //$this->generateMenu();
        $this->generateSelectMenu();
        $this->moduleTemplate->setContent($this->view->render());
        return new HtmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @todo put in respective cnotroller
     */
    protected function queryParams(ServerRequestInterface $request): void
    {
        /**
         * @var array<string,string> $parsedBody
         */
        $parsedBody = $request->getParsedBody();
        // currentPage
        // id

        // :sort:
        // orderBy

        // paginationPage
        // depth

        // _filter:
        // uid_searchFilter
        // url_searchFilter
        // title_searchFilter

        // refreshLinkList = "RefreshDisplay"

    }

    protected function initializeView(): StandaloneView
    {
        /**
         * @var StandaloneView $view
         */
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setLayoutRootPaths(['EXT:brofix/Resources/Private/Layouts']);
        $view->setPartialRootPaths(['EXT:brofix/Resources/Private/Partials']);
        $view->setTemplateRootPaths(['EXT:brofix/Resources/Private/Templates/Backend']);

        // todo depends on action
        $view->setTemplate('Brokenlinks');

        return $view;
    }

    protected function generateSelectMenu()
    {
        $menuItems = [
            'brokenlinks' => [
                'route' => 'web_brofix',
                'controller' => 'BrokenLinks',
                'action' => 'brokenlinks',
                'parameters' => [
                    'action' => 'brokenlinks'
                ],
                'title' => 'Broken Link List',
            ],
            'manageexclusions' => [
                'route' => 'web_brofix',
                'controller' => 'ManageExclusions',
                'action' => 'exclusionList',
                'parameters' => [
                    'action' => 'manageexclusions'
                ],
                'title' => 'Manage Exclusions',
            ],
        ];

        $menu = $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $menu->setIdentifier('WebBrofixJumpMenu');
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

        foreach ($menuItems as $menuItem) {
            $controller = $menuItem['controller'];
            $uri = (string)$uriBuilder->buildUriFromRoute(
                self::MODULE_NAME,
                [
                    'id' => $this->id,
                    'SET' => [
                        'function' => $controller
                    ]
                ]
            );
            $item = $menu
                ->makeMenuItem()
                ->setHref(
                    $uri
                )
                ->setTitle($menuItem['title']);
            if ($controller === $this->MOD_SETTINGS['function']) {
                $item->setActive(true);
            }
            $menu->addMenuItem($item);
        }
        $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);

    }

    protected function generateButtonMenu()
    {
        $menuItems = [
            'brokenlinks' => [
                'route' => 'web_brofix',
                //'controller' => 'BrokenLinks',
                //'action' => 'brokenlinks',
                'parameters' => [
                    'action' => 'brokenlinks'
                ],
                'title' => 'Broken Link List',
            ],
            'manageexclusions' => [
                'route' => 'web_brofix',
                //'controller' => 'BrokenLinks',
                //'action' => 'brokenlinks',
                'parameters' => [
                    'action' => 'manageexclusions'
                ],
                'title' => 'Manage Exclusions',
            ],
        ];

        // set button bar
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();

        foreach ($menuItems as $menuItem) {
            $href = $this->constructBackendUri($menuItem['parameters'], $menuItem['route']);
            $button = $buttonBar->makeLinkButton()
                ->setHref($href)
                //->reset()->uriFor($menuItem['action'], [], $menuItem['controller']))
                ->setTitle($menuItem['title'])
                ->setShowLabelText('Link')
                ->setIcon($this->moduleTemplate->getIconFactory()->getIcon('actions-extension-import', Icon::SIZE_SMALL))
            ;
            $buttonBar->addButton($button, ButtonBar::BUTTON_POSITION_LEFT, 1);

        }
    }

    /**
     * @param array<string,mixed> $additionalQueryParameters
     * @param string $route
     * @return string
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
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

        $uri = (string)$this->uriBuilder->buildUriFromRoute($route, $parameters);

        return $uri;
    }


}
