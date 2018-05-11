<?php

namespace Reaction\Web\Sessions;

use React\Promise\ExtendedPromiseInterface;
use React\Promise\PromiseInterface;
use Reaction;
use Reaction\Base\RequestAppComponent;
use Reaction\Exceptions\InvalidArgumentException;
use Reaction\Exceptions\InvalidConfigException;

/**
 * Session provides session data management and the related configurations.
 *
 * Session is a Web application component that can be accessed via `Yii::$app->session`.
 *
 * To start the session, call [[open()]]; To complete and send out session data, call [[close()]];
 * To destroy the session, call [[destroy()]].
 *
 * Session can be used like an array to set and get session data. For example,
 *
 * ```php
 * $session = new Session;
 * $session->open();
 * $value1 = $session['name1'];  // get session variable 'name1'
 * $value2 = $session['name2'];  // get session variable 'name2'
 * foreach ($session as $name => $value) // traverse all session variables
 * $session['name3'] = $value3;  // set session variable 'name3'
 * ```
 *
 * Session can be extended to support customized session storage.
 *
 * Session also supports a special type of session data, called *flash messages*.
 * A flash message is available only in the current request and the next request.
 * After that, it will be deleted automatically. Flash messages are particularly
 * useful for displaying confirmation messages. To use flash messages, simply
 * call methods such as [[setFlash()]], [[getFlash()]].
 *
 * For more details and usage information on Session, see the [guide article on sessions](guide:runtime-sessions-cookies).
 *
 * @property array $allFlashes Flash messages (key => message or key => [message1, message2]). This property
 * is read-only.
 * @property string $cacheLimiter Current cache limiter. This property is read-only.
 * @property array $cookieParams The session cookie parameters. This property is read-only.
 * @property int $count The number of session variables. This property is read-only.
 * @property string $flash The key identifying the flash message. Note that flash messages and normal session
 * variables share the same name space. If you have a normal session variable using the same name, its value will
 * be overwritten by this method. This property is write-only.
 * @property bool $hasSessionId Whether the current request has sent the session ID.
 * @property string $id The current session ID.
 * @property bool $isActive Whether the session has started. This property is read-only.
 * @property SessionIterator $iterator An iterator for traversing the session variables. This property is
 * read-only.
 * @property string $name The current session name.
 * @property bool|null $useCookies The value indicating whether cookies should be used to store session IDs.
 * @property Reaction\Web\RequestHelper $request
 */
class Session extends RequestAppComponent implements \IteratorAggregate, \ArrayAccess, \Countable, RequestSessionInterface
{
    /**
     * @var string the name of the session variable that stores the flash message data.
     */
    public $flashParam = '__flash';
    /**
     * @var SessionHandlerInterface|array an object implementing the SessionHandlerInterface or a configuration array. If set, will be used to provide persistency instead of build-in methods.
     */
    public $handler = 'sessionHandler';
    /**
     * @var string Cookie name with session ID
     */
    public $cookieName = '_sess';
    /**
     * @var array Session data array
     */
    public $data = [];

    /**
     * @var array parameter-value pairs to override default session cookie parameters that are used for session_set_cookie_params() function
     * Array may have the following possible keys: 'lifetime', 'path', 'domain', 'secure', 'httponly'
     * @see http://www.php.net/manual/en/function.session-set-cookie-params.php
     */
    protected $_cookieParams = ['httponly' => true];
    /**
     * @var array|null is used for saving session between recreations due to session parameters update.
     */
    protected $frozenSessionData;

    /** @var string|null */
    protected $_cookieName;
    /** @var string Current session ID */
    protected $_sessionId;
    /** @var bool Flag indicates that session is active */
    protected $_isActive = false;

    /** @var array Session data before changes */
    protected $_dataPrev;
    /** @var bool */
    protected $_initialized = false;


    /**
     * Initializes the application component.
     * This method is required by IApplicationComponent and is invoked by application.
     */
    public function init()
    {
        parent::init();
        $self = $this;
        //Close session on end of request
        $this->app->once(Reaction\RequestApplicationInterface::EVENT_REQUEST_END, function() use (&$self) {
            return $self->close();
        });

        if ($this->getIsActive()) {
            Reaction::warning('Session is already started');
            $this->updateFlashCounters();
        }
    }

    /**
     * Init callback. Called by parent container/service/component on init and must return a fulfilled Promise
     * @return PromiseInterface
     */
    public function initComponent()
    {
        $this->_initialized = true;
        return $this->open();
    }

