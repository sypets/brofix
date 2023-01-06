<?php

declare(strict_types=1);
namespace Sypets\Brofix\CheckLinks;

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
}