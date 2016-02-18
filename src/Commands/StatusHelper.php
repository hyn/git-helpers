<?php

namespace Hyn\GitHelpers\Commands;

use Hyn\GitHelpers\Objects\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StatusHelper extends Command
{
    /**
     * Directory command is ran from.
     *
     * @var string
     */
    protected $directory;

    protected function configure()
    {
        $this->setName('status')
            ->setDescription('Shows status of git repositories in all subdirectories.');

        $this->directory = getcwd();
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeLn(["<comment>Verifying status for: {$this->directory}</comment>", '']);

        $subDirectories = glob("*", GLOB_ONLYDIR);

        if(!count($subDirectories)) {
            $output->writeln('<error>No subdirectories found.</error>');
            return;
        }

        // Loop through all directories to search for repositories.
        foreach ($subDirectories as $subDirectory) {

            if (!file_exists("{$subDirectory}/composer.json")) {
                continue;
            }

            // Instantiates package from absolute path.
            $package = new Package("{$this->directory}/{$subDirectory}");

            $package->syncRemote();

            $output->writeln(
                sprintf(
                    '<info>%s (%s/%s) - version: %s</info>',
                    $package->name,
                    $package->getRemote()->getName(),
                    $package->getBranch()->getName(),
                    $package->getLastTag()? $package->getLastTag()->getName() : '(dev)'
                ));

            // Show commit status, open, added and new.
//            $output->writeln($package->getCommitsSince());
            if($package->getCommitState(false) > 0) {
                $output->writeln(sprintf('Uncommitted changes: %s', $package->getCommitState(false)));
            }
//            $output->writeln(implode(', ', $package->getCommitState()));
//            $output->writeln($package->getCommitState());
//            $output->writeln($package->getUnpushedCommitState());
//            $output->writeln($package->getChangesSinceLatestTag());

            // Return to original path.
            chdir($this->directory);
        }
    }
}