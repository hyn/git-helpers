<?php

namespace Hyn\GitHelpers\Objects;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;


/**
 * @property string $name
 */
class Package
{

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
        'D'  => 'Deleted files',
        'R'  => 'Renamed files',
        'C'  => 'Copied files',
        'U'  => 'Unmerged files',
        'M'  => 'Modified files',
        '??' => 'Files not in .git'
    ];

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

        if (realpath("$path/composer.json")) {
            $this->composer = collect(json_decode(file_get_contents(realpath("$path/composer.json")), true));
        } else {
            $this->composer = collect();
        }

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
        if ($this->composer->has($name)) {
            return $this->composer->get($name);
        }
    }

    /**
     * @param $tag
     */
    public function addTag($tag)
    {
        exec("git tag {$tag} && git push {$this->remoteName} {$this->branch} --tags");
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
     * @param bool $count
     * @return null|string
     */
    public function getChangesSinceLatestTag($count = true)
    {
        if (!empty($this->latestTag)) {
            $c = exec("git rev-list {$this->latestTag}..HEAD --count");
            if($count) {
                return $c > 0 ? "Commits since latest tag: {$c}" : null;
            } else {
                passthru("git log {$this->latestTag}..HEAD --oneline --full-history --graph -n {$c}");
            }
        }
    }

    /**
     * Pulls latest changes.
     */
    public function pullLatestChanges()
    {
        exec("git pull {$this->remoteName} {$this->branch}");
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
            if (preg_match('/^(?<state>[^ ]+) (?<file>.*)$/', $line, $match)) {
                $state = Arr::get($match, 'state');
                $file  = Arr::get($match, 'file');

                if (!array_key_exists($state, $information)) {
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

        foreach ($collection as $name => $entries) {
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

    /**
     * Loads remote information from repository.
     */
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
        exec('git tag', $tagCount);

        if(!count($tagCount)) {
            return;
        }

        $one = exec('git describe --abbrev=0 --tags `git rev-list --tags --max-count=1`', $lines, $state);
        $two = exec('git describe --abbrev=0 --tags', $lines, $state);

        if(version_compare($one, $two, '>=')) {
            $this->latestTag = $one;
        } else {
            $this->latestTag = $two;
        }
    }

    /**
     * Identifies the working branch this package is on.
     */
    protected function identifyBranch()
    {
        $line = exec('git branch --no-color --contains HEAD | grep ^\*');
        list($tmp, $branch) = preg_split('/\s+/', $line);
        $this->branch = $branch;
    }

    /**
     * Identifies the remote name, url and checks for push/fetch rights.
     */
    protected function identifyRemote()
    {
        exec('git remote -v', $lines);

        foreach ($lines as $line) {
            list($remote, $url, $type) = preg_split('/\s+/', $line);
            $type = trim($type, '()');
            if ($remote === 'composer' && count($lines) > 2) {
                continue;
            }
            if ($remote === 'origin') {
                $this->remoteName = $remote;
                $this->remoteUrl  = $url;
                if ($type == 'fetch') {
                    $this->remoteFetchable = true;
                } elseif ($type == 'push') {
                    $this->remotePushable = false;
                }
            }
        }
    }
}