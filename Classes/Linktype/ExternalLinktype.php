<?php

declare(strict_types=1);
namespace Sypets\Brofix\Linktype;

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
use Sypets\Brofix\CheckLinks\LinkTargetResponse\LinkTargetResponse;
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
     *
     * @deprecated
     */
    protected $isExcludeUrl = false;

    /**
     * @var int
     * @deprecated
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

    protected function insertIntoLinkTargetCache(string $url, LinkTargetResponse $linkTargetResponse): void
    {
        $this->linkTargetCache->setResult($url, 'external', $linkTargetResponse);
    }

    /**
     * Checks a given URL for validity
     *
     * @param string $origUrl The URL to check
     * @param mixed[] $softRefEntry The soft reference entry which builds the context of that URL
     * @param int $flags can be a combination of flags defined in AbstractLinktype CHECK_LINK_FLAG_*
     * @return LinkTargetResponse
     * @throws \InvalidArgumentException
     */
    public function checkLink(string $origUrl, array $softRefEntry, int $flags = 0): LinkTargetResponse
    {
        $linkTargetResponse = LinkTargetResponse::createInstanceByStatus(LinkTargetResponse::RESULT_OK);

        // check if URL should be excluded, treat excluded URLs as valid URLs
        if ($this->excludeLinkTarget->isExcluded($origUrl, 'external')) {
            return LinkTargetResponse::createInstanceByStatus(LinkTargetResponse::RESULT_EXCLUDED);
        }

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
            if ($urlResponse) {
                if ((($flags & AbstractLinktype::CHECK_LINK_FLAG_NO_CACHE_ON_ERROR) !== 0)
                    && $urlResponse->getStatus() === LinkTargetResponse::RESULT_BROKEN) {
                    // make sure result is fresh if invalid URL
                    // skip cache result here and continue checking
                } else {
                    return $urlResponse;
                }
            }
        }

        $cookieJar = GeneralUtility::makeInstance(CookieJar::class);

        $options = [
            'cookies' => $cookieJar,
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

            $linkTargetResponse = $this->requestUrl($url, 'HEAD', $options);
            if ($linkTargetResponse->isError()) {
                // HEAD was not allowed or threw an error, now trying GET
                $options['headers']['Range'] = 'bytes=0-4048';
                $linkTargetRespone = $this->requestUrl($url, 'GET', $options);
            }
            $this->crawlDelay->setLastCheckedTime($this->domain);
        }
        $this->insertIntoLinkTargetCache($url, $linkTargetResponse);
        return $linkTargetResponse;
    }

    /**
     * Check URL using the specified request methods
     *
     * @param string $url
     * @param string $method
     * @param mixed[] $options
     * @return LinkTargetResponse
     */
    protected function requestUrl(string $url, string $method, array $options): LinkTargetResponse
    {
        $responseHeaders = [];
        try {
            $response = $this->requestFactory->request($url, $method, $options);
            if ($response->getStatusCode() >= 300) {
                $linkTargetResponse = LinkTargetResponse::createInstanceByError(
                    self::ERROR_TYPE_HTTP_STATUS_CODE,
                    $response->getStatusCode()
                );
            } else {
                $linkTargetResponse = LinkTargetResponse::createInstanceByStatus(LinkTargetResponse::RESULT_OK);
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
            $linkTargetResponse = LinkTargetResponse::createInstanceByError(
                self::ERROR_TYPE_TOO_MANY_REDIRECTS,
                0,
                '',
                $e->getMessage()
            );
        } catch (ClientException | ServerException $e) {
            // ClientException - A GuzzleHttp\Exception\ClientException is thrown for 400 level errors if the http_errors request option is set to true.
            // ServerException - A GuzzleHttp\Exception\ServerException is thrown for 500 level errors if the http_errors request option is set to true.
            if ($e->hasResponse()) {
                $linkTargetResponse = LinkTargetResponse::createInstanceByError(
                    self::ERROR_TYPE_HTTP_STATUS_CODE,
                    $e->getResponse()->getStatusCode()
                );
                $responseHeaders = $e->getResponse()->getHeaders();
            } else {
                $linkTargetResponse = LinkTargetResponse::createInstanceByError(self::ERROR_TYPE_UNKNOWN);
            }
            $linkTargetResponse->setExceptionMessage($e->getMessage());
        } catch (ConnectException | RequestException $e) {
            // RequestException - In the event of a networking error (connection timeout, DNS errors, etc.), a GuzzleHttp\Exception\RequestException is thrown.
            // Catching this exception will catch any exception that can be thrown while transferring requests.
            // ConnectException - A GuzzleHttp\Exception\ConnectException exception is thrown in the event of a networking error.
            // RequestException has getHandlerContext
            // The contents of this array will vary depending on which handler you are
            // * using. It may also be just an empty array. Relying on this data will
            // * couple you to a specific handler, but can give more debug information
            // * when needed.
            $exceptionMessage = $e->getMessage();
            $handlerContext = $e->getHandlerContext();
            if ((($handlerContext['errno'] ?? 0) !== 0) && (strncmp(
                $e->getMessage(),
                'cURL error',
                strlen('cURL error')
            ) === 0)) {
                // use shorter error message
                if (isset($handlerContext['error'])) {
                    $exceptionMessage = $handlerContext['error'];
                }
                $linkTargetResponse = LinkTargetResponse::createInstanceByError(
                    self::ERROR_TYPE_LOWLEVEL_LIBCURL_ERRNO,
                    (int)($handlerContext['errno']),
                    '',
                    $exceptionMessage
                );
            } else {
                $linkTargetResponse = LinkTargetResponse::createInstanceByError(
                    self::ERROR_TYPE_UNKNOWN,
                    0,
                    '',
                    $exceptionMessage
                );
            }
        } catch (\InvalidArgumentException $e) {
            $linkTargetResponse = LinkTargetResponse::createInstanceByError(
                self::ERROR_TYPE_UNABLE_TO_PARSE,
                0,
                '',
                $e->getMessage()
            );
        } catch (\Exception $e) {
            // Generic catch for anything else that may go wrong
            $linkTargetResponse = LinkTargetResponse::createInstanceByError(
                self::ERROR_TYPE_UNKNOWN,
                0,
                '',
                $e->getMessage()
            );
        }

        if ($this->isCombinedErrorNonCheckable($linkTargetResponse)) {
            $linkTargetResponse->setStatus(LinkTargetResponse::RESULT_CANNOT_CHECK);
            foreach ($responseHeaders as $headerName => $headerValue) {
                if (str_starts_with(mb_strtolower($headerName), 'cf-')) {
                    $linkTargetResponse->setReasonCannotCheck(LinkTargetResponse::REASON_CANNOT_CHECK_CLOUDFLARE);
                }
            }
        }

        return $linkTargetResponse;
    }

    protected function isCombinedErrorNonCheckable(LinkTargetResponse $linkTargetResponse): bool
    {
        if (!$this->configuration) {
            return false;
        }

        $combinedErrorNonCheckableMatch =  $this->configuration->getCombinedErrorNonCheckableMatch();
        $combinedError = $linkTargetResponse->getCombinedError(true);
        if (!$combinedErrorNonCheckableMatch || !$combinedError) {
            return false;
        }

        if (str_starts_with($combinedErrorNonCheckableMatch, 'regex:')) {
            $regex = trim(substr($combinedErrorNonCheckableMatch, strlen('regex:')));
            if (preg_match($regex, $combinedError)) {
                return true;
            }
        } else {
            foreach (explode(',', $combinedErrorNonCheckableMatch) as $match) {
                if (str_starts_with($combinedError, $match)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Generate the localized error message from the error params saved from the parsing
     *
     * @param ?LinkTargetResponse $linkTargetResponse All parameters needed for the rendering of the error message
     * @return string Validation error message
     *
     * @deprecated
     */
    public function getErrorMessage(?LinkTargetResponse $linkTargetResponse): string
    {
        if ($linkTargetResponse === null) {
            return '';
        }

        $lang = $this->getLanguageService();
        $errorType = $linkTargetResponse->getErrorType();
        $errno = $linkTargetResponse->getErrno();
        $exception = $linkTargetResponse->getExceptionMessage();

        switch ($errorType) {
            case self::ERROR_TYPE_HTTP_STATUS_CODE:
                $message = $lang->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.report.error.httpstatus.' . $errno);
                if (!$message) {
                    if ($errno !== 0) {
                        // fall back to generic error message
                        $message = sprintf($lang->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.report.error.httpstatus.general'), (string)$errno);
                    } else {
                        $message = $exception;
                    }
                }
                break;

            case self::ERROR_TYPE_LOWLEVEL_LIBCURL_ERRNO:
                $message = '';
                if ($errno > 0) {
                    // get localized error message
                    $message = $lang->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.report.error.libcurl.' . $errno);
                }
                if (!$message) {
                    // fallback to  generic error message and show exception
                    $message = $lang->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.report.error.networkexception');
                    if ($exception !== '') {
                        $message .= ' ('
                            . $exception
                            . ')';
                    }
                }
                break;

            case 'loop':
                $message = sprintf(
                    $lang->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.report.error.redirectloop'),
                    $exception,
                    ''
                );
                break;

            case 'tooManyRedirects':
                $message = $lang->sL('LLL:EXT:brofix/Resources/Private/Language/Module/locallang.xlf:list.report.error.tooManyRedirects');
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
                $newDomain = (string)idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
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
