<?php

namespace Reaction\Web;

use React\Promise\PromiseInterface;
use Reaction;
use Reaction\Base\ComponentAutoloadInterface;
use Reaction\Base\ComponentInitBlockingInterface;
use Reaction\Exceptions\Error;
use Reaction\Exceptions\Http\ForbiddenException;
use Reaction\Exceptions\InvalidConfigException;
use Reaction\Exceptions\InvalidValueException;
use Reaction\Promise\ExtendedPromiseInterface;
use Reaction\Rbac\CheckAccessInterface;
use function Reaction\Promise\reject;
use function Reaction\Promise\resolve;

/**
 * User is the class for the `user` application component that manages the user authentication status.
 *
 * You may use [[isGuest]] to determine whether the current user is a guest or not.
 * If the user is a guest, the [[identity]] property would return `null`. Otherwise, it would
 * be an instance of [[IdentityInterface]].
 *
 * You may call various methods to change the user authentication status:
 *
 * - [[login()]]: sets the specified identity and remembers the authentication status in session and cookie;
 * - [[logout()]]: marks the user as a guest and clears the relevant information from session and cookie;
 * - [[setIdentity()]]: changes the user identity without touching session or cookie
 *   (this is best used in stateless RESTful API implementation).
 *
 * Note that User only maintains the user authentication status. It does NOT handle how to authenticate
 * a user. The logic of how to authenticate a user should be done in the class implementing [[IdentityInterface]].
 * You are also required to set [[identityClass]] with the name of this class.
 *
 * User is configured as an application component in [[\yii\web\Application]] by default.
 * You can access that instance via `Yii::$app->user`.
 *
 * You can modify its configuration by adding an array to your application config under `components`
 * as it is shown in the following example:
 *
 * ```php
 * 'user' => [
 *     'identityClass' => 'App\Models\User', // User must implement the IdentityInterface
 *     'enableAutoLogin' => true,
 *     // 'loginUrl' => ['user/login'],
 *     // ...
 * ]
 * ```
 *
 * @property string|int $id The unique identifier for the user. If `null`, it means the user is a guest. This
 * property is read-only.
 * @property IdentityInterface|null $identity The identity object associated with the currently logged-in
 * user. `null` is returned if the user is not logged in (not authenticated).
 * @property bool $isGuest Whether the current user is a guest. This property is read-only.
 * @property string $returnUrl The URL that the user should be redirected to after login. Note that the type
 * of this property differs in getter and setter. See [[getReturnUrl()]] and [[setReturnUrl()]] for details.
 * @property RequestHelper $request
 */
class User extends Reaction\Base\RequestAppServiceLocator implements UserInterface, ComponentAutoloadInterface, ComponentInitBlockingInterface
{
    const EVENT_BEFORE_LOGIN = 'beforeLogin';
    const EVENT_AFTER_LOGIN = 'afterLogin';
    const EVENT_BEFORE_LOGOUT = 'beforeLogout';
    const EVENT_AFTER_LOGOUT = 'afterLogout';

    const PERMISSION_LOGGED_IN = '@';
    const PERMISSION_NOT_LOGGED_IN = '?';

