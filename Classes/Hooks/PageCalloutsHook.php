<?php

declare(strict_types=1);
namespace Sypets\Brofix\Hooks;

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

use Sypets\Brofix\Repository\BrokenLinkRepository;
use TYPO3\CMS\Backend\Module\ModuleLoader;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\ViewHelpers\Be\InfoboxViewHelper;

final class PageCalloutsHook
{
    /**
     * @var BrokenLinkRepository
     */
    private $brokenLinkRepository;

    public function __construct()
    {
        $this->brokenLinkRepository = GeneralUtility::makeInstance(BrokenLinkRepository::class);
    }

    /**
     * Create flash message for showing information about broken links in page module
     *
     * @param mixed[] $pageInfo
     * @return array{'title'?: string, 'message'?: string, 'state'?: int}:
     */
    public function addMessages(array $pageInfo): array
    {
        if (!$pageInfo || !is_array($pageInfo)) {
            return [];
        }
        $pageId = (int)($pageInfo['uid']);
        if ($pageId === 0) {
            return [];
        }
        $lang = $this->getLanguageService();

        // todo: access check
        $count = $this->brokenLinkRepository->getLinkCountForPage($pageId);
        if ($count == 0) {
            // no broken links to report
            return [];
        }

        // the following code is loosely based on PageLayoutController::getHeaderFlashMessagesForCurrentPid
        $moduleLoader = GeneralUtility::makeInstance(ModuleLoader::class);
        $moduleLoader->load($GLOBALS['TBE_MODULES']);
        $modules = $moduleLoader->modules;
        if (!is_array($modules['web']['sub']['brofix'])) {
            return [];
        }
        //$title = $lang->getLL('goToListModule');
        //$message = '<p>' . $lang->getLL('goToListModuleMessage') . '</p>';
        $message = '<p>' . sprintf(
            ($count === 1 ? $lang->sL('LLL:EXT:brofix/Resources/Private/Language/locallang.xlf:count_singular_broken_links_found_for_page')
                : $lang->sL('LLL:EXT:brofix/Resources/Private/Language/locallang.xlf:count_plural_broken_links_found_for_page'))
                ?: '%d broken links were found on this page',
            $count . '</p>'
        );
        $message .= '<p>' . ($lang->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:goto') ?: '');
        $message .= ' <a class="btn btn-info" href="javascript:top.goToModule(\'web_brofix\',1);">'
            . ($lang->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang_mod.xlf:mlang_tabs_tab') ?: 'Brofix')
            . '</a></p>';
        return [
            'title' => '',
            'message' => $message,
            'state' => InfoboxViewHelper::STATE_WARNING
        ];
    }

    /**
     * @return LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }
}
