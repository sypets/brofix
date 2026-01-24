<?php

declare(strict_types=1);

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

    /**
     * @var array<string,array<string,bool>>
     */
    private array $resultsCache = [];

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
        if ($url) {
            if (isset($this->resultsCache['external'][$url])) {
                $isBroken = $this->resultsCache['external'][$url];
            } else {
                $isBroken = $this->brokenLinkRepository->isLinkTargetBrokenLink($url, 'external');
                $this->resultsCache['external'][$url] = $isBroken;
            }
            if ($isBroken) {
                $event->markAsBrokenLink('Broken link');
            }
        }
        $event->markAsCheckedLink();
    }

    public function checkRecordLink(BrokenLinkAnalysisEvent $event): void
    {
        if ($event->getLinkType() !== LinkService::TYPE_RECORD) {
            return;
        }

        $hrefInformation = $event->getLinkData();
        $identifier = (string)($hrefInformation['identifier'] ?? '');
        if ($identifier !== '') {
            $uid = (int)($hrefInformation['uid'] ?? 0);
            $url = $identifier . ':' . $uid;

            if (isset($this->resultsCache['db'][$url])) {
                $isBroken = $this->resultsCache['db'][$url];
            } else {
                $isBroken = $this->brokenLinkRepository->isLinkTargetBrokenLink($url, 'db');
                $this->resultsCache['db'][$url] = $isBroken;
            }

            if ($isBroken) {
                $event->markAsBrokenLink('Broken link');
            }
        }

        $event->markAsCheckedLink();
    }

    public function checkPageLink(BrokenLinkAnalysisEvent $event): void
    {
        if ($event->getLinkType() !== LinkService::TYPE_PAGE) {
            return;
        }
        $hrefInformation = $event->getLinkData();
        $url = (string)($hrefInformation['pageuid'] ?? '');
        if ($url != '' && $url !== 'current') {
            $fragment = $hrefInformation['fragment'] ?? '';
            if ($fragment !== '') {
                $url .= '#c' . $fragment;
            }

            if (isset($this->resultsCache['db'][$url])) {
                $isBroken = $this->resultsCache['db'][$url];
            } else {
                $isBroken = $this->brokenLinkRepository->isLinkTargetBrokenLink((string)('pages:' . $url), 'db');
                $this->resultsCache['db'][$url] = $isBroken;
            }

            if ($isBroken) {
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
