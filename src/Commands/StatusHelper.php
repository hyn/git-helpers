<?php

namespace Hyn\GitHelpers\Commands;

use Hyn\GitHelpers\Objects\Directory;
use Hyn\GitHelpers\Objects\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
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
            ->addArgument('match', InputArgument::OPTIONAL, 'Only check status of matching directories [optional, regex]')
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
            if ($input->getArgument('match') && ! preg_match("/{$input->getArgument('match')}/", $subDirectory)) {
                $output->writeln("<comment>Skipped {$subDirectory}</comment>");
                continue;
            }

            // Instantiates package from directory.
            $package = new Package(
                $subDirectory
            );

            $output->writeln(sprintf(
                '<info>%s - branch: %s, version: %s</info>', 
                $package->name, 
                $package->branch,
                $package->latestTag ?: 'none'
            ));

            // Show commit status, open, added and new.
            $output->writeln($package->getCommitState());
            $output->writeln($package->getUnpushedCommitState());
            $output->writeln($package->getChangesSinceLatestTag());

            // Return to original path.
            chdir($this->directory->getCurrentPath());
        }
    }
}
