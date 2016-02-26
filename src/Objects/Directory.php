<?php

namespace Hyn\GitHelpers\Objects;

class Directory {

    /**
     * @var string
     */
    protected $current_path;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var \Illuminate\Support\Collection
     */
    protected $subdirectories;

    public function __construct($path = null)
    {
        $this->current_path = getcwd();

        if (is_null($path)) {
            $this->path = getcwd();
        }

        $this->subdirectories = collect(glob("{$this->path}/*", GLOB_ONLYDIR));
    }

    /**
     * @return string
     */
    public function getCurrentPath()
    {
        return $this->current_path;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function getSubdirectories()
    {
        return $this->subdirectories;
    }
}