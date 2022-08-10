<?php
declare(strict_types=1);

namespace Sypets\Brofix\Controller\UiHelper;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Settings that are persisted for the current user (in the user settings)
 */
class UserSettings
{
    public const VIEW_MODE_VALUE_MIN = 'view_table_min';
    public const VIEW_MODE_VALUE_COMPLEX = 'view_table_complex';
    public const VIEW_MODE_VALUE_DEFAULT = self::VIEW_MODE_VALUE_COMPLEX;

    public const KEY_VIEW_MODE = 'brofix_viewMode';

    /** @var string */
    protected $viewMode = self::VIEW_MODE_VALUE_DEFAULT;

    public function __construct(string $viewMode)
    {
        $this->viewMode = $viewMode;
    }

    /**
     * Named constructor
     * @return void
     */
    public static function initializeFromSettings(array $modSettings): UserSettings
    {
        return new UserSettings($modSettings[self::KEY_VIEW_MODE] ?: self::VIEW_MODE_VALUE_DEFAULT);
    }

    /**
     * Named constructor
     * @return void
     */
    public static function initializeFromSettingsAndGetParameters(array $modSettings): UserSettings
    {
        $viewMode = $modSettings[self::KEY_VIEW_MODE] ?: '';
        if (GeneralUtility::_GP('view_mode')) {
            $viewMode = GeneralUtility::_GP('view_mode') ?: '';
        }
        if (!$viewMode) {
            $viewMode = self::VIEW_MODE_VALUE_DEFAULT;
        }
       return new UserSettings($viewMode);
    }

    public function getViewMode(): string
    {
        return $this->viewMode;
    }

    static public function getViewModeFromSettings(array $modSettings): string
    {
        return $modSettings['brofix_' . self::KEY_VIEW_MODE] ?? '';
    }

    public function persistToArray(array &$modSettings): void
    {
        $modSettings[self::KEY_VIEW_MODE] = $this->viewMode;
    }

}
