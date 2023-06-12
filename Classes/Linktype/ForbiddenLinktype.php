<?php

declare(strict_types=1);
namespace Sypets\Brofix\Linktype;

/**
 * Generic link types for not allowed link types (e.g. 'applewebdata://')
 */
class ForbiddenLinktype extends AbstractLinktype
{
    protected const ERROR_TYPE_FORBIDDEN = 'forbidden';
    protected const ERRNO_FORBIDDEN_GENERIC = 1;
    protected const ERRNO_FORBIDDEN_LOCAL = 2;

    protected const SUPPORTED_TYPES = [
        'applewebdata' => 'applewebdata'
    ];

    protected const URLS_START_WITH_ERRNO = [
        'applewebdata://' => self::ERRNO_FORBIDDEN_LOCAL,
    ];

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
        if (isset($value['type']) && $value['type'] === 'external' && isset($value['tokenValue']) && strpos($value['tokenValue'], 'applewebdata:') === 0) {
            // is type "applewebdata" if type is "external" and URL starts with applewebdata:
            return 'applewebdata';
        }
        return 'external';
    }

    /**
     * Checks a given URL + /path/filename.ext for validity
     *
     * @param string $url Url to check
     * @param mixed[] $softRefEntry The soft reference entry which builds the context of the url
     * @param int $flags see LinktypeInterface::checkLink(), not used here
     * @return bool TRUE on success or FALSE on error
     */
    public function checkLink(string $url, array $softRefEntry, int $flags = 0): bool
    {
        $this->initializeErrorParams();
        $this->errorParams->setErrorType(self::ERROR_TYPE_FORBIDDEN);

        foreach (self::URLS_START_WITH_ERRNO as $startUrl => $errnoOverride) {
            if (strpos($url, $startUrl) === 0) {
                $this->errorParams->setErrno($errnoOverride);
                $this->errorParams->setMessage($this->getErrorMessage($this->errorParams));
                return false;
            }
        }
        return true;
    }

    /**
     * Generate the localized error message from the error params saved from the parsing
     *
     * @param ErrorParams $errorParams All parameters needed for the rendering of the error message
     * @return string error message
     */
    public function getErrorMessage(ErrorParams $errorParams = null): string
    {
        switch ($errorParams->getErrno()) {
            case self::ERRNO_FORBIDDEN_LOCAL:
                return $this->getLanguageService()->getLL('list.report.error.forbidden.local');
        }
        // fallback
        return $this->getLanguageService()->getLL('list.report.error.forbidden');
    }
}
