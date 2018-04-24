<?php

namespace Reaction\Web;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Reaction\Helpers\Request\HelpersGroup;

/**
 * Interface RequestInterface
 * @package Reaction\Web
 * @property string                   $charset
 * @property string                   $language
 * @property string                   $methodParam
 * @property bool                     $enableCsrfValidation
 * @property string                   $csrfParam
 * @property bool                     $enableCookieValidation
 * @property string                   $cookieValidationKey
 * @property array                    $csrfCookie
 * @property HelpersGroup             $helpers Helpers group
 * @property ResponseBuilderInterface $response
 * @property CookieCollection         $cookies
 */
interface RequestInterface extends ServerRequestInterface
{
    /**
     * Returns the token used to perform CSRF validation.
     *
     * This token is generated in a way to prevent [BREACH attacks](http://breachattack.com/). It may be passed
     * along via a hidden field of an HTML form or an HTTP header value to support CSRF validation.
     * @param bool $regenerate whether to regenerate CSRF token. When this parameter is true, each time
     * this method is called, a new CSRF token will be generated and persisted (in session or cookie).
     * @return string the token used to perform CSRF validation.
     */
    public function getCsrfToken($regenerate = false);

    /**
     * @return string the CSRF token sent via [[CSRF_HEADER]] by browser. Null is returned if no such header is sent.
     */
    public function getCsrfTokenFromHeader();

    /**
     * Performs the CSRF validation.
     *
     * This method will validate the user-provided CSRF token by comparing it with the one stored in cookie or session.
     * This method is mainly called in [[Controller::beforeAction()]].
     *
     * Note that the method will NOT perform CSRF validation if [[enableCsrfValidation]] is false or the HTTP method
     * is among GET, HEAD or OPTIONS.
     *
     * @param string $clientSuppliedToken the user-provided CSRF token to be validated. If null, the token will be retrieved from
     * the [[csrfParam]] POST field or HTTP header.
     * This parameter is available since version 2.0.4.
     * @return bool whether CSRF token is valid. If [[enableCsrfValidation]] is false, this method will return true.
     */
    public function validateCsrfToken($clientSuppliedToken = null);


    /**
     * Create request from React Request
     * @param ServerRequestInterface $requestReact
     * @return RequestInterface
     */
    public static function createFromReactRequest(ServerRequestInterface $requestReact);
}