<?php

declare(strict_types=1);
namespace Sypets\Brofix\Exceptions;

class MissingConfigurationException extends \Exception
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        if ($message === '') {
            $message = 'Missing configuration';
        }

        // make sure everything is assigned properly
        parent::__construct($message, $code, $previous);
    }
}
