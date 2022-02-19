<?php

declare(strict_types=1);

namespace Sypets\Brofix\Controller\Filter;

class BrokenLinkListFilter
{
    /**
     * @var string
     */
    protected $uid_filtre = '';

    /** @var string */
    protected $linktype_filter = 'all';

    /**
     * @var string
     */
    protected $url_filtre = '';

    /**
     * @var string
     * @deprecated
     */
    protected $title_filter = '';

    public function getUidFilter(): string
    {
        return $this->uid_filtre;
    }

    public function setUidFilter(string $uid_filter): void
    {
        $this->uid_filtre = $uid_filter;
    }

    public function getLinktypeFilter(): string
    {
        return $this->linktype_filter;
    }

    public function setLinktypeFilter(string $linktype_filter): void
    {
        $this->linktype_filter = $linktype_filter;
    }

    public function getUrlFilter(): string
    {
        return $this->url_filtre;
    }

    public function setUrlFilter(string $url_filter): void
    {
        $this->url_filtre = $url_filter;
    }

    /** @deprecated */
    public function getTitleFilter(): string
    {
        return $this->title_filter;
    }

    /** @deprecated */
    public function setTitleFilter(string $title_filter): void
    {
        $this->title_filter = $title_filter;
    }
}
