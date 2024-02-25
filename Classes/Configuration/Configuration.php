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

namespace Sypets\Brofix\Configuration;

use Symfony\Component\Mime\Address;
use Sypets\Brofix\Linktype\AbstractLinktype;
use Sypets\Brofix\Linktype\LinktypeInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MailUtility;

/**
 * Wrapper class for handling TSconfig. Using the convenience
 * functions in this class the properties can be accessed
 * directly and the functions will already return the correct type.
 *
 * Configuration can still be extended by extending the TSconfig mod.brofix
 * and the function getTsConfig() used in this class.
 */
class Configuration
{
    public const TRAVERSE_MAX_NUMBER_OF_PAGES_IN_BACKEND_DEFAULT = 1000;
    public const DEFAULT_TSCONFIG = [
        'searchFields.' => [
            'pages' => 'media,url',
            'tt_content' => 'bodytext,header_link,records',
        ],
        'excludeCtype' => 'html',
        'linktypes' => 'db,file,external,applewebdata',
        'check.' => [
            'doNotCheckContentOnPagesDoktypes' => '3,4',
            'doNotCheckPagesDoktypes' => '6,7,199,255',
            'doNotTraversePagesDoktypes' => '6,199,255',
            'doNotCheckLinksOnWorkspace' => false,
        ],
        'checkhidden' => false,
        'depth' => 999,
        'reportHiddenRecords' => true,
        'linktypesConfig.' => [
            'external.' => [
                'headers.' => [
                    'User-Agent' => '',
                    'Accept' => '*/*'
                ],
                'timeout' => 10,
                'redirects' => 5,
            ]
        ],
        'excludeLinkTarget.' => [
            'storagePid' => 0,
            'allowed' => 'external',
        ],
        'linkTargetCache.' => [
            'expiresLow' => 604800,
            'expiresHigh' => 691200,
        ],
        'crawlDelay.' => [
            'seconds' => 5,
            'nodelay' => '',
        ],
        'report.' => [
            'docsurl' => '',
            'recheckButton' => -1,
        ],
        'mail.' => [
            'sendOnCheckLinks' => 1,
            'recipients' => '',
            'fromname' => '',
            'fromemail' => '',
            'replytoname' => '',
            'replytoemail' => '',
            'subject' => '',
            'template' => 'CheckLinksResults',
            'language' => 'en',
        ],
        'custom.' => []
    ];

    /**
     * @var mixed[]
     * @todo replace this array and use member variables for all values
     */
    protected $tsConfig = self::DEFAULT_TSCONFIG;

    /**
     * Limit number of pages traversed in backend. This limit is only active when displaying
     * and checking links in the Backend, not when checking links via CLI or scheduler
     *
     * We set to 0 here by default (which means no limit) to not limit link checking via CLI.
     *
     * @var int
     */
    protected int $traverseMaxNumberOfPagesInBackend = 0;

    /**
     * @var string[]
     *
     * Do not use these softreference parsers (only in fields $excludeSoftrefsInFields)
     */
    protected $excludeSoftrefs;

    /** @var string[] */
    protected $excludeSoftrefsInFields;

    /**
     * Array for hooks for own checks
     *
     * @var array<string,LinktypeInterface>
     */
    protected array $hookObjectsArr = [];

