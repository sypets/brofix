<?php

declare(strict_types=1);

namespace Sypets\Brofix\Parser\SoftReference;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\SoftReference\AbstractSoftReferenceParser;
use TYPO3\CMS\Core\DataHandling\SoftReference\SoftReferenceParserResult;
use TYPO3\CMS\Core\Html\HtmlParser;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * TypoLink tag processing for type LinkServer::TYPE_RECORD. Handles only links of this type!
 *
 * This is a workaround since TYPO3 core TypoLinkTagSoftReferenceParser does not handle links of type
 * LinkServer::TYPE_RECORD.
 *
 * @see https://forge.typo3.org/issues/106661
 * @see https://github.com/sypets/brofix/issues/422
 *
 * @todo should be removed if fixed in core
 */
class TypolinkRecordTagSoftReferenceParser extends AbstractSoftReferenceParser
{
    public function parse(string $table, string $field, int $uid, string $content, string $structurePath = ''): SoftReferenceParserResult
    {
        $this->setTokenIdBasePrefix($table, (string)$uid, $field, $structurePath);

        // Parse string for special TYPO3 <link> tag:
        $htmlParser = GeneralUtility::makeInstance(HtmlParser::class);
        $linkService = GeneralUtility::makeInstance(LinkService::class);
        $linkTags = $htmlParser->splitTags('a', $content);
        // Traverse result:
        $elements = [];
        foreach ($linkTags as $key => $foundValue) {
            if ($key % 2 && preg_match('/href="([^"]+)"/', $foundValue, $matches)) {
                try {
                    $linkDetails = $linkService->resolve($matches[1]);
                    if ($linkDetails['type'] === LinkService::TYPE_RECORD) {
                        $token = $this->makeTokenID((string)$key);
                        $elements[$key]['matchString'] = $foundValue;
                        $linkTags[$key] = str_replace($matches[1], '{softref:' . $token . '}', $foundValue);

                        $referencePageId = $table === 'pages'
                            ? $uid
                            : (int)(BackendUtility::getRecord($table, $uid)['pid'] ?? 0);
                        if ($referencePageId) {
                            $pageTsConfig = BackendUtility::getPagesTSconfig($referencePageId);
                            $targetTable = $pageTsConfig['TCEMAIN.']['linkHandler.'][$linkDetails['identifier'] . '.']['configuration.']['table'] ?? $linkDetails['identifier'];
                        } else {
                            // Backwards compatibility for the old behaviour, where the identifier was saved as the table.
                            $targetTable = $linkDetails['identifier'] ?? '';
                        }

                        $elements[$key]['subst'] = [
                            'type' => 'db',
                            'tokenID' => $token,
                            'identifier' => $linkDetails['identifier'],
                            'table' => $targetTable,
                            /** !!! instead of adding the table, we use the identifier here, this way it is possible to query links in RTE without loading page TSconfig
                              * this is different from core functionality and may need additional changes once this class is made obsolete
                             */
                            'recordRef' => $linkDetails['identifier'] . ':' . $linkDetails['uid'],
                            'tokenValue' => (string)($linkDetails['uid'] ?? ''),
                        ];
                    }
                } catch (\Exception $e) {
                    // skip invalid links
                }
            }
        }
        // Return output:
        return SoftReferenceParserResult::create(
            implode('', $linkTags),
            $elements
        );
    }
}