    /**
     * Check that component was initialized earlier
     * @return bool
     */
    public function isInitialized()
    {
        return $this->_initialized;
    }

    /**
     * Starts the session.
     * @return PromiseInterface
     */
    public function open()
    {
        if ($this->getIsActive()) {
            return \Reaction\Promise\resolve(true);
        }

        $this->registerSessionHandler();

        $self = $this;
        return $this->openInternal()->then(
            function() use ($self) {
                if (Reaction::isDebug()) {
                    Reaction::info('Session started');
                }
                $self->updateFlashCounters();
            }
        );
    }

    /**
     * Open session internal method
     * @return PromiseInterface
     */
    protected function openInternal()
    {
        $self = $this;
        if (!$this->getId()) {
            $self = $this;
            return $this->handler->createId($this->app)->then(
                function($id = null) use ($self) {
                    $self->_isActive = true;
                    $self->setId($id);
                    $cookie = $self->createSessionCookie();
                    $self->app->response->cookies->add($cookie);
                }
            );
        } else {
            $this->_isActive = true;
            return $this->readSession()->then(
                function($data) use ($self) {
                    $self->data = is_array($data) ? $data : [];
                    return true;
                },
                function() use ($self) {
                    $self->data = [];
                }
            );
        }
    }

    /**
     * Registers session handler.
     * @throws \Reaction\Exceptions\InvalidConfigException
     */
    protected function registerSessionHandler()
    {
        if ($this->handler !== null) {
            if (!is_object($this->handler)) {
                if (is_string($this->handler) && Reaction::$app->has($this->handler)) {
                    $this->handler = Reaction::$app->get($this->handler);
                } else {
                    $this->handler = Reaction::create($this->handler);
                }
            }
            if (!$this->handler instanceof SessionHandlerInterface) {
                throw new InvalidConfigException('"'.get_class($this).'::handler" must implement the \Reaction\Web\Sessions\SessionHandlerInterface.');
            }
        } else {
            throw new InvalidConfigException('"'.get_class($this).'::handler" can not be empty.');
        }
    }

    /**
     * Ends the current session and store session data.
     * @return PromiseInterface
     */
    public function close()
    {
        if ($this->getIsActive()) {
            if (Reaction::isDebug()) {
                Reaction::info('Session closed');
            }
            return $this->writeSession();
        }
        $this->_isActive = false;
        return \Reaction\Promise\resolve(null);
    }

    /**
     * Frees all session variables and destroys all data registered to a session.
     *
     * This method has no effect when session is not [[getIsActive()|active]].
     * Make sure to call [[open()]] before calling it.
     * @see open()
     * @see isActive
     */
    public function destroy()
    {
        if ($this->getIsActive()) {
            $sessionId = $this->getId();
            $this->close();
            $this->setId($sessionId);
            $this->open();
        }
    }

    /**
     * @return bool whether the session has started
     */
    public function getIsActive()
    {
        return $this->_isActive;
    }

    private $_hasSessionId;

    /**
     * Returns a value indicating whether the current request has sent the session ID.
     * The default implementation will check cookie and $_GET using the session name.
     * If you send session ID via other ways, you may need to override this method
     * or call [[setHasSessionId()]] to explicitly set whether the session ID is sent.
     * @return bool whether the current request has sent the session ID.
     */
    public function getHasSessionId()
    {
        if ($this->_hasSessionId === null) {
            $name = $this->getName();
            $request = $this->request;
            $cookie = $request->getCookies()->getValue($name);
            if (!empty($cookie) && ini_get('session.use_cookies')) {
                $this->_hasSessionId = true;
                $this->_sessionId = $cookie;
            } elseif (!ini_get('session.use_only_cookies') && ini_get('session.use_trans_sid')) {
                $idFromRequest = $request->get($name);
                $this->_hasSessionId = $idFromRequest != '';
                $this->_sessionId = $idFromRequest != '' ? $idFromRequest : null;
            } else {
                $this->_sessionId = null;
                $this->_hasSessionId = false;
            }
        }

        return $this->_hasSessionId;
    }

    /**
     * Sets the value indicating whether the current request has sent the session ID.
     * This method is provided so that you can override the default way of determining
     * whether the session ID is sent.
     * @param bool $value whether the current request has sent the session ID.
     */
    public function setHasSessionId($value)
    {
        $this->_hasSessionId = $value;
    }

