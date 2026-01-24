<?php

declare(strict_types=1);
namespace Sypets\Brofix\Command;

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

use pq\Exception\InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Sypets\Brofix\CheckLinks\CheckLinksStatistics;
use Sypets\Brofix\Configuration\Configuration;
use Sypets\Brofix\LinkAnalyzer;
use Sypets\Brofix\Mail\GenerateCheckResultFluidMail;
use Sypets\Brofix\Repository\BrokenLinkRepository;
use Sypets\Brofix\Repository\PagesRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @internal This class is for internal use inside this extension only.
 */
class CheckLinksCommand extends Command
{
    /**
     * @var SymfonyStyle
     */
    protected $io;

    protected string $backendUri = 'https://localhost/typo3';

    /**
     * @var bool
     */
    protected $dryRun;

    /**
     * @var int
     */
    protected $depth;

    /**
     * @var string
     */
    protected $sendTo;

    /**
     * @var string "never" | "always" | "any" | "new" | "auto"
     *
     * older values, deprecated:
     *   -1 => "auto" : use default (from configuration),
     *   1 => "always": always send email
     *   0 => "never" : do not send
     */
    protected $sendEmail;

    /**
     * @var CheckLinksStatistics[]
     */
    protected $statistics;

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var BrokenLinkRepository
     */
    protected $brokenLinkRepository;

    /**
     * @var PagesRepository
     */
    protected $pagesRepository;

    /**
     * @var int[]
     */
    protected $excludedPages = [];

    protected GenerateCheckResultFluidMail $generateCheckResultMail;

    public function __construct(
        GenerateCheckResultFluidMail $generateCheckResultMail,
        ExtensionConfiguration $extensionConfiguration,
        BrokenLinkRepository $brokenLinkRepository,
        PagesRepository $pagesRepository
    ) {
        parent::__construct();

        $extConfArray  = $extensionConfiguration->get('brofix') ?: [];
        $this->configuration = GeneralUtility::makeInstance(Configuration::class, $extConfArray);
        $this->generateCheckResultMail = $generateCheckResultMail;
        $this->brokenLinkRepository = $brokenLinkRepository;
        $this->pagesRepository = $pagesRepository;

        $this->statistics = [];
    }

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure(): void
    {
        $this->setDescription('Check links')
            ->addOption(
                'backend-uri',
                '',
                InputOption::VALUE_REQUIRED,
                'Backend URI (e.g. https://mysite/typo3), is used to generate a request.',
                'https://localhost/typo3'
            )
            ->addOption(
                'start-pages',
                'p',
                InputOption::VALUE_REQUIRED  | InputOption::VALUE_IS_ARRAY,
                'Page id(s) to start with.'
                . ' In cli: Use several -p options if more than one, e.g -p1 -p2.'
                . ' In scheduler: Separate several with "," if several used.'
                . 'If none are given, the configured site start pages are used.'
            )
            ->addOption(
                'depth',
                'd',
                InputOption::VALUE_REQUIRED,
                'Page recursion depth (how many levels of pages to check, starting with the start pages).' .
                'Default is 999, where 999 means infinite depth. If none is given, TSconfig mod.brofix.depth is used'
            )
            ->addOption(
                'to',
                't',
                InputOption::VALUE_REQUIRED,
                'Email address of recipient. If none is given, TSconfig mod.brofix.mail.recipients is used.'
            )
            ->addOption(
                'dry-run',
                '',
                InputOption::VALUE_NONE,
                'Do not execute link checking and do not send email, just show what would get checked.'
            )
            ->addOption(
                'send-email',
                'e',
                InputOption::VALUE_REQUIRED,
                'Send email. Possible values: ' . implode(' | ', Configuration::SEND_EMAIL_AVAILABLE_VALUES)
                    . ' (default:' . Configuration::SEND_EMAIL_DEFAULT_VALUE . ')',
                Configuration::SEND_EMAIL_AUTO,
                Configuration::SEND_EMAIL_AVAILABLE_VALUES
            )
            ->addOption(
                'exclude-uid',
                'x',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Page id (and subpages), which will not be checked.'
                    . ' In cli: Use several -x options if more than one, e.g -x1 -x2.'
                    . ' In scheduler: Separate several with "," if several used.'
            )
        ;
    }

