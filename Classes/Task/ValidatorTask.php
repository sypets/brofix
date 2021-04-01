<?php

namespace Sypets\Brofix\Task;

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

use Sypets\Brofix\Configuration\Configuration;
use Sypets\Brofix\LinkAnalyzer;
use Sypets\Brofix\Repository\BrokenLinkRepository;
use Sypets\Brofix\Repository\PagesRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Service\MarkerBasedTemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * This class provides Scheduler plugin implementation
 * @internal This class is a specific Scheduler task implementation and is not part of the TYPO3's Core API.
 */
class ValidatorTask extends AbstractTask
{
    /**
     * @var int
     */
    protected $sleepTime;

    /**
     * @var int
     */
    protected $sleepAfterFinish;

    /**
     * @var int
     */
    protected $countInARun;

    /**
     * Broken link statistics
     *
     * @var array
     */
    protected $pageSectionStatistics = [];

    /**
     * @var int
     */
    protected $oldTotalBrokenLinks = 0;

    /**
     * @var int
     */
    protected $totalBrokenLinks = 0;

    /**
     * Mail template fetched from the given template file
     *
     * @var string
     */
    protected $templateMail;

    /**
     * specific TSconfig for this task.
     *
     * @var array
     */
    protected $overrideTsConfigString = [];

    /**
     * Template to be used for the email
     *
     * @var string
     */
    protected $emailTemplateFile;

    /**
     * Level of pages the task should check
     *
     * @var int
     */
    protected $depth;

    /**
     * UID of the start page for this task
     *
     * @var int
     */
    protected $page;

    /**
     * Email address to which an email report is sent
     *
     * @var string
     */
    protected $email;

    /**
     * Only send an email, if broken links were found
     *
     * @var int
     *
     * @todo change to bool and use strict types
     */
    protected $emailOnBrokenLinkOnly = 0;

    /**
     * @var MarkerBasedTemplateService
     */
    protected $templateService;

    /**
     * Default language file of the extension brofix
     *
     * @var string
     */
    protected $languageFile = 'LLL:EXT:brofix/Resources/Private/Language/locallang.xlf';

    /**
     * @var BrokenLinkRepository
     */
    protected $brokenLinkRepository;

    /**
     * @var PagesRepository
     */
    protected $pagesRepository;

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * Get the value of the protected property email
     *
     * @return string Email address to which an email report is sent
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set the value of the private property email.
     *
     * @param string $email Email address to which an email report is sent
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * Get the value of the protected property emailOnBrokenLinkOnly
     *
     * @return bool Whether to send an email, if broken links were found
     */
    public function getEmailOnBrokenLinkOnly()
    {
        return $this->emailOnBrokenLinkOnly;
    }

    /**
     * Set the value of the private property emailOnBrokenLinkOnly
     *
     * @param bool $emailOnBrokenLinkOnly Only send an email, if broken links were found
     */
    public function setEmailOnBrokenLinkOnly($emailOnBrokenLinkOnly)
    {
        $this->emailOnBrokenLinkOnly = (bool)$emailOnBrokenLinkOnly;
    }

    /**
     * Get the value of the protected property page
     *
     * @return int UID of the start page for this task
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * Set the value of the private property page
     *
     * @param int $page UID of the start page for this task.
     */
    public function setPage($page)
    {
        $this->page = $page;
    }

    /**
     * Get the value of the protected property depth
     *
     * @return int Level of pages the task should check
     */
    public function getDepth()
    {
        return $this->depth;
    }

    /**
     * Set the value of the private property depth
     *
     * @param int $depth Level of pages the task should check
     */
    public function setDepth($depth)
    {
        $this->depth = $depth;
    }

    /**
     * Get the value of the protected property emailTemplateFile
     *
     * @return string Template to be used for the email
     */
    public function getEmailTemplateFile()
    {
        return $this->emailTemplateFile;
    }

    /**
     * Set the value of the private property emailTemplateFile
     *
     * @param string $emailTemplateFile Template to be used for the email
     */
    public function setEmailTemplateFile($emailTemplateFile)
    {
        $this->emailTemplateFile = $emailTemplateFile;
    }

    /**
     * Get the value of the protected property configuration
     *
     * @return string specific TSconfig for this task
     *
     * @deprecated Use Configuration class
     */
    public function getOverrideTsConfigString(): string
    {
        return $this->overrideTsConfigString;
    }

    /**
     * Set the value of the private property configuration
     *
     * @param array $overrideTsConfigString specific TSconfig for this task
     *
     * @deprecated Use Configuration class
     */
    public function setOverrideTsConfigString(string $overrideTsConfigString): void
    {
        $this->overrideTsConfigString = $overrideTsConfigString;
    }

