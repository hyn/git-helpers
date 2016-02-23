<?php

namespace Hyn\GitHelpers\Commands;

use Hyn\GitHelpers\Objects\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PullHelper extends Command
{
    protected $directory;

    protected function configure()
    {
        $this->setName('pull')
            ->setDescription('Pull remote changes for repositories in all subdirectories.')
            ->addArgument('match', InputArgument::OPTIONAL, 'Only pull matching directories [optional, regex]')
            ->addOption('force', 'f', InputOption::VALUE_OPTIONAL, 'Force pulling in changes');

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
        $output->writeLn(["<comment>Loading directories to pull for: {$this->directory}</comment>", '']);

        $subDirectories = glob("*", GLOB_ONLYDIR);

        if (!count($subDirectories)) {
            $output->writeln('<error>No subdirectories found.</error>');

            return;
        }

        // Loop through all directories to search for repositories.
        foreach ($subDirectories as $subDirectory) {

            if ($input->getArgument('match') && ! preg_match("/{$input->getArgument('match')}/", $subDirectory)) {
                $output->writeln("<comment>Skipped {$subDirectory}</comment>");
                continue;
            }

            // Instantiates package from directory.
            $package = new Package(
                "{$this->directory}/{$subDirectory}"
            );

            $output->writeln(sprintf('<info>%s - version: %s</info>', $package->name, $package->latestTag));

            // files not committed?!
            if (!$input->getOption('force') && $package->getCommitState()) {
                $output->writeln('<error>Local changes might be lost by pulling, commit/stash before continuing.</error>');
                continue;
            } else {
                $package->pullLatestChanges();
            }

            // Return to original path.
            chdir($this->directory);
        }
    }
}