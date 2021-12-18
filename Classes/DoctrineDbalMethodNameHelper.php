<?php

declare(strict_types=1);

namespace Sypets\Brofix;

use TYPO3\CMS\Core\Information\Typo3Version;

/**
 * Small helper for supporting core v10/v11 and changes in doctrine/dbal accros version,
 * but be fully compatible for full doctrine/dbal version range used from core v10 to v11.
 *
 * This whole class can vanish if branches are splitt and we are on core v11 as min core version
 * or doctrine/dbal:^2.13.6 (which would break v10 legacy install support).
 *
 * @internal
 */
class DoctrineDbalMethodNameHelper
{
    /** @var DoctrineDbalMethodNameHelper|null */
    protected static $instance;

    /** @var Typo3Version */
    private $coreVersion;

    /** @var string[][] */
    private $methodMap = [
        10 => [
            'fetchAssociative' => 'fetch',
            'fetchAllAssociative' => 'fetchAll',
            'fetchOne' => 'fetchColumn',
        ],
    ];

    public function __construct()
    {
        $this->coreVersion = new Typo3Version();
    }

    public function getMethodName(string $methodName): string
    {
        $map = $this->methodMap[$this->coreVersion->getMajorVersion()] ?? [];
        return $map[$methodName] ?? $methodName;
    }

    /**
     * @deprecated Replace calls to this method if min core version is at least v11 or doctrine/dbal:^2.13.6
     */
    public static function fetchAssociative(): string
    {
        return self::get()->getMethodName('fetchAssociative');
    }

    /**
     * @deprecated Replace calls to this method if min core version is at least v11 or doctrine/dbal:^2.13.6
     */
    public static function fetchAllAssociative(): string
    {
        return self::get()->getMethodName('fetchAllAssociative');
    }

    /**
     * @deprecated Replace calls to this method if min core version is at least v11 or doctrine/dbal:^2.13.6
     */
    public static function fetchOne(): string
    {
        return self::get()->getMethodName('fetchOne');
    }

    protected static function get(): DoctrineDbalMethodNameHelper
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