    /**
     * Executes the command for showing sys_log entries
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (Environment::isCli()) {
            // link checking (in particular FormEngine processing with FormDataCompiler) requires an initialized session
            // due to AbstractUserAuthentication::getModuleData called from Clipboard::initializeClipboard called from TcaGroup::addData
            // see https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/DataHandler/UsingDataHandler/Index.html#dataHandler-cli-command
            \TYPO3\CMS\Core\Core\Bootstrap::initializeBackendAuthentication();
            $GLOBALS['BE_USER']->initializeUserSessionManager();
        }

        $this->io = new SymfonyStyle($input, $output);

        $this->backendUri = $input->getOption('backend-uri');

        $this->dryRun = (bool)($input->getOption('dry-run') ?: false);
        if ($this->dryRun) {
            $this->io->writeln('Dry run is activated, do not check and do not send email');
        }

        $this->sendEmail = ($input->getOption('send-email') ?? Configuration::SEND_EMAIL_AUTO);
        // map old values
        switch ($this->sendEmail) {
            case '0':
                $this->sendEmail = Configuration::SEND_EMAIL_NEVER;
                break;
            case '1':
                $this->sendEmail = Configuration::SEND_EMAIL_ALWAYS;
                break;
            case '-1':
                $this->sendEmail = Configuration::SEND_EMAIL_AUTO;
                break;
        }

        if ($this->sendEmail === Configuration::SEND_EMAIL_NEVER) {
            $this->io->writeln('Do not send email (never).');
        } elseif ($this->sendEmail === Configuration::SEND_EMAIL_ALWAYS) {
            $this->io->writeln('Always send email (always).');
        } if ($this->sendEmail === Configuration::SEND_EMAIL_ANY) {
            $this->io->writeln('Send email only if broken links found (any).');
        } if ($this->sendEmail === Configuration::SEND_EMAIL_NEW) {
            $this->io->writeln('Send email only if new broken links found (new).');
        } if ($this->sendEmail === Configuration::SEND_EMAIL_AUTO) {
            $this->io->writeln('Send email based on TSconfig configuration (auto).');
        }

        // exluded pages uid
        $this->excludedPages = [];
        foreach ($input->getOption('exclude-uid') ?: [] as $value) {
            $id = (int)$value;
            $this->excludedPages[$id] = $id;
        }

        /** @var string|array<int,string>|array<int,int> $startPages */
        $startPages = ($input->getOption('start-pages') ?? []);
        if ($startPages) {
            if (is_string($startPages)) {
                $startPages = explode(',', $startPages);
            }
            // should be an array now
            if (!is_array($startPages)) {
                throw new InvalidArgumentException('--start-pages invalid values passed for this option', 7986018416);
            }
            $startPagesResults = [];
            foreach ($startPages as $page) {
                // option should not contain comma, but someone might still be separating an option with comma (",")
                // instead of passing several options because that is what used to be documented
                if (str_contains($page, ',')) {
                    foreach (explode(',', $page) as $pageExplodedItem) {
                        $startPagesResults[(int)($pageExplodedItem)] = (int)($pageExplodedItem);
                    }
                } else {
                    $startPagesResults[(int)$page] = (int)$page;
                }
            }
            $startPages = $startPagesResults;
        } else {
            $startPages = [];
        }
        if ($startPages === []) {
            /** @var SiteFinder $siteFinder */
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            /** @var Site[] $sites */
            $sites = $siteFinder->getAllSites();
            foreach ($sites as $site) {
                $startPages[] = $site->getRootPageId();
            }
            $this->io->writeln('Use page ids (from site configuration): ' . implode(',', $startPages));
        }
        if ($startPages === []) {
            // @extensionScannerIgnoreLine problem with ->error()
            $this->io->error('No pages to check ... abort');
            // @todo use constant Command::FAILURE (not available in earlier Symfony versions)
            return 1;
        }

