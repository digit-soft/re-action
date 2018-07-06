<?php

namespace Reaction;

use Psr\Http\Message\ServerRequestInterface;
use React\Filesystem\FilesystemInterface;
use React\Promise\PromiseInterface;
use Reaction\Base\Logger\LoggerInterface;
use Reaction\Db\DatabaseInterface;
use Reaction\Events\EventEmitterWildcardInterface;
use Reaction\I18n\I18N;
use Reaction\Promise\ExtendedPromiseInterface;
use Reaction\Routes\UrlManager;
use Reaction\Web\Response;
use Reaction\Web\Sessions\SessionHandlerInterface;

/**
 * Interface StaticApplicationInterface
 * @package Reaction
 * @property bool                             $debug
 * @property bool                             $test
 * @property string                           $envType
 * @property string                           $timeZone
 * @property string                           $charset
 * @property string                           $language
 * @property string                           $sourceLanguage
 * @property \React\EventLoop\LoopInterface   $loop
 * @property \React\Http\Server               $http
 * @property \React\Socket\Server             $socket
 * @property \Reaction\Routes\RouterInterface $router
 * @property \Reaction\Base\Security          $security
 * @property LoggerInterface                  $logger
 * TODO: Check that app level formatter is needed
 * @property \Reaction\I18n\Formatter         $formatter
 * @property SessionHandlerInterface          $sessionHandler
 * @property FilesystemInterface              $fs
 * @property UrlManager                       $urlManager
 * @property DatabaseInterface                $db
 * @property I18N                             $i18n
 * @property ExtendedPromiseInterface         $initPromise
 * @property bool                             $initialized
 */
interface StaticApplicationInterface extends EventEmitterWildcardInterface
{
    const APP_ENV_PROD      = 'production';
    const APP_ENV_DEV       = 'development';

    const APP_TYPE_WEB      = 'web';
    const APP_TYPE_CONSOLE  = 'console';

    /**
     * Run application
     */
    public function run();

    /**
     * Initialize router
     */
    public function initRouter();

    /**
     * Initialize with React HTTP and Sockets
     */
    public function initHttp();

    /**
     * Add middleware to application
     * @param callable|array $middleware
     */
    public function addMiddleware($middleware);

    /**
     * Process React request
     * @param ServerRequestInterface $request
     * @return ExtendedPromiseInterface
     */
    public function processRequest(ServerRequestInterface $request);

    /**
     * Create RequestApplicationInterface instance from react request
     * @param ServerRequestInterface $request
     * @return RequestApplicationInterface
     */
    public function createRequestApplication(ServerRequestInterface $request);

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
     * @see setAlias()
     */
    public function getAlias($alias, $throwException = true);

    /**
     * Returns the root alias part of a given alias.
     * A root alias is an alias that has been registered via [[setAlias()]] previously.
     * If a given alias matches multiple root aliases, the longest one will be returned.
     * @param string $alias the alias
     * @return string|bool the root alias, or false if no root alias is found
     */
    public function getRootAlias($alias);

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
     * @see getAlias()
     */
    public function setAlias($alias, $path);

    /**
     * Set aliases from config
     * @param array $aliases
     */
    public function setAliases($aliases);

    /**
     * Get path for '@views'
     * @return string
     */
    public function getViewPath();

    /**
     * Get path for '@runtime'
     * @return string
     */
    public function getRuntimePath();

    /**
     * Just for autocomplete. Method located in Component class
     * @return string
     */
    public function getBasePath();

    /**
     * Get Application auth manager
     * @return \Reaction\Rbac\ManagerInterface|null
     */
    public function getAuthManager();

    /**
     * Get default database
     * @return DatabaseInterface
     */
    public function getDb();

    /**
     * Get internationalization component
     * @return I18N
     */
    public function getI18n();

    /**
     * Returns a value indicating whether the locator has the specified component definition or has instantiated the component.
     * This method may return different results depending on the value of `$checkInstance`.
     *
     * - If `$checkInstance` is false (default), the method will return a value indicating whether the locator has the specified
     *   component definition.
     * - If `$checkInstance` is true, the method will return a value indicating whether the locator has
     *   instantiated the specified component.
     *
     * @param string $id component ID (e.g. `db`).
     * @param bool $checkInstance whether the method should check if the component is shared and instantiated.
     * @return bool whether the locator has the specified component definition or has instantiated the component.
     * @see set()
     */
    public function has($id, $checkInstance = false);

    /**
     * Returns the component instance with the specified ID.
     *
     * @param string $id component ID (e.g. `db`).
     * @param bool   $throwException whether to throw an exception if `$id` is not registered with the locator before.
     * @return \object|null the component of the specified ID. If `$throwException` is false and `$id`
     * is not registered before, null will be returned.
     * @see has()
     * @see set()
     */
    public function get($id, $throwException = true);

    /**
     * Registers a component definition with this locator.
     *
     * If a component definition with the same ID already exists, it will be overwritten.
     *
     * @param string $id component ID (e.g. `db`).
     * @param mixed $definition the component definition to be registered with this locator.
     * It can be one of the following:
     *
     * - a class name
     * - a configuration array: the array contains name-value pairs that will be used to
     *   initialize the property values of the newly created object when [[get()]] is called.
     *   The `class` element is required and stands for the the class of the object to be created.
     * - a PHP callable: either an anonymous function or an array representing a class method (e.g. `['Foo', 'bar']`).
     *   The callable will be called by [[get()]] to return an object associated with the specified component ID.
     * - an object: When [[get()]] is called, this object will be returned.
     */
    public function set($id, $definition);
}