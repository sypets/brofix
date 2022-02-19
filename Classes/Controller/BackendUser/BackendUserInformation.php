<?php

declare(strict_types=1);

namespace Sypets\Brofix\Controller\BackendUser;

/**
 * Class stores permission information for current BE user
 */
class BackendUserInformation
{
    /** @var bool */
    protected $brokenLinkList;

    /** @var bool */
    protected $excludeLinks;

    public function __construct(bool $brokenLinkList, bool $excludeLinks)
    {
        $this->brokenLinkList = $brokenLinkList;
        $this->excludeLinks = $excludeLinks;
    }

    public function hasPermissionBrokenLinkList(): bool
    {
        return $this->brokenLinkList;
    }

    public function hasPermissionExcludeLinks(): bool
    {
        return $this->excludeLinks;
    }
}
