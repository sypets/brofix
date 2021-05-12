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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Sypets\Brofix\CheckLinks\CheckLinksStatistics;
use Sypets\Brofix\Configuration\Configuration;
use Sypets\Brofix\LinkAnalyzer;
use Sypets\Brofix\Mail\GenerateCheckResultFluidMail;
use Sypets\Brofix\Mail\GenerateCheckResultMailInterface;
use Sypets\Brofix\Mail\GenerateCheckResultPlainMail;
use Sypets\Brofix\Repository\BrokenLinkRepository;
use Sypets\Brofix\Repository\PagesRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;
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

    /**
     * @var bool
     */
    protected $verbose;

    /**
     * @var CheckLinksStatistics[]
     */
    protected $statistics;

    /**
     * @var object|Configuration
     */
    protected $configuration;

    /**
     * @var object|BrokenLinkRepository
     */
    protected $brokenLinkRepository;

    /**
     * @var object|PagesRepository
     */
    protected $pagesRepository;

    public function __construct(string $name = null)
    {
        parent::__construct($name);

        $this->configuration = GeneralUtility::makeInstance(Configuration::class);
        $this->brokenLinkRepository = GeneralUtility::makeInstance(BrokenLinkRepository::class);
        $this->pagesRepository = GeneralUtility::makeInstance(PagesRepository::class);
        $this->statistics = [];
    }

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure(): void
    {
        $this->setDescription('Check links')
            ->addOption('start-pages', 'p', InputOption::VALUE_REQUIRED, 'Page id(s). Separate with , if several are used, e.g. "1,23". If none are given, the configured site start pages are used.')
            ->addOption('depth', 'd', InputOption::VALUE_REQUIRED, 'Depth, default is 999, where 999 means infinite depth.')
            ->addOption('to', 't', InputOption::VALUE_REQUIRED, 'Email recipients')
        ;
    }

    /**
     * Executes the command for showing sys_log entries
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title($this->getDescription());

        $options = $input->getOptions();
        $startPageString = (string)($input->getOption('start-pages') ?? '');
        if ($startPageString !== '') {
            $startPages = explode(',', $startPageString);
        } else {
            $startPages = [];
        }
        $this->verbose = (bool)($options['verbose'] ?? false);

        if ($startPages === []) {
            /** @var SiteFinder $siteFinder */
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            /** @var Site[] $sites */
            $sites = $siteFinder->getAllSites();
            foreach ($sites as $site) {
                $startPages[] = $site->getRootPageId();
            }
            $this->writeln('Use page ids (from site configuration): ' . implode(',', $startPages));
        }

        if ($startPages === []) {
            $this->io->error('No pages to check ... abort');
            // @todo can be changed to return Command::FAILURE when TYPO3 v9 support is dropped
            return 1;
        }

        foreach ($startPages as $pageId) {
            $pageId = (int)$pageId;
            if ($pageId <= 0) {
                $this->io->warning('Page id ' . $pageId . ' not allowed ... skipping');
                continue;
            }

            print "\nstartPage=$pageId\n";

            $this->configuration->loadPageTsConfig($pageId);
            if (isset($options['depth'])) {
                $this->configuration->setDepth((int)$options['depth']);
            }
            if (isset($options['to'])) {
                $this->configuration->setMailRecipients($options['to']);
            }
            if (!$this->checkPageLinks($pageId, $options)
                || !isset($this->statistics[$pageId])
            ) {
                $this->io->warning(sprintf('No result for checking %d ... abort', $pageId));
                continue;
            }

            $stats = $this->statistics[$pageId];
            $this->writeln(sprintf(
                'Result for "%s": number of broken links=%d',
                $stats->getPageTitle(),
                $stats->getCountBrokenLinks()
            ));
            if ($this->configuration->getMailSendOnCheckLinks()) {
                // @todo check can be removed once support for 9 is dropped
                if (((int)(\TYPO3\CMS\Core\Utility\GeneralUtility::intExplode(
                    '.',
                    \TYPO3\CMS\Core\Utility\VersionNumberUtility::getCurrentTypo3Version()
                )[0])) < 10) {
                    /**
                     * @var GenerateCheckResultMailInterface
                     */
                    $generateCheckResultMail = GeneralUtility::makeInstance(GenerateCheckResultPlainMail::class);
                } else {
                    // FluidEmail - only for TYPO3 10
                    /**
                     * @var GenerateCheckResultMailInterface
                     */
                    $generateCheckResultMail = GeneralUtility::makeInstance(GenerateCheckResultFluidMail::class);
                }
                $generateCheckResultMail->generateMail($this->configuration, $this->statistics[$pageId], $pageId);
            }
        }

        // @todo can be changed to return  Command::SUCCESS once support for TYPO3 v9 is dropped
        return 0;
    }

    protected function writeln(string $message): void
    {
        if ($this->verbose) {
            $this->io->writeln($message);
        }
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
    protected function checkPageLinks(int $pageUid, array $options): bool
    {
        $depth = $this->configuration->getDepth();
        $searchFields = $this->configuration->getSearchFields();
        $linkTypes = $this->configuration->getLinkTypes();

        $pageRow = BackendUtility::getRecord('pages', $pageUid, '*', '', false);
        if ($pageRow === null) {
            throw new \InvalidArgumentException(
                sprintf('Invalid page uid passed as argument: %d', $pageUid)
            );
        }

        $this->writeln(sprintf('Checking start page "%s" [%d], depth: %s', $pageRow['title'], $pageUid, $depth === 999 ? 'infinite' : $depth));

        $rootLineHidden = $this->pagesRepository->getRootLineIsHidden($pageRow);

        $checkHidden = $this->configuration->isCheckHidden();
        if (!$rootLineHidden || $checkHidden) {
            $pageIds = $this->pagesRepository->getPageList(
                $pageUid,
                $depth,
                '1=1',
                $checkHidden
            );
        } else {
            if ($this->verbose) {
                $this->io->warning(
                    sprintf('Will not check hidden page: %s [%d]', $pageRow['title'] ?? '', $pageUid)
                );
            }
            return false;
        }
        if (!empty($pageIds)) {
            /** @var LinkAnalyzer $linkAnalyzer */
            $linkAnalyzer = GeneralUtility::makeInstance(LinkAnalyzer::class);
            $linkAnalyzer->init($searchFields, $pageIds, $this->configuration);
            $linkAnalyzer->generateBrokenLinkRecords($linkTypes, $checkHidden);

            $stats = $linkAnalyzer->getStatistics();
            $stats->setPageTitle($pageRow['title']);
            $this->statistics[$pageUid] = $stats;
        }

        return true;
    }
}
