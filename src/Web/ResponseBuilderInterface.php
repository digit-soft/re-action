<?php

namespace Reaction\Web;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Interface ResponseBuilderInterface
 * @package Reaction\Web
 * @property int                 $statusCode
 * @property string              $charset
 * @property HeaderCollection    $headers
 * @property CookieCollection    $cookies
 * @property AppRequestInterface $request
 */
interface ResponseBuilderInterface
{
    /**
     * Get status code
     * @return int
     */
    public function getStatusCode();

    /**
     * Set status code
     * @param int $code
     * @return Response
     */
    public function setStatusCode($code = 200);

    /**
     * Sets the response status code based on the exception.
     * @param \Exception|\Error $e the exception object.
     * @return Response the response object itself
     */
    public function setStatusCodeByException($e);

    /**
     * Set Response format
     * @param string $format
     * @return ResponseBuilderInterface
     */
    public function setFormat($format);

    /**
     * Returns the header collection.
     * The header collection contains the currently registered HTTP headers.
     * @return HeaderCollection the header collection
     */
    public function getHeaders();

    /**
     * Returns the cookie collection.
     *
     * Through the returned cookie collection, you add or remove cookies as follows,
     *
     * ```php
     * // add a cookie
     * $response->cookies->add(new Cookie([
     *     'name' => $name,
     *     'value' => $value,
     * ]);
     *
     * // remove a cookie
     * $response->cookies->remove('name');
     * // alternatively
     * unset($response->cookies['name']);
     * ```
     *
     * @return CookieCollection the cookie collection.
     */
    public function getCookies();

    /**
     * Set Response body
     * @param string|array|StreamInterface $body
     */
    public function setBody($body);

    /**
     * Get Response body and optionally format it
     * @param bool $format
     * @return array|StreamInterface|string
     */
    public function getBody($format = false);

    /**
     * Get Response body without formatting
     * @return array|StreamInterface|string
     */
    public function getRawBody();

    /**
     * Get Response body with formatting
     * @return StreamInterface|string
     */
    public function getFormattedBody();

    /**
     * Build response object
     * @return Response
     */
    public function build();
}