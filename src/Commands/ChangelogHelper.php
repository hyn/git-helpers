<?php

namespace Hyn\GitHelpers\Commands;

use Hyn\GitHelpers\Objects\Directory;
use Hyn\GitHelpers\Objects\Package;
use Illuminate\Support\Collection;
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
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force pulling in changes.')
            ->addOption('commit', 'c', InputOption::VALUE_NONE, 'Auto commits the generated changelogs.')
            ->addOption('head', null, InputOption::VALUE_OPTIONAL,
                'Generate changelog from last version to HEAD, specify version for HEAD.')
            ->addOption('path', 'p', InputOption::VALUE_OPTIONAL, 'Save changelogs into specified path.', 'changelogs');

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
        $output->writeLn([
            "<comment>Loading directories to create changelogs for: {$this->directory->getPath()}</comment>",
            ''
        ]);

        if ($this->directory->getSubdirectories()->isEmpty()) {
            $output->writeln('<error>No subdirectories found.</error>');

            return;
        }

        // Loop through all directories to search for repositories.
        foreach ($this->directory->getSubdirectories() as $subDirectory) {

            if ($input->getArgument('match') && !preg_match("/{$input->getArgument('match')}/", $subDirectory)) {
                continue;
            }

            /** @var Package $package */
            $package = new Package(
                $subDirectory
            );

            $path = rtrim($input->getOption('path'), '/');

            $output->writeln(sprintf('<info>%s - version: %s</info>', $package->name, $package->latestTag));

            // attempt to create directory
            if (!is_dir($path)) {
                if (!mkdir($path, 0755, true)) {
                    $output->writeln("<error>Couldn't create directory {$path}</error>");

                    return;
                }
            }

            // files not committed?!
            if (!$input->getOption('force') && $package->getCommitState()) {
                $output->writeln('<error>Package has an unresolved commit state; please resolve before creating changelogs.</error>');
                continue;
            } else {

                $higherVersion = null;
                $aliasVersion  = null;

                if ($input->getOption('head')) {

                    $higherVersion = 'HEAD';
                    $tags          = [$package->latestTag];
                    $aliasVersion  = $input->getOption('head');

                } else {
                    $tags = $package->getTags();
                }

                foreach ($tags as $version) {

                    if (!is_null($higherVersion)) {

                        if ($aliasVersion) {
                            $output->writeln("<info>Version {$version} - {$aliasVersion}</info>");
                        } else {
                            $output->writeln("<info>Version {$version} - {$higherVersion}</info>");
                        }

                        $changes = $package->getChangesBetween($version, $higherVersion, false);

                        if ($changes instanceof Collection) {
                            if ($aliasVersion) {
                                $fileName = "{$path}/{$version}..{$aliasVersion}";
                            } else {
                                $fileName = "{$path}/{$version}..{$higherVersion}";
                            }

                            if ($package->generateChangelog($path, $version, $higherVersion, $aliasVersion)) {
                                $output->writeln("{$changes->count()} lines of changelog written to {$fileName}");
                            }
                        }
                    }

                    $higherVersion = $version;

                    if ($input->getOption('commit')) {
                        $package->commit("Changelog added for {$version}.");
                    }
                }
            }

            // Return to original path.
            chdir($this->directory->getCurrentPath());
        }
    }
}