<?php

namespace Reaction\Helpers\Request;

/**
 * Class FormatConverter. Proxy to \Reaction\Helpers\FormatConverter
 * @package Reaction\Helpers\Request
 */
class FormatConverter extends RequestAppHelperProxy
{
    public $helperClass = 'Reaction\Helpers\FormatConverter';

    /**
     * Converts a date format pattern from [ICU format][] to [php date() function format][].
     *
     * The conversion is limited to date patterns that do not use escaped characters.
     * Patterns like `d 'of' MMMM yyyy` which will result in a date like `1 of December 2014` may not be converted correctly
     * because of the use of escaped characters.
     *
     * Pattern constructs that are not supported by the PHP format will be removed.
     *
     * [php date() function format]: http://php.net/manual/en/function.date.php
     * [ICU format]: http://userguide.icu-project.org/formatparse/datetime#TOC-Date-Time-Format-Syntax
     *
     * @param string $pattern date format pattern in ICU format.
     * @param string $type 'date', 'time', or 'datetime'.
     * @param string $locale the locale to use for converting ICU short patterns `short`, `medium`, `long` and `full`.
     * If not given, `Yii::$app->language` will be used.
     * @return string The converted date format pattern.
     * @see \Reaction\Helpers\FormatConverter::convertDateIcuToPhp()
     */
    public function convertDateIcuToPhp($pattern, $type = 'date', $locale = null)
    {
        return $this->proxyWithLanguage(__FUNCTION__, [$pattern, $type, $locale]);
    }

    /**
     * Converts a date format pattern from [php date() function format][] to [ICU format][].
     *
     * Pattern constructs that are not supported by the ICU format will be removed.
     *
     * [php date() function format]: http://php.net/manual/en/function.date.php
     * [ICU format]: http://userguide.icu-project.org/formatparse/datetime#TOC-Date-Time-Format-Syntax
     *
     * @param string $pattern date format pattern in php date()-function format.
     * @return string The converted date format pattern.
     * @see \Reaction\Helpers\FormatConverter::convertDatePhpToIcu()
     */
    public function convertDatePhpToIcu($pattern)
    {
        return $this->proxy(__FUNCTION__, [$pattern]);
    }

    /**
     * Converts a date format pattern from [ICU format][] to [jQuery UI date format][].
     *
     * Pattern constructs that are not supported by the jQuery UI format will be removed.
     *
     * [jQuery UI date format]: http://api.jqueryui.com/datepicker/#utility-formatDate
     * [ICU format]: http://userguide.icu-project.org/formatparse/datetime#TOC-Date-Time-Format-Syntax
     *
     * @param string $pattern date format pattern in ICU format.
     * @param string $type 'date', 'time', or 'datetime'.
     * @param string $locale the locale to use for converting ICU short patterns `short`, `medium`, `long` and `full`.
     * If not given, `Reaction::$app->language` will be used.
     * @return string The converted date format pattern.
     * @see \Reaction\Helpers\FormatConverter::convertDateIcuToJui()
     */
    public function convertDateIcuToJui($pattern, $type = 'date', $locale = null)
    {
        return $this->proxyWithLanguage(__FUNCTION__, [$pattern, $type, $locale]);
    }

    /**
     * Converts a date format pattern from [php date() function format][] to [jQuery UI date format][].
     *
     * The conversion is limited to date patterns that do not use escaped characters.
     * Patterns like `jS \o\f F Y` which will result in a date like `1st of December 2014` may not be converted correctly
     * because of the use of escaped characters.
     *
     * Pattern constructs that are not supported by the jQuery UI format will be removed.
     *
     * [php date() function format]: http://php.net/manual/en/function.date.php
     * [jQuery UI date format]: http://api.jqueryui.com/datepicker/#utility-formatDate
     *
     * @param string $pattern date format pattern in php date()-function format.
     * @return string The converted date format pattern.
     * @see \Reaction\Helpers\FormatConverter::convertDatePhpToJui()
     */
    public function convertDatePhpToJui($pattern)
    {
        return $this->proxy(__FUNCTION__, [$pattern]);
    }
}