    /**
     * @todo Should go in constructor, but constructor is not called.
     */
    protected function init()
    {
        $this->configuration = GeneralUtility::makeInstance(Configuration::class);
        $this->brokenLinkRepository = GeneralUtility::makeInstance(BrokenLinkRepository::class);
        $this->pagesRepository = GeneralUtility::makeInstance(PagesRepository::class);
        $this->oldTotalBrokenLinks = 0;
        $this->totalBrokenLinks = 0;
    }

    /**
     * Function execute from the Scheduler
     *
     * @return bool TRUE on successful execution, FALSE on error
     * @throws \InvalidArgumentException if the email template file can not be read
     */
    public function execute()
    {
        $this->init();

        $this->setCliArguments();
        $this->templateService = GeneralUtility::makeInstance(MarkerBasedTemplateService::class);
        $successfullyExecuted = true;
        if (!file_exists($file = GeneralUtility::getFileAbsFileName($this->emailTemplateFile))
            && !empty($this->email)
        ) {
            if ($this->emailTemplateFile === 'EXT:brofix/res/mailtemplate.html') {
                // Update the default email template file path
                $this->emailTemplateFile = 'EXT:brofix/Resources/Private/Templates/mailtemplate.html';
                $this->save();
            } else {
                $lang = $this->getLanguageService();
                throw new \InvalidArgumentException(
                    $lang->sL($this->languageFile . ':tasks.error.invalidEmailTemplateFile'),
                    '1295476972'
                );
            }
        }
        $htmlFile = file_get_contents($file);
        $this->templateMail = $this->templateService->getSubpart($htmlFile, '###REPORT_TEMPLATE###');
        // The array to put the content into
        $pageSections = '';
        $this->isDifferentToLastRun = false;
        $pageList = GeneralUtility::trimExplode(',', $this->page, true);
        $this->configuration->loadPageTsConfig($this->page);
        $this->configuration->overrideTsConfigByString($this->overrideTsConfigString);
        if (is_array($pageList)) {
            foreach ($pageList as $page) {
                $pageSections .= $this->checkPageLinks($page);
            }
        }

        if (($this->totalBrokenLinks > 0 || !$this->emailOnBrokenLinkOnly)
            && !empty($this->email)
        ) {
            $successfullyExecuted = $this->reportEmail($pageSections);
        }
        return $successfullyExecuted;
    }

    /**
     * Validate all links for a page (and possibly its subpages) based on the task configuration.
     *
     * If there are several pages to check, this function will be called several times.
     *
     * @param int $page Uid of the page to parse
     * @return string $pageSections Content of page section
     * @throws \InvalidArgumentException
     */
    protected function checkPageLinks($page)
    {
        $page = (int)$page;
        $pageSections = '';
        $pageIds = '';
        $oldLinkCounts = [];
        $this->configuration->loadPageTsConfig($page);
        $searchFields = $this->configuration->getSearchFields();
        $linkTypes = $this->configuration->getLinkTypes();
        /** @var LinkAnalyzer $processor */
        $processor = GeneralUtility::makeInstance(LinkAnalyzer::class);
        if ($page === 0) {
            $rootLineHidden = false;
        } else {
            $pageRow = BackendUtility::getRecord('pages', $page, '*', '', false);
            if ($pageRow === null) {
                throw new \InvalidArgumentException(
                    sprintf($this->getLanguageService()->sL($this->languageFile . ':tasks.error.invalidPageUid'), $page),
                    1502800555
                );
            }
            $rootLineHidden = $this->pagesRepository->getRootLineIsHidden($pageRow);
        }

        $checkHidden = $this->configuration->isCheckHidden();
        if (!$rootLineHidden || $checkHidden) {
            $pageIds = $this->pagesRepository->getPageList(
                $page,
                $this->depth,
                '1=1',
                $checkHidden
            );
        }
        if (!empty($pageIds)) {
            $processor->init($searchFields, $pageIds, $this->configuration->getTsConfig());
            if (!empty($this->email)) {
                $oldLinkCounts = $this->brokenLinkRepository->getLinkCounts($pageIds);
            }
            $processor->generateBrokenLinkRecords($linkTypes, $checkHidden);
            if (!empty($this->email)) {
                $linkCounts = $this->brokenLinkRepository->getLinkCounts($pageIds, $this->configuration->getLinkTypes());

                $accumulatedValues = $processor->getStatistics();
                $this->updateStatistics($accumulatedValues, $linkCounts, $oldLinkCounts);
                $pageSections = $this->buildMailPageSection($page, $pageIds, $linkCounts, $oldLinkCounts);
            }
        }

        return $pageSections;
    }

