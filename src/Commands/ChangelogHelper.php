<?php

namespace Hyn\GitHelpers\Commands;

use Hyn\GitHelpers\Objects\Directory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ChangelogHelper extends Command
{
    /**
     * @var Directory
     */
    protected $directory;

    protected function configure()
    {
        $this->setName('changelog')
            ->setDescription('Create changelogs for repositories in all subdirectories.')
            ->addArgument('match', InputArgument::OPTIONAL, 'Only pull matching directories [optional, regex].')
            ->addOption('force', 'f', InputOption::VALUE_OPTIONAL, 'Force pulling in changes.')
            ->addOption('path', 'p', InputOption::VALUE_OPTIONAL, 'Save changelogs into specified path.', 'changelogs')
        ;

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
        $output->writeLn(["<comment>Loading directories to create changelogs for: {$this->directory->getPath()}</comment>", '']);

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

            $output->writeln(sprintf('<info>%s - version: %s</info>', $package->name, $package->latestTag));

            // files not committed?!
            if (!$input->getOption('force') && $package->getCommitState()) {
                continue;
            } else {
            }

            // Return to original path.
            chdir($this->directory->getCurrentPath());
        }
    }
}