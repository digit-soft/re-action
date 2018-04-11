<?php

namespace Reaction\Base;


use Reaction\Helpers\ArrayHelper;

class ConfigReader extends BaseObject
{
    /** @var string Config files dir path */
    public $path;
    /** @var array Config files extensions */
    public $extensions = ['php'];
    /** @var array Config files possible names */
    public $names;
    /** @var array Config array */
    public $data = [];

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
        $_env_ = getenv('APP_ENV');
        $_env_ = strtolower((!empty($_env_) ? $_env_ : \Reaction::APP_ENV_PROD));
        $_suffixes_ = ['', $_env_, 'local'];
        $_templates_ = ['base', 'main'];
        $configFiles = [];
        foreach ($_templates_ as $_tpl_) {
            foreach ($_suffixes_ as $_sfx_) {
                $_tpl_sfx_ = $_tpl_ . ($_sfx_ ? '.' . $_sfx_ : '');
                foreach ($this->extensions as $_ext_) {
                    $configFiles[] = $_tpl_sfx_ . '.' . $_ext_;
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
        if(!isset($key)) {
            $this->data = ArrayHelper::merge($this->data, $data);
        } else {
            if(!isset($this->data[$key])) { $this->data[$key] = $data; }
            else {
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
        if(empty($this->names)) {
            $this->names = $this->generateNames();
        }
        $_confData_ = [];
        foreach ($this->names as $_name_) {
            $_fileName_ = $this->path . DIRECTORY_SEPARATOR . $_name_;
            if(!file_exists($_fileName_)) continue;
            /** @noinspection PhpIncludeInspection */
            $_confData_[] = include $_fileName_;
        }
        if(empty($_confData_)) return [];
        elseif (count($_confData_) === 1) return reset($_confData_);
        return ArrayHelper::merge(...$_confData_);
    }
}