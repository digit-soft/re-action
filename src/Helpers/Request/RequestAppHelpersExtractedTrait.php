<?php

namespace Reaction\Helpers\Request;

use Reaction\RequestApplicationInterface;

/**
 * Trait RequestAppHelpersExtractedTrait
 * @package Reaction\Helpers\Request
 *
 * @property RequestApplicationInterface $app
 * @property Inflector                   $inflector
 * @property StringHelper                $stringHlp
 * @property ArrayHelper                 $arrayHlp
 * @property HtmlHelper                  $htmlHlp
 * @property JsonHelper                  $jsonHlp
 * @property IpHelper                    $ipHlp
 * @property HtmlPurifier                $purifierHlp
 * @property FileHelper                  $fileHlp
 * @property UrlHelper                   $urlHlp
 */
trait RequestAppHelpersExtractedTrait
{
    /**
     * Getter for Inflector
     * @return Inflector
     */
    public function getInflector()
    {
        return $this->app->helpers->inflector;
    }

    /**
     * Getter for StringHelper
     * @return StringHelper
     */
    public function getStringHlp()
    {
        return $this->app->helpers->string;
    }

    /**
     * Getter for ArrayHelper
     * @return ArrayHelper
     */
    public function getArrayHlp()
    {
        return $this->app->helpers->array;
    }

    /**
     * Getter for HtmlHelper
     * @return HtmlHelper
     */
    public function getHtmlHlp()
    {
        return $this->app->helpers->html;
    }

    /**
     * Getter for HtmlPurifier
     * @return HtmlPurifier
     */
    public function getPurifierHlp()
    {
        return $this->app->helpers->htmlPurifier;
    }

    /**
     * Getter for JsonHelper
     * @return JsonHelper
     */
    public function getJsonHlp()
    {
        return $this->app->helpers->json;
    }

    /**
     * Getter for IpHelper
     * @return IpHelper
     */
    public function getIpHlp()
    {
        return $this->app->helpers->ip;
    }

    /**
     * Getter for FileHelper
     * @return FileHelper
     */
    public function getFileHlp()
    {
        return $this->app->helpers->file;
    }

    /**
     * Getter for UrlHelper
     * @return UrlHelper
     */
    public function getUrlHlp()
    {
        return $this->app->helpers->url;
    }
}