    /**
     * Gets the session ID.
     * This is a wrapper for [PHP session_id()](http://php.net/manual/en/function.session-id.php).
     * @return string the current session ID
     */
    public function getId()
    {
        if (!isset($this->_sessionId)) {
            $this->getHasSessionId();
        }
        return $this->_sessionId;
    }

    /**
     * Sets the session ID.
     * This is a wrapper for [PHP session_id()](http://php.net/manual/en/function.session-id.php).
     * @param string $value the session ID for the current session
     */
    public function setId($value)
    {
        $this->_sessionId = $value;
    }

    /**
     * Updates the current session ID with a newly generated one.
     *
     * Please refer to <http://php.net/session_regenerate_id> for more details.
     *
     * This method has no effect when session is not [[getIsActive()|active]].
     * Make sure to call [[open()]] before calling it.
     *
     * @param bool $deleteOldSession Whether to delete the old associated session file or not.
     * @see open()
     * @see isActive
     * @return \React\Promise\PromiseInterface
     */
    public function regenerateID($deleteOldSession = false)
    {
        if ($this->getIsActive()) {
            return $this->handler->regenerateId($this->id, $this->app, $deleteOldSession);
        }
        return \Reaction\Promise\reject(new Reaction\Exceptions\SessionException('Session is not active'));
    }

    /**
     * Gets the name of the current session.
     * This is a wrapper for [PHP session_name()](http://php.net/manual/en/function.session-name.php).
     * @return string the current session name
     */
    public function getName()
    {
        if (!isset($this->_cookieName)) {
            if (strlen($this->_cookieName) < 32 && $this->request->enableCookieValidation) {
                $key = $this->request->cookieValidationKey;
                $bytesLn = Reaction\Helpers\StringHelper::byteLength($key);
                $strLn = mb_strlen($key);
                $this->_cookieName = $this->cookieName.md5(crypt($bytesLn.':'.$strLn, $key));
            }
        }
        return $this->_cookieName;
    }

    /**
     * Sets the name for the current session.
     * This is a wrapper for [PHP session_name()](http://php.net/manual/en/function.session-name.php).
     * @param string $value the session name for the current session, must be an alphanumeric string.
     * It defaults to "PHPSESSID".
     */
    public function setName($value)
    {
        $this->freeze();
        $this->cookieName = $value;
        $this->_cookieName = null;
        $this->unfreeze();
    }

    /**
     * @return array the session cookie parameters.
     * @see http://php.net/manual/en/function.session-get-cookie-params.php
     */
    public function getCookieParams()
    {
        return array_merge(session_get_cookie_params(), array_change_key_case($this->_cookieParams));
    }

    /**
     * Sets the session cookie parameters.
     * The cookie parameters passed to this method will be merged with the result
     * of `session_get_cookie_params()`.
     * @param array $value cookie parameters, valid keys include: `lifetime`, `path`, `domain`, `secure` and `httponly`.
     * @throws InvalidArgumentException if the parameters are incomplete.
     * @see http://us2.php.net/manual/en/function.session-set-cookie-params.php
     */
    public function setCookieParams(array $value)
    {
        $this->_cookieParams = $value;
    }

    /**
     * Returns the value indicating whether cookies should be used to store session IDs.
     * @return bool|null the value indicating whether cookies should be used to store session IDs.
     * @see setUseCookies()
     */
    public function getUseCookies()
    {
        if (ini_get('session.use_cookies') === '0') {
            return false;
        } elseif (ini_get('session.use_only_cookies') === '1') {
            return true;
        }

        return null;
    }

    /**
     * Sets the value indicating whether cookies should be used to store session IDs.
     *
     * Three states are possible:
     *
     * - true: cookies and only cookies will be used to store session IDs.
     * - false: cookies will not be used to store session IDs.
     * - null: if possible, cookies will be used to store session IDs; if not, other mechanisms will be used (e.g. GET parameter)
     *
     * @param bool|null $value the value indicating whether cookies should be used to store session IDs.
     */
    public function setUseCookies($value)
    {
        $this->freeze();
        if ($value === false) {
            ini_set('session.use_cookies', '0');
            ini_set('session.use_only_cookies', '0');
        } elseif ($value === true) {
            ini_set('session.use_cookies', '1');
            ini_set('session.use_only_cookies', '1');
        } else {
            ini_set('session.use_cookies', '1');
            ini_set('session.use_only_cookies', '0');
        }
        $this->unfreeze();
    }

