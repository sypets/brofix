<?php

declare(strict_types=1);

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

namespace Sypets\Brofix\EventListener;

use Sypets\Brofix\Repository\BrokenLinkRepository;
use TYPO3\CMS\Core\Html\Event\BrokenLinkAnalysisEvent;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Resource\FileInterface;

/**
 * Event listeners to identify if a link is broken. For external URLs, the database is queried), for pages
 * this is handled via a direct check to the database record  in pages.
 */
final class CheckBrokenRteLinkEventListener
{
    /**
     * @var BrokenLinkRepository
     */
    private $brokenLinkRepository;

    public function __construct(BrokenLinkRepository $brokenLinkRepository)
    {
        $this->brokenLinkRepository = $brokenLinkRepository;
    }

    public function checkExternalLink(BrokenLinkAnalysisEvent $event): void
    {
        if ($event->getLinkType() !== LinkService::TYPE_URL) {
            return;
        }
        $url = (string)($event->getLinkData()['url'] ?? '');
        if (!empty($url)
            && $this->brokenLinkRepository->isLinkTargetBrokenLink($url, 'external')) {
            $event->markAsBrokenLink('Broken link');
        }
        $event->markAsCheckedLink();
    }

    public function checkPageLink(BrokenLinkAnalysisEvent $event): void
    {
        if ($event->getLinkType() !== LinkService::TYPE_PAGE) {
            return;
        }
        $hrefInformation = $event->getLinkData();
        $url = $hrefInformation['pageuid'] ?? '';
        if ($url != '' && $url !== 'current') {
            $fragment = $hrefInformation['fragment'] ?? '';
            if ($fragment !== '') {
                $url .= '#c' . $fragment;
            }
            if ($this->brokenLinkRepository->isLinkTargetBrokenLink($url, 'db')) {
                $event->markAsBrokenLink('Broken link');
            }
        }
        $event->markAsCheckedLink();
    }

    public function checkFileLink(BrokenLinkAnalysisEvent $event): void
    {
        if ($event->getLinkType() !== LinkService::TYPE_FILE) {
            return;
        }
        $event->markAsCheckedLink();

        $hrefInformation = $event->getLinkData();
        $file = $hrefInformation['file'] ?? null;
        if (!$file instanceof FileInterface) {
            $event->markAsBrokenLink('Broken link');
            return;
        }

        if (!$file->hasProperty('uid') || (int)$file->getProperty('uid') === 0) {
            $event->markAsBrokenLink('Broken link');
            return;
        }

        if ($this->brokenLinkRepository->isLinkTargetBrokenLink('file:' . $file->getProperty('uid'), 'file')) {
            $event->markAsBrokenLink('Broken link');
        }
    }
}
