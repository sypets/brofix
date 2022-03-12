<?php

declare(strict_types=1);

namespace Sypets\Brofix\Util;

class StringUtil
{
    /**
     * Converts timestamp into date and time using defaults from
     * $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'].
     *
     * If the date is today, only show time.
     *
     * @param int $timestamp
     * @return string
     */
    public static function formatTimestampAsString(int $timestamp): string
    {
        $currentDate = date($GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'], \time());
        $date = date($GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'], $timestamp);
        $time = date($GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'], $timestamp);
        return (($currentDate != $date) ? $date . ' ' : '') . $time;
    }
}
