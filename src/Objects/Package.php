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

        $this->path = $path;

        $this->moveToPackageDirectory();

        $this->syncWithRemotes();

        $this->identifyRemote();
        $this->identifyBranch();
        $this->identifyLatestTag();
    }

    /**
     * Changes working directory to that of the Package.
     */
    public function moveToPackageDirectory()
    {
        chdir($this->path);
    }

    /**
     * Loads remote information from repository.
     */
    protected function syncWithRemotes()
    {
        exec('git fetch --all --quiet', $lines);
        unset($lines);
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
     * Loads latest tag/version.
     */
    protected function identifyLatestTag()
    {
        exec('git tag', $tagCount);

        if (!count($tagCount)) {
            return;
        }

        $one = exec('git describe --abbrev=0 --tags `git rev-list --tags --max-count=1`', $lines, $state);
        $two = exec('git describe --abbrev=0 --tags', $lines, $state);

        if (version_compare($one, $two, '>=')) {
            $this->latestTag = $one;
        } else {
            $this->latestTag = $two;
        }
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
     * Pushes latest changes to remote.
     */
    public function push()
    {
        exec("git push {$this->remoteName} {$this->branch}");
    }

    /**
     * Commit changes to remote.
     *
     * @param string $message
     */
    public function commit($message = '')
    {
        $message = str_replace("'", "\\'", $message);
        exec("git commit -am '{$message}'");
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
        return $this->getChangesBetween($this->latestTag, 'HEAD', $count);
    }

    /**
     * Loads all changes between commit states.
     *
     * @param null   $start
     * @param string $end
     * @param bool   $count
     * @return Collection|string|null
     */
    public function getChangesBetween($start = null, $end = 'HEAD', $count = true)
    {
        if (empty($start) && !empty($this->latestTag)) {
            $start = $this->latestTag;
        }

        if (empty($start)) {
            return;
        }

        $c = exec("git rev-list {$start}..{$end} --count");

        if ($count) {
            return $c > 0 ? "Commits since latest tag: {$c}" : null;
        } else {
            exec("git log {$start}..{$end} --oneline --full-history --graph -n {$c}", $lines);

            return new Collection($lines);
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
     * Loads all tags.
     *
     * @param string $direction
     * @return Collection
     */
    public function getTags($direction = 'DESC')
    {
        exec('git tag', $lines);
        $versions = new Collection($lines);
        $versions = $versions->sort(function ($a, $b) use ($direction) {
            return version_compare($a, $b, $direction == 'DESC' ? '<' : '>');
        });

        return $versions;
    }

    /**
     * Stash open changes, including non-added files.
     */
    public function stashChanges()
    {
        exec('git stash save --include-untracked');
    }

    /**
     * Restores stashed changes.
     */
    public function restoreChanges()
    {
        exec('git stash pop');
    }

    public function generateChangelog($path, $version, $higherVersion, $aliasVersion = false, $force = false)
    {
        $changes = $this->getChangesBetween($version, $higherVersion, false);

        if ($aliasVersion) {
            $fileName = "{$path}/{$version}..{$aliasVersion}";
        } else {
            $fileName = "{$path}/{$version}..{$higherVersion}";
        }

        if (file_exists($fileName) && $force) {
            unlink($fileName);
        } elseif (file_exists($fileName)) {
            return false;
        }

        $filePointer = fopen($fileName, "wb");

        $changes->each(function ($item) use ($filePointer) {
            fwrite($filePointer, $item);
            fwrite($filePointer, "\r\n");
        });

        fclose($filePointer);

        return true;
    }
}