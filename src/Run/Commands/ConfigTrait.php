<?php
/**
 * Created by PhpStorm.
 * User: digit
 * Date: 05.07.18
 * Time: 17:05
 */

namespace Reaction\Run\Commands;


use Reaction\Base\ConfigReader;
use Reaction\Helpers\ArrayHelper;
use Reaction\StaticApplicationInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

trait ConfigTrait
{
    /**
     * @var ConfigReader
     */
    private $_reader;
    /**
     * @var string
     */
    protected $confDir = 'Config';

    /**
     * Config application's wide options
     * @param Command $command
     */
    protected function configDefaultOptions(Command $command)
    {
        $defaultConfPath = getcwd() . '/' . $this->confDir;
        $command->addArgument('working-directory', InputOption::VALUE_REQUIRED, 'Working directory', getcwd());
        $command
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Application type', StaticApplicationInterface::APP_TYPE_WEB)
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Host to run', '127.0.0.1')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Port to run', 4000)
            ->addOption('debug', null, InputOption::VALUE_REQUIRED, 'Enable debug', true)
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Configs dir path', $defaultConfPath);
    }

    /**
     * Get option from input or config array
     * @param InputInterface $input
     * @param array          $config
     * @param string         $optionName
     * @param string         $configName
     * @return mixed
     */
    protected function optionOrConfigValue(InputInterface $input, $config, $optionName, $configName = null)
    {
        $optionValue = $this->getOptionWithTypeCast($input, $optionName);
        if ($optionValue !== null) {
            return $optionValue;
        }

        $configValue = ArrayHelper::getValue($config, $configName, null);

        return isset($configValue) ? $configValue : $input->getOption($optionName);
    }

    /**
     * Get option with typecast
     * @param InputInterface $input
     * @param string         $optionName
     * @param mixed          $defaultValue
     * @return mixed
     */
    protected function getOptionWithTypeCast(InputInterface $input, $optionName, $defaultValue = null)
    {
        $optionNameExp = explode(':', $optionName);
        if (count($optionNameExp) > 1) {
            $optionName = $optionNameExp[0];
            $optionType = $optionNameExp[1];
        }
        if (!$input->hasParameterOption('--' . $optionName)) {
            return $defaultValue;
        }
        $optionValue = $input->getOption($optionName);
        if (isset($optionType) && function_exists($optionType . 'val')) {
            $typeCastFunc = $optionType . 'val';
            $optionValue = $typeCastFunc($optionValue);
        }
        return $optionValue;
    }

    /**
     * Load config data from file
     * @param string $path
     * @param string $appType
     * @return array
     */
    protected function loadConfigFromFile($path, $appType = StaticApplicationInterface::APP_TYPE_WEB)
    {
        if (!isset($this->_reader)) {
            $reader = new ConfigReader([
                'path' => $path,
                'appType' => $appType,
            ]);
            $this->_reader = $reader;
        }
        return $this->_reader->data;
    }

    /**
     * Get path of config file
     * @param InputInterface $input
     * @return string
     * @throws \Exception
     */
    protected function getConfigPath(InputInterface $input)
    {
        $configOption = $input->getOption('config');
        if ($configOption && !file_exists($configOption)) {
            throw new \Exception(sprintf('Config file not found: "%s"', $configOption));
        }
        $possiblePaths = [
            $configOption,
            $this->confDir,
            sprintf('%s/%s', dirname($GLOBALS['argv'][0]), $this->confDir)
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path) && is_dir($path)) {
                return realpath($path);
            }
        }
        return '';
    }

    /**
     * Load config data
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param bool            $saveConfig
     * @return array|mixed
     * @throws \Exception
     */
    protected function loadConfig(InputInterface $input, OutputInterface $output, $saveConfig = true)
    {
        $configsPath = $this->getConfigPath($input);
        $appType = $input->getOption('config');
        $config = $this->loadConfigFromFile($configsPath, $appType);
        /**
         * Array format
         * [ 'OPTION_NAME:TYPE' => 'CONFIG_ARRAY_PATH', ]
         */
        $configOptions = [
            'host' => 'appStatic.hostname',
            'port:int' => 'appStatic.port',
            'debug:bool' => 'appStatic.debug',
        ];

        foreach ($configOptions as $optionName => $configName) {
            $value = $this->optionOrConfigValue($input, $config, $optionName, $configName);
            ArrayHelper::setValue($config, $configName, $value);
        }

        if ($saveConfig) {
            $this->_reader->data = $config;
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
    protected function initializeConfig(InputInterface $input, OutputInterface $output, $render = true)
    {
        if ($workingDir = $input->getArgument('working-directory')) {
            chdir($workingDir);
        }
        $config = $this->loadConfig($input, $output);

        if ($render) {
            $this->renderConfig($output, $config);
        }
        return $config;
    }

    /**
     * Render config as table in console
     * @param OutputInterface $output
     * @param array           $config
     */
    protected function renderConfig(OutputInterface $output, array $config)
    {
        $table = new Table($output);
        $rows = $this->renderConfigRows($config);
        $table->addRows($rows);
        $table->render();
    }

    /**
     * Normalize config rows for render in table
     * @param array $rows
     * @param int   $cardinality
     * @return array
     */
    private function renderConfigRows($rows, $cardinality = 0) {
        if ($cardinality > 0) {
            $prefix = str_repeat('  ', $cardinality - 1) . '↳ ';
        } else {
            $prefix = '';
        }
        $rowsNew = [];
        foreach ($rows as $key => $value) {
            $keyStr = !is_numeric($key) ? $prefix . $key : '';
            $valueArray = null;
            if (is_array($value)) {
                if (ArrayHelper::isIndexed($value)) {
                    foreach ($value as &$valueChild) {
                        $valueChild = $this->renderValue($valueChild);
                    }
                    $valueStr = '[' . implode(', ', $value) . ']';
                } else {
                    $valueStr = '';
                    $valueArray = $this->renderConfigRows($value, $cardinality + 1);
                }
            } else {
                $valueStr = $this->renderValue($value);
            }
            $valueStr = '<comment>' . $valueStr . '</comment>';
            $rowsNew[] = [$keyStr, $valueStr];
            if (isset($valueArray)) {
                $rowsNew = ArrayHelper::merge($rowsNew, $valueArray);
            }
        }
        return $rowsNew;
    }

    /**
     * Get string representation of value
     * @param mixed $value
     * @return string
     */
    private function renderValue($value)
    {
        $type = gettype($value);
        $valueStr = null;
        switch ($type) {
            case 'object':
                $valueStr = 'object(' . get_class($value) . ')';
                break;
            case 'array':
                $valueStr = 'array(' . count($value) . ')';
                break;
            case 'boolean':
                $valueStr = $value ? 'TRUE' : 'FALSE';
                break;
            case 'string':
                $valueStr = '"' . $value . '"';
                break;
            default: $valueStr = $value;
        }
        return $valueStr;
    }
}