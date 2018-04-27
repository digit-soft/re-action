<?php

namespace Reaction\Base;

use Reaction\Exceptions\InvalidArgumentException;
use Reaction\Validators\RegularExpressionValidator;

/**
 * Trait EventEmitterWildcardTrait
 * @package Reaction\Base
 */
trait EventEmitterWildcardTrait
{
    protected $listeners = [];
    protected $onceListeners = [];
    protected $listenersWildcard = [];
    protected $onceListenersWildcard = [];

    public function on($event, callable $listener)
    {
        if ($event === null) {
            throw new InvalidArgumentException('event name must not be null');
        }

        list($event, $isWildcard) = $this->processEventName($event);

        if ($isWildcard) {
            return $this->onWildcard($event, $listener);
        }

        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }

        $this->listeners[$event][] = $listener;

        return $this;
    }

    public function once($event, callable $listener)
    {
        if ($event === null) {
            throw new InvalidArgumentException('event name must not be null');
        }

        list($event, $isWildcard) = $this->processEventName($event);

        if ($isWildcard) {
            return $this->onceWildcard($event, $listener);
        }

        if (!isset($this->onceListeners[$event])) {
            $this->onceListeners[$event] = [];
        }

        $this->onceListeners[$event][] = $listener;

        return $this;
    }

    public function removeListener($event, callable $listener)
    {
        if ($event === null) {
            throw new InvalidArgumentException('event name must not be null');
        }

        if (isset($this->listeners[$event])) {
            $index = \array_search($listener, $this->listeners[$event], true);
            if (false !== $index) {
                unset($this->listeners[$event][$index]);
                if (\count($this->listeners[$event]) === 0) {
                    unset($this->listeners[$event]);
                }
            }
        }

        if (isset($this->onceListeners[$event])) {
            $index = \array_search($listener, $this->onceListeners[$event], true);
            if (false !== $index) {
                unset($this->onceListeners[$event][$index]);
                if (\count($this->onceListeners[$event]) === 0) {
                    unset($this->onceListeners[$event]);
                }
            }
        }
    }

    public function removeAllListeners($event = null)
    {
        if ($event !== null) {
            unset($this->listeners[$event]);
        } else {
            $this->listeners = [];
        }

        if ($event !== null) {
            unset($this->onceListeners[$event]);
        } else {
            $this->onceListeners = [];
        }
    }

    public function listeners($event = null): array
    {
        if ($event === null) {
            $events = [];
            $eventNames = \array_unique(
                \array_merge(\array_keys($this->listeners), \array_keys($this->onceListeners))
            );
            foreach ($eventNames as $eventName) {
                $events[$eventName] = \array_merge(
                    isset($this->listeners[$eventName]) ? $this->listeners[$eventName] : [],
                    isset($this->onceListeners[$eventName]) ? $this->onceListeners[$eventName] : []
                );
            }
            return $events;
        }

        return \array_merge(
            isset($this->listeners[$event]) ? $this->listeners[$event] : [],
            isset($this->onceListeners[$event]) ? $this->onceListeners[$event] : []
        );
    }

    public function emit($event, array $arguments = [])
    {
        if ($event === null) {
            throw new InvalidArgumentException('event name must not be null');
        }

        if (isset($this->listeners[$event])) {
            foreach ($this->listeners[$event] as $listener) {
                $listener(...$arguments);
            }
        }

        if (isset($this->onceListeners[$event])) {
            $listeners = $this->onceListeners[$event];
            unset($this->onceListeners[$event]);
            foreach ($listeners as $listener) {
                $listener(...$arguments);
            }
        }

        //Wildcard listeners
        $listenersWc = $this->findWildcardListeners($event);
        if (!empty($listenersWc)) {
            foreach ($listenersWc as $eventName => $listeners) {
                foreach ($listeners as $listener) {
                    $listener(...$arguments);
                }
            }
        }
        //Wildcard listeners (once)
        $listenersOnceWc = $this->findWildcardListeners($event, 'onceListenersWildcard');
        if (!empty($listenersOnceWc)) {
            foreach ($listenersOnceWc as $eventName => $listeners) {
                unset($this->onceListenersWildcard[$eventName]);
                foreach ($listeners as $listener) {
                    $listener(...$arguments);
                }
            }
        }
    }

    protected function onWildcard($event, callable $listener) {
        if (!isset($this->listenersWildcard[$event])) {
            $this->listenersWildcard[$event] = [];
        }

        $this->listenersWildcard[$event][] = $listener;

        return $this;
    }

    protected function onceWildcard($event, callable $listener) {
        if (!isset($this->onceListenersWildcard[$event])) {
            $this->onceListenersWildcard[$event] = [];
        }

        $this->onceListenersWildcard[$event][] = $listener;

        return $this;
    }

    /**
     * Process event name and find wildcards and regex
     * @param string $event
     * @return array
     */
    protected function processEventName($event) {
        //Not a regular expression or wildcard
        if (!preg_match('/(\~|\*|\^|\$|\?|\(|\)|\[|\])/', $event)) return [$event, false];
        //Regular expression begins with ~ (tilda symbol)
        if (strpos($event, '~') !== 0) {
            //Wildcards with stars placeholder
            $starPl = '__STAR__';
            //Replace some regex symbols
            $event = str_replace('\\*', $starPl, $event);
            $event = preg_replace_callback('/(^|[^\\\])(\.|\$|\[|\]|\(|\)|\?|\+)/', function ($matches) {
                return $matches[1] . '\\' . $matches[2];
            }, $event);
            //Replace wildcard with regex
            $event = str_replace('*', '(.*)', $event);
            //Return back slash prefixed star
            $event = str_replace($starPl, '\\*', $event);
        } else {
            //Remove ~ symbol from regex
            $event = substr($event, 1);
        }
        //Add regex slashes
        if (strcmp(substr($event, 0, 1), '/') !== 0 || strcmp(substr($event, -1), '/') !== 0) {
            $event = '/' . $event . '/';
        }
        return [$event, true];
    }

    /**
     * Find wildcard event handlers
     * @param string $event
     * @param string $listenersKey
     * @return array
     */
    protected function findWildcardListeners($event, $listenersKey = 'listenersWildcard') {
        $listeners = [];
        $keys = array_keys($this->{$listenersKey});
        if (!empty($keys)) {
            foreach ($keys as $key) {
                if (preg_match($key, $event)) {
                    $listeners[$key] = $this->{$listenersKey}[$key];
                }
            }
        }
        return $listeners;
    }
}