    /**
     * Configuration constructor.
     * @param array<mixed> $extConfArray
     */
    public function __construct(array $extConfArray)
    {
        // initialize from extension configuration
        $this->excludeSoftrefs = explode(',', $extConfArray['excludeSoftrefs'] ?? '');
        $this->excludeSoftrefsInFields = explode(',', $extConfArray['excludeSoftrefsInFields'] ?? '');
        $this->setTraverseMaxNumberOfPagesInBackend(
            (int)($extConfArray['traverseMaxNumberOfPagesInBackend']
                ?? self::TRAVERSE_MAX_NUMBER_OF_PAGES_IN_BACKEND_DEFAULT)
        );

        // initialize from global configuration
        // Hook to handle own checks
        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['checkLinks'] ?? [] as $key => $className) {
            /**
             * @var LinktypeInterface $linktype
             */
            $linktype = GeneralUtility::makeInstance($className); // @phpstan-ignore-line
            $this->hookObjectsArr[$key] = $linktype;
            $linktype->setConfiguration($this);
        }
    }

    /**
     * @param mixed[] $tsConfig
     */
    public function setTsConfig(array $tsConfig): void
    {
        $this->tsConfig = $tsConfig;
    }

    /**
     * Get the brofix modTSconfig for a page
     *
     * @param int $page Uid of the page
     * @throws \Exception
     */
    public function loadPageTsConfig(int $page): void
    {
        $this->setTsConfig(BackendUtility::getPagesTSconfig($page)['mod.']['brofix.'] ?? []);
    }

    /**
     * Reads page TSconfig from string and overrides existing tsconfig in
     * $this->tsconfig with the values.
     *
     * @param string $tsConfigString
     * @throws \Exception
     *
     * @deprecated use overrideTsConfigByArray
     */
    public function overrideTsConfigByString(string $tsConfigString): void
    {
        /**
         * @var TypoScriptParser $parseObj
         */
        $parseObj = GeneralUtility::makeInstance(TypoScriptParser::class);
        $parseObj->parse($tsConfigString);
        if (!empty($parseObj->errors)) {
            $parseErrorMessage = 'Invalid TSconfig'
                . '<br />';
            foreach ($parseObj->errors as $errorInfo) {
                $parseErrorMessage .= $errorInfo[0] . '<br />';
            }
            throw new \Exception($parseErrorMessage);
        }
        $tsConfig = $parseObj->setup;
        $overrideTs = $tsConfig['mod.']['brofix.'];

        if (is_array($overrideTs)) {
            ArrayUtility::mergeRecursiveWithOverrule($this->tsConfig, $overrideTs);
        }
    }

    /**
     * @param array<mixed> $override
     */
    public function overrideTsConfigByArray(array $override): void
    {
        ArrayUtility::mergeRecursiveWithOverrule($this->tsConfig, $override);
    }

    /**
     * @return mixed[]
     */
    public function getTsConfig(): array
    {
        return $this->tsConfig;
    }

    /**
     * @param array<string,array<string>> $searchFields , e.g.
     *   [
     *      'tt_content' => ['bodytext', 'media']
     *   ]
     */
    public function setSearchFields(array $searchFields): void
    {
        unset($this->tsConfig['searchFields.']);
        foreach ($searchFields as $table => $fields) {
            $this->tsConfig['searchFields.'][$table] = implode(',', $fields);
        }
    }

    /**
     * Get the list of fields to parse in modTSconfig
     *
     * @return array<string,array<string>> $searchFields List of fields, e.g.
     *   [
     *      'tt_content' => ['bodytext', 'media']
     *   ]
     */
    public function getSearchFields(): array
    {
        if (!isset($this->tsConfig['searchFields.'])) {
            return [];
        }
        // Get the searchFields from TypoScript
        foreach ($this->tsConfig['searchFields.'] as $table => $fieldList) {
            $fields = GeneralUtility::trimExplode(',', $fieldList);
            foreach ($fields as $field) {
                $searchFields[$table][] = $field;
            }
        }
        return $searchFields ?? [];
    }

    /**
     * @return array<string>
     */
    public function getExcludedCtypes(): array
    {
        return explode(',', (string)($this->tsConfig['excludeCtype'] ?? ''));
    }

    /**
     * @param array<int,string> $linkTypes
     */
    public function setLinkTypes(array $linkTypes): void
    {
        $this->tsConfig['linktypes'] = implode(',', $linkTypes);
    }

    /**
     * @return array<string>
     */
    public function getLinkTypes(): array
    {
        return explode(',', $this->tsConfig['linktypes'] ?? 'external,db,file');
    }

    /**
     * @return array<int,int>
     */
    public function getDoNotCheckContentOnPagesDoktypes(): array
    {
        $doktypes = explode(',', $this->tsConfig['check.']['doNotCheckContentOnPagesDoktypes'] ?? '3,4');
        $result = [];
        foreach ($doktypes as $doktype) {
            $doktype = (int)$doktype;
            $result[$doktype] = $doktype;
        }
        return $result;
    }

    /**
     * @return array<int,int>
     */
    public function getDoNotCheckPagesDoktypes(): array
    {
        $doktypes = explode(',', $this->tsConfig['check.']['doNotCheckPagesDoktypes'] ?? '6,7,199,255');
        $result = [];
        foreach ($doktypes as $doktype) {
            $doktype = (int)$doktype;
            $result[$doktype] = $doktype;
        }
        return $result;
    }

    /**
     * @return array<int,int>
     */
    public function getDoNotTraversePagesDoktypes(): array
    {
        $doktypes = explode(',', $this->tsConfig['check.']['doNotTraversePagesDoktypes'] ?? '6,199,255');
        $result = [];
        foreach ($doktypes as $doktype) {
            $doktype = (int)$doktype;
            $result[$doktype] = $doktype;
        }
        return $result;
    }

    /**
     * @return bool
     */
    public function getDoNotCheckLinksOnWorkspace(): bool
    {
        return $this->tsConfig['check.']['doNotCheckLinksOnWorkspace'] == 1;
    }

    /**
     * @return bool
     */
    public function isCheckHidden(): bool
    {
        return (bool)($this->tsConfig['checkhidden'] ?? false);
    }

    public function isReportHiddenRecords(): bool
    {
        return (bool)($this->tsConfig['reportHiddenRecords'] ?? true);
    }

    /**
     * @param string $linktype
     * @return mixed[]
     */
    public function getLinktypesConfig(string $linktype): array
    {
        return $this->tsConfig['linktypesConfig.'][$linktype . '.'] ?? [];
    }

    /**
     * @return mixed[]
     */
    public function getLinktypesConfigExternalHeaders(): array
    {
        $headers = $this->tsConfig['linktypesConfig.']['external.']['headers.'] ?? [];
        if (($headers['User-Agent'] ?? '') === '') {
            $headers['User-Agent'] = $this->getUserAgent();
        }
        return $headers;
    }

    public function getUserAgent(): string
    {
        return $this->tsConfig['linktypesConfig.']['external.']['headers.']['User-Agent'] ??
            'Mozilla/5.0 (compatible; using Brofix link checker/ ' . (MailUtility::getSystemFromAddress() ?: '');
    }

    public function getLinktypesConfigExternalTimeout(): int
    {
        return (int)($this->tsConfig['linktypesConfig.']['external.']['timeout'] ?? 10);
    }

    public function getLinktypesConfigExternalRedirects(): int
    {
        return (int)($this->tsConfig['linktypesConfig.']['external.']['redirects'] ?? 5);
    }

    public function getExcludeLinkTargetStoragePid(): int
    {
        return (int)($this->tsConfig['excludeLinkTarget.']['storagePid'] ?? 0);
    }

    /**
     * @return array<string>
     */
    public function getExcludeLinkTargetAllowedTypes(): array
    {
        return explode(',', $this->tsConfig['excludeLinkTarget.']['allowed'] ?? 'external');
    }

    public function getLinkTargetCacheExpires(int $flags = 0): int
    {
        if ($flags | AbstractLinktype::CHECK_LINK_FLAG_SYNCHRONOUS) {
            return (int)($this->tsConfig['linkTargetCache.']['expiresHigh'] ?? 604800);
        }
        return (int)($this->tsConfig['linkTargetCache.']['expiresLow'] ?? 518400);
    }

    /**
     * @return int
     *
     * @deprecated Use getCrawlDelaySeconds. Accuracy is only by seconds.
     */
    public function getCrawlDelayMs(): int
    {
        trigger_error(
            'Method "getCrawlDelayMs" is deprecated since brofix version 2.0.0, should use getCrawlDelaySeconds',
            E_USER_DEPRECATED
        );

        if (isset($this->tsConfig['crawlDelay.']['seconds'])) {
            return (int)($this->tsConfig['crawlDelay.']['seconds'] * 1000);
        }
        if (isset($this->tsConfig['crawlDelay.']['ms'])) {
            return (int)($this->tsConfig['crawlDelay.']['ms']);
        }
        return 5000;
    }

    /**
     * @return int
     */
    public function getCrawlDelaySeconds(): int
    {
        return (int)($this->tsConfig['crawlDelay.']['seconds'] ?? 5);
    }

    /**
     * Return crawlDelay nodelay domains.
     * @return array<string>
     * @deprecated Use getCrawlDelayNodelayDomains
     */
    public function getCrawlDelayNodelay(): array
    {
        $noDelayString = $this->getCrawlDelayNodelayString();
        if (str_starts_with($noDelayString, 'regex:')) {
            return [];
        } else {
            return explode(',', $$noDelayString);
        }
    }

    /**
     * Return crawlDelay.nodelay domains.
     * @return array<string>awlDelayNodelayDomains
     */
    public function getCrawlDelayNodelayDomains(): array
    {
        $noDelayString = $this->getCrawlDelayNodelayString();
        if (str_starts_with($noDelayString, 'regex:')) {
            return [];
        } else {
            return explode(',', $noDelayString);
        }
    }

    public function isCrawlDelayNoDelayRegex(): bool
    {
        return str_starts_with($this->getCrawlDelayNodelayString(), 'regex:');
    }

    public function getCrawlDelayNoDelayRegex(): string
    {
        if ($this->isCrawlDelayNoDelayRegex()) {
            return trim(substr($this->getCrawlDelayNodelayString(), strlen('regex:')));
        }
        return '';
    }

    public function getCrawlDelayNodelayString(): string
    {
        return trim($this->tsConfig['crawlDelay.']['nodelay'] ?? '');
    }

    public function getDocsUrl(): string
    {
        return $this->tsConfig['report.']['docsurl'] ?? '';
    }

    public function getRecheckButton(): int
    {
        return (int)($this->tsConfig['report.']['recheckButton'] ?? -1);
    }

    public function getMailSendOnCheckLinks(): bool
    {
        return (bool)($this->tsConfig['mail.']['sendOnCheckLinks'] ?? true);
    }

    public function setMailSendOnCheckLinks(int $value): void
    {
        $this->tsConfig['mail.']['sendOnCheckLinks'] = $value;
    }

    /**
     * Get check depth (number of page recursion levels)
     *
     * @return int
     */
    public function getDepth(): int
    {
        return (int)($this->tsConfig['depth'] ?? 999);
    }

    public function setDepth(int $depth): void
    {
        $this->tsConfig['depth'] = $depth;
    }

    public function setMailRecipientsAsString(string $recipients): void
    {
        $this->tsConfig['mail.']['recipients'] = $recipients;
    }

    /**
     * @return Address[]
     */
    public function getMailRecipients(): array
    {
        $result = [];
        $recipients = trim($this->tsConfig['mail.']['recipients'] ?? '');
        if ($recipients === '') {
            if ($GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] ?? '') {
                $fromAddress = $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] ?? '';
                $fromName = $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] ?? '';
                $result[] = new Address($fromAddress, $fromName);
            }
        } else {
            foreach (explode(',', $recipients) as $recipient) {
                $result[] = Address::create($recipient);
            }
        }
        return $result;
    }

    public function getMailTemplate(): string
    {
        return $this->tsConfig['mail.']['template'] ?? 'CheckLinksResults';
    }

    /**
     * From email address. Returns only the email address.
     *
     * @return string
     */
    public function getMailFromEmail(): string
    {
        if (($this->tsConfig['mail.']['fromemail'] ?? '') !== '') {
            return $this->tsConfig['mail.']['fromemail'];
        }
        if (($this->tsConfig['mail.']['from'] ?? '') !== '') {
            // this is deprecated
            return explode(' ', $this->tsConfig['mail.']['from'])[0];
        }
        return MailUtility::getSystemFromAddress() ?: '';
    }

    /**
     * From Name
     *
     * @return string
     */
    public function getMailFromName(): string
    {
        if (($this->tsConfig['mail.']['fromname'] ?? '') !== '') {
            return $this->tsConfig['mail.']['fromname'];
        }
        return MailUtility::getSystemFromName() ?: '';
    }

    public function getMailReplyToEmail(): string
    {
        if (($this->tsConfig['mail.']['replytoemail'] ?? '') !== '') {
            return $this->tsConfig['mail.']['replytoemail'];
        }
        if (($this->tsConfig['mail.']['replyto'] ?? '') !== '') {
            // this is deprecated
            return explode(' ', $this->tsConfig['mail.']['replyto'])[0];
        }
        // @todo use MailUtility::getSystemReplyTo()
        // getSystemReplyto returns an array of email addresses, can be in format
        // 'name <email@from>'
        return '';
    }

    /**
     * From Name
     *
     * @return string
     */
    public function getMailReplyToName(): string
    {
        if (($this->tsConfig['mail.']['replytoname'] ?? '') !== '') {
            return $this->tsConfig['mail.']['replytoname'];
        }
        // @todo use MailUtility::getSystemReplyTo()
        // getSystemReplyto returns an array of email addresses, can be in format
        // 'name <email@from>'
        return '';
    }

    public function getMailSubject(): string
    {
        return $this->tsConfig['mail.']['subject'] ?? '';
    }

    public function getMailLanguage(): string
    {
        return $this->tsConfig['mail.']['language'] ?? 'en';
    }

    /**
     * @return int
     */
    public function getTraverseMaxNumberOfPagesInBackend(): int
    {
        return $this->traverseMaxNumberOfPagesInBackend;
    }

    /**
     * @param int $traverseMaxNumberOfPagesInBackend
     */
    public function setTraverseMaxNumberOfPagesInBackend(int $traverseMaxNumberOfPagesInBackend): void
    {
        $this->traverseMaxNumberOfPagesInBackend = $traverseMaxNumberOfPagesInBackend;
    }

    /**
     * @return string[]
     */
    public function getExcludeSoftrefs(): array
    {
        return $this->excludeSoftrefs;
    }

    /**
     * @return string[]
     */
    public function getExcludeSoftrefsInFields(): array
    {
        return $this->excludeSoftrefsInFields;
    }

    public function getLinktypeObject(string $linktype): ?LinktypeInterface
    {
        return $this->hookObjectsArr[$linktype] ?? null;
    }

    /**
     * @return array<string,LinktypeInterface>
     */
    public function getLinktypeObjects(): array
    {
        return $this->hookObjectsArr;
    }

    /**
     * Get custom configuration (user defined)
     * @return mixed[]
     */
    public function getCustom(): array
    {
        return $this->tsConfig['custom.'] ?? [];
    }
}
