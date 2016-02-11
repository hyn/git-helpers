# luceos/git-helpers

This package is meant to be used in your terminal. And allows
working with lots of subdirectories containing git repositories.

## Installation

```bash
cd ~
composer create-project luceos/git-helpers
```

Optionally create a symlink to `/usr/share/bin/gh` from `/home/<username>/git-helpers/bin/console.php`:

```bash
cd /usr/share/bin
sudo ln -s /home/<username>/git-helpers/bin/console.php ./gh
```
## Usage

You can now globally use the command `gh`, eg:

```bash
gh git:status
```