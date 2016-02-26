<?php

namespace Hyn\GitHelpers\Commands;

use Hyn\GitHelpers\Objects\Directory;
use Hyn\GitHelpers\Objects\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StatusHelper extends Command
{
    /**
     * @var Directory
     */
    protected $directory;

    protected function configure()
    {
        $this->setName('status')
            ->setDescription('Shows status of git repositories in all subdirectories.');

        $this->directory = new Directory;
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeLn(["<comment>Verifying status for: {$this->directory->getPath()}</comment>", '']);

        if ($this->directory->getSubdirectories()->isEmpty()) {
            $output->writeln('<error>No subdirectories found.</error>');

            return;
        }

        // Loop through all directories to search for repositories.
        foreach ($this->directory->getSubdirectories() as $subDirectory) {
            // Instantiates package from directory.
            $package = new Package(
                $subDirectory
            );

            $output->writeln(sprintf('<info>%s - version: %s</info>', $package->name, $package->latestTag));

            // Show commit status, open, added and new.
            $output->writeln($package->getCommitState());
            $output->writeln($package->getUnpushedCommitState());
            $output->writeln($package->getChangesSinceLatestTag());

            // Return to original path.
            chdir($this->directory->getCurrentPath());
        }
    }
}