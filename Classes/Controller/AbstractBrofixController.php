<?php

declare(strict_types=1);
namespace Sypets\Brofix\Controller;

use Sypets\Brofix\BackendSession\BackendSession;
use Sypets\Brofix\CheckLinks\ExcludeLinkTarget;
use Sypets\Brofix\Configuration\Configuration;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Pagination\PaginationInterface;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

abstract class AbstractBrofixController
{
    /**
     * @var string
     */
    protected $orderBy;

    /**
     * @var int
     */
    protected $paginationCurrentPage;

    /**
     * Current page
     * @var int
     */
    protected $id;

    /**
     * Contains site languages for this page ID
     *
     * @var SiteLanguage[]
     */
    protected $siteLanguages = [];

    /**
     * @var BrofixController Contains a reference to the parent calling object
     */
    protected $pObj;

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

    /**
     * @var BackendSession
     */
    protected $backendSession;

    /** @var PaginationInterface|null */
    protected $pagination;

    /** @var IconFactory */
    protected $iconFactory;

    /**
     * @var ExcludeLinkTarget
     */
    protected $excludeLinkTarget;

    public function __construct(
        Configuration $configuration,
        BackendSession $backendSession,
        IconFactory $iconFactory,
        ModuleTemplate $moduleTemplate,
        ExcludeLinkTarget $excludeLinkTarget
    ) {
        $this->iconFactory = $iconFactory;
        $this->configuration = $configuration;
        $this->backendSession = $backendSession;
        $this->moduleTemplate = $moduleTemplate;
        $this->excludeLinkTarget = $excludeLinkTarget;
        $this->paginationCurrentPage = 1;
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
            'orderBy' => $this->orderBy,
            'paginationPage', $this->paginationCurrentPage
        ];
        // if same key, additionalQueryParameters should overwrite parameters
        $parameters = array_merge($parameters, $additionalQueryParameters);

        /**
         * @var UriBuilder $uriBuilder
         */
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        return (string)$uriBuilder->buildUriFromRoute($route, $parameters);
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