        /**
         * @var string|null $depthOption
         */
        $depthOption = $input->getOption('depth');
        if ($depthOption !== null) {
            $this->depth = (int)($depthOption);
        } else {
            $this->depth = -1;
        }

        $this->sendTo = $input->getOption('to') ?: '';

        foreach ($startPages as $pageId) {
            $this->io->title('Start checking page ' . $pageId);

            $pageId = (int)$pageId;
            if ($pageId <= 0) {
                $this->io->warning('Page id ' . $pageId . ' not allowed ... skipping');
                continue;
            }

            // set configuration via command line arguments
            $this->configuration->loadPageTsConfig($pageId);
            if ($this->depth !== -1) {
                $this->configuration->setDepth($this->depth);
            }
            $depth = $this->configuration->getDepth();

            if ($this->sendTo !== '') {
                $this->configuration->setMailRecipientsAsString($this->sendTo);
            }
            if ($this->sendEmail !== Configuration::SEND_EMAIL_AUTO) {
                $this->configuration->setMailSendOnCheckLinks($this->sendEmail);
            }

            // show configuration
            if ($this->configuration->getMailSendOnCheckLinks() !== Configuration::SEND_EMAIL_NEVER) {
                $this->io->writeln('Configuration: Send mail: ' . $this->configuration->getMailSendOnCheckLinks());
                $recipients = $this->configuration->getMailRecipients();
                $to = '';
                foreach ($recipients as $recipient) {
                    if ($to !== '') {
                        $to .= ',';
                    }
                    $to .= $recipient->toString();
                }
                $this->io->writeln('Configuration: Email recipients: ' . $to);
                $this->io->writeln('Configuration: Email sender (email address): '
                    . $this->configuration->getMailFromEmail());
                $this->io->writeln('Configuration: Email sender (name): '
                    . $this->configuration->getMailFromName());
                if ($this->configuration->getMailReplyToEmail()) {
                    $this->io->writeln('Configuration: Email replyTo (email address): '
                        . $this->configuration->getMailReplyToEmail());
                    if ($this->configuration->getMailReplyToName()) {
                        $this->io->writeln('Configuration: Email replyTo (name): '
                            . $this->configuration->getMailReplyToName());
                    }
                }
                $this->io->writeln('Configuration: Email template: '
                    . $this->configuration->getMailTemplate());
            } else {
                $this->io->writeln('Configuration: Send mail: false');
            }

            // check links
            $result = $this->checkPageLinks($pageId);

            if ($this->dryRun) {
                $this->io->writeln('Dry run is enabled: Do not check and do not send email.');
                continue;
            }

            if (!$result
                || !isset($this->statistics[$pageId])
            ) {
                $this->io->warning(sprintf('No result for checking %d ... abort', $pageId));
                continue;
            }

            $stats = $this->statistics[$pageId];
            $this->io->writeln(sprintf(
                'Result for page "%s" (and %s depth): number of broken links=%d',
                $stats->getPageTitle(),
                (string)($depth === 999 ? 'infinite' : $depth),
                $stats->getCountBrokenLinks()
            ));
            switch ($this->configuration->getMailSendOnCheckLinks()) {
                case Configuration::SEND_EMAIL_NEVER:
                    $this->io->writeln('Do not send mail, because sending was deactivated.');
                    break;
                case Configuration::SEND_EMAIL_ALWAYS:
                    $this->io->writeln('Send email');
                    $this->generateCheckResultMail->generateMail($this->configuration, $this->statistics[$pageId], $pageId);
                    break;
                case Configuration::SEND_EMAIL_ANY:
                    if ($stats->getCountBrokenLinks()) {
                        $this->io->writeln('Broken links found, send email');
                        $this->generateCheckResultMail->generateMail(
                            $this->configuration,
                            $this->statistics[$pageId],
                            $pageId
                        );
                    } else {
                        $this->io->writeln('No broken links found, do not send email');
                    }
                    break;
                case Configuration::SEND_EMAIL_NEW:
                    // send email only if new broken links
                    $newBrokenLinks = $stats->getCountNewBrokenLinks();
                    if ($newBrokenLinks) {
                        $this->io->writeln($newBrokenLinks . ' new broken links found, send email');
                        $this->generateCheckResultMail->generateMail(
                            $this->configuration,
                            $this->statistics[$pageId],
                            $pageId
                        );
                    } else {
                        $this->io->writeln('No new broken links found, do not send email');
                    }
            }
        }

