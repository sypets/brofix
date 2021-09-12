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
use Sypets\Brofix\Configuration\Configuration;
use Sypets\Brofix\LinkAnalyzer;
use Sypets\Brofix\Repository\BrokenLinkRepository;
use Sypets\Brofix\Repository\PagesRepository;
use Sypets\Brofix\Repository\SysHistoryRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @internal This class is for internal use inside this extension only.
 */
class CheckLinksIncrementalCommand extends Command
{
    /**
     * Cache identifier for the last incremental check timestamp.
     *
     * @var string
     */
    protected const CACHE_IDENTIFIER_LAST_INCREMENTAL_CHECK = 'last_inremental_check';

    /**
     * @var string
     */
    protected const TIME_INTERVAL_DEFAULT = '-1 minute';

    /**
     * The maximum timeframe to consider for incrementally checking, should be
     * 24 hours (60*60*24)
     *
     * @var int
     */
    protected const MAX_TIME_DIFF = 86400;

    /**
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * @var bool
     */
    protected $dryRun;

    /**
     * @var string
     */
    protected $sendTo;

    /**
     * @var string
     */
    protected $timeInterval;

    /**
     * @var array<int,string>
     */
    protected $tables;

    /**
     * Use timestamp of last check from cache
     *
     * @var bool
     */
    protected $useCache;

    /**
     * @var int -1: means use default (do not send),
     *   1 means send email, 0 means do not send
     */
    protected $sendEmail;

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
     * @var SysHistoryRepository
     */
    protected $sysHistoryRepository;

    /**
     * @var FrontendInterface
     */
    private $cache;