    /**
     * @var string the class name of the [[identity]] object.
     */
    public $identityClass;
    /**
     * @var bool whether to enable cookie-based login. Defaults to `false`.
     * Note that this property will be ignored if [[enableSession]] is `false`.
     */
    public $enableAutoLogin = false;
    /**
     * @var bool whether to use session to persist authentication status across multiple requests.
     * You set this property to be `false` if your application is stateless, which is often the case
     * for RESTful APIs.
     */
    public $enableSession = true;
    /**
     * @var string|array the URL for login when [[loginRequired()]] is called.
     * If an array is given, [[UrlManager::createUrl()]] will be called to create the corresponding URL.
     * The first element of the array should be the route to the login action, and the rest of
     * the name-value pairs are GET parameters used to construct the login URL. For example,
     *
     * ```php
     * ['site/login', 'ref' => 1]
     * ```
     *
     * If this property is `null`, a 403 HTTP exception will be raised when [[loginRequired()]] is called.
     */
    public $loginUrl = ['site/login'];
    /**
     * @var array the configuration of the identity cookie. This property is used only when [[enableAutoLogin]] is `true`.
     * @see Cookie
     */
    public $identityCookie = ['name' => '_identity', 'httpOnly' => true];
    /**
     * @var int the number of seconds in which the user will be logged out automatically if he
     * remains inactive. If this property is not set, the user will be logged out after
     * the current session expires (c.f. [[Session::timeout]]).
     * Note that this will not work if [[enableAutoLogin]] is `true`.
     */
    public $authTimeout;
    /**
     * @var CheckAccessInterface The access checker to use for checking access.
     * If not set the application auth manager will be used.
     */
    public $accessChecker;
    /**
     * @var int the number of seconds in which the user will be logged out automatically
     * regardless of activity.
     * Note that this will not work if [[enableAutoLogin]] is `true`.
     */
    public $absoluteAuthTimeout;
    /**
     * @var bool whether to automatically renew the identity cookie each time a page is requested.
     * This property is effective only when [[enableAutoLogin]] is `true`.
     * When this is `false`, the identity cookie will expire after the specified duration since the user
     * is initially logged in. When this is `true`, the identity cookie will expire after the specified duration
     * since the user visits the site the last time.
     * @see enableAutoLogin
     */
    public $autoRenewCookie = true;
    /**
     * @var string the session variable name used to store the value of [[id]].
     */
    public $idParam = '__id';
    /**
     * @var string the session variable name used to store the value of expiration timestamp of the authenticated state.
     * This is used when [[authTimeout]] is set.
     */
    public $authTimeoutParam = '__expire';
    /**
     * @var string the session variable name used to store the value of absolute expiration timestamp of the authenticated state.
     * This is used when [[absoluteAuthTimeout]] is set.
     */
    public $absoluteAuthTimeoutParam = '__absoluteExpire';
    /**
     * @var string the session variable name used to store the value of [[returnUrl]].
     */
    public $returnUrlParam = '__returnUrl';
    /**
     * @var array MIME types for which this component should redirect to the [[loginUrl]].
     */
    public $acceptableRedirectTypes = ['text/html', 'application/xhtml+xml'];
    /**
     * @var array
     */
    protected $_access = [];
    /**
     * @var bool Initialization flag
     */
    protected $_initialized = false;


    /**
     * Initializes the application component.
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();

        if ($this->identityClass === null) {
            throw new InvalidConfigException('User::identityClass must be set.');
        }
        if ($this->enableAutoLogin && !isset($this->identityCookie['name'])) {
            throw new InvalidConfigException('User::identityCookie must contain the "name" element.');
        }
        if (!empty($this->accessChecker) && is_string($this->accessChecker)) {
            $this->accessChecker = Reaction::create($this->accessChecker);
        }
    }

    /**
     * Init callback. Called by parent container/service/component on init and must return a fulfilled Promise
     * @return PromiseInterface
     */
    public function initComponent()
    {
        return $this->getIdentityAsync()
            ->always(function() {
                $this->_initialized = true;
            })
            ->then(function() {
                return true;
            }, function() {
                return false;
            });
    }

    protected $_identity = false;

    /**
     * Returns the identity object associated with the currently logged-in user.
     * This is only useful when [[enableSession]] is true.
     * @return IdentityInterface|null|false the identity object associated with the currently logged-in user.
     * `null` is returned if the user is not logged in (not authenticated).
     * @see login()
     * @see logout()
     * @see getIdentityAsync()
     */
    public function getIdentity()
    {
        if ($this->_identity === false || $this->_identity === null) {
            return null;
        }

        return $this->_identity;
    }

