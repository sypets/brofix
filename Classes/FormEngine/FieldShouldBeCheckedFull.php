<?php

declare(strict_types=1);

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

namespace Sypets\Brofix\FormEngine;

use TYPO3\CMS\Backend\Form\FormDataGroup\OrderedProviderList;
use TYPO3\CMS\Backend\Form\FormDataGroupInterface;
use TYPO3\CMS\Backend\Form\FormDataProvider\TcaColumnsProcessRecordTitle;
use TYPO3\CMS\Backend\Form\FormDataProvider\TcaText;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Data provider group for checking if field should be checked for
 * broken links.
 *
 * This class uses almost all checks from tcaDatabaseRecord. Not all DataProviders are required, but
 * is currently necessary to check flexforms
 *
 * @internal
 */
class FieldShouldBeCheckedFull implements FormDataGroupInterface
{
    /**
     * Compile form data
     *
     * @param mixed[] $result Initialized result array
     * @return mixed[] Result filled with data
     * @throws \UnexpectedValueException
     */
    public function compile(array $result): array
    {
        /**
         * @var OrderedProviderList $orderedProviderList
         */
        $orderedProviderList = GeneralUtility::makeInstance(OrderedProviderList::class);
        $orderedProviderList->setProviderList(
            array_diff_key($GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['tcaDatabaseRecord'],
                [
                    [
                        TcaText::class,
                        TcaColumnsProcessRecordTitle::class,
                    ]
                ])
        );

        return $orderedProviderList->compile($result);
    }
}
