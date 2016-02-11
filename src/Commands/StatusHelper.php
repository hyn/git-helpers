<?php

namespace Luceos\GitHelpers\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;

class TagHelper extends Command {
    protected function configure()
    {
        $this->setName('tag')
            ->setDefinition($this->createDefinition());
    }

    private function createDefinition()
    {
        return new InputDefinition([
            new InputArgument('add')
        ]);
    }
}