    /**
     * @param $accumulatedValues this has been counted up while checking (the values may not be entirely accurrate
     *   as they may have changed while checking
     * @param $currentCount current (exact) count from the database
     * @param $previousCount (exact) count from the database before starting the check
     */
    protected function updateStatistics(array $accumulatedValues, array $currentCount, array $previousCount)
    {
        // total statistics
        $this->totalBrokenLinks += (int)($currentCount['brokenlinkCount'] ?? 0);
        $this->oldTotalBrokenLinks += (int)($previousCount['brokenlinkCount'] ?? 0);

        // page section statistics
        foreach ($accumulatedValues as $key => $value) {
            $this->pageSectionStatistics[$key] = (int)$value;
        }
        // accumulated broken link count may be inaccurate, use actual value
        $this->pageSectionStatistics['count_broken_links'] = (int)($currentCount['brokenlinkCount'] ?? 0);
        if (($this->pageSectionStatistics['isExcludedUrl'] ?? 0)
            && ($this->pageSectionStatistics['count_links'] ?? 0)
        ) {
            $this->pageSectionStatistics['percent_excluded_links'] = (float)($this->pageSectionStatistics['isExcludedUrl'] / $this->pageSectionStatistics['count_links'] * 100);
        } else {
            $this->pageSectionStatistics['percent_excluded_links'] =  0;
        }
        if (($this->pageSectionStatistics['count_links_checked'] ?? 0)
            && ($this->pageSectionStatistics['count_broken_links'] ?? 0)
        ) {
            // omit the excluded links from this count to get the actual percentage of broken links in checked links
            $this->pageSectionStatistics['percent_broken_links'] = (float)($this->pageSectionStatistics['count_broken_links'] / $this->pageSectionStatistics['count_links_checked'] * 100);
        } else {
            $this->pageSectionStatistics['percent_broken_links'] = 0;
        }
    }

    /**
     * Build and send email report about broken links
     *
     * @param string $pageSections Content of page section
     * @return bool TRUE if mail was sent, FALSE if or not
     * @throws \Exception if required modTsConfig settings are missing
     */
    protected function reportEmail($pageSections)
    {
        /** @var array $validEmailList */
        $validEmailList = [];
        /** @var bool $sendEmail */
        $sendEmail = true;

        $lang = $this->getLanguageService();
        $content = $this->templateService->substituteSubpart($this->templateMail, '###PAGE_SECTION###', $pageSections);

        /** @var MailMessage $mail */
        $mail = GeneralUtility::makeInstance(MailMessage::class);

        $fromemail = $this->configuration->getMailFromEmail();
        if (GeneralUtility::validEmail($fromemail)) {
            $mail->setFrom([$fromemail => $this->configuration->getMailFromName()]);
        } else {
            throw new \Exception(
                $lang->sL($this->languageFile . ':tasks.error.invalidFromEmail'),
                '1295476760'
            );
        }
        $replyToEmail = $this->configuration->getMailreplytoEmail();
        if (GeneralUtility::validEmail($replyToEmail)) {
            $mail->setReplyTo([$replyToEmail => $this->configuration->getMailreplytoName()]);
        }

        $subject = $this->configuration->getMailSubject();
        if ($subject === '') {
            // default values
            $subject = 'Broken link report';
        }
        $mail->setSubject($subject . ': ' . $this->totalBrokenLinks);

        // set email recipients
        if (!empty($this->email)) {
            // Check if old input field value is still there and save the value a
            if (strpos($this->email, ',') !== false) {
                $emailList = GeneralUtility::trimExplode(',', $this->email, true);
                $this->email = implode(LF, $emailList);
                $this->save();
            } else {
                $emailList = GeneralUtility::trimExplode(LF, $this->email, true);
            }

            foreach ($emailList as $emailAdd) {
                if (!GeneralUtility::validEmail($emailAdd)) {
                    throw new \Exception(
                        $lang->sL($this->languageFile . ':tasks.error.invalidToEmail'),
                        '1295476821'
                    );
                }
                $validEmailList[] = $emailAdd;
            }
        }
        if (is_array($validEmailList) && !empty($validEmailList)) {
            $mail->setTo($validEmailList);
        } else {
            $sendEmail = false;
        }
        if ($sendEmail) {
            $majorVersion = (int)(GeneralUtility::intExplode('.', VersionNumberUtility::getCurrentTypo3Version())[0]);
            if ($majorVersion >= 10) {
                $mail->html($content);
            } else {
                $mail->setBody($content, 'text/html');
            }
            $mail->send();
        }
        return $sendEmail;
    }

