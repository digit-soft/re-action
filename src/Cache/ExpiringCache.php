<?php

namespace Reaction\Cache;

use React\EventLoop\Timer\TimerInterface;
use React\Promise\ExtendedPromiseInterface;
use Reaction\Helpers\ArrayHelper;

/**
 * Class ExpiringCache
 * @package Reaction\Cache
 */
abstract class ExpiringCache extends ExtendedCache implements ExpiringCacheInterface
{
    /**
     * @var string Record timestamp key
     */
    public $timestampKey = '_ts';
    /**
     * @var string User data timestamp key
     */
    public $dataKey = '_data';
    /**
     * @var string Tags key in record
     */
    public $tagsKey = '_tags';
    /**
     * @var int Timer interval seconds
     */
    public $timerInterval = 3;
    /**
     * @var int Default cache lifetime
     */
    public $lifetimeDefault = 3600;
    /**
     * @var array Keys with timestamps
     */
    protected $_timestamps = [];
    /**
     * @var TimerInterface
     */
    protected $_timer;

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->createCleanupTimer();
        parent::init();
    }

    /**
     * Get data from cache
     * @param string|array $key
     * @return ExtendedPromiseInterface  with data then finished
     */
    abstract public function get($key);

    /**
     * Write data to cache
     * @param string|array $key Cache key
     * @param mixed        $value Data
     * @param integer|null $lifetime Cache lifetime in seconds
     * @param array        $tags Possible tags
     * @return ExtendedPromiseInterface  with bool then finished
     */
    abstract public function set($key, $value, $lifetime = null, $tags = []);

    /**
     * @param string|array $key
     * @return ExtendedPromiseInterface  with bool 'true' then finished
     */
    abstract public function remove($key);

    /**
     * Checks that key exists in cache
     * @param string|array $key
     * @return ExtendedPromiseInterface  with bool
     */
    abstract public function exists($key);

    /**
     * Cleanup callback for timer
     */
    public function cleanupTimerCallback() {
        $now = time();
        $keys = [];
        foreach ($this->_timestamps as $key => $timestamp) {
            if ($timestamp < $now) {
                $keys[] = $key;
            }
        }
        if (!empty($keys)) {
            $this->removeMultiple($keys);
        }
    }

    /**
     * Pack record into array with needed format
     * @param mixed $record
     * @return array
     */
    protected function packRecord($record = []) {
        $tsKey = $this->timestampKey;
        $dtKey = $this->dataKey;
        $data = $record;
        $record = [ ];
        $record[$dtKey] = $data;
        $record[$tsKey] = time();
        return $record;
    }

    /**
     * Unpack data from record
     * @param array $record
     * @return mixed
     */
    protected function unpackRecord($record = []) {
        $tsKey = $this->timestampKey;
        $dtKey = $this->dataKey;
        if (!is_array($record) || !isset($record[$tsKey]) || !ArrayHelper::keyExists($dtKey, $record)) {
            return $record;
        }
        return $record[$dtKey];
    }

    /**
     * Create cleanup timer
     */
    protected function createCleanupTimer() {
        if (isset($this->_timer)) {
            $this->loop->cancelTimer($this->_timer);
        }
        $this->_timer = $this->loop->addPeriodicTimer($this->timerInterval, [$this, 'cleanupTimerCallback']);
    }

    /**
     * Get Event loop
     * @return \React\EventLoop\LoopInterface
     */
    protected function getLoop() {
        return \Reaction::$app->loop;
    }
}