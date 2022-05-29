<?php

declare(strict_types=1);

/***
 *
 * This file is based on the "Backend Module" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Christian Fries <christian.fries@lst.team>
 *
 ***/

namespace Sypets\Brofix\BackendSession;

use __PHP_Incomplete_Class;
use Sypets\Brofix\Controller\Filter\BrokenLinkListFilter;
use Sypets\Brofix\Controller\Filter\ManageExclusionsFilter;
use Sypets\Brofix\Util\Arrayable;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

class BackendSession
{
    public const FILTER_KEY_LINKLIST = 'filterKey';
    public const FILTER_KEY_MANAGE_EXCLUSIONS = 'filterKey_excludeLinks';

    /** @var string[] */
    protected $registeredKeys = [];

    protected const STORAGE_KEY_DEFAULT = 'brofix_userData';

    /**
     * The backend session object
     *
     * @var BackendUserAuthentication
     */
    protected $sessionObject;

    public function __construct()
    {
        $this->sessionObject = $GLOBALS['BE_USER'];
        $this->registerFilterKey(self::FILTER_KEY_LINKLIST, BrokenLinkListFilter::class);
        $this->registerFilterKey(self::FILTER_KEY_MANAGE_EXCLUSIONS, ManageExclusionsFilter::class);
    }

    public function registerFilterKey(string $key, string $class): void
    {
        if (!$this->isClassImplementsInterface($class, Arrayable::class)) {
            throw new \InvalidArgumentException('Given class not instance of Arrayable');
        }
        $this->registeredKeys[$key] = $class;
    }

    protected function isClassImplementsInterface(string $class, string $interface): bool
    {
        $interfaces = class_implements($class);
        if ($interfaces && in_array($interface, $interfaces)) {
            return true;
        }
        return false;
    }

    /**
     * Store a value in the session as array
     *
     * Prerequisite: the $key must have been registered previously via registerFilterKey
     *
     * @param string $key
     * @param Arrayable $value
     */
    public function store(string $key, Arrayable $value): void
    {
        if (!isset($this->registeredKeys[$key])) {
            throw new \InvalidArgumentException('Unknown key ' . $key);
        }
        $valueArray = $value->toArray();
        $sessionData = $this->sessionObject->getSessionData(self::STORAGE_KEY_DEFAULT);
        $sessionData[$key] = $valueArray;
        $this->sessionObject->setAndSaveSessionData(self::STORAGE_KEY_DEFAULT, $sessionData);
    }

    /**
     * Delete a value from the session
     *
     * @param string $key
     */
    public function delete($key): void
    {
        $sessionData = $this->sessionObject->getSessionData(self::STORAGE_KEY_DEFAULT);
        unset($sessionData[$key]);
        $this->sessionObject->setAndSaveSessionData(self::STORAGE_KEY_DEFAULT, $sessionData);
    }

    /**
     * Read a value from the session, convert array to object
     *
     * @param string $key
     * @return Arrayable
     */
    public function get(string $key): ?Arrayable
    {
        $sessionData = $this->sessionObject->getSessionData(self::STORAGE_KEY_DEFAULT);
        if (!isset($sessionData[$key]) || !$sessionData[$key]) {
            return null;
        }
        $result = $sessionData[$key];
        // safeguard: check for incomplete class
        if (is_object($result) && is_a($result, __PHP_Incomplete_Class::class)) {
            $this->delete($key);
            return null;
        }
        if (is_object($result) && is_a($result, Arrayable::class)) {
            return $result;
        }
        if (is_array($result) && isset($this->registeredKeys[$key])) {
            return call_user_func([$this->registeredKeys[$key], 'getInstanceFromArray'], $result);
        }
        return null;
    }
}