    /**
     * Build the mail content for a start page (page and subpages).
     *
     * If there are several start pages (with their subpages to check,
     * this function will be called several times.
     *
     * Each one creates a PAGE_SECTION part of the email.
     *
     * @param int $curPage Id of the current page
     * @param string $pageList List of pages id
     * @param array $markerArray Array of markers
     * @param array $oldBrokenLink Marker array with the number of link found
     * @return string Content of the mail
     */
    protected function buildMailPageSection($curPage, $pageList, array $markerArray, array $oldBrokenLink)
    {
        $pageSectionHtml = $this->templateService->getSubpart($this->templateMail, '###PAGE_SECTION###');
        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['brofix']['buildMailMarkers'] ?? [] as $userFunc) {
            $params = [
                'curPage' => $curPage,
                'pageList' => $pageList,
                'markerArray' => $markerArray,
                'oldBrokenLink' => $oldBrokenLink,
                'pObj' => &$this
            ];
            $newMarkers = GeneralUtility::callUserFunction($userFunc, $params, $this);
            if (is_array($newMarkers)) {
                $markerArray = $newMarkers + $markerArray;
            }
            unset($params);
        }
        foreach ($markerArray as $markerKey => $markerValue) {
            if (empty($oldBrokenLink[$markerKey])) {
                $oldBrokenLink[$markerKey] = 0;
            }
            if ($markerValue != $oldBrokenLink[$markerKey]) {
                $this->isDifferentToLastRun = true;
            }
            $markerArray[$markerKey . '_old'] = $oldBrokenLink[$markerKey];
        }
        $markerArray['title'] = BackendUtility::getRecordTitle(
            'pages',
            BackendUtility::getRecord('pages', $curPage)
        );
        $markerArray['depth'] = $this->getDepth();
        $markerArray['COUNT_PAGES'] = $this->pageSectionStatistics['count_pages'];
        $markerArray['COUNT_LINKS'] = $this->pageSectionStatistics['count_links'];
        $markerArray['COUNT_EXCLUDE_LINKS'] = $this->pageSectionStatistics['isExcludedUrl'];
        $markerArray['COUNT_BROKEN_LINKS'] = $this->pageSectionStatistics['count_broken_links'];
        $markerArray['COUNT_LINKS_CHECKED'] = $this->pageSectionStatistics['count_links_checked'];
        if ($this->pageSectionStatistics['percent_excluded_links'] ?? 0) {
            $markerArray['PERCENT_EXCLUDED_LINKS'] = number_format(
                $this->pageSectionStatistics['percent_excluded_links'],
                2
            );
        } else {
            $markerArray['PERCENT_EXCLUDED_LINKS'] = 0;
        }
        if ($this->pageSectionStatistics['percent_broken_links'] ?? 0) {
            $markerArray['PERCENT_BROKEN_LINKS'] = number_format(
                $this->pageSectionStatistics['percent_broken_links'],
                2
            );
        } else {
            $markerArray['PERCENT_BROKEN_LINKS'] = 0;
        }

        $content = $this->templateService->substituteMarkerArray(
            $pageSectionHtml,
            $markerArray,
            '###|###',
            true,
            true
        );

        return $content;
    }

    /**
     * Returns the most important properties of the link validator task as a
     * comma separated string that will be displayed in the scheduler module.
     *
     * @return string
     */
    public function getAdditionalInformation()
    {
        $additionalInformation = [];

        $page = (int)$this->getPage();
        $pageLabel = $page;
        if ($page !== 0) {
            $pageData = BackendUtility::getRecord('pages', $page);
            if (!empty($pageData)) {
                $pageTitle = BackendUtility::getRecordTitle('pages', $pageData);
                $pageLabel = $pageTitle . ' (' . $page . ')';
            }
        }
        $lang = $this->getLanguageService();
        $depth = (int)$this->getDepth();
        $additionalInformation[] = $lang->sL($this->languageFile . ':tasks.validate.page') . ': ' . $pageLabel;
        $additionalInformation[] = $lang->sL($this->languageFile . ':tasks.validate.depth') . ': '
            . $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_' . ($depth === 999 ? 'infi' : $depth));
        $additionalInformation[] = $lang->sL($this->languageFile . ':tasks.validate.email') . ': '
            . $this->getEmail();

        return implode(', ', $additionalInformation);
    }

    /**
     * Simulate cli call with setting the required options to the $_SERVER['argv']
     */
    protected function setCliArguments()
    {
        $_SERVER['argv'] = [
            $_SERVER['argv'][0],
            'tx_link_scheduler_link',
            '0',
            '-ss',
            '--sleepTime',
            $this->sleepTime,
            '--sleepAfterFinish',
            $this->sleepAfterFinish,
            '--countInARun',
            $this->countInARun
        ];
    }
}
