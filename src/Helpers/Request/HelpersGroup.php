<?php

namespace Reaction\Helpers\Request;

use Reaction\Base\RequestAppServiceLocator;

/**
 * Class Helpers
 * @package Reaction\Helpers\Request
 * @property Inflector    $inflector
 * @property StringHelper $string
 * @property ArrayHelper  $array
 * @property JsonHelper   $json
 * @property IpHelper     $ip
 * @property HtmlHelper   $html
 * @property HtmlPurifier $htmlPurifier
 * @property FileHelper   $file
 * @property UrlHelper    $url
 */
class HelpersGroup extends RequestAppServiceLocator
{
}