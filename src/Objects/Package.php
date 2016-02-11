<?php

namespace Luceos\GitHelpers\Objects;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;


/**
 * @property string $name
 */
class Package {

    /**
     * @var string
     */
    public $path;

    /**
     * @var string
     */
    public $latestTag;

    /**
     * @var string
     */
    public $remoteUrl;

    /**
     * @var string
     */
    public $remoteName;

    /**
     * @var boolean
     */
    public $remotePushable;

    /**
     * @var boolean
     */
    public $remoteFetchable;

    /**
     * @var string
     */
    public $branch;

    /**
     * @var Collection
     */
    protected $composer;


    /**
     * @var array
     */
    protected $sections = [
        'M' => 'Modified',
        '??' => 'Not in .git'
    ];

    /**
     * Package constructor.
     *
     * @param $composer
     * @param $path
     */
    public function __construct($composer, $path)
    {
        $this->composer = collect(json_decode($composer, true));

        chdir($path);

        $this->path = $path;

        $this->syncWithRemotes();

        $this->identifyRemote();
        $this->identifyBranch();
        $this->identifyLatestTag();
    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        if($this->composer->has($name))
        {
            return $this->composer->get($name);
        }
    }

    /**
     * @return null|string
     */
    public function getCommitState()
    {
        exec("git status --porcelain", $lines);
        return $this->shortCommitState($this->splitCommitSections($lines));
    }

    /**
     * @return null|string
     */
    public function getUnpushedCommitState()
    {
        exec("git log {$this->remoteName}/{$this->branch}..HEAD --not --remotes --oneline", $lines);
        return count($lines) ? "Commits unpushed: " . count($lines) : null;
    }

    /**
     * Shows the number of commits since the latest tag/version.
     *
     * @return null|string
     */
    public function getChangesSinceLatestTag()
    {
        $count = exec("git rev-list {$this->latestTag}..HEAD --count");
        return $count > 0 ? "Commits since latest tag: {$count}" : null;
    }

    /**
     * @param $lines
     * @return Collection
     */
    protected function splitCommitSections($lines)
    {
        $information = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if(preg_match('/^(?<state>[^ ]+) (?<file>.*)$/', $line, $match)) {
                $state = Arr::get($match, 'state');
                $file = Arr::get($match, 'file');

                if(!array_key_exists($state, $information)) {
                    $information[$state] = [];
                }

                $information[$state][] = $file;
            }
        }
        return collect($information);
    }

    /**
     * @param Collection $collection
     * @return null|string
     */
    protected function shortCommitState(Collection $collection)
    {
        $state = [];

        foreach($collection as $name => $entries)
        {
            $state[] = $this->translateSection($name) . ": " . count($entries);
        }

        return count($state) ? join("\t", $state) : null;
    }

    /**
     * @param $section
     * @return mixed
     */
    protected function translateSection($section)
    {
        return Arr::get($this->sections, $section, $section);
    }

    protected function syncWithRemotes()
    {
        exec('git fetch --all 1>/dev/null', $lines);
        unset($lines);
    }

    /**
     * Loads latest tag/version.
     */
    protected function identifyLatestTag()
    {
        $this->latestTag = exec('git describe --abbrev=0 --tags `git rev-list --tags --max-count=1 HEAD`');
    }

    /**
     * Identifies the working branch this package is on.
     */
    protected function identifyBranch()
    {
        $line = exec('git branch --no-color --contains HEAD');
        list($tmp, $branch) = preg_split('/\s+/', $line);
        $this->branch = $branch;
    }

    /**
     * Identifies the remote name, url and checks for push/fetch rights.
     */
    protected function identifyRemote()
    {
        exec('git remote -v', $lines);

        foreach($lines as $line)
        {
            list($remote, $url, $type) = preg_split('/\s+/', $line);
            $type = trim($type, '()');
            if($remote === 'composer' && count($lines) > 2) {
                continue;
            }
            if($remote === 'origin') {
                $this->remoteName = $remote;
                $this->remoteUrl = $url;
                if($type == 'fetch') {
                    $this->remoteFetchable = true;
                } elseif($type == 'push') {
                    $this->remotePushable = false;
                }
            }
        }
    }
}