    /**
     * Returns the identity object associated with the currently logged-in user.
     * When [[enableSession]] is true, this method may attempt to read the user's authentication data
     * stored in session and reconstruct the corresponding identity object, if it has not done so before.
     * @param bool $autoRenew whether to automatically renew authentication status if it has not been done so before.
     * This is only useful when [[enableSession]] is true.
     * @return ExtendedPromiseInterface with IdentityInterface|null|false the identity object associated with the currently logged-in user.
     * `null` is returned if the user is not logged in (not authenticated).
     * @see login()
     * @see logout()
     * @see getIdentity()
     */
    public function getIdentityAsync($autoRenew = true)
    {
        if ($this->_identity === false) {
            if ($this->enableSession && $autoRenew) {
                $this->_identity = null;
                return $this->renewAuthStatus()
                    ->then(null, function($e) {
                        $this->_identity = false;
                        return reject($e);
                    })->then(function() {
                        return $this->_identity;
                    });
            } else {
                return reject(null);
            }
        } else {
            return resolve($this->_identity);
        }
    }

    /**
     * Sets the user identity object.
     *
     * Note that this method does not deal with session or cookie. You should usually use [[switchIdentity()]]
     * to change the identity of the current user.
     *
     * @param IdentityInterface|null $identity the identity object associated with the currently logged user.
     * If null, it means the current user will be a guest without any associated identity.
     * @throws InvalidValueException if `$identity` object does not implement [[IdentityInterface]].
     */
    public function setIdentity($identity)
    {
        if ($identity instanceof IdentityInterface) {
            $this->_identity = $identity;
            $this->_access = [];
        } elseif ($identity === null) {
            $this->_identity = null;
        } else {
            throw new InvalidValueException('The identity object must implement IdentityInterface.');
        }
    }

    /**
     * Logs in a user.
     *
     * After logging in a user:
     * - the user's identity information is obtainable from the [[identity]] property
     *
     * If [[enableSession]] is `true`:
     * - the identity information will be stored in session and be available in the next requests
     * - in case of `$duration == 0`: as long as the session remains active or till the user closes the browser
     * - in case of `$duration > 0`: as long as the session remains active or as long as the cookie
     *   remains valid by it's `$duration` in seconds when [[enableAutoLogin]] is set `true`.
     *
     * If [[enableSession]] is `false`:
     * - the `$duration` parameter will be ignored
     *
     * @param IdentityInterface $identity the user identity (which should already be authenticated)
     * @param int $duration number of seconds that the user can remain in logged-in status, defaults to `0`
     * @return ExtendedPromiseInterface with bool whether the user is logged in
     */
    public function login(IdentityInterface $identity, $duration = 0)
    {
        //Using root resolve function for promise conversion
        return resolve(true)
            ->then(function() use (&$identity, $duration) {
                return $this->beforeLogin($identity, false, $duration);
            })->then(function() use (&$identity, $duration) {
                $this->switchIdentity($identity, $duration);
                $id = $identity->getId();
                $ip = $this->request->getUserIP();
                if ($this->enableSession) {
                    $log = "User '$id' logged in from $ip with duration $duration.";
                } else {
                    $log = "User '$id' logged in from $ip. Session not enabled.";
                }

                $this->regenerateCsrfToken();

                if (Reaction::isDebug()) {
                    Reaction::info($log);
                }
                return $this->afterLogin($identity, false, $duration);
            });
    }

    /**
     * Regenerates CSRF token
     */
    protected function regenerateCsrfToken()
    {
        $request = $this->request;
        if ($request->enableCsrfCookie || $this->enableSession) {
            $request->getCsrfToken(true);
        }
    }

