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
     * @var array<string>
     */
    protected $noCrawlDelayDomains;

    /**
     * Timestamps when an URL from the domain was last accessed.
     *
     * @var array<string,array{lastChecked: int, stopChecking: bool, retryAfter: int, reasonCannotCheck: string}>
     */
    protected $domainInfo = [];

    protected Configuration $configuration;

    protected int $lastWaitSeconds = 0;

    public function setConfiguration(Configuration $config): void
    {
        $this->configuration = $config;
    }

    /**
     * Make sure there is a delay between checks of the same domain
     *
     * @param string $domain
     *
     * @return bool continue checking
     */
    public function crawlDelay(string $domain): bool
    {
        $this->lastWaitSeconds = 0;

        $current = \time();

        // stopChecking is set, check if current time is past retryAfter
        if ($this->domainInfo[$domain]['stopChecking'] ?? false) {
            if (($this->domainInfo[$domain]['retryAfter'] ?? false) && $current > $this->domainInfo[$domain]['retryAfter']) {
                $this->domainInfo[$domain]['stopChecking'] = false;
                $this->domainInfo[$domain]['retryAfter'] = 0;
                // check again
            } else {
                // still stop checking
                return false;
            }
        }

        $delaySeconds = $this->getCrawlDelayByDomain($domain);
        if ($delaySeconds === 0) {
            return true;
        }
        /**
         * @var int
         */
        $lastTimestamp = (int)($this->domainInfo[$domain]['lastChecked'] ?? 0);

        // check if delay necessary
        $this->lastWaitSeconds = $delaySeconds - ($current-$lastTimestamp);
        if ($this->lastWaitSeconds > 0) {
            // wait now
            sleep($this->lastWaitSeconds);
        } else {
            $this->lastWaitSeconds = 0;
        }
        // set last checked
        $this->domainInfo[$domain]['lastChecked'] = $current;
        return true;
    }

    public function getLastWaitSeconds(): int
    {
        return $this->lastWaitSeconds;
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
        $this->domainInfo[$domain]['lastChecked'] = $current;
        return true;
    }

    protected function getCrawlDelayByDomain(string $domain): int
    {
        if ($domain === '') {
            return 0;
        }

        // check if domain should be skipped: do not use crawlDelay
        if ($this->configuration->isCrawlDelayNoDelayRegex()) {
            if (preg_match($this->configuration->getCrawlDelayNoDelayRegex(), $domain)) {
                return 0;
            }
        } elseif (in_array($domain, $this->configuration->getCrawlDelayNodelayDomains())) {
            // skip delay
            return 0;
        }
        return $this->configuration->getCrawlDelaySeconds();
    }

    public function stopChecking(string $domain, int $retryAfter = 0, string $reasonCannotCheck = ''): void
    {
        $this->domainInfo[$domain]['stopChecking'] = true;
        if ($retryAfter) {
            $this->domainInfo[$domain]['retryAfter'] = $retryAfter;
        }
        if ($reasonCannotCheck) {
            $this->domainInfo[$domain]['reasonCannotCheck'] = $reasonCannotCheck;
        }
    }
}
