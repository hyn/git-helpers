<?php

namespace Luceos\GitHelpers\Commands;

use Luceos\GitHelpers\Objects\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StatusHelper extends Command
{
    protected $directory;

    protected function configure()
    {
        $this->setName('git:status')
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
        $output->writeLn("<comment>Verifying status for: {$this->directory}</comment>");

        foreach (glob("*", GLOB_ONLYDIR) as $subDirectory) {

            if (!file_exists("{$subDirectory}/composer.json")) {
                continue;
            }

            $package = new Package(file_get_contents("{$subDirectory}/composer.json"),
                "{$this->directory}/{$subDirectory}");

            $output->writeln(sprintf('<info>%s - version: %s</info>', $package->name, $package->latestTag));

            // Show commit status, open, added and new.
            $output->writeln($package->getCommitState());
            $output->writeln($package->getUnpushedCommitState());
            $output->writeln($package->getChangesSinceLatestTag());

            chdir($this->directory);
        }
    }
}