    /**
     * Logs in a user by the given access token.
     * This method will first authenticate the user by calling [[IdentityInterface::findIdentityByAccessToken()]]
     * with the provided access token. If successful, it will call [[login()]] to log in the authenticated user.
     * If authentication fails or [[login()]] is unsuccessful, it will return null.
     * @param string $token the access token
     * @param mixed $type the type of the token. The value of this parameter depends on the implementation.
     * For example, [[\yii\filters\auth\HttpBearerAuth]] will set this parameter to be `yii\filters\auth\HttpBearerAuth`.
     * @return ExtendedPromiseInterface with IdentityInterface|null the identity associated with the given access token. Null is returned if
     * the access token is invalid or [[login()]] is unsuccessful.
     */
    public function loginByAccessToken($token, $type = null)
    {
        /* @var $class IdentityInterface */
        $class = $this->identityClass;
        return $class::findIdentityByAccessToken($token, $type)
            ->then(function($identity) {
                return $this->login($identity)
                    ->then(function() use (&$identity) {
                        return $identity;
                    });
            });
    }

    /**
     * Logs in a user by cookie.
     *
     * This method attempts to log in a user using the ID and authKey information
     * provided by the [[identityCookie|identity cookie]].
     * @return ExtendedPromiseInterface
     */
    protected function loginByCookie()
    {
        return $this->getIdentityAndDurationFromCookie()
            ->then(function($data) {
                if (isset($data['identity'], $data['duration'])) {
                    /** @var IdentityInterface $identity */
                    $identity = $data['identity'];
                    $duration = $data['duration'];
                    return $this->beforeLogin($identity, true, $duration)
                        ->then(function() use (&$identity, $duration) {
                            $this->switchIdentity($identity, $this->autoRenewCookie ? $duration : 0);
                            $id = $identity->getId();
                            $ip = $this->request->getUserIP();
                            Reaction::info("User '$id' logged in from $ip via cookie.");
                            return $this->afterLogin($identity, true, $duration);
                        });
                } else {
                    return reject(new Error("Invalid cookie value or user identity"));
                }
            });
    }

    /**
     * Logs out the current user.
     * This will remove authentication-related session data.
     * If `$destroySession` is true, all session data will be removed.
     * @param bool $destroySession whether to destroy the whole session. Defaults to true.
     * This parameter is ignored if [[enableSession]] is false.
     * @return ExtendedPromiseInterface with bool whether the user is logged out
     * @throws \Throwable
     */
    public function logout($destroySession = true)
    {
        $identity = $this->getIdentity();
        if ($identity === null) {
            return $this->getIsGuest() ? resolve(true) : reject(false);
        }

        //Using root resolve function for promise conversion
        return resolve(true)
            ->then(function() use (&$identity) { return $this->beforeLogout($identity); })
            ->then(function() use (&$identity, $destroySession) {
                $this->switchIdentity(null);
                $id = $identity->getId();
                $ip = $this->request->getUserIP();
                Reaction::info("User '$id' logged out from $ip.");
                if ($destroySession && $this->enableSession) {
                    return $this->app->session->destroy();
                } else {
                    return true;
                }
            })->always(function() use (&$identity) {
                return $this->afterLogout($identity);
            })->then(function() use (&$identity) {
                return $this->getIsGuest() ? true : reject("User logout failed");
            });
    }

    /**
     * Returns a value indicating whether the user is a guest (not authenticated).
     * @return bool whether the current user is a guest.
     * @see getIdentity()
     */
    public function getIsGuest()
    {
        return $this->getIdentity() === null;
    }

    /**
     * Returns a value that uniquely represents the user.
     * @return string|int the unique identifier for the user. If `null`, it means the user is a guest.
     * @see getIdentity()
     */
    public function getId()
    {
        $identity = $this->getIdentity();

        return $identity !== null ? $identity->getId() : null;
    }

