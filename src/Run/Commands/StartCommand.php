<?php

namespace Reaction\Run\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class StartCommand
 * @package Reaction\Run\Commands
 */
class StartCommand extends Command
{
    use ConfigTrait;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        parent::configure();
        $this->setName('start')
            ->setDescription('Start web application');
        $this->configDefaultOptions($this);
        $this->addOption('printConfig', null, InputOption::VALUE_REQUIRED, 'Print config', true);
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $printConfig = !empty($this->getOptionWithTypeCast($input,'printConfig'));
        $this->initializeConfig($input, $output, $printConfig);
        $output->writeln('start app');
        //TODO: START CODE MUST BE HERE
    }
}