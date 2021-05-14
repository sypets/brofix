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

use Sypets\Brofix\Configuration\Configuration;

/**
 * Makes sure that there is a minimum wait time between checking
 * URLs. Specifically if the URLs are from the same domain.
 */
class CrawlDelay
{
    /**
     * @var int
     */
    protected $delaySeconds;

    /**
     * @var array<string>
     */
    protected $noCrawlDelayDomains;

    /**
     * Timestamps when an URL from the domain was last accessed.
     *
     * @var array<string,int>
     */
    protected $lastCheckedDomainTimestamps = [];

    public function setConfiguration(Configuration $config): void
    {
        $this->delaySeconds = $config->getCrawlDelaySeconds();
        $this->noCrawlDelayDomains = $config->getCrawlDelayNodelay();
    }

    /**
     * Make sure there is a delay between checks of the same domain
     *
     * @param string $domain
     *
     * @return int returns number of microseconds waited
     */
    public function crawlDelay(string $domain): int
    {
        if ($domain === '' || in_array($domain, $this->noCrawlDelayDomains)) {
            // skip delay
            return 0;
        }
        /**
         * @var int
         */
        $lastTimestamp = $this->lastCheckedDomainTimestamps[$domain] ?? 0;
        $current = \time();

        // check if delay necessary
        $wait = $this->delaySeconds - ($current-$lastTimestamp);
        if ($wait > 0) {
            // wait now
            sleep($wait);
            return $wait;
        }
        // no delay necessary
        $this->lastCheckedDomainTimestamps[$domain] = $current;
        return 0;
    }

    /**
     * Store time for last check of this URL - used for crawlDelay.
     *
     * @param string $domain
     * @return bool
     */
    public function setLastCheckedTime(string $domain): bool
    {
        if ($domain === '') {
            return false;
        }
        $current = \time();
        $this->lastCheckedDomainTimestamps[$domain] = $current;
        return true;
    }
}
