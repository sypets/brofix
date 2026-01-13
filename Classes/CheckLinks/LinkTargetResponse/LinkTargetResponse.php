<?php

declare(strict_types=1);
namespace Sypets\Brofix\CheckLinks\LinkTargetResponse;

class LinkTargetResponse
{
    public const RESULT_ALL = -1;

    public const RESULT_BROKEN = 1;
    public const RESULT_OK = 2;

    /**
     * The URL check returns a status code / header which lets us assume we cannot check it correctly, so
     * we do not know whether the URL is broken or not
     */
    public const RESULT_CANNOT_CHECK = 3;

    /**
     * The URL will not be checked at all, usually because the URL or domain is in the exclude list
     */
    public const RESULT_EXCLUDED = 4;

    public const RESULT_UNKNOWN = 5;

    public const REASON_CANNOT_CHECK_CLOUDFLARE = 'cloudflare';
    public const REASON_CANNOT_CHECK_429 = '429:too many requests';
    public const REASON_CANNOT_CHECK_503 = '503:service unavailable';
    public const REASON_CANNOT_ROBOTS_TXT = 'robots.txt';

    protected int $status;
    protected int $lastChecked = 0;

    /** @var array<mixed> */
    protected array $custom = [];
    protected string $errorType = '';
    protected int $errno = 0;
    protected string $exceptionMessage = '';
    protected string $message = '';

    protected string $reasonCannotCheck = '';

    protected string $urlChecker = '';

    /**
     * @var array<int,array{from:string, to:string}>
     */
    protected array $redirects = [];

    /**
     * @param int $status
     * @param int $lastChecked
     * @param array<mixed> $custom
     * @param string $errorType
     * @param int $errno
     * @param string $exceptionMessage
     * @param string $message
     * @param string $reasonCannotCheck
     */
    public function __construct(
        int $status,
        int $lastChecked = 0,
        array $custom = [],
        string $errorType = '',
        int $errno = 0,
        string $exceptionMessage = '',
        string $message = '',
        string $reasonCannotCheck = ''
    ) {
        $this->status = $status;
        if ($lastChecked) {
            $this->lastChecked = $lastChecked;
        } else {
            $this->lastChecked = \time();
        }
        $this->custom = $custom;
        $this->errorType = $errorType;
        $this->errno = $errno;
        $this->exceptionMessage = $exceptionMessage;
        $this->message = $message;
        $this->reasonCannotCheck = $reasonCannotCheck;
    }

    public static function createInstanceFromJson(string $jsonSting): LinkTargetResponse
    {
        $values = json_decode($jsonSting, true);
        if (isset($values['valid'])) {
            return self::createInstanceFromLegacyArray($values);
        }
        return self::createInstanceFromArray($values);
    }

    /**
     * @param array<mixed> $values
     * @return LinkTargetResponse
     */
    public static function createInstanceFromLegacyArray(array $values): LinkTargetResponse
    {
        $status = $values['valid'] ? self::RESULT_OK : self::RESULT_BROKEN;
        $errorParams = $values['errorParams'] ?? [];
        $linkTargetResponse = new LinkTargetResponse(
            $status,
            $values['lastChecked'] ?? 0,
            $errorParams['custom'] ?? [],
            $errorParams['errorType'] ?? '',
            $errorParams['errno'] ?? 0,
            $errorParams['exceptionMsg'] ?? '',
            $errorParams['message'] ?? ''
        );
        if (($values['url_checker'] ?? false)) {
            $linkTargetResponse->setUrlChecker($values['url_checker']);
        }
        return $linkTargetResponse;
    }

    /**
     * @param array<mixed> $values
     * @return LinkTargetResponse
     */
    public static function createInstanceFromArray(array $values): LinkTargetResponse
    {
        $linkTargetResponse = new LinkTargetResponse(
            $values['status'],
            $values['lastChecked'] ?? \time(),
            $values['custom'] ?? [],
            $values['errorType'] ?? '',
            $values['errno'] ?? 0,
            $values['exceptionMessage'] ?? '',
            $values['message'] ?? '',
            $values['reasonCannotCheck'] ?? ''
        );
        if ($values['redirects'] ?? false) {
            $linkTargetResponse->setRedirects($values['redirects'] ?? []);
        }
        if (($values['url_checker'] ?? false)) {
            $linkTargetResponse->setUrlChecker($values['url_checker']);
        }
        return $linkTargetResponse;
    }

