<?php

namespace Hyn\GitHelpers\Commands;

use Hyn\GitHelpers\Objects\Directory;
use Hyn\GitHelpers\Objects\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PullHelper extends Command
{
    /**
     * @var Directory
     */
    protected $directory;

    protected function configure()
    {
        $this->setName('pull')
            ->setDescription('Pull remote changes for repositories in all subdirectories.')
            ->addArgument('match', InputArgument::OPTIONAL, 'Only pull matching directories [optional, regex]')
            ->addOption('stash', 's', InputOption::VALUE_NONE, 'Stash changes before pulling.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force pulling in changes');

        $this->directory = new Directory();
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeLn(["<comment>Loading directories to pull for: {$this->directory->getPath()}</comment>", '']);

        if ($this->directory->getSubdirectories()->isEmpty()) {
            $output->writeln('<error>No subdirectories found.</error>');

            return;
        }

        // Loop through all directories to search for repositories.
        foreach ($this->directory->getSubdirectories() as $subDirectory) {

            if ($input->getArgument('match') && !preg_match("/{$input->getArgument('match')}/", $subDirectory)) {
                $output->writeln("<comment>Skipped {$subDirectory}</comment>");
                continue;
            }

            // Instantiates package from directory.
            $package = new Package(
                $subDirectory
            );

            $output->writeln(sprintf('<info>%s - version: %s</info>', $package->name, $package->latestTag));

            $stashed = false;

            // files not committed?!
            if (!$input->getOption('force') && !$input->getOption('stash') && $package->getCommitState()) {
                $output->writeln('<error>Local changes might be lost by pulling, commit/stash before continuing.</error>');
                continue;
            } else {
                if ($input->getOption('stash') && $package->getCommitState()) {
                    $package->stashChanges();
                    $stashed = true;
                }

                $package->pullLatestChanges();

                if ($input->getOption('stash') && $stashed) {
                    $package->restoreChanges();
                }
            }

            // Return to original path.
            chdir($this->directory->getCurrentPath());
        }
    }
}