    /**
     * Session read handler.
     * @internal Do not call this method directly.
     * @param string $id session ID
     * @return ExtendedPromiseInterface with array the session data
     */
    public function readSession($id = null)
    {
        $id = !isset($id) && isset($this->_sessionId) ? $this->_sessionId : $id;
        if (!isset($id)) {
            return \Reaction\Promise\reject(new Reaction\Exceptions\ErrorException('Param "$id" must be specified in "'.__METHOD__.'"'));
        }
        return $this->handler->read($id);
    }

    /**
     * Session write handler.
     * @internal Do not call this method directly.
     * @param string $id session ID
     * @param array  $data session data
     * @return PromiseInterface with bool whether session write is successful
     */
    public function writeSession($data = null, $id = null)
    {
        $id = !isset($id) && isset($this->_sessionId) ? $this->_sessionId : $id;
        if (!isset($id)) {
            return \Reaction\Promise\reject(new Reaction\Exceptions\ErrorException('Param "$id" must be specified in "'.__METHOD__.'"'));
        }
        if (!isset($data)) {
            $data = $this->data;
        }
        return $this->handler->write($id, $data);
    }

    /**
     * Session destroy handler.
     * @internal Do not call this method directly.
     * @param string $id session ID
     * @return PromiseInterface with bool whether session is destroyed successfully
     */
    public function destroySession($id = null)
    {
        $id = !isset($id) && isset($this->_sessionId) ? $this->_sessionId : $id;
        if (!isset($id)) {
            return \Reaction\Promise\reject(new Reaction\Exceptions\ErrorException('Param "$id" must be specified in "'.__METHOD__.'"'));
        }
        return $this->handler->destroy($id);
    }

    /**
     * Returns an iterator for traversing the session variables.
     * This method is required by the interface [[\IteratorAggregate]].
     * @return SessionIterator an iterator for traversing the session variables.
     */
    public function getIterator()
    {
        $this->open();
        return new SessionIterator($this);
    }

    /**
     * Returns the number of items in the session.
     * @return int the number of session variables
     */
    public function getCount()
    {
        $this->open();
        return count($this->data);
    }

    /**
     * Returns the number of items in the session.
     * This method is required by [[\Countable]] interface.
     * @return int number of items in the session.
     */
    public function count()
    {
        return $this->getCount();
    }

    /**
     * Returns the session variable value with the session variable name.
     * If the session variable does not exist, the `$defaultValue` will be returned.
     * @param string $key the session variable name
     * @param mixed $defaultValue the default value to be returned when the session variable does not exist.
     * @return mixed the session variable value, or $defaultValue if the session variable does not exist.
     */
    public function get($key, $defaultValue = null)
    {
        $this->open();
        return isset($this->data[$key]) ? $this->data[$key] : $defaultValue;
    }

    /**
     * Adds a session variable.
     * If the specified name already exists, the old value will be overwritten.
     * @param string $key session variable name
     * @param mixed  $value session variable value
     */
    public function set($key, $value)
    {
        $this->open();
        $data = $this->data;
        $data[$key] = $value;
        $this->data = $data;
    }

    /**
     * Removes a session variable.
     * @param string $key the name of the session variable to be removed
     */
    public function remove($key)
    {
        $this->open();
        $data = $this->data;
        if (isset($data[$key])) {
            unset($data[$key]);
        }
        $this->data = $data;
    }

    /**
     * Removes all session variables.
     * @return PromiseInterface with bool when write process ends
     */
    public function removeAll()
    {
        $this->open();
        $data = $this->data;
        foreach (array_keys($data) as $key) {
            unset($data[$key]);
        }
        $this->data = $data;
        return $this->writeSession();
    }

    /**
     * @param mixed $key session variable name
     * @return bool whether there is the named session variable
     */
    public function has($key)
    {
        $this->open();
        return isset($this->data[$key]);
    }

    /**
     * Updates the counters for flash messages and removes outdated flash messages.
     * This method should only be called once in [[init()]].
     */
    protected function updateFlashCounters()
    {
        $counters = $this->get($this->flashParam, []);
        $data = $this->data;
        if (is_array($counters)) {
            foreach ($counters as $key => $count) {
                if ($count > 0) {
                    unset($counters[$key], $this->data[$key]);
                } elseif ($count == 0) {
                    $counters[$key]++;
                }
            }
            $data[$this->flashParam] = $counters;
        } else {
            // fix the unexpected problem that flashParam doesn't return an array
            unset($data[$this->flashParam]);
        }
        $this->data = $data;
    }

