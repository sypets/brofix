<?php

declare(strict_types=1);
namespace Sypets\Brofix\CheckLinks;

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

class CheckLinksStatistics
{
    protected string $pageTitle = '';

    protected int $countExcludedLinks = 0;

    protected int $countBrokenLinks = 0;

    protected int $checkStartTime = 0;
    protected int $checkEndTime = 0;

    protected int $countPages = 0;

    protected int $countLinksChecked = 0;

    protected float $percentExcludedLinks = 0.0;

    protected float $percentBrokenLinks = 0.0;

    /** @var array<int,int> */
    protected array $countLinksByStatus = [];

    protected int $countLinksTotal = 0;

    protected int $countNewBrokenLinks = 0;

    public function __construct()
    {
        $this->initialize();
    }

    public function initialize(): void
    {
        $this->checkStartTime = \time();
        $this->countExcludedLinks = 0;
        $this->countBrokenLinks = 0;
        $this->countPages = 0;
        $this->countLinksTotal = 0;
        $this->percentBrokenLinks = 0;
        $this->percentExcludedLinks = 0;
        $this->countNewBrokenLinks = 0;
        $this->pageTitle = '';
    }

    public function calculateStats(): void
    {
        $this->checkEndTime = \time();
        // number of links actually checked
        $this->countLinksChecked = $this->countLinksTotal - $this->countExcludedLinks;
        if ($this->countExcludedLinks > 0
            && $this->countLinksTotal > 0
        ) {
            $this->percentExcludedLinks = $this->countExcludedLinks / $this->countLinksTotal * 100;
        } else {
            $this->percentExcludedLinks = 0;
        }
        // omit the excluded links from this count to get the actual percentage of broken links in checked links
        if ($this->countLinksChecked > 0
            && $this->countBrokenLinks > 0
        ) {
            $this->percentBrokenLinks = $this->countBrokenLinks / $this->countLinksChecked * 100;
        } else {
            $this->percentBrokenLinks =  0;
        }
    }

    public function incrementNewBrokenLink(): void
    {
        $this->countNewBrokenLinks++;
    }

    public function getCountNewBrokenLinks(): int
    {
        return $this->countNewBrokenLinks;
    }

    public function incrementCountExcludedLinks(): void
    {
        $this->countExcludedLinks++;
    }

    public function incrementCountBrokenLinks(): void
    {
        $this->countBrokenLinks++;
    }

    public function addCountLinks(int $count): void
    {
        $this->countLinksTotal += $count;
    }

    public function setCountPages(int $count): void
    {
        $this->countPages = $count;
    }

    /**
     * @param string $pageTitle
     */
    public function setPageTitle(string $pageTitle): void
    {
        $this->pageTitle = $pageTitle;
    }

    public function getPageTitle(): string
    {
        return $this->pageTitle;
    }

    public function getCountPages(): int
    {
        return $this->countPages;
    }

    public function getCountLinks(): int
    {
        return $this->countLinksTotal;
    }

    public function getCountLinksByStatus(int $status): int
    {
        return (int)($this->countLinksByStatus[$status] ?? 0);
    }

    public function getCountBrokenLinks(): int
    {
        return $this->countBrokenLinks;
        // todo use
        //return (int)($this->countLinksByStatus[LinkTargetResponse::RESULT_BROKEN] ?? 0);
    }

    public function getCountExcludedLinks(): int
    {
        return $this->countExcludedLinks;
        // todo: use
        //return (int)($this->countLinksByStatus[LinkTargetResponse::RESULT_EXCLUDED] ?? 0);
    }

    /**
     * @return int
     */
    public function getCheckStartTime(): int
    {
        return $this->checkStartTime;
    }

    /**
     * @return int
     */
    public function getCheckEndTime(): int
    {
        return $this->checkEndTime;
    }

    /**
     * @return int
     */
    public function getCountLinksChecked(): int
    {
        return $this->countLinksChecked;
        // todo
        /*
        return $this->countLinksTotal - ($this->countLinksByStatus[LinkTargetResponse::RESULT_EXCLUDED] ?? 0)
            - ($this->countLinksByStatus[LinkTargetResponse::RESULT_CANNOT_CHECK] ?? 0);
        */
    }

    public function getPercentLinksByStatus(int $status): float
    {
        if (!isset($this->countLinksByStatus[$status])) {
            return 0;
        }

        if ($this->countLinksByStatus[$status] > 0
            && $this->getCountLinksChecked() > 0
        ) {
            return $this->countLinksByStatus[$status] / $this->getCountLinksChecked() * 100;
        }
        return 0;
    }

    /**
     * @return float
     */
    public function getPercentExcludedLinks(): float
    {
        return $this->percentExcludedLinks;
    }

    /**
     * @return float
     */
    public function getPercentBrokenLinks(): float
    {
        return $this->percentBrokenLinks;
    }
}
