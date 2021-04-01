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

use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\Exception\FolderDoesNotExistException;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class provides Check File Links plugin implementation
 */
class FileLinktype extends AbstractLinktype
{
    public function __construct()
    {
        $this->initializeErrorParams();
    }

    /**
     * Type fetching method, based on the type that softRefParserObj returns
     *
     * @param array $value Reference properties
     * @param string $type Current type
     * @param string $key Validator hook name
     * @return string fetched type
     */
    public function fetchType(array $value, string $type, string $key): string
    {
        if (strpos(strtolower($value['tokenValue']), 'file:') === 0) {
            $type = 'file';
        }
        return $type;
    }

    /**
     * Checks a given URL + /path/filename.ext for validity
     *
     * @param string $url Url to check
     * @param array $softRefEntry The soft reference entry which builds the context of the url
     * @param int $flags see LinktypeInterface::checkLink(), not used here
     * @return bool TRUE on success or FALSE on error
     */
    public function checkLink(string $url, array $softRefEntry, int $flags = 0): bool
    {
        $this->initializeErrorParams();

        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        try {
            $file = $resourceFactory->retrieveFileOrFolderObject($url);
        } catch (FileDoesNotExistException $e) {
            return false;
        } catch (FolderDoesNotExistException $e) {
            return false;
        }

        return (bool)(($file !== null) ? !$file->isMissing() : false);
    }

    /**
     * Generate the localized error message from the error params saved from the parsing
     *
     * @param ErrorParams $errorParams All parameters needed for the rendering of the error message
     * @return string error message
     */
    public function getErrorMessage(ErrorParams $errorParams = null): string
    {
        return $this->getLanguageService()->getLL('list.report.filenotexisting');
    }

    /**
     * Construct a valid Url for browser output
     *
     * @param array $row Broken link record
     * @return string Parsed broken url
     */
    public function getBrokenUrl(array $row): string
    {
        // do not return an URL to a missing file
        return '';
    }

    /**
     * Text to be displayed with the Link as anchor text
     * (not the real anchor text of the Link.
     * @param array $row
     * @param array $additionalConfig
     * @return string
     */
    public function getBrokenLinkText(array $row, array $additionalConfig = null): string
    {
        return $this->getLanguageService()->getLL('list.report.url.file');
    }
}