    /**
     * Returns a flash message.
     * @param string $key the key identifying the flash message
     * @param mixed $defaultValue value to be returned if the flash message does not exist.
     * @param bool $delete whether to delete this flash message right after this method is called.
     * If false, the flash message will be automatically deleted in the next request.
     * @return mixed the flash message or an array of messages if addFlash was used
     * @see setFlash()
     * @see addFlash()
     * @see hasFlash()
     * @see getAllFlashes()
     * @see removeFlash()
     */
    public function getFlash($key, $defaultValue = null, $delete = false)
    {
        $counters = $this->get($this->flashParam, []);
        if (isset($counters[$key])) {
            $value = $this->get($key, $defaultValue);
            if ($delete) {
                $this->removeFlash($key);
            } elseif ($counters[$key] < 0) {
                // mark for deletion in the next request
                $counters[$key] = 1;
                $this->data[$this->flashParam] = $counters;
            }

            return $value;
        }

        return $defaultValue;
    }

    /**
     * Returns all flash messages.
     *
     * You may use this method to display all the flash messages in a view file:
     *
     * ```php
     * <?php
     * foreach (Yii::$app->session->getAllFlashes() as $key => $message) {
     *     echo '<div class="alert alert-' . $key . '">' . $message . '</div>';
     * } ?>
     * ```
     *
     * With the above code you can use the [bootstrap alert][] classes such as `success`, `info`, `danger`
     * as the flash message key to influence the color of the div.
     *
     * Note that if you use [[addFlash()]], `$message` will be an array, and you will have to adjust the above code.
     *
     * [bootstrap alert]: http://getbootstrap.com/components/#alerts
     *
     * @param bool $delete whether to delete the flash messages right after this method is called.
     * If false, the flash messages will be automatically deleted in the next request.
     * @return array flash messages (key => message or key => [message1, message2]).
     * @see setFlash()
     * @see addFlash()
     * @see getFlash()
     * @see hasFlash()
     * @see removeFlash()
     */
    public function getAllFlashes($delete = false)
    {
        $counters = $this->get($this->flashParam, []);
        $flashes = [];
        foreach (array_keys($counters) as $key) {
            if (array_key_exists($key, $this->data)) {
                $flashes[$key] = $this->data[$key];
                if ($delete) {
                    unset($counters[$key], $this->data[$key]);
                } elseif ($counters[$key] < 0) {
                    // mark for deletion in the next request
                    $counters[$key] = 1;
                }
            } else {
                unset($counters[$key]);
            }
        }

        $this->data[$this->flashParam] = $counters;

        return $flashes;
    }

    /**
     * Sets a flash message.
     * A flash message will be automatically deleted after it is accessed in a request and the deletion will happen
     * in the next request.
     * If there is already an existing flash message with the same key, it will be overwritten by the new one.
     * @param string $key the key identifying the flash message. Note that flash messages
     * and normal session variables share the same name space. If you have a normal
     * session variable using the same name, its value will be overwritten by this method.
     * @param mixed $value flash message
     * @param bool $removeAfterAccess whether the flash message should be automatically removed only if
     * it is accessed. If false, the flash message will be automatically removed after the next request,
     * regardless if it is accessed or not. If true (default value), the flash message will remain until after
     * it is accessed.
     * @see getFlash()
     * @see addFlash()
     * @see removeFlash()
     */
    public function setFlash($key, $value = true, $removeAfterAccess = true)
    {
        $counters = $this->get($this->flashParam, []);
        $counters[$key] = $removeAfterAccess ? -1 : 0;
        $this->data[$key] = $value;
        $this->data[$this->flashParam] = $counters;
    }

    /**
     * Adds a flash message.
     * If there are existing flash messages with the same key, the new one will be appended to the existing message array.
     * @param string $key the key identifying the flash message.
     * @param mixed $value flash message
     * @param bool $removeAfterAccess whether the flash message should be automatically removed only if
     * it is accessed. If false, the flash message will be automatically removed after the next request,
     * regardless if it is accessed or not. If true (default value), the flash message will remain until after
     * it is accessed.
     * @see getFlash()
     * @see setFlash()
     * @see removeFlash()
     */
    public function addFlash($key, $value = true, $removeAfterAccess = true)
    {
        $counters = $this->get($this->flashParam, []);
        $counters[$key] = $removeAfterAccess ? -1 : 0;
        $this->data[$this->flashParam] = $counters;
        if (empty($this->data[$key])) {
            $this->data[$key] = [$value];
        } else {
            if (is_array($this->data[$key])) {
                $this->data[$key][] = $value;
            } else {
                $this->data[$key] = [$this->data[$key], $value];
            }
        }
    }