    /**
     * Returns the URL that the browser should be redirected to after successful login.
     *
     * This method reads the return URL from the session. It is usually used by the login action which
     * may call this method to redirect the browser to where it goes after successful authentication.
     *
     * @param string|array $defaultUrl the default return URL in case it was not set previously.
     * If this is null and the return URL was not set previously, [[Application::homeUrl]] will be redirected to.
     * Please refer to [[setReturnUrl()]] on accepted format of the URL.
     * @return string the URL that the user should be redirected to after login.
     * @see loginRequired()
     */
    public function getReturnUrl($defaultUrl = null)
    {
        $url = $this->app->session->get($this->returnUrlParam, $defaultUrl);
        if (is_array($url)) {
            if (isset($url[0])) {
                return $this->app->urlManager->createUrl($url);
            }

            $url = null;
        }

        return $url === null ? $this->app->urlManager->getHomeUrl() : $url;
    }

    /**
     * Remembers the URL in the session so that it can be retrieved back later by [[getReturnUrl()]].
     * @param string|array $url the URL that the user should be redirected to after login.
     * If an array is given, [[UrlManager::createUrl()]] will be called to create the corresponding URL.
     * The first element of the array should be the route, and the rest of
     * the name-value pairs are GET parameters used to construct the URL. For example,
     *
     * ```php
     * ['admin/index', 'ref' => 1]
     * ```
     */
    public function setReturnUrl($url)
    {
        $this->app->session->set($this->returnUrlParam, $url);
    }

    /**
     * Redirects the user browser to the login page.
     *
     * Before the redirection, the current URL (if it's not an AJAX url) will be kept as [[returnUrl]] so that
     * the user browser may be redirected back to the current page after successful login.
     *
     * Make sure you set [[loginUrl]] so that the user browser can be redirected to the specified login URL after
     * calling this method.
     *
     * Note that when [[loginUrl]] is set, calling this method will NOT terminate the application execution.
     *
     * @param bool $checkAjax whether to check if the request is an AJAX request. When this is true and the request
     * is an AJAX request, the current URL (for AJAX request) will NOT be set as the return URL.
     * @param bool $checkAcceptHeader whether to check if the request accepts HTML responses. Defaults to `true`. When this is true and
     * the request does not accept HTML responses the current URL will not be SET as the return URL. Also instead of
     * redirecting the user an ForbiddenHttpException is thrown. This parameter is available since version 2.0.8.
     * @return ResponseBuilderInterface the redirection response if [[loginUrl]] is set
     * @throws ForbiddenException the "Access Denied" HTTP exception if [[loginUrl]] is not set or a redirect is
     * not applicable.
     */
    public function loginRequired($checkAjax = true, $checkAcceptHeader = true)
    {
        $request = $this->request;
        $canRedirect = !$checkAcceptHeader || $this->checkRedirectAcceptable();
        if ($this->enableSession
            && $request->getIsGet()
            && (!$checkAjax || !$request->getIsAjax())
            && $canRedirect
        ) {
            $this->setReturnUrl($request->getUrl());
        }
        if ($this->loginUrl !== null && $canRedirect) {
            $loginUrl = (array)$this->loginUrl;
            if ($loginUrl[0] !== $this->app->getRoute()->getRoutePath(true)) {
                return $this->app->response->redirect($this->loginUrl);
            }
        }
        throw new ForbiddenException(Reaction::t('rct', 'Login Required'));
    }

    /**
     * This method is called before logging in a user.
     * The default implementation will trigger the [[EVENT_BEFORE_LOGIN]] event.
     * If you override this method, make sure you call the parent implementation
     * so that the event is triggered.
     * @param IdentityInterface $identity the user identity information
     * @param bool              $cookieBased whether the login is cookie-based
     * @param int               $duration number of seconds that the user can remain in logged-in status.
     * If 0, it means login till the user closes the browser or the session is manually destroyed.
     * @return \React\Promise\PromiseInterface
     */
    protected function beforeLogin($identity, $cookieBased, $duration)
    {
        return $this->emitAndWait(static::EVENT_BEFORE_LOGIN, [&$identity, $cookieBased, $duration]);
    }

