<?php

namespace Reaction\Run\Commands;

use Reaction\PM\ProcessManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StartPmCommand extends Command
{
    use ConfigTrait;
    use ConfigPmTrait;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        parent::configure();
        $this->setName('start-pm')
            ->setDescription('Start with process manager')
            ->addOption('print-config', null, InputOption::VALUE_REQUIRED, 'Print config', true);

        $this->configDefaultOptions($this);
        $this->configurePMOptions($this);
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $printConfig = $this->getOptionWithTypeCast($input,'print-config');
        $appType = $input->getOption('app-type');

        $this->initializeConfig($input, $output, false);
        $config = $this->initializePmConfig($input, $output);

        $handler = new ProcessManager($output, $config['port'], $config['host'], $config['workers']);

        $handler->setBridge($config['bridge']);
        $handler->setAppEnv($config['app-env']);
        $handler->setDebug((boolean)$config['debug']);
        $handler->setReloadTimeout((int)$config['reload-timeout']);
        $handler->setLogging((boolean)$config['logging']);
        $handler->setAppBootstrap($config['bootstrap']);
        $handler->setMaxRequests($config['max-requests']);
        $handler->setTtl($config['ttl']);
        $handler->setPhpExecutable($config['cli-path']);
        $handler->setSocketPath($config['socket-path']);
        $handler->setPIDFile($config['pidfile']);
        $handler->setPopulateServer($config['populate-server-var']);
        $handler->setStaticDirectory($config['static-directory']);
        $handler->setMaxMemoryUsage($config['max-memory-usage']);
        $handler->run();

        return null;
    }
}