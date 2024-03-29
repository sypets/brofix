<?php

declare(strict_types=1);

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
 * Currently not used, experimental !
 *
 * This class uses almost all checks from tcaDatabaseRecord. Not all DataProviders are required.
 *
 * Some database providers cause problems, e.g.
 * - clipboard is used, requires user session which is not available in CLI
 * - BE user permissions checks are performed which should not be done when checking links
 * - TcaText data provider causes e.g. tt_content.bodytext to be written to the resulting fields, even if
 *   this will not be edited
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
            array_diff_key(
                $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['tcaDatabaseRecord'],
                [
                    [
                        TcaText::class,
                        TcaColumnsProcessRecordTitle::class,
                    ]
                ]
            )
        );

        return $orderedProviderList->compile($result);
    }
}