    /**
     * This method is called after the user is successfully logged in.
     * The default implementation will trigger the [[EVENT_AFTER_LOGIN]] event.
     * If you override this method, make sure you call the parent implementation
     * so that the event is triggered.
     * @param IdentityInterface $identity the user identity information
     * @param bool              $cookieBased whether the login is cookie-based
     * @param int               $duration number of seconds that the user can remain in logged-in status.
     * If 0, it means login till the user closes the browser or the session is manually destroyed.
     * @return \React\Promise\PromiseInterface
     */
    protected function afterLogin($identity, $cookieBased, $duration)
    {
        return $this->emitAndWait(static::EVENT_AFTER_LOGIN, [&$identity, $cookieBased, $duration]);
    }

    /**
     * This method is invoked when calling [[logout()]] to log out a user.
     * The default implementation will trigger the [[EVENT_BEFORE_LOGOUT]] event.
     * If you override this method, make sure you call the parent implementation
     * so that the event is triggered.
     * @param IdentityInterface $identity the user identity information
     * @return \React\Promise\PromiseInterface whether the user should continue to be logged out
     */
    protected function beforeLogout($identity)
    {
        return $this->emitAndWait(static::EVENT_BEFORE_LOGOUT, [&$identity]);
    }

    /**
     * This method is invoked right after a user is logged out via [[logout()]].
     * The default implementation will trigger the [[EVENT_AFTER_LOGOUT]] event.
     * If you override this method, make sure you call the parent implementation
     * so that the event is triggered.
     * @param IdentityInterface $identity the user identity information
     * @return \React\Promise\PromiseInterface
     */
    protected function afterLogout($identity)
    {
        return $this->emitAndWait(static::EVENT_AFTER_LOGOUT, [&$identity]);
    }

    /**
     * Renews the identity cookie.
     * This method will set the expiration time of the identity cookie to be the current time
     * plus the originally specified cookie duration.
     */
    protected function renewIdentityCookie()
    {
        $name = $this->identityCookie['name'];
        $value = $this->request->getCookies()->getValue($name);
        if ($value !== null) {
            $data = json_decode($value, true);
            if (is_array($data) && isset($data[2])) {
                $cookie = Reaction::create(array_merge($this->identityCookie, [
                    'class' => 'Reaction\Web\Cookie',
                    'value' => $value,
                    'expire' => time() + (int)$data[2],
                ]));
                $this->app->response->getCookies()->add($cookie);
            }
        }
    }

