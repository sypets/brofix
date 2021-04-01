<?php

namespace Sypets\Brofix\Xclass;

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

use Sypets\Brofix\EventListener\CheckBrokenRteLinkEventListener;
use Sypets\Brofix\Repository\BrokenLinkRepository;
use TYPO3\CMS\Core\Html\RteHtmlParser;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Overrides the core RteHtmlParser::markBrokenLinks in order to enhance the methods for checking if
 * a link is broken. The core RteHtmlParser does not provide a check for external links.
 *
 * This class is only used in TYPO3 version 9, not since v10.
 * @see CheckBrokenRteLinkEventListener
 */
class RteHtmlParserWithBrokenLinkHook extends RteHtmlParser
{
    public function __construct()
    {
        $this->brokenLinkRepository = GeneralUtility::makeInstance(BrokenLinkRepository::class);
    }

    /**
     * Content Transformation from DB to RTE
     * Checks all <a> tags which reference a t3://page and checks if the page is available
     * If not, some offensive styling is added.
     *
     * @param string $content
     * @return string the modified content
     */
    protected function markBrokenLinks(string $content): string
    {
        $blocks = $this->splitIntoBlock('A', $content);
        $linkService = GeneralUtility::makeInstance(LinkService::class);
        foreach ($blocks as $position => $value) {
            if ($position % 2 === 0) {
                continue;
            }
            list($attributes) = $this->get_tag_attributes($this->getFirstTag($value), true);
            if (empty($attributes['href'])) {
                continue;
            }

            $hrefInformation = $linkService->resolve($attributes['href']);
            switch ($hrefInformation['type']) {
                case LinkService::TYPE_PAGE:
                    $url = $hrefInformation['pageuid'] ?? '';
                    if ($url != '' && $url !== 'current') {
                        $fragment = $hrefInformation['fragment'] ?? '';
                        if ($fragment !== '') {
                            $url .= '#c' . $fragment;
                        }
                        if ($this->brokenLinkRepository->isLinkTargetBrokenLink($url, 'db')) {
                            // Page does not exist
                            $attributes['data-rte-error'] = 'Page with ID ' . $url . ' not found';
                        }
                    }
                    break;
                case LinkService::TYPE_URL:
                    $url = $hrefInformation['url'] ?? '';
                    if ($url !== '') {
                        if ($this->brokenLinkRepository->isLinkTargetBrokenLink($url, 'external')) {
                            // url broken
                            $attributes['data-rte-error'] = 'Broken link';
                        }
                    }
                    break;
                case LinkService::TYPE_FILE:
                    $file = $hrefInformation['file'] ?? null;
                    if (!$file instanceof FileInterface) {
                        $attributes['data-rte-error'] = 'File does not exist';
                    } else {
                        $fileUid = 0;
                        if ($file->hasProperty('uid')) {
                            $fileUid = (int)$file->getProperty('uid');
                        }
                        if ($fileUid === 0) {
                            $attributes['data-rte-error'] = 'File does not exist';
                        } else {
                            if ($this->brokenLinkRepository->isLinkTargetBrokenLink('file:' . $fileUid, 'file')) {
                                // Page does not exist
                                $attributes['data-rte-error'] = 'File with ID ' . $fileUid . ' not found';
                            }
                        }
                    }
                    break;
            }

            // Always rewrite the block to allow the nested calling even if a page is found
            $blocks[$position] =
                '<a ' . GeneralUtility::implodeAttributes($attributes, true, true) . '>'
                . $this->markBrokenLinks($this->removeFirstAndLastTag($blocks[$position]))
                . '</a>';
        }
        return implode('', $blocks);
    }
}
