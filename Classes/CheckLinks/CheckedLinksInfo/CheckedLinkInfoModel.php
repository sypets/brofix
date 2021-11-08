<?php

namespace Sypets\Brofix\CheckLinks\CheckedLinksInfo;

class CheckedLinkInfoModel
{
    // UID, Title, URL
    /**
     * @var string
     */
    protected $pageTitle = '';

    /**
     * @var string
     */
    protected $url = '';

    /**
     * @var int
     */
    protected $uid;

    /**
     * @var int
     */
    protected $pid;

    // getters
    public function getPageTitle(): string
    {
        return $this->pageTitle;
    }
    public function getUrl(): string
    {
        return $this->url;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    // Setters
    public function setPageTitle(string $pageTitle): void
    {
        $this->pageTitle = $pageTitle;
    }
    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function setUid(int $uid): void
    {
        $this->uid = $uid;
    }

    public function setPid(int $pid): void
    {
        $this->pid = $pid;
    }
}
