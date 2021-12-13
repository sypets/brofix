<?php

declare(strict_types=1);
namespace Sypets\Brofix\CheckLinks\LinkTargetCache;

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

use Sypets\Brofix\Linktype\ErrorParams;

abstract class AbstractLinkTargetCache implements LinkTargetCacheInterface
{
    /**
     * How long to use external URLs (in seconds)
     * default: 1 week - 1hour
     *
     * @var int
     */
    protected $expire = 604800;

    public function setExpire(int $expire): void
    {
        $this->expire = $expire;
    }

    /**
     * Generate UrlResponse array from arguments.
     *
     * @param bool $isValid
     * @param ErrorParams $errorParams
     * @return array{'valid': bool, 'errorParams': array<mixed>}
     */
    public function generateUrlResponse(bool $isValid, ErrorParams $errorParams): array
    {
        $result = [
            'valid' => $isValid,
            'errorParams' => $errorParams->toArray()
        ];
        return $result;
    }
}
