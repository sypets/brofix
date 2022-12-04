<?php

declare(strict_types=1);

namespace Sypets\Brofix\FormEngine\CustomEvaluation;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Validation of input field
 * tx_brofix_exclude_link_target.linktarget
 *
 * - If match='domain', this should contain only the domain
 * - If match='url' and link_type='external', this should start with https?://
 * - If linktype='page' or 'file', should be a number
 */
class ExcludeLinkTargetsLinkTargetEvaluation
{
    /**
     * JavaScript code for client side validation/evaluation
     *
     * @return string JavaScript code for client side validation/evaluation
     */
    public function returnFieldJS(): string
    {
        return 'return value;';
    }

    /**
     * Server-side validation/evaluation on saving the record
     *
     * @param string $value The field value to be evaluated
     * @param string $is_in The "is_in" value of the field configuration from TCA
     * @param bool $set Boolean defining if the value is written to the database or not.
     * @return string Evaluated field value
     */
    public function evaluateFieldValue($value, $is_in, &$set): string
    {
        $value = $this->normalizeLinkTarget(trim($value));

        return $value;
    }

    /**
     * Server-side validation/evaluation on opening the record
     *
     * @param array<mixed> $parameters Array with key 'value' containing the field value from the database
     * @return string Evaluated field value
     */
    public function deevaluateFieldValue(array $parameters): string
    {
        return $parameters['value'] ?? '';
    }

    /**
     * Normalize linktype for domains, this should contain only
     * the domain, not the scheme.
     *
     * @param string $value
     * @return string
     */
    protected function normalizeLinkTarget(string $value): string
    {
        $formData = GeneralUtility::_GP('data');
        $id = key($formData['tx_brofix_exclude_link_target']);
        $data = $formData['tx_brofix_exclude_link_target'][$id];

        $match = $data['match'] ?? 'url';
        $linkType = $data['link_type'] ?? 'external';

        if ($match === 'domain' && $linkType === 'external') {
            // extract domain
            $value = preg_replace('#(?:https?://)?([^/]*).*#', '$1', $value);
        }
        return $value;
    }
}