        // @todo use constant Command::SUCCESS (not available in earlier Symfony versions)
        return 0;
    }

    public function getStatistics(int $pageUid): CheckLinksStatistics
    {
        return $this->statistics[$pageUid];
    }

    /**
     * Validate all links for a page (and possibly its subpages) based on the task configuration.
     *
     * If there are several pages to check, this function will be called several times.
     *
     * @param int $pageUid Startpage of the pages to check
     * @return bool
     * @throws \InvalidArgumentException
     */
    protected function checkPageLinks(int $pageUid): bool
    {
        $depth = $this->configuration->getDepth();
        $linkTypes = $this->configuration->getLinkTypes();

        $pageRow = BackendUtility::getRecord('pages', $pageUid, '*', '', false);
        if ($pageRow === null) {
            throw new \InvalidArgumentException(
                sprintf('Invalid page uid passed as argument: %d', $pageUid),
                1582332944
            );
        }

        $this->io->writeln(
            sprintf(
                'Checking start page "%s" [%d], depth: %s',
                $pageRow['title'],
                $pageUid,
                $depth === 999 ? 'infinite' : $depth
            )
        );

        if ($this->dryRun) {
            return true;
        }

        $rootLineHidden = $this->pagesRepository->getRootLineIsHidden($pageRow);

        $checkHidden = $this->configuration->isCheckHidden();
        $pageIds = [];
        if (!$rootLineHidden || $checkHidden) {
            $this->pagesRepository->getPageList(
                $pageIds,
                [$pageUid],
                $depth,
                '1=1',
                $checkHidden,
                $this->excludedPages,
                $this->configuration->getDoNotCheckPagesDoktypes(),
                $this->configuration->getDoNotTraversePagesDoktypes(),
                0,
                false
            );
        } else {
            $this->io->warning(
                sprintf(
                    'Will not check hidden pages or children of hidden pages, rootline is hidden: %s [%d]',
                    $pageRow['title'] ?? '',
                    $pageUid
                )
            );
            return false;
        }
        if (!empty($pageIds)) {
            /** @var LinkAnalyzer $linkAnalyzer */
            $linkAnalyzer = GeneralUtility::makeInstance(LinkAnalyzer::class);
            $linkAnalyzer->init($pageIds, $this->configuration);
            if (isset($GLOBALS['TYPO3_REQUEST'])) {
                $request = $GLOBALS['TYPO3_REQUEST'];
            } else {
                $request = CommandUtility::createFakeWebRequest($this->backendUri);
                // set global variable here because it might be used by FormEngine processing (e.g. FormDataProvider, hooks etc.)
                $GLOBALS['TYPO3_REQUEST'] = $request;
            }
            $linkAnalyzer->generateBrokenLinkRecords(
                $request,
                $linkTypes,
                $checkHidden
            );

            $stats = $linkAnalyzer->getStatistics();
            $stats->setPageTitle($pageRow['title']);
            $this->statistics[$pageUid] = $stats;
        }

        return true;
    }
}
