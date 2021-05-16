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

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Sypets\Brofix\CheckLinks\CrawlDelay;
use Sypets\Brofix\CheckLinks\ExcludeLinkTarget;
use Sypets\Brofix\CheckLinks\LinkTargetCache\LinkTargetCacheInterface;
use Sypets\Brofix\CheckLinks\LinkTargetCache\LinkTargetPersistentCache;
use Sypets\Brofix\Configuration\Configuration;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;

/**
 * This class provides Check External Links plugin implementation
 */
class ExternalLinktype extends AbstractLinktype implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    // HTTP status code was delivered (and can be found in $errorParams->errno)
    public const ERROR_TYPE_HTTP_STATUS_CODE = 'httpStatusCode';
    // An error occurred in lowlevel handler and a cURL error code can be found in $errorParams->errno
    public const ERROR_TYPE_LOWLEVEL_LIBCURL_ERRNO = 'libcurlErrno';
    public const ERROR_TYPE_TOO_MANY_REDIRECTS = 'tooManyRedirects';
    public const ERROR_TYPE_UNABLE_TO_PARSE = 'unableToParseUri';
    public const ERROR_TYPE_UNKNOWN = 'unknown';

    /**
     * @var RequestFactory
     */
    protected $requestFactory;

    /**
     * Current URL is excluded from checking - handle as valid.
     *
     * @var bool
     */
    protected $isExcludeUrl = false;

    /**
     * @var int
     */
    protected $lastChecked = 0;

    /**
     * @var ExcludeLinkTarget
     */
    protected $excludeLinkTarget;

    /**
     * @var string
     */
    protected $domain = '';

    /**
     * @var LinkTargetCacheInterface
     */
    protected $linkTargetCache;

    /**
     * @var CrawlDelay
     */
    protected $crawlDelay;

    public function __construct(
        RequestFactory $requestFactory = null,
        ExcludeLinkTarget $excludeLinkTarget = null,
        LinkTargetCacheInterface $linkTargetCache = null,
        CrawlDelay $crawlDelay = null
    ) {
        $this->initializeErrorParams();
        $this->requestFactory = $requestFactory ?: GeneralUtility::makeInstance(RequestFactory::class);
        $this->excludeLinkTarget = $excludeLinkTarget ?: GeneralUtility::makeInstance(ExcludeLinkTarget::class);
        $this->linkTargetCache = $linkTargetCache ?: GeneralUtility::makeInstance(LinkTargetPersistentCache::class);
        $this->crawlDelay = $crawlDelay ?: GeneralUtility::makeInstance(CrawlDelay::class);
    }

    public function setConfiguration(Configuration $configuration): void
    {
        parent::setConfiguration($configuration);
        $this->excludeLinkTarget->setExcludeLinkTargetsPid($this->configuration->getExcludeLinkTargetStoragePid());
        $this->crawlDelay->setConfiguration($this->configuration);
    }

    /**
     * @param string $url
     * @return bool
     */
    protected function getResultForUrlFromCache(string $url): bool
    {
        /**
         *  'valid' => bool,
         *  'isExcluded' => bool,
         *  'errorParams' => array
         *  'lastChecked' => int
         */
        $urlResponse = $this->linkTargetCache->getUrlResponseForUrl($url, 'external');

        $this->errorParams->initialize($urlResponse['errorParams'] ?? []);
        $this->lastChecked = $urlResponse['lastChecked'] ?? 0;
        return (bool)($urlResponse['valid'] ?? false);
    }

    /**
     * @param string $url
     * @param bool $isValid
     */
    protected function insertIntoLinkTargetCache(string $url, bool $isValid): void
    {
        $urlResponse = $this->linkTargetCache->generateUrlResponse($isValid, $this->errorParams);
        $this->linkTargetCache->setResult($url, 'external', $urlResponse);
    }

    /**
     * Checks a given URL for validity
     *
     * @param string $origUrl The URL to check
     * @param mixed[] $softRefEntry The soft reference entry which builds the context of that URL
     * @param int $flags can be a combination of flags defined in AbstractLinktype CHECK_LINK_FLAG_*
     * @return bool true on success or false on error
     * @throws \InvalidArgumentException
     */
    public function checkLink(string $origUrl, array $softRefEntry, int $flags = 0): bool
    {
        $isValidUrl = false;
        $this->initializeErrorParams();

        // check if URL should be excluded, treat excluded URLs as valid URLs
        if ($this->excludeLinkTarget->isExcluded($origUrl, 'external')) {
            $this->isExcludeUrl = true;
            return true;
        }
        $this->isExcludeUrl = false;

        // use URL from cache, if available
        if ((($flags & AbstractLinktype::CHECK_LINK_FLAG_NO_CACHE) === 0)
            //&& $this->linkTargetCache->hasEntryForUrl($origUrl, 'external', true, $this->configuration->getLinkTargetCacheExpires($flags))
        ) {

            /**
             *  'valid' => bool,
             *  'isExcluded' => bool,
             *  'errorParams' => array
             *  'lastChecked' => int
             */
            $urlResponse = $this->linkTargetCache->getUrlResponseForUrl(
                $origUrl,
                'external',
                $this->configuration->getLinkTargetCacheExpires($flags)
            );
            if ($urlResponse !== []) {
                $this->errorParams->initialize($urlResponse['errorParams'] ?? []);
                $this->lastChecked = $urlResponse['lastChecked'] ?? 0;
                $result = (bool)($urlResponse['valid'] ?? false);

                if ((($flags & AbstractLinktype::CHECK_LINK_FLAG_NO_CACHE_ON_ERROR) !== 0) && $result === false) {
                    // make sure result is fresh if invalid URL
                    // skip cache result here and continue checking
                    $this->initializeErrorParams();
                    $this->lastChecked = 0;
                } else {
                    return $result;
                }
            }
        }

        $options = [
            'cookies' => GeneralUtility::makeInstance(CookieJar::class),
            'allow_redirects' => [
                'strict' => true,
                'referer' => true,
                'max' => $this->configuration->getLinktypesConfigExternalRedirects(),
            ],
            'headers'         => $this->configuration->getLinktypesConfigExternalHeaders(),
            'timeout' => $this->configuration->getLinktypesConfigExternalTimeout(),
        ];

        $url = $this->preprocessUrl($origUrl);
        if (!empty($url)) {
            if (($flags & AbstractLinktype::CHECK_LINK_FLAG_NO_CRAWL_DELAY) === 0) {
                $delayed = $this->crawlDelay->crawlDelay($this->domain);
                $this->logger->debug('crawl delay=' . $delayed . ' for URL=' . $url);
            }

            $isValidUrl = $this->requestUrl($url, 'HEAD', $options);
            if (!$isValidUrl) {
                // HEAD was not allowed or threw an error, now trying GET
                $options['headers']['Range'] = 'bytes=0-4048';
                $isValidUrl = $this->requestUrl($url, 'GET', $options);
            }
            $this->crawlDelay->setLastCheckedTime($this->domain);
        }
        $this->lastChecked = \time();
        $this->insertIntoLinkTargetCache($url, $isValidUrl);
        return $isValidUrl;
    }

    /**
     * Check URL using the specified request methods
     *
     * @param string $url
     * @param string $method
     * @param mixed[] $options
     * @return bool
     */
    protected function requestUrl(string $url, string $method, array $options): bool
    {
        $this->initializeErrorParams();
        $isValidUrl = false;
        try {
            $response = $this->requestFactory->request($url, $method, $options);
            if ($response->getStatusCode() >= 300) {
                $this->errorParams->setErrorType(self::ERROR_TYPE_HTTP_STATUS_CODE);
                $this->errorParams->setErrno($response->getStatusCode());
                $this->errorParams->setMessage($this->getErrorMessage($this->errorParams));
            } else {
                $isValidUrl = true;
            }
            /* Guzzle Exceptions:
            * . \RuntimeException
            * ├── SeekException (implements GuzzleException)
            * └── TransferException (implements GuzzleException)
            * ....└── RequestException
            * ----....├── BadResponseException
            * ........│   ├── ServerException
            * ........│   └── ClientException
            * ........├── ConnectException
            * ........└── TooManyRedirectsException
            */
        } catch (TooManyRedirectsException $e) {
            $this->errorParams->setErrorType(self::ERROR_TYPE_TOO_MANY_REDIRECTS);
            $this->errorParams->setExceptionMsg($e->getMessage());
            $this->errorParams->setMessage($this->getErrorMessage($this->errorParams));
        } catch (ClientException | ServerException $e) {
            // ClientException - A GuzzleHttp\Exception\ClientException is thrown for 400 level errors if the http_errors request option is set to true.
            // ServerException - A GuzzleHttp\Exception\ServerException is thrown for 500 level errors if the http_errors request option is set to true.
            if ($e->hasResponse()) {
                $this->errorParams->setErrorType(self::ERROR_TYPE_HTTP_STATUS_CODE);
                $this->errorParams->setErrno($e->getResponse()->getStatusCode());
            } else {
                $this->errorParams->setErrorType(self::ERROR_TYPE_UNKNOWN);
            }
            $this->errorParams->setExceptionMsg($e->getMessage());
            $this->errorParams->setMessage($this->getErrorMessage($this->errorParams));
        } catch (ConnectException | RequestException $e) {
            // RequestException - In the event of a networking error (connection timeout, DNS errors, etc.), a GuzzleHttp\Exception\RequestException is thrown.
            // Catching this exception will catch any exception that can be thrown while transferring requests.
            // ConnectException - A GuzzleHttp\Exception\ConnectException exception is thrown in the event of a networking error.
            $this->errorParams->setExceptionMsg($e->getMessage());
            // RequestException has getHandlerContext
            // The contents of this array will vary depending on which handler you are
            // * using. It may also be just an empty array. Relying on this data will
            // * couple you to a specific handler, but can give more debug information
            // * when needed.
            $handlerContext = $e->getHandlerContext();
            if ((($handlerContext['errno'] ?? 0) !== 0) && (strncmp(
                $this->errorParams->getExceptionMsg(),
                'cURL error',
                strlen('cURL error')
            ) === 0)) {
                $this->errorParams->setErrorType(self::ERROR_TYPE_LOWLEVEL_LIBCURL_ERRNO);
                $this->errorParams->setErrno((int)($handlerContext['errno']));
                // use shorter error message
                if (isset($handlerContext['error'])) {
                    $this->errorParams->setExceptionMsg($handlerContext['error'] ?? $this->errorParams['exception'] ?? '');
                }
            } else {
                $this->errorParams->setErrorType(self::ERROR_TYPE_UNKNOWN);
            }
            $this->errorParams->setMessage($this->getErrorMessage($this->errorParams));
        } catch (\InvalidArgumentException $e) {
            $this->errorParams->setErrorType(self::ERROR_TYPE_UNABLE_TO_PARSE);
            $this->errorParams->setExceptionMsg($e->getMessage());
        } catch (\Exception $e) {
            // Generic catch for anything else that may go wrong
            $this->errorParams->setErrorType(self::ERROR_TYPE_UNKNOWN);
            $this->errorParams->setExceptionMsg($e->getMessage());
            $this->errorParams->setMessage($this->getErrorMessage($this->errorParams));
        }
        return $isValidUrl;
    }

    /**
     * @return bool
     */
    public function isExcludeUrl(): bool
    {
        return $this->isExcludeUrl;
    }

    public function getLastChecked(): int
    {
        return $this->lastChecked;
    }

    /**
     * Generate the localized error message from the error params saved from the parsing
     *
     * @param ErrorParams $errorParams All parameters needed for the rendering of the error message
     * @return string Validation error message
     */
    public function getErrorMessage(ErrorParams $errorParams = null): string
    {
        if ($errorParams === null) {
            $errorParams = $this->errorParams;
        }

        $lang = $this->getLanguageService();
        $errorType = $errorParams->getErrorType();
        $errno = $errorParams->getErrno();
        $exception = $errorParams->getExceptionMsg();

        switch ($errorType) {
            case self::ERROR_TYPE_HTTP_STATUS_CODE:
                $message = $lang->getLL('list.report.error.httpstatus.' . $errno);
                if (!$message) {
                    if ($errno !== 0) {
                        // fall back to generic error message
                        $message = sprintf($lang->getLL('list.report.externalerror'), (string)$errno);
                    } else {
                        $message = $exception;
                    }
                }
                break;

            case self::ERROR_TYPE_LOWLEVEL_LIBCURL_ERRNO:
                $message = '';
                if ($errno > 0) {
                    // get localized error message
                    $message = $lang->getLL('list.report.error.libcurl.' . $errno);
                }
                if (!$message) {
                    // fallback to  generic error message and show exception
                    $message = $lang->getLL('list.report.networkexception');
                    if ($exception !== '') {
                        $message .= ' ('
                            . $exception
                            . ')';
                    }
                }
                break;

            case 'loop':
                $message = sprintf(
                    $lang->getLL('list.report.redirectloop'),
                    $exception,
                    ''
                );
                break;

            case 'tooManyRedirects':
                $message = $lang->getLL('list.report.tooManyRedirects');
                break;

            default:
                $message = $exception;
        }
        return $message;
    }

    /**
     * Get the external type from the softRefParserObj result
     *
     * @param mixed[] $value Reference properties
     * @param string $type Current type
     * @param string $key Validator hook name
     * @return string Fetched type
     */
    public function fetchType(array $value, string $type, string $key): string
    {
        $tokenValue = $value['tokenValue'] ?? '';
        if ($tokenValue === '' || !is_string($tokenValue)) {
            return $type;
        }

        preg_match_all('/((?:http|https))(?::\\/\\/)(?:[^\\s<>]+)/i', $tokenValue, $urls, PREG_PATTERN_ORDER);
        if (!empty($urls[0][0])) {
            $type = 'external';
        }
        return $type;
    }

    /**
     * Convert given URL to punycode to handle domains with non-ASCII characters
     *
     * @param string $url
     * @return string
     */
    protected function preprocessUrl(string $url): string
    {
        $url = html_entity_decode($url);
        $parts = parse_url($url);
        $host = (string)($parts['host'] ?? '');
        if ($host !== '') {
            try {
                $newDomain = (string)HttpUtility::idn_to_ascii($host);
                if (strcmp($host, $newDomain) !== 0) {
                    $parts['host'] = $newDomain;
                    $url = HttpUtility::buildUrl($parts);
                }
            } catch (\Exception | \Throwable $e) {
                // proceed with link checking
            }
        }
        $this->domain = $parts['host'] ?? '';
        return $url;
    }
}
