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

use Sypets\Brofix\CheckLinks\LinkTargetResponse\LinkTargetResponse;

class CheckLinksStatistics
{
    /**
     * @var string
     */
    protected $pageTitle;

    /**
     * @var int
     */
    protected $checkStartTime;

    /**
     * @var int
     */
    protected $checkEndTime;

    /**
     * @var int
     */
    protected $countPages;

    /** @var array<int,int> */
    protected array $countLinksByStatus;

    /**
     * @var int
     */
    protected $countLinksTotal = 0;

    protected int $countNewBrokenLinks = 0;

    public function __construct()
    {
        $this->initialize();
    }

    public function initialize(): void
    {
        $this->checkStartTime = \time();
        $this->countPages = 0;
        $this->countLinksTotal = 0;
        $this->countNewBrokenLinks = 0;
        $this->countLinksByStatus = [];
        $this->pageTitle = '';
    }

    public function calculateStats(): void
    {
        $this->checkEndTime = \time();
    }

    public function incrementCountLinksByStatus(int $status): void
    {
        if (!isset($this->countLinksByStatus[$status])) {
            $this->countLinksByStatus[$status] = 0;
        }
        $this->countLinksByStatus[$status]++;
        $this->countLinksTotal++;
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
        $this->incrementCountLinksByStatus(LinkTargetResponse::RESULT_EXCLUDED);
    }

    public function incrementCountBrokenLinks(): void
    {
        $this->incrementCountLinksByStatus(LinkTargetResponse::RESULT_BROKEN);
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

    /**
     * Get total number of links
     *
     * @return int
     */
    public function getCountLinks(): int
    {
        return $this->countLinksTotal;
    }

    public function getCountLinksByStatus(int $status): int
    {
        return $this->countLinksByStatus[$status];
    }

    public function getCountBrokenLinks(): int
    {
        return $this->countLinksByStatus[LinkTargetResponse::RESULT_BROKEN];
    }

    public function getCountExcludedLinks(): int
    {
        return $this->countLinksByStatus[LinkTargetResponse::RESULT_EXCLUDED];
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
     * Get number of links actually checked. This is the number of links total minus excluded links and non-checkable
     * links.
     *
     * @return int
     */
    public function getCountLinksChecked(): int
    {
        return $this->countLinksTotal - $this->countLinksByStatus[LinkTargetResponse::RESULT_EXCLUDED]
            - $this->countLinksByStatus[LinkTargetResponse::RESULT_CANNOT_CHECK];
    }

    public function getPercentLinksByStatus(int $status): float
    {
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
        return $this->getPercentLinksByStatus(LinkTargetResponse::RESULT_EXCLUDED);
    }

    /**
     * Get % of broken link / number of links checked
     *
     * @return float
     */
    public function getPercentBrokenLinks(): float
    {
        return $this->getPercentLinksByStatus(LinkTargetResponse::RESULT_BROKEN);
    }
}
