<?php

declare(strict_types=1);
namespace Sypets\Brofix\Linktype;

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
use TYPO3\CMS\Core\Localization\LanguageService;

abstract class AbstractLinktype implements LinktypeInterface
{

    /**
     * Flag used int checkLink() $flags
     * All CHECK_LINK_FLAG_ flags can be combined
     * with bitwise OR (|)
     *
     * @var int
     */
    public const CHECK_LINK_FLAG_NO_CRAWL_DELAY = 1;

    /**
     * If this flag is set, we do not use result from cache if URL is invalid.
     * This results in URLs with errors always getting rechecked
     */
    public const CHECK_LINK_FLAG_NO_CACHE_ON_ERROR = 2;

    /**
     * Do not get result from cache. Always recheck URL (but write it to cache)
     */
    public const CHECK_LINK_FLAG_NO_CACHE = 4;

    /**
     * If synchronous checking is performed (in contrast to asynchronous checking
     * in the background), the objective is usually to do
     * a faster check - e.g. always retrieve from link target cache and
     * possibly use a longer expires time for that.
     *
     * Synchronous checking is currently used for On-the-fly checking after editing
     * a record and returning to list of broken links. This will most likely change
     * in the future, as it is clunky.
     *
     * @var int
     */
    public const CHECK_LINK_FLAG_SYNCHRONOUS = 8;

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * Contains parameters needed for the rendering of the error message
     *
     * @var ErrorParams
     */
    protected $errorParams;

    public function setConfiguration(Configuration $configuration): void
    {
        $this->configuration = $configuration;
    }

    /**
     * @param mixed[]|null $params
     */
    public function initializeErrorParams(array $params = null): void
    {
        $this->errorParams = new ErrorParams($params);
    }

    /**
     * Get the value of the private property errorParams
     *
     * @return ErrorParams All parameters needed for the rendering of the error message
     */
    public function getErrorParams(): ErrorParams
    {
        return $this->errorParams;
    }

    /**
     * Base type fetching method, based on the type that softRefParserObj returns
     *
     * @param mixed[] $value Reference properties
     * @param string $type Current type
     * @param string $key Validator hook name
     * @return string Fetched type
     */
    public function fetchType(array $value, string $type, string $key): string
    {
        $newType = $value['type'] ?? '';
        if ($newType === '' || !is_string($newType)) {
            return $type;
        }

        if ($newType === $key) {
            $type = $newType;
        }
        return $type;
    }

    public function getLastChecked(): int
    {
        return \time();
    }

    /**
     * Check if URL is being excluded.
     *
     * @return bool
     */
    public function isExcludeUrl(): bool
    {
        return false;
    }

    /**
     * Construct a valid Url for browser output
     *
     * @param mixed[] $row Broken link record
     * @return string Parsed broken url
     */
    public function getBrokenUrl(array $row): string
    {
        return $row['url'];
    }

    /**
     * Text to be displayed with the Link as anchor text
     * (not the real anchor text of the Link.
     * @param mixed[] $row
     * @param mixed[] $additionalConfig
     * @return string
     */
    public function getBrokenLinkText(array $row, array $additionalConfig = null): string
    {
        return $row['url'];
    }

    /**
     * @return LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }
}
