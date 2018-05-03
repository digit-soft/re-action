<?php

namespace Reaction\Web;

use Reaction\Base\BaseObject;

/**
 * Cookie represents information related with a cookie, such as [[name]], [[value]], [[domain]], etc.
 *
 * For more details and usage information on Cookie, see the [guide article on handling cookies](guide:runtime-sessions-cookies).
 */
class Cookie extends BaseObject
{
    /**
     * @var string name of the cookie
     */
    public $name;
    /**
     * @var string value of the cookie
     */
    public $value = '';
    /**
     * @var string domain of the cookie
     */
    public $domain = '';
    /**
     * @var int the timestamp at which the cookie expires. This is the server timestamp.
     * Defaults to 0, meaning "until the browser is closed".
     */
    public $expire = 0;
    /**
     * @var string the path on the server in which the cookie will be available on. The default is '/'.
     */
    public $path = '/';
    /**
     * @var bool whether cookie should be sent via secure connection
     */
    public $secure = false;
    /**
     * @var bool whether the cookie should be accessible only through the HTTP protocol.
     * By setting this property to true, the cookie will not be accessible by scripting languages,
     * such as JavaScript, which can effectively help to reduce identity theft through XSS attacks.
     */
    public $httpOnly = true;


    /**
     * Magic method to turn a cookie object into a string without having to explicitly access [[value]].
     *
     * ```php
     * if (isset($request->cookies['name'])) {
     *     $value = (string) $request->cookies['name'];
     * }
     * ```
     *
     * @return string The value of the cookie. If the value property is null, an empty string will be returned.
     */
    public function __toString()
    {
        return (string) $this->value;
    }

    /**
     * Get cookie for Set-Cookie header
     * @param string $validationKey Cookie validation key
     * @return string
     * @throws \Reaction\Exceptions\InvalidConfigException
     */
    public function getForHeader($validationKey = null) {
        if ($this->expire != 1 && isset($validationKey)) {
            $value = \Reaction::$app->security->hashData(serialize([$this->name, $this->value]), $validationKey);
        }
        $value = isset($value) && $value !== "" ? urlencode($value) : '';
        $name = urlencode($this->name);
        $data = [
            'Secure' => $this->secure ? null : '',
            'HttpOnly' => $this->httpOnly ? null : '',
            'Domain' => isset($this->domain) ? $this->domain : '',
            'Path' => isset($this->path) ? $this->path : '',
            'Expires' => !empty($this->expire) ? gmdate('D, d M Y H:i:s T', $this->expire) : '',
        ];
        foreach ($data as $key => $value) {
            if ($value === '') {
                unset($data[$key]);
            }
        }
        $data[$name] = $value;

        return $this->convertArrayToHeaderString($data);
    }

    /**
     * Convert cookie data array to Set-cookie header string
     * @param array $data
     * @return string
     */
    protected function convertArrayToHeaderString(array $data) {
        $strings = [];
        foreach ($data as $key => $val) {
            if (null === $val) {
                $strings[] = $key;
                continue;
            }
            if (is_bool($val)) {
                $val = !empty($val) ? 1 : 0;
            }
            $strings[] = $key . '=' . $val;
        }

        return implode('; ', $strings);
    }
}
