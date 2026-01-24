<?php

declare(strict_types=1);
namespace Sypets\Brofix\Hooks;

use Sypets\Brofix\Repository\BrokenLinkRepository;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Fluid\ViewHelpers\Be\InfoboxViewHelper;

class PageCalloutsHook implements SingletonInterface
{
    protected bool $showPageCalloutBrokenLinksExist = false;

    public function __construct(
        protected BrokenLinkRepository $brokenLinkRepository,
        ExtensionConfiguration $extensionConfiguration,
        protected readonly UriBuilder $uriBuilder
    ) {
        $this->showPageCalloutBrokenLinksExist = (bool)$extensionConfiguration->get('brofix', 'showPageCalloutBrokenLinksExist');
    }

    /**
     * Create flash message for showing information about broken links in page module
     *
     * @param mixed[] $pageInfo
     * @return array{'title'?: string, 'message'?: string, 'state'?: int}
     */
    public function addMessages(array $pageInfo): array
    {
        // check extension configuration
        if (!$this->showPageCalloutBrokenLinksExist) {
            return [];
        }

        if (!$pageInfo) {
            return [];
        }
        $pageId = (int)($pageInfo['uid']);
        if ($pageId === 0) {
            return [];
        }

        /** @var BackendUserAuthentication $beUser */
        $beUser = $GLOBALS['BE_USER'];
        if (!$beUser->isAdmin() && !$beUser->check('modules', 'web_brofix')) {
            // no output in case the user does not have access to the "brofix" module
            return [];
        }
        // check user settings (default is 1)
        if (((bool)($beUser->uc['tx_brofix_showPageCalloutBrokenLinksExist'] ?? false)) === false) {
            return [];
        }

        $lang = $this->getLanguageService();

        $count = $this->brokenLinkRepository->getLinkCountForPage($pageId);
        if ($count == 0) {
            // no broken links to report
            return [];
        }

        $message = '<p>' . sprintf(
            ($count === 1 ? $lang->sL('LLL:EXT:brofix/Resources/Private/Language/locallang.xlf:count_singular_broken_links_found_for_page')
                : $lang->sL('LLL:EXT:brofix/Resources/Private/Language/locallang.xlf:count_plural_broken_links_found_for_page'))
                ?: '%d broken links were found on this page',
            $count . '</p>'
        );
        $message .= '<p>' . ($lang->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:goto') ?: '');
        $message .= ' <a class="btn btn-info" href="' . $this->createBackendUri($pageId) . '">'
            . ($lang->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang_mod.xlf:mlang_tabs_tab') ?: 'Brofix')
            . '</a></p>';
        return [
            'title' => '',
            'message' => $message,
            'state' => InfoboxViewHelper::STATE_WARNING
        ];
    }

    protected function createBackendUri(int $pageId, string $route = 'web_brofix'): string
    {
        $uriBuilder = $this->uriBuilder;
        return (string)$uriBuilder->buildUriFromRoute($route, ['id' => $pageId]);
    }

    /**
     * @return LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }
}
