<?php

namespace App\Command;

use App\Entity\Alert;
use App\Entity\Log\RowCollection;
use App\Entity\Log\Stats as Stats;
use App\Service\TrafficMonitor;
use App\Service\LogAnalyzer;
use App\Service\LogIterator;
use League\Csv\Exception as LeagueCsvException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AnalyzeLogsCommand extends Command
{
    private const ARG_INPUT_FILE = 'input-file';

    private const OPT_STATS_TIMESPAN = 'stats-timespan';

    private const OPT_ALERT_THRESHOLD = 'alert-threshold';

    private const OPT_ALERT_TIME_WINDOW = 'alert-time-window';

    protected static $defaultName = 'app:analyze-logs';

    private LogIterator $logIterator;

    public function __construct(LogIterator $logIterator)
    {
        parent::__construct();

        $this->logIterator = $logIterator;
    }

    protected function configure()
    {
        $this
            ->addArgument(
                static::ARG_INPUT_FILE,
                InputArgument::REQUIRED,
                'Which log file do you want to process?'
            )
            ->addOption(
                static::OPT_STATS_TIMESPAN,
                's',
                InputOption::VALUE_REQUIRED,
                'How many seconds of log lines to consider for stats computation? Default 10.',
                10
            )
            ->addOption(
                static::OPT_ALERT_THRESHOLD,
                't',
                InputOption::VALUE_REQUIRED,
                'How many requests per second on average should trigger an alert? Default 10.',
                10
            )
            ->addOption(
                static::OPT_ALERT_TIME_WINDOW,
                'w',
                InputOption::VALUE_REQUIRED,
                'The time expressed as seconds at which logs are taken into account for alerting? Default 120.',
                120
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $inputFile = $input->getArgument(static::ARG_INPUT_FILE);
        $statsTimespan = $input->getOption(static::OPT_STATS_TIMESPAN);

        $alertThreshold = $input->getOption(static::OPT_ALERT_THRESHOLD);
        $alertTimeWindow = $input->getOption(static::OPT_ALERT_TIME_WINDOW);

        $highTrafficAlertMonitor = new TrafficMonitor((int)$alertTimeWindow, (int)$alertThreshold);
        $logAnalyzer = new LogAnalyzer($statsTimespan);

        $output->writeln(sprintf('<info>Processing the log file %s</info>', $inputFile));
        $output->writeln(sprintf('<info>Using %d seconds as a timespan for stats computation</info>', $statsTimespan));
        $output->writeln(sprintf('<info>Using %d seconds as a timewindow for alerting</info>', $alertTimeWindow));
        $output->writeln(sprintf('<info>Using %d hits as a threshold for alerting</info>', $alertThreshold));

        try {
            $currentAlert = null;
            // iterate over chunks of logs - chunks are sized by time span expressed in seconds
            foreach ($this->logIterator->yieldCollectionFromLogFile($inputFile) as $collection) {
                /** @var RowCollection $collection */
                foreach ($collection->all() as $logRow) {

                    // push the log row to the high traffic alert monitor and logs analyzer
                    $highTrafficAlertMonitor->pushLog($logRow);
                    $logAnalyzer->pushLog($logRow);

                    // run the high traffic monitor and see if it returns an alert instance (either started or recovered)
                    $alert = $highTrafficAlertMonitor->monitor();
                    if ($alert !== null && $alert->isActive() === true) {
                        $this->printAlert($output, $alert);
                    } else if ($alert !== null && $alert->isActive() === false) {
                        $this->printRecoveredAlert($output, $alert);
                    }

                    if ($logAnalyzer->canCalculateStats()) {
                        $stats = $logAnalyzer->calculateStats();
                        if ($stats !== null) {
                            $this->renderStats($output, $stats);
                            $logAnalyzer->purge();
                        }
                    }
                }
            }
            // print last piece of stats, even if there's less than
            $stats = $logAnalyzer->calculateStats();
            if ($stats !== null) {
                $this->renderStats($output, $stats);
                $logAnalyzer->purge();
            }
        } catch (LeagueCsvException $e) {
            $output->writeln('<error>Failed to parse the log file!</error>');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function renderStats(OutputInterface $output, Stats $stats): void
    {
        $table = new Table($output);
        $table->setHeaders([
            'section',
            LogAnalyzer::METRIC_HITS,
            LogAnalyzer::METRIC_404s,
            LogAnalyzer::METRIC_SUCCESSFUL,
            LogAnalyzer::METRIC_REDIRECTIONS,
            LogAnalyzer::METRIC_CLIENT_ERROR,
            LogAnalyzer::METRIC_SERVER_ERROR,
        ]);
        foreach ($stats->getStats() as $section => $rawStats) {
            $table->addRow([
                $section,
                $rawStats[LogAnalyzer::METRIC_HITS],
                $rawStats[LogAnalyzer::METRIC_404s],
                $rawStats[LogAnalyzer::METRIC_SUCCESSFUL],
                $rawStats[LogAnalyzer::METRIC_REDIRECTIONS],
                $rawStats[LogAnalyzer::METRIC_CLIENT_ERROR],
                $rawStats[LogAnalyzer::METRIC_SERVER_ERROR],
            ]);
        }

        $output->writeln(sprintf(
            '<comment>Traffic stats between %s and %s</comment>',
            date('Y-m-d H:i:s', $stats->getFirstTimestamp()),
            date('Y-m-d H:i:s', $stats->getLastTimestamp())
        ));
        $table->render();
    }

    private function printAlert(OutputInterface $output, Alert $alert): void
    {
        $text = sprintf(
            'High traffic generated an alert - hits = %d, triggered at %s',
            $alert->getAverageHits(),
            date('Y-m-d H:i:s', $alert->getAlertStartedTimestamp())
        );
        $this->printStyledInfo($output, $text, 'yellow', 'red');
    }

    private function printRecoveredAlert(OutputInterface $output, Alert $alert): void
    {
        $text = sprintf(
            'High traffic alert recovered at %s. Alert duration: %d seconds.',
            date('Y-m-d H:i:s', $alert->getAlertRecoveredTimestamp()),
            $alert->getAlertDurationInSeconds()
        );
        $this->printStyledInfo($output, $text, 'yellow', 'green');
    }

    private function printStyledInfo(
        OutputInterface $output,
        string $text,
        string $foregroundColor,
        string $backgroundColor
    ): void
    {
        $text = sprintf('### %s ###', $text);
        $style = sprintf('<fg=%s;bg=%s;options=bold>', $foregroundColor, $backgroundColor);
        $output->writeln(sprintf(
            '%s%s#</>',
            $style,
            str_repeat('#', strlen($text) - 1)
        ));
        $output->writeln(sprintf(
            '%s%s</>',
            $style,
            $text
        ));
        $output->writeln(sprintf(
            '%s%s#</>',
            $style,
            str_repeat('#', strlen($text) - 1)
        ));
    }
}