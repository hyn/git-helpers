<?php

namespace Hyn\GitHelpers\Commands;

use Hyn\GitHelpers\Objects\Directory;
use Hyn\GitHelpers\Objects\Package;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class TagHelper extends Command
{
    /**
     * @var Directory
     */
    protected $directory;

    protected function configure()
    {
        $this->setName('tag')
            ->setDescription('Tag possible subdirectories to mark new versions.')
            ->addArgument('match', InputArgument::OPTIONAL, 'Only tag matching directories [optional, regex]')
            ->addOption('up', null, InputOption::VALUE_OPTIONAL, 'The version type to increment, [major, minor, patch]', 'patch')
            ->addOption('changelog', 'c', InputOption::VALUE_NONE, 'Automatically generate changelog for the new version and commit it.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force tagging');

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
        $output->writeLn(["<comment>Loading directories to tag for: {$this->directory->getPath()}</comment>", '']);

        if ($this->directory->getSubdirectories()->isEmpty()) {
            $output->writeln('<error>No subdirectories found.</error>');

            return;
        }

        // Loop through all directories to search for repositories.
        foreach ($this->directory->getSubdirectories() as $subDirectory) {

            if ($input->getArgument('match') && ! preg_match("/{$input->getArgument('match')}/", $subDirectory)) {
                continue;
            }

            // Instantiates package from directory.
            /** @var Package $package */
            $package = new Package(
                $subDirectory
            );

            $output->writeln(sprintf('<info>%s - version: %s</info>', $package->name, $package->latestTag));

            // files not committed?!
            if (!$input->getOption('force') && !$package->getChangesSinceLatestTag()) {
                $output->writeln('<comment>No commits detected since last tag, skipping.</comment>');
                continue;
            } else {
                $changes = $package->getChangesSinceLatestTag(false);

                if ($changes instanceof Collection) {
                    $changes->each(function($item) use ($output) {
                        $output->writeln($item);
                    });
                }

                $version = $this->askForVersion($input, $output, $package);
                if (empty($version)) {
                    $output->writeln('<comment>Skipped tagging, no version provided.</comment>');
                    continue;
                }

                if ($input->getOption('changelog')) {
                    $package->generateChangelog('changelogs', $package->latestTag, 'HEAD', $version);
                    $package->commit("Auto-generated changelog for {$version}.");
                }

                $package->addTag($version);
            }

            // Return to original path.
            chdir($this->directory->getCurrentPath());
        }
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param Package         $package
     * @return string
     */
    protected function askForVersion(InputInterface $input, OutputInterface $output, Package $package)
    {
        $helper = $this->getHelper('question');
        $suggest = null;

        if($package->latestTag && preg_match('/^(?<prefix>v)?(?<version>[0-9]+\.[0-9]+\.[0-9]+)$/', $package->latestTag, $m)) {
            list($major, $minor, $patch) = explode('.', $m['version']);

            $major = intval($major);
            $minor = intval($minor);
            $patch = intval($patch);

            $up = $input->getOption('up');

            switch ($up) {
                case 'major':
                    $major++;
                    break;
                case 'minor':
                    $minor++;
                    break;
                case 'patch':
                    $patch++;
                    break;
            }
            $suggest = (Arr::has($m, 'prefix') ? Arr::get($m, 'prefix') : '') . join('.', [$major, $minor, $patch]);
        }

        $question = new Question('Specify the version to increment to or leave empty to skip: ');
        if($suggest) {
            $question->setAutocompleterValues([$suggest]);
        }

        return $helper->ask($input, $output, $question);
    }
}