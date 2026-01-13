<?php
declare(strict_types=1);
namespace Sypets\Brofix\CheckLinks;

use Spatie\Robots\Robots;
use Spatie\Robots\RobotsTxt;

class RobotsTxtChecker
{
    public function __construct()
    {

    }

    public function isAllowed(string $url, string $userAgent = ''): bool
    {
        $robotsTxt = RobotsTxt::create($this->createRobotsUrl($url));
        if (! $robotsTxt->allows($url, $userAgent)) {
            return false;
        }
        return true;
    }

    protected function createRobotsUrl(string $url): string
    {
        $robotsUrl = parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST);

        if ($port = parse_url($url, PHP_URL_PORT)) {
            $robotsUrl .= ":{$port}";
        }

        return "{$robotsUrl}/robots.txt";
    }

}
