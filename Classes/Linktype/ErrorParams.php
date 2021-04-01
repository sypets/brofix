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

/**
 * @todo rename to UrlResult and also add isExlude and lastCheck
 */
class ErrorParams
{
    /**
     * @var string
     */
    protected $errorType;

    /**
     * @var int
     */
    protected $errno;

    /**
     * Exception message
     *
     * @var string
     */
    protected $exceptionMsg;

    /**
     * @var array
     */
    protected $custom;

    /**
     * @var string
     */
    protected $message;

    public function __construct(array $errorParams = null)
    {
        $this->initialize($errorParams);
    }

    public function initialize(array $params = null): void
    {
        if ($params) {
            if (is_array($params['errorType'])) {
                if (isset($params['errorType']['page'])) {
                    $params['errorType'] = 'page';
                } elseif (isset($params['errorType']['content'])) {
                    $params['errorType'] = 'content';
                } else {
                    $params['errorType'] = 'unknown';
                }
            }

            $this->errorType    = $params['errorType'] ?? '';
            $this->errno        = $params['errno'] ?? 0;
            $this->exceptionMsg = $params['exceptionMsg'] ?? '';
            $this->message      = $params['message'] ?? '';
            $this->custom       = $params['custom'] ?? [];
        } else {
            $this->errorType = '';
            $this->errno = 0;
            $this->exceptionMsg = '';
            $this->message = '';
            $this->custom = [];
        }
    }

    /**
     * Is valid URL: Is valid by default and if errorType
     * is empty string
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->errorType === '';
    }

    /**
     * @return string
     */
    public function getErrorType(): string
    {
        return $this->errorType;
    }

    /**
     * @param string $errorType
     */
    public function setErrorType(string $errorType): void
    {
        $this->errorType = $errorType;
    }

    /**
     * @return int
     */
    public function getErrno(): int
    {
        return $this->errno;
    }

    /**
     * @param int $errno
     */
    public function setErrno(int $errno): void
    {
        $this->errno = $errno;
    }

    /**
     * @return string
     */
    public function getExceptionMsg(): string
    {
        return $this->exceptionMsg;
    }

    /**
     * @param string $exceptionMsg
     */
    public function setExceptionMsg(string $exceptionMsg): void
    {
        $this->exceptionMsg = $exceptionMsg;
    }

    /**
     * @return array
     */
    public function getCustom(): array
    {
        return $this->custom;
    }

    /**
     * @param array $custom
     */
    public function setCustom(array $custom): void
    {
        $this->custom = $custom;
    }

    /**
     * @param array $custom
     */
    public function addCustom(array $custom): void
    {
        $this->custom = array_merge($this->custom, $custom);
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @param string $message
     */
    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    public function toArray(): array
    {
        return [
            'isValid'   => $this->isValid(),
            'errorType' => $this->errorType,
            'errno'     => $this->errno,
            'exceptionMsg' => $this->exceptionMsg,
            'message'   => $this->message,
            'custom' => $this->custom
        ];
    }
}