    public function __construct(string $name = null, FrontendInterface $cache = null)
    {
        parent::__construct($name);
        $this->configuration = GeneralUtility::makeInstance(Configuration::class);
        $this->brokenLinkRepository = GeneralUtility::makeInstance(BrokenLinkRepository::class);
        $this->pagesRepository = GeneralUtility::makeInstance(PagesRepository::class);
        $this->sysHistoryRepository = GeneralUtility::makeInstance(SysHistoryRepository::class);
        $this->cache = $cache ?: GeneralUtility::makeInstance(CacheManager::class)->getCache('brofix_checklinkcache');
    }

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure(): void
    {
        $this->setDescription('Check links (incremental)')
            ->addOption(
                'tables',
                'd',
                InputOption::VALUE_REQUIRED,
                'Which database tables to check (default tt_content,pages). Which tables are checked also depends on the configuration.'
            )
            ->addOption(
                'time-interval',
                '',
                InputOption::VALUE_REQUIRED,
                'Time interval to use (e.g. "-1 minute"). Only changed records after this time interval are considered.' .
                'If none are given, the default is used (-1 minute).'
            )
            ->addOption(
                'use-cache',
                'c',
                InputOption::VALUE_REQUIRED,
                'Use the timestamp of last check from cache. Use value 0 or 1. Default is 1 (use cache)'
            )
            ->addOption(
                'to',
                't',
                InputOption::VALUE_OPTIONAL,
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
                InputOption::VALUE_OPTIONAL,
                'Send email. 1: send, 0: do not send, -1: use default (do not send)'
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
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Start checking');

        $this->dryRun = (bool)($input->getOption('dry-run') ?: false);
        if ($this->dryRun) {
            $this->io->writeln('Dry run is activated, do not check and do not send email');
        }
        $this->sendEmail = (int)($input->getOption('send-email') ?? -1);
        if ($this->sendEmail === 0) {
            $this->io->writeln('Do not send email.');
        }
        $this->sendTo = $input->getOption('to') ?: '';
        $this->timeInterval = $input->getOption('time-interval') ?: self::TIME_INTERVAL_DEFAULT;
        $this->tables = explode(',', $input->getOption('tables') ?: 'pages,tt_content');
        $this->useCache = (bool)($input->getOption('use-cache') ?? true);

        if ($this->useCache) {
            $timestamp = (int)$this->cache->get(self::CACHE_IDENTIFIER_LAST_INCREMENTAL_CHECK);
        } else {
            $timestamp = 0;
        }
        if ($timestamp) {
            if (\time() - $timestamp > self::MAX_TIME_DIFF) {
                // timeframe exceeds max, set it to max.
                $timestamp = \time() - self::MAX_TIME_DIFF;
            } else {
                $this->io->writeln('Using timestamp from cache:' . $timestamp);
            }
        } else {
            $timestamp = strtotime($this->timeInterval);
        }

        $this->io->writeln('Check records changed after <' . date('d.m.Y H:i:s', $timestamp) . '> (timestamp='
            . $timestamp . ') in tables <'
            . implode(',', $this->tables) . '>');

        $this->cache->set(self::CACHE_IDENTIFIER_LAST_INCREMENTAL_CHECK, \time());
        $rows = $this->sysHistoryRepository->getLastChangedRecords($this->tables, $timestamp);
        $results = [];
        foreach ($rows as $key => $row) {
            $tablename = $row['tablename'];
            $uid = (int)($row['recuid']);
            /**
             * @var int $pid
             */
            $pid = 0;
            if ($tablename === 'pages') {
                $pid = $uid;
            } else {
                $result = BackendUtility::getRecord($tablename, $uid, 'pid');
                $pid = (int)($result['pid'] ?? 0);
            }
            if ($pid === 0) {
                // unable to get pid
                unset($rows[$key]);
                continue;
            }


            // concatenate history_data
            $data = $row['history_data'] . ($results[$pid][$tablename][$uid]['history_data'] ?? '');
            $results[$pid][$tablename][$uid] = [
                'uid' => $uid,
                'history_data' => $data
            ];
        }

        foreach ($results as $pid => $tables) {
            if ($this->io->isDebug()) {
                $this->io->writeln('Loading configuration for pid=' . $pid);
            }
            $this->configuration->loadPageTsConfig($pid);
            if (!$this->configuration->getEnable()) {
                continue;
            }

            if (!($this->configuration->isCheckHidden() ?? false)) {
                $pageRow = BackendUtility::getRecord('pages', $pid, '*', '', false);
                if ($pageRow['hidden'] ?? false) {
                    $hiddenPids[$pid] = $pid;
                    unset($rows[$key]);
                    continue;
                }
                $rootLineHidden = $this->pagesRepository->getRootLineIsHidden($pageRow);
                if ($rootLineHidden) {
                    $hiddenPids[$pid] = $pid;
                    unset($rows[$key]);
                    continue;
                }
            }

            /** @var LinkAnalyzer $linkAnalyzer */
            $linkAnalyzer = GeneralUtility::makeInstance(LinkAnalyzer::class);
            $linkAnalyzer->init([], $this->configuration);
            foreach ($tables as $tablename => $uids) {
                if (!in_array($tablename, array_keys($this->configuration->getSearchFields()))) {
                    // tablename is not in list of tables to be searched
                    continue;
                }
                $fields = $this->configuration->getSearchFields()[$tablename] ?? [];
                if (!$fields) {
                    continue;
                }
                foreach ($uids as $uid => $values) {
                    $data = $values['history_data'];
                    $pattern = '/"(' . implode('|', $fields) . ')":/';
                    if (!preg_match($pattern, $data)) {
                        continue;
                    }
                    foreach ($fields as $field) {
                        if (!preg_match('/"' . $field . '":/', $data)) {
                            continue;
                        }
                        $message = '';
                        $this->io->writeln('check table=' . $tablename . ' uid=' . $uid . ' field=' . $field);
                        $linkAnalyzer->recheckLinks(
                            $message,
                            $this->configuration->getLinkTypes(),
                            $uid,
                            $tablename,
                            $field,
                            0
                        );
                        if ($message) {
                            $this->io->writeln($message);
                        }
                    }
                }
            }
        }

        // @todo use constant Command::SUCCESS (not available in earlier Symfony versions)
        return 0;
    }
}
