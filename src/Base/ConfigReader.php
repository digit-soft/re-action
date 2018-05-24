<?php

namespace Reaction\Base;

use Reaction\Helpers\ArrayHelper;
use Reaction\Helpers\IgnoreArrayValue;
use Reaction\Helpers\ReplaceArrayValue;
use Reaction\Helpers\UnsetArrayValue;

/**
 * Class ConfigReader. Reads content of file configs and merges those into one array
 * @package Reaction\Base
 */
class ConfigReader extends BaseObject
{
    /** @var string Config files dir path */
    public $path;
    /** @var string Application type (web|console) */
    public $appType = 'web';
    /** @var array Config files extensions */
    public $extensions = ['php'];
    /** @var array Config files possible names */
    public $names;
    /** @var array Config array */
    public $data = [];
    /**
     * @var string Default configs path (relative to re-action/src dir)
     */
    public $configPathDefault = 'Config';

    /**
     * @inheritdoc
     */
    public function init() {
        if(isset($this->path)) {
            $this->data = $this->readData();
        }
    }

    /**
     * Get config data by its key
     * @param string $key
     * @param mixed  $defaultValue
     * @return mixed
     */
    public function get($key, $defaultValue = null) {
        return ArrayHelper::getValue($this->data, $key, $defaultValue);
    }

    /**
     * Set config data for a key
     * @param string $key
     * @param mixed  $value
     */
    public function set($key, $value) {
        $this->data[$key] = $value;
    }

    /**
     * Generate config files names
     * @return array
     */
    protected function generateNames() {
        $env = getenv('APP_ENV');
        $env = strtolower((!empty($env) ? $env : \Reaction::APP_ENV_PROD));
        $suffixes = ['', $env, 'local'];
        $templates = ['main', $this->appType];
        $configFiles = [];
        foreach ($templates as $tpl) {
            foreach ($suffixes as $sfx) {
                $tplSuffix = $sfx ? '.' . $sfx : '';
                foreach ($this->extensions as $ext) {
                    $configFiles[] = $tpl . $tplSuffix . '.' . $ext;
                }
            }
        }
        return $configFiles;
    }

    /**
     * Merge config data with given one
     * @param array       $data
     * @param string|null $key
     */
    public function merge($data = [], $key = null) {
        if (!isset($key)) {
            $this->data = ArrayHelper::merge($this->data, $data);
        } else {
            if (!isset($this->data[$key])) {
                $this->data[$key] = $data;
            } else {
                if(is_array($this->data[$key])) {
                    $this->data[$key] = ArrayHelper::merge($this->data[$key], $data);
                } else {
                    $this->data[$key] = $data;
                }
            }
        }
    }

    /**
     * Read configs and merge
     * @return array
     */
    public function readData() {
        if (empty($this->names)) {
            $this->names = $this->generateNames();
        }
        //Add default configs
        $defaultPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . $this->configPathDefault;
        $paths = [ $defaultPath, $this->path ];
        $confData = [];
        foreach ($paths as $basePath) {
            foreach ($this->names as $_name_) {
                $_fileName_ = $this->normalizeConfigPath($_name_, $basePath);
                if (($fileConfig = $this->readFileData($_fileName_)) !== null) {
                    $confData[] = $fileConfig;
                }
            }
        }
        if (empty($confData)) {
            return [];
        } elseif (count($confData) === 1) {
            return reset($confData);
        }
        return static::mergeConfigs(...$confData);
    }

    /**
     * Read particular config file
     * @param string $_fileName_
     * @return array|null
     */
    protected function readFileData($_fileName_ = null) {
        if (!file_exists($_fileName_)) {
            return null;
        }
        /** @noinspection PhpIncludeInspection */
        $_conf_data_ = include $_fileName_;
        return is_array($_conf_data_) ? $_conf_data_ : [];
    }

    /**
     * Normalize config file path
     * @param string $filePath
     * @param string|null $basePath
     * @return string
     */
    protected function normalizeConfigPath($filePath, $basePath = null) {
        if (null === $basePath) {
            $basePath = $this->path;
        }
        $fullPath = strpos($filePath, '/') === 0 ? $filePath : $basePath . DIRECTORY_SEPARATOR . $filePath;
        return $fullPath;
    }

    /**
     * Merge config arrays.
     * Same as ArrayHelper::merge() but without adding new elements to indexed array if already exists.
     * @param array $a
     * @param array $b
     * @return array
     */
    protected static function mergeConfigs($a, $b) {
        $args = func_get_args();
        $res = array_shift($args);
        while (!empty($args)) {
            foreach (array_shift($args) as $k => $v) {
                if ($v instanceof UnsetArrayValue) {
                    unset($res[$k]);
                } elseif ($v instanceof ReplaceArrayValue) {
                    $res[$k] = $v->value;
                } elseif ($v instanceof IgnoreArrayValue) {
                    if(!array_key_exists($k, $res)) {
                        $res[$k] = $v->value;
                    }
                } elseif (is_array($v) && isset($res[$k]) && is_array($res[$k])) {
                    $res[$k] = self::mergeConfigs($res[$k], $v);
                } else {
                    $res[$k] = $v;
                }
            }
        }

        return ArrayHelper::cleanupMergedValues($res);
    }
}