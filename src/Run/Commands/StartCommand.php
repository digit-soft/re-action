<?php

namespace Reaction\Run\Commands;

use Reaction\Run\Application;
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
        $this->addOption('print-config', null, InputOption::VALUE_REQUIRED, 'Print config', true);
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $printConfig = $this->getOptionWithTypeCast($input,'print-config');
        $appType = $input->getOption('app-type');
        $this->initializeConfig($input, $output, $printConfig);
        $output->writeln('start app');

        /** @var Application $application */
        $application = $this->getApplication();
        $configsPath = $this->getConfigPath($input);
        \Reaction::init($application->loader, $configsPath, $appType);
        \Reaction::$app->initHttp();
        \Reaction::$app->run();
    }
}