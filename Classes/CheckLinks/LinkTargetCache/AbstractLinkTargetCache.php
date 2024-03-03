<?php

declare(strict_types=1);
namespace Sypets\Brofix\CheckLinks\LinkTargetCache;

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
