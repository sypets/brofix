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

use Sypets\Brofix\CheckLinks\LinkTargetResponse\LinkTargetResponse;
use Sypets\Brofix\Configuration\Configuration;

/**
 * This class provides interface implementation.
 */
interface LinktypeInterface
{
    public function setConfiguration(Configuration $configuration): void;

    /**
     * Checks a given link for validity
     *
     * @param string $url Url to check
     * @param mixed[] $softRefEntry The soft reference entry which builds the context of that url
     * @param int $flags can be a combination of flags, see flags defined in AbstractLinktype, e.g.
     *   e.g. AbstractLinktype::CHECK_LINK_FLAG_NO_CRAWL_DELAY
     * @return LinkTargetResponse
     */
    public function checkLink(string $url, array $softRefEntry, int $flags = 0): LinkTargetResponse;

    /**
     * Base type fetching method, based on the type that softRefParserObj returns.
     *
     * @param mixed[] $value Reference properties
     * @param string $type Current type
     * @param string $key Validator hook name
     * @return string Fetched type
     */
    public function fetchType(array $value, string $type, string $key): string;

    /**
     * Generate the localized error message from the error params saved from the parsing
     *
     * @param LinkTargetResponse|null $linkTargetResponse All parameters needed for the rendering of the error message
     * @return string Validation error message
     */
    public function getErrorMessage(?LinkTargetResponse $linkTargetResponse): string;

    /**
     * Construct a valid Url for browser output
     *
     * @param mixed[] $row Broken link record
     * @return string Parsed broken url
     */
    public function getBrokenUrl(array $row): string;

    /**
     * Text to be displayed with the Link as anchor text
     * (not the real anchor text of the Link.
     * @param mixed[] $row
     * @param mixed[] $additionalConfig
     * @return string
     */
    public function getBrokenLinkText(array $row, array $additionalConfig = null): string;
}