    /**
     * Sends an identity cookie.
     * This method is used when [[enableAutoLogin]] is true.
     * It saves [[id]], [[IdentityInterface::getAuthKey()|auth key]], and the duration of cookie-based login
     * information in the cookie.
     * @param IdentityInterface $identity
     * @param int               $duration number of seconds that the user can remain in logged-in status.
     * @see loginByCookie()
     */
    protected function sendIdentityCookie($identity, $duration)
    {
        $cookie = Reaction::create(array_merge($this->identityCookie, [
            'class' => 'Reaction\Web\Cookie',
            'value' => json_encode([
                $identity->getId(),
                $identity->getAuthKey(),
                $duration,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'expire' => time() + $duration,
        ]));
        $this->app->response->getCookies()->add($cookie);
    }

    /**
     * Determines if an identity cookie has a valid format and contains a valid auth key.
     * This method is used when [[enableAutoLogin]] is true.
     * This method attempts to authenticate a user using the information in the identity cookie.
     * @return ExtendedPromiseInterface with array|null Returns an array of 'identity' and 'duration' if valid, otherwise null.
     * @see loginByCookie()
     */
    protected function getIdentityAndDurationFromCookie()
    {
        $value = $this->request->getCookies()->getValue($this->identityCookie['name']);
        if ($value === null) {
            return reject(null);
        }
        $data = json_decode($value, true);
        if (is_array($data) && count($data) == 3) {
            list($id, $authKey, $duration) = $data;
            /* @var $class IdentityInterface */
            $class = $this->identityClass;
            return $class::findIdentity($id)
                ->then(null, function($error) {
                    $this->removeIdentityCookie();
                    return reject($error);
                })->then(function($identity) use ($id, $authKey, $class, $duration) {
                    if (!$identity instanceof IdentityInterface) {
                        throw new InvalidValueException("$class::findIdentity() must return an object implementing IdentityInterface.");
                    } elseif (!$identity->validateAuthKey($authKey)) {
                        Reaction::warning("Invalid auth key attempted for user '$id': $authKey");
                        throw new InvalidValueException("Invalid auth key attempted for user '$id': $authKey");
                    } else {
                        return ['identity' => $identity, 'duration' => $duration];
                    }
                });
        }
        $this->removeIdentityCookie();
        return reject(null);
    }

    /**
     * Removes the identity cookie.
     * This method is used when [[enableAutoLogin]] is true.
     */
    protected function removeIdentityCookie()
    {
        $cookie = Reaction::create(array_merge($this->identityCookie, [
            'class' => 'Reaction\Web\Cookie',
        ]));
        $this->app->response->getCookies()->remove($cookie);
    }

    /**
     * Switches to a new identity for the current user.
     *
     * When [[enableSession]] is true, this method may use session and/or cookie to store the user identity information,
     * according to the value of `$duration`. Please refer to [[login()]] for more details.
     *
     * This method is mainly called by [[login()]], [[logout()]] and [[loginByCookie()]]
     * when the current user needs to be associated with the corresponding identity information.
     *
     * @param IdentityInterface|null $identity the identity information to be associated with the current user.
     * If null, it means switching the current user to be a guest.
     * @param int $duration number of seconds that the user can remain in logged-in status.
     * This parameter is used only when `$identity` is not null.
     */
    public function switchIdentity($identity, $duration = 0)
    {
        $this->setIdentity($identity);

        if (!$this->enableSession) {
            return;
        }

        /* Ensure any existing identity cookies are removed. */
        if ($this->enableAutoLogin && ($this->autoRenewCookie || $identity === null)) {
            $this->removeIdentityCookie();
        }

        $session = $this->app->session;
        if (!Reaction::isTest()) {
            $session->regenerateID(true);
        }
        $session->remove($this->idParam);
        $session->remove($this->authTimeoutParam);

        if ($identity) {
            $session->set($this->idParam, $identity->getId());
            if ($this->authTimeout !== null) {
                $session->set($this->authTimeoutParam, time() + $this->authTimeout);
            }
            if ($this->absoluteAuthTimeout !== null) {
                $session->set($this->absoluteAuthTimeoutParam, time() + $this->absoluteAuthTimeout);
            }
            if ($this->enableAutoLogin && $duration > 0) {
                $this->sendIdentityCookie($identity, $duration);
            }
        }
    }

    /**
     * Updates the authentication status using the information from session and cookie.
     *
     * This method will try to determine the user identity using the [[idParam]] session variable.
     *
     * If [[authTimeout]] is set, this method will refresh the timer.
     *
     * If the user identity cannot be determined by session, this method will try to [[loginByCookie()|login by cookie]]
     * if [[enableAutoLogin]] is true.
     * @return ExtendedPromiseInterface
     */
    protected function renewAuthStatus()
    {
        $session = $this->app->session;
        $id = $session->getHasSessionId() || $session->getIsActive() ? $session->get($this->idParam) : null;

        if ($id === null) {
            $identityPromise = resolve(null);
        } else {
            /* @var $class IdentityInterface */
            $class = $this->identityClass;
            $identityPromise = $class::findIdentity($id);
        }

        return $identityPromise
            ->then(function($identity) use ($session) {
                /** @var IdentityInterface $identity */
                $this->setIdentity($identity);

                $logoutPromise = resolve(true);
                if ($identity !== null && ($this->authTimeout !== null || $this->absoluteAuthTimeout !== null)) {
                    $expire = $this->authTimeout !== null ? $session->get($this->authTimeoutParam) : null;
                    $expireAbsolute = $this->absoluteAuthTimeout !== null ? $session->get($this->absoluteAuthTimeoutParam) : null;
                    if ($expire !== null && $expire < time() || $expireAbsolute !== null && $expireAbsolute < time()) {
                        $logoutPromise = $this->logout(false);
                    } elseif ($this->authTimeout !== null) {
                        $session->set($this->authTimeoutParam, time() + $this->authTimeout);
                    }
                }

                return $logoutPromise->then(function() {
                    if ($this->enableAutoLogin) {
                        if ($this->getIsGuest()) {
                            return $this->loginByCookie();
                        } elseif ($this->autoRenewCookie) {
                            $this->renewIdentityCookie();
                        }
                    }
                    return true;
                });
            });
    }

    /**
     * Checks if the user can perform the operation as specified by the given permission.
     *
     * Note that you must configure "authManager" application component in order to use this method.
     * Otherwise it will always return false.
     *
     * @param string $permissionName the name of the permission (e.g. "edit post") that needs access check.
     * @param array $params name-value pairs that would be passed to the rules associated
     * with the roles and permissions assigned to the user.
     * @param bool $allowCaching whether to allow caching the result of access check.
     * When this parameter is true (default), if the access check of an operation was performed
     * before, its result will be directly returned when calling this method to check the same
     * operation. If this parameter is false, this method will always call
     * [[\Reaction\Rbac\CheckAccessInterface::checkAccess()]] to obtain the up-to-date access result. Note that this
     * caching is effective only within the same request and only works when `$params = []`.
     * @return ExtendedPromiseInterface with bool whether the user can perform the operation as specified by the given permission.
     */
    public function can($permissionName, $params = [], $allowCaching = true)
    {
        //Special permissions for logged in status handling
        if (in_array($permissionName, [static::PERMISSION_NOT_LOGGED_IN, static::PERMISSION_LOGGED_IN])) {
            $isGuest = $this->getIsGuest();
            if ($isGuest && static::PERMISSION_NOT_LOGGED_IN || !$isGuest && static::PERMISSION_LOGGED_IN) {
                return resolve(true);
            }
            return reject(new Error("Uses::can() - denied by logged in status"));
        }
        if ($allowCaching && empty($params) && isset($this->_access[$permissionName])) {
            return $this->_access[$permissionName]
                ? resolve(true)
                : reject(new Error("Uses::can() - denied"));
        }
        if (($accessChecker = $this->getAccessChecker()) === null) {
            return reject(new Error("Uses::can() - denied (no access checker)"));
        }
        return $accessChecker->checkAccess($this->getId(), $permissionName, $params)
            ->otherwise(function() {
                return false;
            })
            ->then(function($access) use ($allowCaching, $permissionName) {
                if ($allowCaching && empty($params)) {
                    $this->_access[$permissionName] = $access;
                }
                return $access ? resolve(true) : reject(new Error("Uses::can() - denied"));
            });
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
     * Checks if the `Accept` header contains a content type that allows redirection to the login page.
     * The login page is assumed to serve `text/html` or `application/xhtml+xml` by default. You can change acceptable
     * content types by modifying [[acceptableRedirectTypes]] property.
     * @return bool whether this request may be redirected to the login page.
     * @see acceptableRedirectTypes
     */
    protected function checkRedirectAcceptable()
    {
        $acceptableTypes = $this->request->getAcceptableContentTypes();
        if (empty($acceptableTypes) || count($acceptableTypes) === 1 && array_keys($acceptableTypes)[0] === '*/*') {
            return true;
        }

        foreach ($acceptableTypes as $type => $params) {
            if (in_array($type, $this->acceptableRedirectTypes, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the access checker used for checking access.
     * @return CheckAccessInterface|null
     */
    protected function getAccessChecker()
    {
        return $this->accessChecker !== null ? $this->accessChecker : Reaction::$app->getAuthManager();
    }

    /**
     * Get Request helper
     * @return RequestHelper
     */
    protected function getRequest() {
        return $this->app->reqHelper;
    }
}
