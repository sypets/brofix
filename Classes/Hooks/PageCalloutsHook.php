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

/**
 * Show broken links in page module.
 *
 * Requirements:
 * - sypets/page-callouts installed
 * - extension configuration showPageCalloutBrokenLinksExist is 2
 *   or showPageCalloutBrokenLinksExist is 1 and user setting tx_brofix_showPageCalloutBrokenLinksExist is 1
 */
class PageCalloutsHook implements SingletonInterface
{
    protected int $showPageCalloutBrokenLinksExist = 1;

    public function __construct(
        protected BrokenLinkRepository $brokenLinkRepository,
        ExtensionConfiguration $extensionConfiguration,
        protected readonly UriBuilder $uriBuilder
    ) {
        $extensionConfigurationArray = $extensionConfiguration->get('brofix');
        $this->showPageCalloutBrokenLinksExist = (int)($extensionConfigurationArray['showPageCalloutBrokenLinksExist'] ?? 1);
    }

    /**
     * Create flash message for showing information about broken links in page module
     *
     * @param mixed[] $pageInfo
     * @return array{'title'?: string, 'message'?: string, 'state'?: int}
     */
    public function addMessages(array $pageInfo): array
    {
        /** @var BackendUserAuthentication $beUser */
        $beUser = $GLOBALS['BE_USER'];
        if (!$beUser->isAdmin() && !$beUser->check('modules', 'web_brofix')) {
            // no output in case the user does not have access to the "brofix" module
            return [];
        }

        // check extension configuration:
        // if 0: do not show
        // if 1: only show if user setting is also true
        // if 2 : always show
        switch ($this->showPageCalloutBrokenLinksExist) {
            case 0:
                return [];
            case 1:
                if (((bool)($beUser->uc['tx_brofix_showPageCalloutBrokenLinksExist'] ?? true)) === false) {
                    // do not show broken links in page module
                    return [];
                }
                // case 2: continue
        }

        if (!$pageInfo) {
            return [];
        }
        $pageId = (int)($pageInfo['uid']);
        if ($pageId === 0) {
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
