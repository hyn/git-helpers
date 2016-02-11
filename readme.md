# hyn/git-helpers

This package is meant to be used in your terminal. And allows
working with lots of subdirectories containing git repositories.

## Installation

```bash
composer global require hyn/git-helpers
```

## Usage

You can now globally use the command `gh`, eg:

```bash
gh status
```

## Use cases

This package is especially helpful in case you manage your development packages
with a [repository path configuration](https://getcomposer.org/doc/05-repositories.md#path) in composer.

If you create a workbench directory containing all your development packages. You can
set your composer file to point to these packages:

```json
{
    "require": {
        "vendor/package": "*@dev"
    },
    "repositories": [
        {
            "type": "path",
            "url": "workbench/*/"
        }
    ]
}
```
The above will symlink your development packages from workbench to the vendor folder
automatically. Now you can use the `gh status` command in the folder workbench to 
check the status of your git remote and local changes immediately.

> Please note in the above repositories path configuration packages are expected immediately in the workbench directory, eg: `workbench/package`. A composer.json file should exist in `workbench/package/composer.json`.