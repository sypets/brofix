<?php

declare(strict_types=1);
namespace Sypets\Brofix\Linktype;

use Sypets\Brofix\CheckLinks\LinkTargetResponse\LinkTargetResponse;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\Exception\FolderDoesNotExistException;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class provides Check File Links plugin implementation
 */
class FileLinktype extends AbstractLinktype
{
    public const ERROR_TYPE_MISSING = 'missing';

    public const ERROR_CODE_FILE_MISSING = 1;

    public const ERROR_CODE_FOLDER_MISSING = 2;
    public function __construct(private readonly ResourceFactory $resourceFactory)
    {
    }

    /**
    * Type fetching method, based on the type that softRefParserObj returns
    *
    * @param mixed[] $value Reference properties
    * @param string $type Current type
    * @param string $key Validator hook name
    * @return string fetched type
    */
    public function fetchType(array $value, string $type, string $key): string
    {
        $tokenValue = $value['tokenValue'] ?? '';
        if ($tokenValue === '' || !is_string($tokenValue)) {
            return $type;
        }
        if (strpos(strtolower($tokenValue), 'file:') === 0) {
            $type = 'file';
        }
        return $type;
    }

    /**
     * Checks a given URL + /path/filename.ext for validity
     *
     * @param string $url Url to check
     * @param mixed[] $softRefEntry The soft reference entry which builds the context of the url
     * @param int $flags see LinktypeInterface::checkLink(), not used here
     * @return LinkTargetResponse
     */
    public function checkLink(string $url, array $softRefEntry, int $flags = 0): LinkTargetResponse
    {
        if (strpos($url, 'sys_file:') === 0) {
            $parts = explode(':', $url);
            $url = (string)($parts[1] ?? 0);
        }

        /**
         * @var ResourceFactory $resourceFactory
         */
        $resourceFactory = $this->resourceFactory;
        try {
            $file = $resourceFactory->retrieveFileOrFolderObject($url);
        } catch (FileDoesNotExistException $e) {
            return LinkTargetResponse::createInstanceByError(self::ERROR_TYPE_MISSING, self::ERROR_CODE_FILE_MISSING);
        } catch (FolderDoesNotExistException $e) {
            return LinkTargetResponse::createInstanceByError(self::ERROR_TYPE_MISSING, self::ERROR_CODE_FOLDER_MISSING);
        }

        if (!$file || $file->isMissing()) {
            return LinkTargetResponse::createInstanceByError(self::ERROR_TYPE_MISSING, self::ERROR_CODE_FILE_MISSING);
        }
        return LinkTargetResponse::createInstanceByStatus(LinkTargetResponse::RESULT_OK);
    }

    /**
     * Generate the localized error message from the error params saved from the parsing
     *
     * @param LinkTargetResponse|null $linkTargetResponse All parameters needed for the rendering of the error message
     * @return string error message
     */
    public function getErrorMessage(?LinkTargetResponse $linkTargetResponse): string
    {
        return $this->getLanguageService()->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.report.error.file.notexisting');
    }

    /**
     * Construct a valid Url for browser output
     *
     * @param mixed[] $row Broken link record
     * @return string Parsed broken url
     *
     * @todo We also show links to non-broken files, here the link could be created
     */
    public function getBrokenUrl(array $row): string
    {
        // do not return an URL to a missing file
        return '';
    }

    /**
     * Text to be displayed with the Link as anchor text
     * (not the real anchor text of the Link.
     * @param mixed[] $row
     * @param array<mixed>|null $additionalConfig
     * @return string
     *
     * @todo Show filename if possible
     */
    public function getBrokenLinkText(array $row, ?array $additionalConfig = null): string
    {
        return $this->getLanguageService()->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.report.url.file');
    }
}
