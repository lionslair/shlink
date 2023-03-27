<?php

declare(strict_types=1);

namespace Shlinkio\Shlink\CLI\Command\Visit;

use Shlinkio\Shlink\CLI\Command\Util\AbstractLockedCommand;
use Shlinkio\Shlink\CLI\Command\Util\LockedCommandConfig;
use Shlinkio\Shlink\CLI\Util\ExitCodes;
use Shlinkio\Shlink\Common\Util\IpAddress;
use Shlinkio\Shlink\Core\Exception\IpCannotBeLocatedException;
use Shlinkio\Shlink\Core\Visit\Entity\Visit;
use Shlinkio\Shlink\Core\Visit\Entity\VisitLocation;
use Shlinkio\Shlink\Core\Visit\Geolocation\VisitGeolocationHelperInterface;
use Shlinkio\Shlink\Core\Visit\Geolocation\VisitLocatorInterface;
use Shlinkio\Shlink\Core\Visit\Geolocation\VisitToLocationHelperInterface;
use Shlinkio\Shlink\Core\Visit\Model\UnlocatableIpType;
use Shlinkio\Shlink\IpGeolocation\Model\Location;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;
use Throwable;

use function sprintf;

class LocateVisitsCommand extends AbstractLockedCommand implements VisitGeolocationHelperInterface
{
    public const NAME = 'visit:locate';

    private SymfonyStyle $io;

    public function __construct(
        private readonly VisitLocatorInterface $visitLocator,
        private readonly VisitToLocationHelperInterface $visitToLocation,
        LockFactory $locker,
    ) {
        parent::__construct($locker);
    }

    protected function configure(): void
    {
        $this
            ->setName(self::NAME)
            ->setDescription(
                'Resolves visits origin locations. It implicitly downloads/updates the GeoLite2 db file if needed.',
            )
            ->addOption(
                'retry',
                'r',
                InputOption::VALUE_NONE,
                'Will retry the location of visits that were located with a not-found location, in case it was due to '
                . 'a temporal issue.',
            )
            ->addOption(
                'all',
                'a',
                InputOption::VALUE_NONE,
                'When provided together with --retry, will locate all existing visits, regardless the fact that they '
                . 'have already been located.',
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $retry = $input->getOption('retry');
        $all = $input->getOption('all');

        if ($all && !$retry) {
            $this->io->writeln(
                '<comment>The <fg=yellow;options=bold>--all</> flag has no effect on its own. You have to provide it '
                . 'together with <fg=yellow;options=bold>--retry</>.</comment>',
            );
        }

        if ($all && $retry && ! $this->warnAndVerifyContinue()) {
            throw new RuntimeException('Execution aborted');
        }
    }

    private function warnAndVerifyContinue(): bool
    {
        $this->io->warning([
            'You are about to process the location of all existing visits your short URLs received.',
            'Since shlink saves visitors IP addresses anonymized, you could end up losing precision on some of '
            . 'your visits.',
            'Also, if you have a large amount of visits, this can be a very time consuming process. '
            . 'Continue at your own risk.',
        ]);
        return $this->io->confirm('Do you want to proceed?', false);
    }

    protected function lockedExecute(InputInterface $input, OutputInterface $output): int
    {
        $retry = $input->getOption('retry');
        $all = $retry && $input->getOption('all');

        try {
            $this->checkDbUpdate();

            if ($all) {
                $this->visitLocator->locateAllVisits($this);
            } else {
                $this->visitLocator->locateUnlocatedVisits($this);
                if ($retry) {
                    $this->visitLocator->locateVisitsWithEmptyLocation($this);
                }
            }

            $this->io->success('Finished locating visits');
            return ExitCodes::EXIT_SUCCESS;
        } catch (Throwable $e) {
            $this->io->error($e->getMessage());
            if ($this->io->isVerbose()) {
                $this->getApplication()?->renderThrowable($e, $this->io);
            }

            return ExitCodes::EXIT_FAILURE;
        }
    }

    /**
     * @throws IpCannotBeLocatedException
     */
    public function geolocateVisit(Visit $visit): Location
    {
        $ipAddr = $visit->getRemoteAddr() ?? '?';
        $this->io->write(sprintf('Processing IP <fg=blue>%s</>', $ipAddr));

        try {
            return $this->visitToLocation->resolveVisitLocation($visit);
        } catch (IpCannotBeLocatedException $e) {
            $this->io->writeln(match ($e->type) {
                UnlocatableIpType::EMPTY_ADDRESS => ' [<comment>Ignored visit with no IP address</comment>]',
                UnlocatableIpType::LOCALHOST => ' [<comment>Ignored localhost address</comment>]',
                UnlocatableIpType::ERROR => ' [<fg=red>An error occurred while locating IP. Skipped</>]',
            });

            if ($e->type === UnlocatableIpType::ERROR && $this->io->isVerbose()) {
                $this->getApplication()?->renderThrowable($e, $this->io);
            }

            throw $e;
        }
    }

    public function onVisitLocated(VisitLocation $visitLocation, Visit $visit): void
    {
        if (! $visitLocation->isEmpty()) {
            $this->io->writeln(sprintf(' [<info>Address located in "%s"</info>]', $visitLocation->getCountryName()));
        } elseif ($visit->hasRemoteAddr() && $visit->getRemoteAddr() !== IpAddress::LOCALHOST) {
            $this->io->writeln(' <comment>[Could not locate address]</comment>');
        }
    }

    private function checkDbUpdate(): void
    {
        $cliApp = $this->getApplication();
        if ($cliApp === null) {
            return;
        }

        $downloadDbCommand = $cliApp->find(DownloadGeoLiteDbCommand::NAME);
        $exitCode = $downloadDbCommand->run(new ArrayInput([]), $this->io);

        if ($exitCode === ExitCodes::EXIT_FAILURE) {
            throw new RuntimeException('It is not possible to locate visits without a GeoLite2 db file.');
        }
    }

    protected function getLockConfig(): LockedCommandConfig
    {
        return LockedCommandConfig::nonBlocking(self::NAME);
    }
}