    /**
     * Removes a flash message.
     * @param string $key the key identifying the flash message. Note that flash messages
     * and normal session variables share the same name space.  If you have a normal
     * session variable using the same name, it will be removed by this method.
     * @return mixed the removed flash message. Null if the flash message does not exist.
     * @see getFlash()
     * @see setFlash()
     * @see addFlash()
     * @see removeAllFlashes()
     */
    public function removeFlash($key)
    {
        $counters = $this->get($this->flashParam, []);
        $value = isset($this->data[$key], $counters[$key]) ? $this->data[$key] : null;
        unset($counters[$key], $this->data[$key]);
        $this->data[$this->flashParam] = $counters;

        return $value;
    }

    /**
     * Removes all flash messages.
     * Note that flash messages and normal session variables share the same name space.
     * If you have a normal session variable using the same name, it will be removed
     * by this method.
     * @see getFlash()
     * @see setFlash()
     * @see addFlash()
     * @see removeFlash()
     */
    public function removeAllFlashes()
    {
        $counters = $this->get($this->flashParam, []);
        foreach (array_keys($counters) as $key) {
            unset($this->data[$key]);
        }
        unset($this->data[$this->flashParam]);
    }

    /**
     * Returns a value indicating whether there are flash messages associated with the specified key.
     * @param string $key key identifying the flash message type
     * @return bool whether any flash messages exist under specified key
     */
    public function hasFlash($key)
    {
        return $this->getFlash($key) !== null;
    }

    /**
     * This method is required by the interface [[\ArrayAccess]].
     * @param mixed $offset the offset to check on
     * @return bool
     */
    public function offsetExists($offset)
    {
        $this->open();

        return isset($this->data[$offset]);
    }

    /**
     * This method is required by the interface [[\ArrayAccess]].
     * @param int $offset the offset to retrieve element.
     * @return mixed the element at the offset, null if no element is found at the offset
     */
    public function offsetGet($offset)
    {
        $this->open();

        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }

    /**
     * This method is required by the interface [[\ArrayAccess]].
     * @param int $offset the offset to set element
     * @param mixed $item the element value
     */
    public function offsetSet($offset, $item)
    {
        $this->open();
        $this->data[$offset] = $item;
    }

    /**
     * This method is required by the interface [[\ArrayAccess]].
     * @param mixed $offset the offset to unset element
     */
    public function offsetUnset($offset)
    {
        $this->open();
        unset($this->data[$offset]);
    }

    /**
     * If session is started it's not possible to edit session ini settings. In PHP7.2+ it throws exception.
     * This function saves session data to temporary variable and stop session.
     */
    protected function freeze()
    {
        if ($this->getIsActive()) {
            if (isset($this->data)) {
                $this->frozenSessionData = $this->data;
            }
            //$this->close();
            if (Reaction::isDebug()) {
                Reaction::info('Session frozen');
            }
        }
    }

    /**
     * Starts session and restores data from temporary variable
     */
    protected function unfreeze()
    {
        if (null !== $this->frozenSessionData) {

            //$this->open();

            if ($this->getIsActive()) {
                Reaction::info('Session unfrozen');
            } else {
                $error = error_get_last();
                $message = isset($error['message']) ? $error['message'] : 'Failed to unfreeze session.';
                Reaction::error($message);
            }

            $this->data = $this->frozenSessionData;
            $this->frozenSessionData = null;
            $this->writeSession();
        }
    }

    /**
     * Create session cookie
     * @return Reaction\Web\Cookie
     */
    protected function createSessionCookie() {
        $_params = $this->getCookieParams();
        $config = [
            'class' => 'Reaction\Web\Cookie',
        ];
        foreach ($_params as $key => $value) {
            if ($key === 'httponly') {
                $key = 'httpOnly';
            } elseif ($key === 'lifetime') {
                $expireValue = intval($value);
                if (empty($expireValue)) continue;
                $key = 'expire';
                $value = time() + $expireValue;
            }
            $config[$key] = $value;
        }
        $config['value'] = $this->id;
        $config['name'] = $this->getName();
        return Reaction::create($config);
    }

    /**
     * Get Request helper
     * @return Reaction\Web\RequestHelper
     */
    protected function getRequest() {
        return $this->app->reqHelper;
    }
}
