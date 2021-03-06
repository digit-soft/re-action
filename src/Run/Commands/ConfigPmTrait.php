<?php

namespace Reaction\Run\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Trait ConfigPmTrait
 * @package Reaction\Run\Commands
 */
trait ConfigPmTrait
{

    protected $file = './pm.json';

    /**
     * Configure PM options
     * @param Command $command
     */
    protected function configurePMOptions(Command $command)
    {
        $iniMemLimit = ceil($this->getIniMemoryLimit() * 0.9);
        $command
            ->addOption('bridge', null, InputOption::VALUE_REQUIRED, 'Bridge for converting React Psr7 requests to target framework.', 'ReactionBridge')
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Load-Balancer host. Default is 127.0.0.1', '127.0.0.1')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Load-Balancer port. Default is 8080', 8080)
            ->addOption('workers', null, InputOption::VALUE_REQUIRED, 'Worker count. Default is 8. Should be minimum equal to the number of CPU cores.', 8)
            ->addOption('app-env', null, InputOption::VALUE_REQUIRED, 'The environment that your application will use to bootstrap (if any)', 'dev')
            ->addOption('debug', null, InputOption::VALUE_REQUIRED, 'Enable/Disable debugging so that your application is more verbose, enables also hot-code reloading. 1|0', 0)
            ->addOption('logging', null, InputOption::VALUE_REQUIRED, 'Enable/Disable http logging to stdout. 1|0', 1)
            ->addOption('static-directory', null, InputOption::VALUE_REQUIRED, 'Static files root directory, if not provided static files will not be served', '')
            ->addOption('max-requests', null, InputOption::VALUE_REQUIRED, 'Max requests per worker until it will be restarted', 1000)
            ->addOption('ttl', null, InputOption::VALUE_REQUIRED, 'Time to live for a worker until it will be restarted', null)
            ->addOption('populate-server-var', null, InputOption::VALUE_REQUIRED, 'If a worker application uses $_SERVER var it needs to be populated by request data 1|0', 1)
            ->addOption('bootstrap', null, InputOption::VALUE_REQUIRED, 'Class responsible for bootstrapping the application', 'Reaction\PM\Bootstraps\ReactionBootstrap')
            ->addOption('cli-path', null, InputOption::VALUE_REQUIRED, 'Full path to the php-cli executable', false)
            ->addOption('socket-path', null, InputOption::VALUE_REQUIRED, 'Path to a folder where socket files will be placed. Relative to working-directory or cwd()', '.pm/run/')
            ->addOption('pidfile', null, InputOption::VALUE_REQUIRED, 'Path to a file where the pid of the master process is going to be stored', '.pm/pm.pid')
            ->addOption('reload-timeout', null, InputOption::VALUE_REQUIRED, 'The number of seconds to wait before force closing a worker during a reload, or -1 to disable. Default: 30', 30)
            ->addOption('max-memory-usage', null, InputOption::VALUE_REQUIRED, 'The number of slave memory usage in bytes until it will be restarted, or 0 to disable.', $iniMemLimit)
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file', '');
    }

    /**
     * Get path of config file
     * @param InputInterface $input
     * @param bool $create
     * @return string
     * @throws \Exception
     */
    protected function getPmConfigPath(InputInterface $input, $create = false)
    {
        $configOption = $input->getOption('config');
        if ($configOption && !file_exists($configOption)) {
            if ($create) {
                file_put_contents($configOption, json_encode([]));
            } else {
                throw new \Exception(sprintf('Config file not found: "%s"', $configOption));
            }
        }
        $possiblePaths = [
            $configOption,
            $this->file,
            sprintf('%s/%s', dirname($GLOBALS['argv'][0]), $this->file)
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return realpath($path);
            }
        }
        return '';
    }

    /**
     * Load config file content
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return array|mixed
     * @throws \Exception
     */
    protected function loadPmConfig(InputInterface $input, OutputInterface $output)
    {
        $config = [];

        if ($path = $this->getPmConfigPath($input)) {
            $content = file_get_contents($path);
            $config = json_decode($content, true);
        }

        $config['bridge'] = $this->optionOrConfigValue($input, $config, 'bridge');
        $config['host'] = $this->optionOrConfigValue($input, $config, 'host');
        $config['port'] = (int)$this->optionOrConfigValue($input, $config, 'port');
        $config['workers'] = (int)$this->optionOrConfigValue($input, $config, 'workers');
        $config['app-env'] = $this->optionOrConfigValue($input, $config, 'app-env');
        $config['debug'] = $this->optionOrConfigValue($input, $config, 'debug');
        $config['logging'] = $this->optionOrConfigValue($input, $config, 'logging');
        $config['static-directory'] = $this->optionOrConfigValue($input, $config, 'static-directory');
        $config['bootstrap'] = $this->optionOrConfigValue($input, $config, 'bootstrap');
        $config['max-requests'] = (int)$this->optionOrConfigValue($input, $config, 'max-requests');
        $config['ttl'] = (int)$this->optionOrConfigValue($input, $config, 'ttl');
        $config['populate-server-var'] = (boolean)$this->optionOrConfigValue($input, $config, 'populate-server-var');
        $config['socket-path'] = $this->optionOrConfigValue($input, $config, 'socket-path');
        $config['pidfile'] = $this->optionOrConfigValue($input, $config, 'pidfile');
        $config['reload-timeout'] = $this->optionOrConfigValue($input, $config, 'reload-timeout');
        $config['max-memory-usage'] = $this->optionOrConfigValue($input, $config, 'max-memory-usage');

        $config['cli-path'] = $this->optionOrConfigValue($input, $config, 'cli-path');

        if (false === $config['cli-path']) {
            //not set in config nor in command options -> autodetect path
            $executableFinder = new PhpExecutableFinder();
            $binary = $executableFinder->find();
            $config['cli-path'] = $binary;

            if (false === $config['cli-path']) {
                $output->writeln('<error>PPM could find a php-cli path. Please specify by --cli-path=</error>');
                exit(1);
            }
        }

        return $config;
    }

    /**
     * Init config.
     * Locate file, load it and check values
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param bool            $render
     * @return array
     */
    protected function initializePmConfig(InputInterface $input, OutputInterface $output, $render = true)
    {
        if ($workingDir = $input->getArgument('working-directory')) {
            chdir($workingDir);
        }
        $config = $this->loadPmConfig($input, $output);

        if ($path = $this->getPmConfigPath($input)) {
            $modified = '';
            $fileConfig = json_decode(file_get_contents($path), true);
            if (json_encode($fileConfig) !== json_encode($config)) {
                $modified = ', modified by command arguments';
            }
            $output->writeln(sprintf('<info>Read configuration %s%s.</info>', $path, $modified));
        }
        $output->writeln(sprintf('<info>%s</info>', getcwd()));

        if ($render) {
            $this->renderConfig($output, $config);
        }
        return $config;
    }

    /**
     * Get memory_limit directive in bytes
     * @return int
     */
    private function getIniMemoryLimit()
    {
        $iniMemLimit = ini_get('memory_limit');
        if (!is_numeric($iniMemLimit)) {
            $iniSuffix = strtoupper(substr($iniMemLimit, -1));
            $iniMemLimit = substr($iniMemLimit, 0, -1);
            $iniMultiplier = 1024; //K
            $iniMultiplier = $iniSuffix === 'M' ? $iniMultiplier * 1024 : $iniMultiplier * 1024 * 1024; //M || G
            $iniMemLimit *= $iniMultiplier;
        }
        return (int)$iniMemLimit;
    }
}