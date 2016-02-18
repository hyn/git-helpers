<?php

namespace Hyn\GitHelpers\Objects;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use GitElephant\Repository;
use GitElephant\Objects\Branch;
use GitElephant\Objects\Remote;
use GitElephant\Objects\Tag;

/**
 * @property string $name
 */
class Package
{
    /**
     * @var string
     */
    protected $path;

    /**
     * @var Tag
     */
    protected $lastTag;

    /**
     * @var Remote
     */
    protected $remote;

    /**
     * @var Branch
     */
    protected $branch;

    /**
     * @var string
     */
    public $latestTag;

    /**
     * @var Collection
     */
    protected $composer;

    /**
     * @var Repository
     */
    protected $git;

    /**
     * Package constructor.
     *
     * @param $path
     * @throws \Exception
     */
    public function __construct($path)
    {
        if (!is_dir($path)) {
            throw new \Exception("$path does not exist.");
        }

        if (!realpath("$path/composer.json")) {
            throw new \Exception("No composer file in $path.");
        }

        $this->composer = collect(json_decode(file_get_contents(realpath("$path/composer.json")), true));

        chdir($path);

        $this->path = $path;

        $this->identifyProperties();
    }

    /**
     * Sets up the environment by identifying needed properties.
     */
    protected function identifyProperties()
    {
        $this->git = new Repository($this->path);
        $this->identifyRemote();
        $this->identifyBranch();
        $this->identifyLatestTag();
    }

    /**
     * Loads latest tag/version.
     */
    protected function identifyLatestTag()
    {
        $this->lastTag = $this->git->getLastTag();
    }

    /**
     * Identifies the working branch this package is on.
     */
    protected function identifyBranch()
    {
        $this->branch = $this->git->getMainBranch();
    }

    /**
     * Identifies the remote in use.
     */
    protected function identifyRemote()
    {
        // get local remotes
        $remotes = $this->git->getRemotes(false);

        /** @var Remote $remote */
        foreach ($remotes as $remote) {
            if ($remote->getName() == 'origin' || ($remote->getFetchURL() != '' && $remote->getPushURL() != '')) {
                $this->remote = $remote;
            }
        }
    }

    public function getCommitsSinceTag(Tag $tag = null)
    {
        if($tag == null) {
            $tag = $this->lastTag;
        }
        return $this->git->countCommits($tag);
    }

//
//    /**
//     * @param $name
//     * @return mixed
//     */
//    public function __get($name)
//    {
//        if ($this->composer->has($name)) {
//            return $this->composer->get($name);
//        }
//    }
//
//    /**
//     * @return null|string
//     */
//    public function getCommitState()
//    {
//        exec("git status --porcelain", $lines);
//
//        return $this->shortCommitState($this->splitCommitSections($lines));
//    }
//
//    /**
//     * @return null|string
//     */
//    public function getUnpushedCommitState()
//    {
//        exec("git log {$this->remoteName}/{$this->branch}..HEAD --not --remotes --oneline", $lines);
//
//        return count($lines) ? "Commits unpushed: " . count($lines) : null;
//    }
//
//    /**
//     * Shows the number of commits since the latest tag/version.
//     *
//     * @return null|string
//     */
//    public function getChangesSinceLatestTag()
//    {
//        $count = exec("git rev-list {$this->latestTag}..HEAD --count");
//
//        return $count > 0 ? "Commits since latest tag: {$count}" : null;
//    }
//
//    /**
//     * @param $lines
//     * @return Collection
//     */
//    protected function splitCommitSections($lines)
//    {
//        $information = [];
//
//        foreach ($lines as $line) {
//            $line = trim($line);
//            if (preg_match('/^(?<state>[^ ]+) (?<file>.*)$/', $line, $match)) {
//                $state = Arr::get($match, 'state');
//                $file  = Arr::get($match, 'file');
//
//                if (!array_key_exists($state, $information)) {
//                    $information[$state] = [];
//                }
//
//                $information[$state][] = $file;
//            }
//        }
//
//        return collect($information);
//    }
//
//    /**
//     * @param Collection $collection
//     * @return null|string
//     */
//    protected function shortCommitState(Collection $collection)
//    {
//        $state = [];
//
//        foreach ($collection as $name => $entries) {
//            $state[] = $this->translateSection($name) . ": " . count($entries);
//        }
//
//        return count($state) ? join("\t", $state) : null;
//    }
//
//    /**
//     * @param $section
//     * @return mixed
//     */
//    protected function translateSection($section)
//    {
//        return Arr::get($this->sections, $section, $section);
//    }
//
//    protected function syncWithRemotes()
//    {
//        exec('git fetch --all 1>/dev/null', $lines);
//        unset($lines);
//    }
//

}