    /**
     * @param int $status
     * @param int $lastChecked
     * @param array<mixed> $custom
     * @return LinkTargetResponse
     */
    public static function createInstanceByStatus(int $status, int $lastChecked = 0, array $custom = []): LinkTargetResponse
    {
        return new LinkTargetResponse($status, $lastChecked, $custom);
    }

    /**
     * @param string $errorType
     * @param int $errno
     * @param string $message
     * @param string $exceptionMessage
     * @param array<mixed> $custom
     * @param int $lastChecked
     * @return LinkTargetResponse
     */
    public static function createInstanceByError(
        string $errorType = '',
        int $errno = 0,
        string $message = '',
        string $exceptionMessage = '',
        array $custom = [],
        int $lastChecked = 0
    ): LinkTargetResponse {
        return new LinkTargetResponse(
            self::RESULT_BROKEN,
            $lastChecked ? $lastChecked : \time(),
            $custom,
            $errorType,
            $errno,
            $exceptionMessage,
            $message
        );
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        // get_object_vars can get private / protected vars from within class ("accessible scope")
        $result = get_object_vars($this);
        return $result;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    public function isOk(): bool
    {
        return $this->status === self::RESULT_OK;
    }

    public function isError(): bool
    {
        return $this->status === self::RESULT_BROKEN;
    }

    public function isExcluded(): bool
    {
        return $this->status === self::RESULT_EXCLUDED;
    }

    public function isCannotCheck(): bool
    {
        return $this->status === self::RESULT_CANNOT_CHECK;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): LinkTargetResponse
    {
        $this->status = $status;
        return $this;
    }

    public function getLastChecked(): int
    {
        return $this->lastChecked;
    }

    public function setLastChecked(int $lastChecked): void
    {
        $this->lastChecked = $lastChecked;
    }

    /**
     * @return array<mixed>
     */
    public function getCustom(): array
    {
        return $this->custom;
    }

    /**
     * @param array<mixed> $custom
     */
    public function setCustom(array $custom): void
    {
        $this->custom = $custom;
    }

    public function getErrorType(): string
    {
        return $this->errorType;
    }

    public function setErrorType(string $errorType): void
    {
        $this->errorType = $errorType;
    }

    public function getErrno(): int
    {
        return $this->errno;
    }

    public function setErrno(int $errno): void
    {
        $this->errno = $errno;
    }

    public function getExceptionMessage(): string
    {
        return $this->exceptionMessage;
    }

    public function setExceptionMessage(string $exceptionMessage): void
    {
        $this->exceptionMessage = $exceptionMessage;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getReasonCannotCheck(): string
    {
        return $this->reasonCannotCheck;
    }

    public function setReasonCannotCheck(string $reasonCannotCheck): void
    {
        $this->reasonCannotCheck = $reasonCannotCheck;
    }

    public function getCombinedError(bool $withExceptionString = false): string
    {
        $result = $this->getErrorType() . ':' . $this->getErrno();
        if ($withExceptionString) {
            $result .= ':' . $this->getExceptionMessage();
        }
        return $result;
    }

    public function getEffectiveUrl(): string
    {
        if ($this->redirects) {
            return(string)(end($this->redirects)['to'] ?? '');
        }
        return '';
    }

    /**
     * @param array<int,array{from:string, to:string}> $redirects
     */
    public function setRedirects(array $redirects): void
    {
        $this->redirects = $redirects;
    }

    /**
     * @return array<int,array{from:string, to:string}>
     */
    public function getRedirects(): array
    {
        return $this->redirects;
    }

    public function getRedirectCount(): int
    {
        return count($this->redirects);
    }

    public function getUrlChecker(): string
    {
        return $this->urlChecker;
    }

    public function setUrlChecker(string $urlChecker): void
    {
        $this->urlChecker = $urlChecker;
    }
}
