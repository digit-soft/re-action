<?php

namespace Reaction;

use React\EventLoop\LoopInterface;
use React\Http\Server as Http;
use React\Socket\Server as Socket;
use React\Socket\ServerInterface as SocketServerInterface;
use Reaction\DI\ServiceLocator;
use Reaction\Exceptions\InvalidArgumentException;
use Reaction\Middleware\RequestReplacer;

/**
 * Class StaticApplication
 * @package Reaction
 */
class StaticApplication extends ServiceLocator implements StaticApplicationInterface
{
    /** @var string */
    public $envType = self::APP_ENV_DEV;
    /** @var bool */
    public $debug = false;
    /** @var bool */
    public $test = false;
    /** @var string */
    public $charset = 'UTF-8';
    /** @var string Default language */
    public $language = 'en-US';
    /** @var string Source language */
    public $sourceLanguage = 'en-US';
    /** @var string Default timezone */
    public $timeZone = 'UTC';
    /** @var string */
    public $hostname;
    /** @var integer */
    public $port;
    /** @var LoopInterface */
    public $loop;
    /** @var Http */
    public $http;
    /** @var Socket */
    public $socket;

    /** @var array Added middleware */
    protected $middleware = [];
    /** @var array Aliases defined */
    protected $aliases = [];

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->loop = \Reaction::create(\React\EventLoop\LoopInterface::class);
    }

    /**
     * Run application
     * @throws Exceptions\InvalidConfigException
     */
    public function run() {
        // TODO: Make more serious Exception handler :)
        $this->router->registerControllers();
        $this->router->publishRoutes();
        $this->addMiddleware([$this->router, 'resolveRequest']);
        $this->socket = \Reaction::create(SocketServerInterface::class);
        $this->http = \Reaction::create(Http::class, [$this->middleware]);
        $this->http->listen($this->socket);
        echo "Running server on $this->hostname:$this->port\n";
        //Exception handler
        $this->http->on('error', function (\Throwable $error) {
            $message = get_class($error) . "\n" . $error->getMessage() . "\n" . $error->getTraceAsString();
            $this->logger->alert($message);
            if (!empty($error->getPrevious())) {
                $this->logger->info("Previous\n" . $error->getPrevious()->getMessage()
                    . "\n" . $error->getPrevious()->getFile()
                    . " #" . $error->getPrevious()->getLine());
            }
        });
        $this->loop->run();
    }

    /**
     * Add middleware to application
     * @param callable|array $middleware
     */
    public function addMiddleware($middleware) {
        if(!is_callable($middleware) && !is_array($middleware)) {
            throw new InvalidArgumentException("Middleware must be a valid callable");
        } else {
            $this->middleware[] = $middleware;
        }
    }

    /**
     * Translates a path alias into an actual path.
     *
     * The translation is done according to the following procedure:
     *
     * 1. If the given alias does not start with '@', it is returned back without change;
     * 2. Otherwise, look for the longest registered alias that matches the beginning part
     *    of the given alias. If it exists, replace the matching part of the given alias with
     *    the corresponding registered path.
     * 3. Throw an exception or return false, depending on the `$throwException` parameter.
     *
     * For example, by default '@reaction' is registered as the alias to the Reaction framework directory,
     * say '/path/to/reaction'. The alias '@reaction/Web' would then be translated into '/path/to/reaction/Web'.
     *
     * If you have registered two aliases '@foo' and '@foo/bar'. Then translating '@foo/bar/config'
     * would replace the part '@foo/bar' (instead of '@foo') with the corresponding registered path.
     * This is because the longest alias takes precedence.
     *
     * However, if the alias to be translated is '@foo/barbar/config', then '@foo' will be replaced
     * instead of '@foo/bar', because '/' serves as the boundary character.
     *
     * Note, this method does not check if the returned path exists or not.
     *
     * See the [guide article on aliases](guide:concept-aliases) for more information.
     *
     * @param string $alias the alias to be translated.
     * @param bool $throwException whether to throw an exception if the given alias is invalid.
     * If this is false and an invalid alias is given, false will be returned by this method.
     * @return string|bool the path corresponding to the alias, false if the root alias is not previously registered.
     * @throws InvalidArgumentException if the alias is invalid while $throwException is true.
     * @see setAlias()
     */
    public function getAlias($alias, $throwException = true)
    {
        if (strncmp($alias, '@', 1)) {
            // not an alias
            return $alias;
        }

        $pos = strpos($alias, '/');
        $root = $pos === false ? $alias : substr($alias, 0, $pos);

        if (isset($this->aliases[$root])) {
            if (is_string($this->aliases[$root])) {
                $aliasResolved = $this->aliases[$root];
                if(strncmp($this->aliases[$root], '@', 1) === 0) {
                    $aliasResolved = $this->getAlias($aliasResolved);
                }
                return $pos === false ? $aliasResolved : $aliasResolved . substr($alias, $pos);
            }

            foreach ($this->aliases[$root] as $name => $path) {
                if (strpos($alias . '/', $name . '/') === 0) {
                    return $path . substr($alias, strlen($name));
                }
            }
        }

        if ($throwException) {
            throw new InvalidArgumentException("Invalid path alias: $alias");
        }

        return false;
    }

    /**
     * Returns the root alias part of a given alias.
     * A root alias is an alias that has been registered via [[setAlias()]] previously.
     * If a given alias matches multiple root aliases, the longest one will be returned.
     * @param string $alias the alias
     * @return string|bool the root alias, or false if no root alias is found
     */
    public function getRootAlias($alias)
    {
        $pos = strpos($alias, '/');
        $root = $pos === false ? $alias : substr($alias, 0, $pos);

        if (isset($this->aliases[$root])) {
            if (is_string($this->aliases[$root])) {
                return $root;
            }

            foreach ($this->aliases[$root] as $name => $path) {
                if (strpos($alias . '/', $name . '/') === 0) {
                    return $name;
                }
            }
        }

        return false;
    }

    /**
     * Registers a path alias.
     *
     * A path alias is a short name representing a long path (a file path, a URL, etc.)
     * For example, we use '@reaction' as the alias of the path to the Reaction framework directory.
     *
     * A path alias must start with the character '@' so that it can be easily differentiated
     * from non-alias paths.
     *
     * Note that this method does not check if the given path exists or not. All it does is
     * to associate the alias with the path.
     *
     * Any trailing '/' and '\' characters in the given path will be trimmed.
     *
     * See the [guide article on aliases](guide:concept-aliases) for more information.
     *
     * @param string $alias the alias name (e.g. "@yii"). It must start with a '@' character.
     * It may contain the forward slash '/' which serves as boundary character when performing
     * alias translation by [[getAlias()]].
     * @param string $path the path corresponding to the alias. If this is null, the alias will
     * be removed. Trailing '/' and '\' characters will be trimmed. This can be
     *
     * - a directory or a file path (e.g. `/tmp`, `/tmp/main.txt`)
     * - a URL (e.g. `http://www.example.com`)
     * - a path alias (e.g. `@root/config`). In this case, the path alias will be converted into the
     *   actual path first by calling [[getAlias()]].
     *
     * @throws InvalidArgumentException if $path is an invalid alias.
     * @see getAlias()
     */
    public function setAlias($alias, $path)
    {
        if (strncmp($alias, '@', 1)) {
            $alias = '@' . $alias;
        }
        $pos = strpos($alias, '/');
        $root = $pos === false ? $alias : substr($alias, 0, $pos);
        if ($path !== null) {
            $path = strncmp($path, '@', 1) ? rtrim($path, '\\/') : $this->getAlias($path);
            if (!isset($this->aliases[$root])) {
                if ($pos === false) {
                    $this->aliases[$root] = $path;
                } else {
                    $this->aliases[$root] = [$alias => $path];
                }
            } elseif (is_string($this->aliases[$root])) {
                if ($pos === false) {
                    $this->aliases[$root] = $path;
                } else {
                    $this->aliases[$root] = [
                        $alias => $path,
                        $root => $this->aliases[$root],
                    ];
                }
            } else {
                $this->aliases[$root][$alias] = $path;
                krsort($this->aliases[$root]);
            }
        } elseif (isset($this->aliases[$root])) {
            if (is_array($this->aliases[$root])) {
                unset($this->aliases[$root][$alias]);
            } elseif ($pos === false) {
                unset($this->aliases[$root]);
            }
        }
    }

    /**
     * Set aliases from config
     * @param array $aliases
     */
    public function setAliases($aliases) {
        foreach ($aliases as $alias => $path) { $this->setAlias($alias, $path); }
    }

    /**
     * Get path for '@views'
     * @return string
     */
    public function getViewPath()
    {
        return $this->getAlias('@views');
    }

    /**
     * Get path for '@runtime'
     * @return string
     */
    public function getRuntimePath()
    {
        return $this->getAlias('@runtime');
    }

    /**
     * Get Application auth manager
     * @return \Reaction\Rbac\ManagerInterface|null
     * @throws Exceptions\InvalidConfigException
     */
    public function getAuthManager() {
        /** @var \Reaction\Rbac\ManagerInterface|null $component */
        $component = $this->has('authManager') ? $this->get('authManager') : null;
        return $component;
    }
}