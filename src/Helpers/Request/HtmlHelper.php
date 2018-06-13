<?php

namespace Reaction\Helpers\Request;

use Reaction\Base\Model;
use Reaction\Exceptions\InvalidArgumentException;
use Reaction\Helpers\Html;
use Reaction\Helpers\Url;
use Reaction\Web\RequestHelper;

/**
 * Class HtmlHelper. Proxy to \Reaction\Helpers\Html
 * @package Reaction\Helpers\Request
 */
class HtmlHelper extends RequestAppHelperProxy
{
    /**
     * @var int Counter for widgets unique ID creation
     */
    public $counter = 0;

    public $helperClass = 'Reaction\Helpers\Html';

    /**
     * Encodes special characters into HTML entities.
     * The [[\Reaction\RequestApplicationInterface::charset|application charset]] will be used for encoding.
     * @param string $content the content to be encoded
     * @param bool   $doubleEncode whether to encode HTML entities in `$content`. If false,
     * HTML entities in `$content` will not be further encoded.
     * @param string $encoding
     * @return string the encoded content
     * @see decode()
     * @see Html::encode()
     * @see http://www.php.net/manual/en/function.htmlspecialchars.php
     */
    public function encode($content, $doubleEncode = true, $encoding = null)
    {
        $this->ensureTranslated($content);
        return $this->proxyWithCharset(__FUNCTION__, [$content, $doubleEncode, $encoding]);
    }

    /**
     * Decodes special HTML entities back to the corresponding characters.
     * This is the opposite of [[encode()]].
     * @param string $content the content to be decoded
     * @return string the decoded content
     * @see encode()
     * @see Html::decode()
     * @see http://www.php.net/manual/en/function.htmlspecialchars-decode.php
     */
    public function decode($content)
    {
        $this->ensureTranslated($content);
        return $this->proxy(__FUNCTION__, [$content]);
    }

    /**
     * Generates a complete HTML tag.
     * @param string|bool|null $name the tag name. If $name is `null` or `false`, the corresponding content will be rendered without any tag.
     * @param string           $content the content to be enclosed between the start and end tags. It will not be HTML-encoded.
     * If this is coming from end users, you should consider [[encode()]] it to prevent XSS attacks.
     * @param array            $options the HTML tag attributes (HTML options) in terms of name-value pairs.
     * These will be rendered as the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     *
     * For example when using `['class' => 'my-class', 'target' => '_blank', 'value' => null]` it will result in the
     * html attributes rendered like this: `class="my-class" target="_blank"`.
     *
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @param string           $encoding
     * @return string the generated HTML tag
     * @see beginTag()
     * @see endTag()
     * @see Html::tag()
     */
    public function tag($name, $content = '', $options = [], $encoding = null)
    {
        $this->ensureTranslated($content);
        return $this->proxyWithCharset(__FUNCTION__, [$name, $content, $options, $encoding]);
    }

    /**
     * Generates a start tag.
     * @param string|bool|null $name the tag name. If $name is `null` or `false`, the corresponding content will be rendered without any tag.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @param string           $encoding
     * @return string the generated start tag
     * @see endTag()
     * @see tag()
     * @see Html::beginTag()
     */
    public function beginTag($name, $options = [], $encoding = null)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$name, $options, $encoding]);
    }

    /**
     * Generates an end tag.
     * @param string|bool|null $name the tag name. If $name is `null` or `false`, the corresponding content will be rendered without any tag.
     * @return string the generated end tag
     * @see beginTag()
     * @see tag()
     * @see Html::endTag()
     */
    public function endTag($name)
    {
        return $this->proxy(__FUNCTION__, [$name]);
    }

    /**
     * Generates a style tag.
     * @param string $content the style content
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @param string $encoding
     * @return string the generated style tag
     * @see Html::style()
     */
    public function style($content, $options = [], $encoding = null)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$content, $options, $encoding]);
    }

    /**
     * Generates a script tag.
     * @param string $content the script content
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @param string $encoding
     * @return string the generated script tag
     * @see Html::script()
     */
    public function script($content, $options = [], $encoding = null)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$content, $options, $encoding]);
    }

    /**
     * Generates a link tag that refers to an external CSS file.
     * @param array|string $url the URL of the external CSS file. This parameter will be processed by [[Url::to()]].
     * @param array $options the tag options in terms of name-value pairs. The following options are specially handled:
     *
     * - condition: specifies the conditional comments for IE, e.g., `lt IE 9`. When this is specified,
     *   the generated `link` tag will be enclosed within the conditional comments. This is mainly useful
     *   for supporting old versions of IE browsers.
     * - noscript: if set to true, `link` tag will be wrapped into `<noscript>` tags.
     *
     * The rest of the options will be rendered as the attributes of the resulting link tag. The values will
     * be HTML-encoded using [[encode()]]. If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @param string $encoding
     * @return string the generated link tag
     * @see Url::to()
     * @see Html::cssFile()
     */
    public function cssFile($url, $options = [], $encoding = null)
    {
        $arguments = [$url, $options, $encoding];
        $this->injectVariableToArguments($this->app->charset, $arguments, -1);
        $arguments[] = $this->app;
        return $this->proxy(__FUNCTION__, $arguments);
    }

    /**
     * Generates a script tag that refers to an external JavaScript file.
     * @param string $url the URL of the external JavaScript file. This parameter will be processed by [[Url::to()]].
     * @param array $options the tag options in terms of name-value pairs. The following option is specially handled:
     *
     * - condition: specifies the conditional comments for IE, e.g., `lt IE 9`. When this is specified,
     *   the generated `script` tag will be enclosed within the conditional comments. This is mainly useful
     *   for supporting old versions of IE browsers.
     *
     * The rest of the options will be rendered as the attributes of the resulting script tag. The values will
     * be HTML-encoded using [[encode()]]. If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @param string $encoding
     * @return string the generated script tag
     * @see Url::to()
     * @see Html::jsFile()
     */
    public function jsFile($url, $options = [], $encoding = null)
    {
        $arguments = [$url, $options, $encoding];
        $this->injectVariableToArguments($this->app->charset, $arguments, -1);
        $arguments[] = $this->app;
        return $this->proxy(__FUNCTION__, $arguments);
    }

    /**
     * Generates the meta tags containing CSRF token information.
     * @return string the generated meta tags
     * @see RequestHelper::enableCsrfValidation
     * @see Html::csrfMetaTags()
     */
    public function csrfMetaTags()
    {
        return $this->proxy(__FUNCTION__, [$this->app]);
    }

    /**
     * Generates a form start tag.
     * @param array|string $action the form action URL. This parameter will be processed by [[Url::to()]].
     * @param string       $method the form submission method, such as "post", "get", "put", "delete" (case-insensitive).
     * Since most browsers only support "post" and "get", if other methods are given, they will
     * be simulated using "post", and a hidden input will be added which contains the actual method type.
     * See [[\Reaction\Web\RequestHelper::methodParam]] for more details.
     * @param array        $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * Special options:
     *
     *  - `csrf`: whether to generate the CSRF hidden input. Defaults to true.
     * @return string the generated form start tag.
     * @see endForm()
     * @see Html::beginForm()
     */
    public function beginForm($action = '', $method = 'post', $options = [])
    {
        return $this->proxyWithApp(__FUNCTION__, [$action, $method, $options]);
    }

    /**
     * Generates a form end tag.
     * @return string the generated tag
     * @see beginForm()
     * @see Html::endForm()
     */
    public function endForm()
    {
        return $this->proxy(__FUNCTION__);
    }

    /**
     * Generates a hyperlink tag.
     * @param string            $text link body. It will NOT be HTML-encoded. Therefore you can pass in HTML code
     * such as an image tag. If this is coming from end users, you should consider [[encode()]]
     * it to prevent XSS attacks.
     * @param array|string|null $url the URL for the hyperlink tag. This parameter will be processed by [[Url::to()]]
     * and will be used for the "href" attribute of the tag. If this parameter is null, the "href" attribute
     * will not be generated.
     *
     * If you want to use an absolute url you can call [[Url::to()]] yourself, before passing the URL to this method,
     * like this:
     *
     * ```php
     * Html::a('link text', Url::to($url, true))
     * ```
     *
     * @param array             $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @param string            $encoding
     * @return string the generated hyperlink
     * @see Url::to()
     * @see Html::a()
     */
    public function a($text, $url = null, $options = [], $encoding = null)
    {
        $this->ensureTranslated($text);
        $arguments = [$text, $url, $options, $encoding];
        $this->injectVariableToArguments($this->app->charset, $arguments, -1);
        $arguments[] = $this->app;
        return $this->proxy(__FUNCTION__, $arguments);
    }

    /**
     * Generates a mailto hyperlink.
     * @param string $text link body. It will NOT be HTML-encoded. Therefore you can pass in HTML code
     * such as an image tag. If this is coming from end users, you should consider [[encode()]]
     * it to prevent XSS attacks.
     * @param string $email email address. If this is null, the first parameter (link body) will be treated
     * as the email address and used.
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @param string $encoding
     * @return string the generated mailto link
     * @see Html::mailto()
     */
    public function mailto($text, $email = null, $options = [], $encoding = null)
    {
        $this->ensureTranslated($text);
        $this->ensureTranslated($email);
        return $this->proxyWithCharset(__FUNCTION__, [$text, $email, $options, $encoding]);
    }

    /**
     * Generates an image tag.
     * @param array|string $src the image URL. This parameter will be processed by [[Url::to()]].
     * @param array        $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * It is possible to pass the `srcset` option as an array which keys are
     * descriptors and values are URLs. All URLs will be processed by [[Url::to()]].
     * @param string       $encoding
     * @return string the generated image tag.
     * @see Html::img()
     */
    public function img($src, $options = [], $encoding = null)
    {
        $arguments = [$src, $options, $encoding];
        $this->injectVariableToArguments($this->app->charset, $arguments, -1);
        $arguments[] = $this->app;
        return $this->proxy(__FUNCTION__, $arguments);
    }

    /**
     * Generates a label tag.
     * @param string $content label text. It will NOT be HTML-encoded. Therefore you can pass in HTML code
     * such as an image tag. If this is is coming from end users, you should [[encode()]]
     * it to prevent XSS attacks.
     * @param string $for the ID of the HTML element that this label is associated with.
     * If this is null, the "for" attribute will not be generated.
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @param string $encoding
     * @return string the generated label tag
     * @see Html::label()
     */
    public function label($content, $for = null, $options = [], $encoding = null)
    {
        $this->ensureTranslated($content);
        return $this->proxyWithCharset(__FUNCTION__, [$content, $for, $options, $encoding]);
    }

    /**
     * Generates a button tag.
     * @param string $content the content enclosed within the button tag. It will NOT be HTML-encoded.
     * Therefore you can pass in HTML code such as an image tag. If this is is coming from end users,
     * you should consider [[encode()]] it to prevent XSS attacks.
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @param string $encoding
     * @return string the generated button tag
     * @see Html::button()
     */
    public function button($content = 'Button', $options = [], $encoding = null)
    {
        $this->ensureTranslated($content);
        return $this->proxyWithCharset(__FUNCTION__, [$content, $options, $encoding]);
    }

    /**
     * Generates a submit button tag.
     *
     * Be careful when naming form elements such as submit buttons. According to the [jQuery documentation](https://api.jquery.com/submit/) there
     * are some reserved names that can cause conflicts, e.g. `submit`, `length`, or `method`.
     *
     * @param string $content the content enclosed within the button tag. It will NOT be HTML-encoded.
     * Therefore you can pass in HTML code such as an image tag. If this is is coming from end users,
     * you should consider [[encode()]] it to prevent XSS attacks.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @param string $encoding
     * @return string the generated submit button tag
     * @see Html::submitButton()
     */
    public function submitButton($content = 'Submit', $options = [], $encoding = null)
    {
        $this->ensureTranslated($content);
        return $this->proxyWithCharset(__FUNCTION__, [$content, $options, $encoding]);
    }

    /**
     * Generates a reset button tag.
     * @param string $content the content enclosed within the button tag. It will NOT be HTML-encoded.
     * Therefore you can pass in HTML code such as an image tag. If this is is coming from end users,
     * you should consider [[encode()]] it to prevent XSS attacks.
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @param string $encoding
     * @return string the generated reset button tag
     * @see Html::resetButton()
     */
    public function resetButton($content = 'Reset', $options = [], $encoding = null)
    {
        $this->ensureTranslated($content);
        return $this->proxyWithCharset(__FUNCTION__, [$content, $options, $encoding]);
    }

    /**
     * Generates an input type of the given type.
     * @param string $type the type attribute.
     * @param string $name the name attribute. If it is null, the name attribute will not be generated.
     * @param string $value the value attribute. If it is null, the value attribute will not be generated.
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @param string $encoding
     * @return string the generated input tag
     * @see Html::input()
     */
    public function input($type, $name = null, $value = null, $options = [], $encoding = null)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$type, $name, $value, $options, $encoding]);
    }

    /**
     * Generates an input button.
     * @param string $label the value attribute. If it is null, the value attribute will not be generated.
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @param string $encoding
     * @return string the generated button tag
     * @see Html::buttonInput()
     */
    public function buttonInput($label = 'Button', $options = [], $encoding = null)
    {
        $this->ensureTranslated($label);
        return $this->proxyWithCharset(__FUNCTION__, [$label, $options, $encoding]);
    }

    /**
     * Generates a submit input button.
     *
     * Be careful when naming form elements such as submit buttons. According to the [jQuery documentation](https://api.jquery.com/submit/) there
     * are some reserved names that can cause conflicts, e.g. `submit`, `length`, or `method`.
     *
     * @param string $label the value attribute. If it is null, the value attribute will not be generated.
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @param string $encoding
     * @return string the generated button tag
     * @see Html::submitInput()
     */
    public function submitInput($label = 'Submit', $options = [], $encoding = null)
    {
        $this->ensureTranslated($label);
        return $this->proxyWithCharset(__FUNCTION__, [$label, $options, $encoding]);
    }

    /**
     * Generates a reset input button.
     * @param string $label the value attribute. If it is null, the value attribute will not be generated.
     * @param array  $options the attributes of the button tag. The values will be HTML-encoded using [[encode()]].
     * Attributes whose value is null will be ignored and not put in the tag returned.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @param string $encoding
     * @return string the generated button tag
     * @see Html::resetInput()
     */
    public function resetInput($label = 'Reset', $options = [], $encoding = null)
    {
        $this->ensureTranslated($label);
        return $this->proxyWithCharset(__FUNCTION__, [$label, $options, $encoding]);
    }

    /**
     * Generates a text input field.
     * @param string $name the name attribute.
     * @param string $value the value attribute. If it is null, the value attribute will not be generated.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @param string $encoding
     * @return string the generated text input tag
     * @see Html::textInput()
     */
    public function textInput($name, $value = null, $options = [], $encoding = null)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$name, $value, $options, $encoding]);
    }

    /**
     * Generates a hidden input field.
     * @param string $name the name attribute.
     * @param string $value the value attribute. If it is null, the value attribute will not be generated.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @param string $encoding
     * @return string the generated hidden input tag
     * @see Html::hiddenInput()
     */
    public function hiddenInput($name, $value = null, $options = [], $encoding = null)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$name, $value, $options, $encoding]);
    }

    /**
     * Generates a password input field.
     * @param string $name the name attribute.
     * @param string $value the value attribute. If it is null, the value attribute will not be generated.
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @param string $encoding
     * @return string the generated password input tag
     * @see Html::passwordInput()
     */
    public function passwordInput($name, $value = null, $options = [], $encoding = null)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$name, $value, $options, $encoding]);
    }

    /**
     * Generates a file input field.
     * To use a file input field, you should set the enclosing form's "enctype" attribute to
     * be "multipart/form-data". After the form is submitted, the uploaded file information
     * can be obtained via $_FILES[$name] (see PHP documentation).
     * @param string $name the name attribute.
     * @param string $value the value attribute. If it is null, the value attribute will not be generated.
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @param string $encoding
     * @return string the generated file input tag
     * @see Html::fileInput()
     */
    public function fileInput($name, $value = null, $options = [], $encoding = null)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$name, $value, $options, $encoding]);
    }

    /**
     * Generates a text area input.
     * @param string $name the input name
     * @param string $value the input value. Note that it will be encoded using [[encode()]].
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * The following special options are recognized:
     *
     * - `doubleEncode`: whether to double encode HTML entities in `$value`. If `false`, HTML entities in `$value` will not
     *   be further encoded. This option is available since version 2.0.11.
     *
     * @param null   $encoding
     * @return string the generated text area tag
     * @see Html::textarea()
     */
    public function textarea($name, $value = '', $options = [], $encoding = null)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$name, $value, $options, $encoding]);
    }

    /**
     * Generates a radio button input.
     * @param string $name the name attribute.
     * @param bool $checked whether the radio button should be checked.
     * @param array $options the tag options in terms of name-value pairs.
     * @param string $encoding
     * See [[booleanInput()]] for details about accepted attributes.
     *
     * @return string the generated radio button tag
     * @see Html::radio()
     */
    public function radio($name, $checked = false, $options = [], $encoding = null)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$name, $checked, $options, $encoding]);
    }

    /**
     * Generates a checkbox input.
     * @param string $name the name attribute.
     * @param bool   $checked whether the checkbox should be checked.
     * @param array  $options the tag options in terms of name-value pairs.
     * @param string $encoding
     * See [[booleanInput()]] for details about accepted attributes.
     *
     * @return string the generated checkbox tag
     * @see Html::checkbox()
     */
    public function checkbox($name, $checked = false, $options = [], $encoding = null)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$name, $checked, $options, $encoding]);
    }

    /**
     * Generates a drop-down list.
     * @param string $name the input name
     * @param string|array|null $selection the selected value(s). String for single or array for multiple selection(s).
     * @param array $items the option data items. The array keys are option values, and the array values
     * are the corresponding option labels. The array can also be nested (i.e. some array values are arrays too).
     * For each sub-array, an option group will be generated whose label is the key associated with the sub-array.
     * If you have a list of data models, you may convert them into the format described above using
     * [[\yii\helpers\ArrayHelper::map()]].
     *
     * Note, the values and labels will be automatically HTML-encoded by this method, and the blank spaces in
     * the labels will also be HTML-encoded.
     * @param array $options the tag options in terms of name-value pairs. The following options are specially handled:
     * @param string $encoding
     *
     * - prompt: string, a prompt text to be displayed as the first option. Since version 2.0.11 you can use an array
     *   to override the value and to set other tag attributes:
     *
     *   ```php
     *   ['text' => 'Please select', 'options' => ['value' => 'none', 'class' => 'prompt', 'label' => 'Select']],
     *   ```
     *
     * - options: array, the attributes for the select option tags. The array keys must be valid option values,
     *   and the array values are the extra attributes for the corresponding option tags. For example,
     *
     *   ```php
     *   [
     *       'value1' => ['disabled' => true],
     *       'value2' => ['label' => 'value 2'],
     *   ];
     *   ```
     *
     * - groups: array, the attributes for the optgroup tags. The structure of this is similar to that of 'options',
     *   except that the array keys represent the optgroup labels specified in $items.
     * - encodeSpaces: bool, whether to encode spaces in option prompt and option value with `&nbsp;` character.
     *   Defaults to false.
     * - encode: bool, whether to encode option prompt and option value characters.
     *   Defaults to `true`.
     *
     * The rest of the options will be rendered as the attributes of the resulting tag. The values will
     * be HTML-encoded using [[encode()]]. If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated drop-down list tag
     * @see Html::dropDownList()
     */
    public function dropDownList($name, $selection = null, $items = [], $options = [], $encoding = null)
    {
        $this->ensureTranslatedArray($items);
        return $this->proxyWithCharset(__FUNCTION__, [$name, $selection, $items, $options, $encoding]);
    }

    /**
     * Generates a list box.
     * @param string            $name the input name
     * @param string|array|null $selection the selected value(s). String for single or array for multiple selection(s).
     * @param array             $items the option data items. The array keys are option values, and the array values
     * are the corresponding option labels. The array can also be nested (i.e. some array values are arrays too).
     * For each sub-array, an option group will be generated whose label is the key associated with the sub-array.
     * If you have a list of data models, you may convert them into the format described above using
     * [[\yii\helpers\ArrayHelper::map()]].
     *
     * Note, the values and labels will be automatically HTML-encoded by this method, and the blank spaces in
     * the labels will also be HTML-encoded.
     * @param array             $options the tag options in terms of name-value pairs. The following options are specially handled:
     * @param string $encoding
     *
     * - prompt: string, a prompt text to be displayed as the first option. Since version 2.0.11 you can use an array
     *   to override the value and to set other tag attributes:
     *
     *   ```php
     *   ['text' => 'Please select', 'options' => ['value' => 'none', 'class' => 'prompt', 'label' => 'Select']],
     *   ```
     *
     * - options: array, the attributes for the select option tags. The array keys must be valid option values,
     *   and the array values are the extra attributes for the corresponding option tags. For example,
     *
     *   ```php
     *   [
     *       'value1' => ['disabled' => true],
     *       'value2' => ['label' => 'value 2'],
     *   ];
     *   ```
     *
     * - groups: array, the attributes for the optgroup tags. The structure of this is similar to that of 'options',
     *   except that the array keys represent the optgroup labels specified in $items.
     * - unselect: string, the value that will be submitted when no option is selected.
     *   When this attribute is set, a hidden field will be generated so that if no option is selected in multiple
     *   mode, we can still obtain the posted unselect value.
     * - encodeSpaces: bool, whether to encode spaces in option prompt and option value with `&nbsp;` character.
     *   Defaults to false.
     * - encode: bool, whether to encode option prompt and option value characters.
     *   Defaults to `true`. This option is available since 2.0.3.
     *
     * The rest of the options will be rendered as the attributes of the resulting tag. The values will
     * be HTML-encoded using [[encode()]]. If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated list box tag
     * @see Html::listBox()
     */
    public function listBox($name, $selection = null, $items = [], $options = [], $encoding = null)
    {
        $this->ensureTranslatedArray($items);
        return $this->proxyWithCharset(__FUNCTION__, [$name, $selection, $items, $options, $encoding]);
    }

    /**
     * Generates a list of checkboxes.
     * A checkbox list allows multiple selection, like [[listBox()]].
     * As a result, the corresponding submitted value is an array.
     * @param string            $name the name attribute of each checkbox.
     * @param string|array|null $selection the selected value(s). String for single or array for multiple selection(s).
     * @param array             $items the data item used to generate the checkboxes.
     * The array keys are the checkbox values, while the array values are the corresponding labels.
     * @param array             $options options (name => config) for the checkbox list container tag.
     * The following options are specially handled:
     *
     * - tag: string|false, the tag name of the container element. False to render checkbox without container.
     *   See also [[tag()]].
     * - unselect: string, the value that should be submitted when none of the checkboxes is selected.
     *   By setting this option, a hidden input will be generated.
     * - encode: boolean, whether to HTML-encode the checkbox labels. Defaults to true.
     *   This option is ignored if `item` option is set.
     * - separator: string, the HTML code that separates items.
     * - itemOptions: array, the options for generating the checkbox tag using [[checkbox()]].
     * - item: callable, a callback that can be used to customize the generation of the HTML code
     *   corresponding to a single item in $items. The signature of this callback must be:
     *
     *   ```php
     *   function ($index, $label, $name, $checked, $value)
     *   ```
     *
     *   where $index is the zero-based index of the checkbox in the whole list; $label
     *   is the label for the checkbox; and $name, $value and $checked represent the name,
     *   value and the checked status of the checkbox input, respectively.
     *
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @param string            $encoding
     * @return string the generated checkbox list
     * @see Html::checkboxList()
     */
    public function checkboxList($name, $selection = null, $items = [], $options = [], $encoding = null)
    {
        $this->ensureTranslatedArray($items);
        return $this->proxyWithCharset(__FUNCTION__, [$name, $selection, $items, $options, $encoding]);
    }

    /**
     * Generates a list of radio buttons.
     * A radio button list is like a checkbox list, except that it only allows single selection.
     * @param string            $name the name attribute of each radio button.
     * @param string|array|null $selection the selected value(s). String for single or array for multiple selection(s).
     * @param array             $items the data item used to generate the radio buttons.
     * The array keys are the radio button values, while the array values are the corresponding labels.
     * @param array             $options options (name => config) for the radio button list container tag.
     * The following options are specially handled:
     *
     * - tag: string|false, the tag name of the container element. False to render radio buttons without container.
     *   See also [[tag()]].
     * - unselect: string, the value that should be submitted when none of the radio buttons is selected.
     *   By setting this option, a hidden input will be generated.
     * - encode: boolean, whether to HTML-encode the checkbox labels. Defaults to true.
     *   This option is ignored if `item` option is set.
     * - separator: string, the HTML code that separates items.
     * - itemOptions: array, the options for generating the radio button tag using [[radio()]].
     * - item: callable, a callback that can be used to customize the generation of the HTML code
     *   corresponding to a single item in $items. The signature of this callback must be:
     *
     *   ```php
     *   function ($index, $label, $name, $checked, $value)
     *   ```
     *
     *   where $index is the zero-based index of the radio button in the whole list; $label
     *   is the label for the radio button; and $name, $value and $checked represent the name,
     *   value and the checked status of the radio button input, respectively.
     *
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @param string            $encoding
     * @return string the generated radio button list
     * @see Html::radioList()
     */
    public function radioList($name, $selection = null, $items = [], $options = [], $encoding = null)
    {
        $this->ensureTranslatedArray($items);
        return $this->proxyWithCharset(__FUNCTION__, [$name, $selection, $items, $options, $encoding]);
    }

    /**
     * Generates an unordered list.
     * @param array|\Traversable $items the items for generating the list. Each item generates a single list item.
     * Note that items will be automatically HTML encoded if `$options['encode']` is not set or true.
     * @param array              $options options (name => config) for the radio button list. The following options are supported:
     *
     * - encode: boolean, whether to HTML-encode the items. Defaults to true.
     *   This option is ignored if the `item` option is specified.
     * - separator: string, the HTML code that separates items. Defaults to a simple newline (`"\n"`).
     *   This option is available since version 2.0.7.
     * - itemOptions: array, the HTML attributes for the `li` tags. This option is ignored if the `item` option is specified.
     * - item: callable, a callback that is used to generate each individual list item.
     *   The signature of this callback must be:
     *
     *   ```php
     *   function ($item, $index)
     *   ```
     *
     *   where $index is the array key corresponding to `$item` in `$items`. The callback should return
     *   the whole list item tag.
     *
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @param string             $encoding
     * @return string the generated unordered list. An empty list tag will be returned if `$items` is empty.
     * @see Html::ul()
     */
    public function ul($items, $options = [], $encoding = null)
    {
        $this->ensureTranslatedArray($items);
        return $this->proxyWithCharset(__FUNCTION__, [$items, $options, $encoding]);
    }

    /**
     * Generates an ordered list.
     * @param array|\Traversable $items the items for generating the list. Each item generates a single list item.
     * Note that items will be automatically HTML encoded if `$options['encode']` is not set or true.
     * @param array $options options (name => config) for the radio button list. The following options are supported:
     * @param string $encoding
     *
     * - encode: boolean, whether to HTML-encode the items. Defaults to true.
     *   This option is ignored if the `item` option is specified.
     * - itemOptions: array, the HTML attributes for the `li` tags. This option is ignored if the `item` option is specified.
     * - item: callable, a callback that is used to generate each individual list item.
     *   The signature of this callback must be:
     *
     *   ```php
     *   function ($item, $index)
     *   ```
     *
     *   where $index is the array key corresponding to `$item` in `$items`. The callback should return
     *   the whole list item tag.
     *
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated ordered list. An empty string is returned if `$items` is empty.
     * @see Html::ol()
     */
    public function ol($items, $options = [], $encoding = null)
    {
        $this->ensureTranslatedArray($items);
        return $this->proxyWithCharset(__FUNCTION__, [$items, $options, $encoding]);
    }

    /**
     * Generates a label tag for the given model attribute.
     * The label text is the label associated with the attribute, obtained via [[Model::getAttributeLabel()]].
     * @param Model  $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * The following options are specially handled:
     *
     * - label: this specifies the label to be displayed. Note that this will NOT be [[encode()|encoded]].
     *   If this is not set, [[Model::getAttributeLabel()]] will be called to get the label for display
     *   (after encoding).
     *
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @param string $encoding
     * @return string the generated label tag
     * @see Html::activeLabel()
     */
    public function activeLabel($model, $attribute, $options = [], $encoding = null)
    {
        if(!isset($encoding)) {
            $encoding = $this->app->charset;
        }
        $for = \Reaction\Helpers\ArrayHelper::remove($options, 'for', $this->getInputId($model, $attribute));
        $attribute = $this->getAttributeName($attribute);
        $label = \Reaction\Helpers\ArrayHelper::remove($options, 'label', $this->encode($model->getAttributeLabel($attribute), true, $encoding));
        return $this->label($label, $for, $options, $encoding);
    }

    /**
     * Generates a hint tag for the given model attribute.
     * The hint text is the hint associated with the attribute, obtained via [[Model::getAttributeHint()]].
     * If no hint content can be obtained, method will return an empty string.
     * @param Model  $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * The following options are specially handled:
     *
     * - hint: this specifies the hint to be displayed. Note that this will NOT be [[encode()|encoded]].
     *   If this is not set, [[Model::getAttributeHint()]] will be called to get the hint for display
     *   (without encoding).
     * @param string   $encoding
     *
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated hint tag
     * @see Html::activeHint()
     */
    public function activeHint($model, $attribute, $options = [], $encoding = null)
    {
        $attribute = $this->getAttributeName($attribute);
        $hint = isset($options['hint']) ? $options['hint'] : $model->getAttributeHint($attribute);
        if (empty($hint)) {
            return '';
        }
        $tag = \Reaction\Helpers\ArrayHelper::remove($options, 'tag', 'div');
        unset($options['hint']);
        return $this->tag($tag, $hint, $options, $encoding);
    }

    /**
     * Generates a summary of the validation errors.
     * If there is no validation error, an empty error summary markup will still be generated, but it will be hidden.
     * @param Model|Model[] $models the model(s) whose validation errors are to be displayed.
     * @param array         $options the tag options in terms of name-value pairs. The following options are specially handled:
     *
     * - header: string, the header HTML for the error summary. If not set, a default prompt string will be used.
     * - footer: string, the footer HTML for the error summary. Defaults to empty string.
     * - encode: boolean, if set to false then the error messages won't be encoded. Defaults to `true`.
     * - showAllErrors: boolean, if set to true every error message for each attribute will be shown otherwise
     *   only the first error message for each attribute will be shown. Defaults to `false`.
     *   Option is available since 2.0.10.
     *
     * The rest of the options will be rendered as the attributes of the container tag.
     *
     * @param string        $encoding
     * @return string the generated error summary
     * @see Html::errorSummary()
     */
    public function errorSummary($models, $options = [], $encoding = null)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$models, $options, $encoding]);
    }

    /**
     * Generates a tag that contains the first validation error of the specified model attribute.
     * Note that even if there is no validation error, this method will still return an empty error tag.
     * @param Model  $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array  $options the tag options in terms of name-value pairs. The values will be HTML-encoded
     * using [[encode()]]. If a value is null, the corresponding attribute will not be rendered.
     *
     * The following options are specially handled:
     *
     * - tag: this specifies the tag name. If not set, "div" will be used.
     *   See also [[tag()]].
     * - encode: boolean, if set to false then the error message won't be encoded.
     * - errorSource (since 2.0.14): \Closure|callable, callback that will be called to obtain an error message.
     *   The signature of the callback must be: `function ($model, $attribute)` and return a string.
     *   When not set, the `$model->getFirstError()` method will be called.
     *
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @param string $encoding
     * @return string the generated label tag
     * @see Html::error()
     */
    public function error($model, $attribute, $options = [], $encoding = null)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$model, $attribute, $options, $encoding]);
    }

    /**
     * Generates an input tag for the given model attribute.
     * This method will generate the "name" and "value" tag attributes automatically for the model attribute
     * unless they are explicitly specified in `$options`.
     * @param string $type the input type (e.g. 'text', 'password')
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     *
     * @param string $encoding
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @return string the generated input tag
     * @see Html::activeInput()
     */
    public function activeInput($type, $model, $attribute, $options = [], $encoding = null)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$type, $model, $attribute, $options, $encoding]);
    }

    /**
     * Generates a text input tag for the given model attribute.
     * This method will generate the "name" and "value" tag attributes automatically for the model attribute
     * unless they are explicitly specified in `$options`.
     * @param Model  $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * The following special options are recognized:
     *
     * - maxlength: integer|boolean, when `maxlength` is set true and the model attribute is validated
     *   by a string validator, the `maxlength` option will take the value of [[\yii\validators\StringValidator::max]].
     * - placeholder: string|boolean, when `placeholder` equals `true`, the attribute label from the $model will be used
     *   as a placeholder.
     *
     * @param string $encoding
     * @return string the generated input tag
     * @see Html::activeTextInput()
     */
    public function activeTextInput($model, $attribute, $options = [], $encoding = null)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$model, $attribute, $options, $encoding]);
    }

    /**
     * Generates a hidden input tag for the given model attribute.
     * This method will generate the "name" and "value" tag attributes automatically for the model attribute
     * unless they are explicitly specified in `$options`.
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * @param string $encoding
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @return string the generated input tag
     * @see Html::activeHiddenInput()
     */
    public function activeHiddenInput($model, $attribute, $options = [], $encoding = null)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$model, $attribute, $options, $encoding]);
    }

    /**
     * Generates a password input tag for the given model attribute.
     * This method will generate the "name" and "value" tag attributes automatically for the model attribute
     * unless they are explicitly specified in `$options`.
     * @param Model  $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * The following special options are recognized:
     *
     * - maxlength: integer|boolean, when `maxlength` is set true and the model attribute is validated
     *   by a string validator, the `maxlength` option will take the value of [[\yii\validators\StringValidator::max]].
     *   This option is available since version 2.0.6.
     * - placeholder: string|boolean, when `placeholder` equals `true`, the attribute label from the $model will be used
     *   as a placeholder (this behavior is available since version 2.0.14).
     *
     * @param string $encoding
     *
     * @return string the generated input tag
     * @see Html::activePasswordInput()
     */
    public function activePasswordInput($model, $attribute, $options = [], $encoding = null)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$model, $attribute, $options, $encoding]);
    }

    /**
     * Generates a file input tag for the given model attribute.
     * This method will generate the "name" and "value" tag attributes automatically for the model attribute
     * unless they are explicitly specified in `$options`.
     * Additionally, if a separate set of HTML options array is defined inside `$options` with a key named `hiddenOptions`,
     * it will be passed to the `activeHiddenInput` field as its own `$options` parameter.
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * If `hiddenOptions` parameter which is another set of HTML options array is defined, it will be extracted
     * from `$options` to be used for the hidden input.
     * @param string $encoding
     * @return string the generated input tag
     * @see Html::activeFileInput()
     */
    public function activeFileInput($model, $attribute, $options = [], $encoding = null)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$model, $attribute, $options, $encoding]);
    }

    /**
     * Generates a textarea tag for the given model attribute.
     * The model attribute value will be used as the content in the textarea.
     * @param Model  $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * The following special options are recognized:
     *
     * - maxlength: integer|boolean, when `maxlength` is set true and the model attribute is validated
     *   by a string validator, the `maxlength` option will take the value of [[\yii\validators\StringValidator::max]].
     *   This option is available since version 2.0.6.
     * - placeholder: string|boolean, when `placeholder` equals `true`, the attribute label from the $model will be used
     *   as a placeholder (this behavior is available since version 2.0.14).
     *
     * @param string $encoding
     * @return string the generated textarea tag
     * @see Html::activeTextarea()
     */
    public function activeTextarea($model, $attribute, $options = [], $encoding = null)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$model, $attribute, $options, $encoding]);
    }

    /**
     * Generates a radio button tag together with a label for the given model attribute.
     * This method will generate the "checked" tag attribute according to the model attribute value.
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array $options the tag options in terms of name-value pairs.
     * See [[booleanInput()]] for details about accepted attributes.
     * @param string $encoding
     *
     * @return string the generated radio button tag
     * @see Html::activeRadio()
     */
    public function activeRadio($model, $attribute, $options = [], $encoding = null)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$model, $attribute, $options, $encoding]);
    }

    /**
     * Generates a checkbox tag together with a label for the given model attribute.
     * This method will generate the "checked" tag attribute according to the model attribute value.
     * @param Model  $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array  $options the tag options in terms of name-value pairs.
     * See [[booleanInput()]] for details about accepted attributes.
     * @param string $encoding
     *
     * @return string the generated checkbox tag
     * @see Html::activeCheckbox()
     */
    public function activeCheckbox($model, $attribute, $options = [], $encoding = null)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$model, $attribute, $options, $encoding]);
    }

    /**
     * Generates a drop-down list for the given model attribute.
     * The selection of the drop-down list is taken from the value of the model attribute.
     * @param Model  $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array  $items the option data items. The array keys are option values, and the array values
     * are the corresponding option labels. The array can also be nested (i.e. some array values are arrays too).
     * For each sub-array, an option group will be generated whose label is the key associated with the sub-array.
     * If you have a list of data models, you may convert them into the format described above using
     * [[\yii\helpers\ArrayHelper::map()]].
     *
     * Note, the values and labels will be automatically HTML-encoded by this method, and the blank spaces in
     * the labels will also be HTML-encoded.
     * @param array  $options the tag options in terms of name-value pairs. The following options are specially handled:
     *
     * - prompt: string, a prompt text to be displayed as the first option. Since version 2.0.11 you can use an array
     *   to override the value and to set other tag attributes:
     *
     *   ```php
     *   ['text' => 'Please select', 'options' => ['value' => 'none', 'class' => 'prompt', 'label' => 'Select']],
     *   ```
     *
     * - options: array, the attributes for the select option tags. The array keys must be valid option values,
     *   and the array values are the extra attributes for the corresponding option tags. For example,
     *
     *   ```php
     *   [
     *       'value1' => ['disabled' => true],
     *       'value2' => ['label' => 'value 2'],
     *   ];
     *   ```
     *
     * - groups: array, the attributes for the optgroup tags. The structure of this is similar to that of 'options',
     *   except that the array keys represent the optgroup labels specified in $items.
     * - encodeSpaces: bool, whether to encode spaces in option prompt and option value with `&nbsp;` character.
     *   Defaults to false.
     * - encode: bool, whether to encode option prompt and option value characters.
     *   Defaults to `true`. This option is available since 2.0.3.
     *
     * The rest of the options will be rendered as the attributes of the resulting tag. The values will
     * be HTML-encoded using [[encode()]]. If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @param string $encoding
     *
     * @return string the generated drop-down list tag
     * @see Html::activeDropDownList()
     */
    public function activeDropDownList($model, $attribute, $items, $options = [], $encoding = null)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$model, $attribute, $items, $options, $encoding]);
    }

    /**
     * Generates a list box.
     * The selection of the list box is taken from the value of the model attribute.
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array $items the option data items. The array keys are option values, and the array values
     * are the corresponding option labels. The array can also be nested (i.e. some array values are arrays too).
     * For each sub-array, an option group will be generated whose label is the key associated with the sub-array.
     * If you have a list of data models, you may convert them into the format described above using
     * [[\yii\helpers\ArrayHelper::map()]].
     *
     * Note, the values and labels will be automatically HTML-encoded by this method, and the blank spaces in
     * the labels will also be HTML-encoded.
     * @param array $options the tag options in terms of name-value pairs. The following options are specially handled:
     *
     * - prompt: string, a prompt text to be displayed as the first option. Since version 2.0.11 you can use an array
     *   to override the value and to set other tag attributes:
     *
     *   ```php
     *   ['text' => 'Please select', 'options' => ['value' => 'none', 'class' => 'prompt', 'label' => 'Select']],
     *   ```
     *
     * - options: array, the attributes for the select option tags. The array keys must be valid option values,
     *   and the array values are the extra attributes for the corresponding option tags. For example,
     *
     *   ```php
     *   [
     *       'value1' => ['disabled' => true],
     *       'value2' => ['label' => 'value 2'],
     *   ];
     *   ```
     *
     * - groups: array, the attributes for the optgroup tags. The structure of this is similar to that of 'options',
     *   except that the array keys represent the optgroup labels specified in $items.
     * - unselect: string, the value that will be submitted when no option is selected.
     *   When this attribute is set, a hidden field will be generated so that if no option is selected in multiple
     *   mode, we can still obtain the posted unselect value.
     * - encodeSpaces: bool, whether to encode spaces in option prompt and option value with `&nbsp;` character.
     *   Defaults to false.
     * - encode: bool, whether to encode option prompt and option value characters.
     *   Defaults to `true`. This option is available since 2.0.3.
     *
     * The rest of the options will be rendered as the attributes of the resulting tag. The values will
     * be HTML-encoded using [[encode()]]. If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @param string $encoding
     *
     * @return string the generated list box tag
     * @see Html::activeListBox()
     */
    public function activeListBox($model, $attribute, $items, $options = [], $encoding = null)
    {
        $this->ensureTranslatedArray($items);
        return $this->proxyWithCharset(__FUNCTION__, [$model, $attribute, $items, $options, $encoding]);
    }

    /**
     * Generates a list of checkboxes.
     * A checkbox list allows multiple selection, like [[listBox()]].
     * As a result, the corresponding submitted value is an array.
     * The selection of the checkbox list is taken from the value of the model attribute.
     * @param Model  $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array  $items the data item used to generate the checkboxes.
     * The array keys are the checkbox values, and the array values are the corresponding labels.
     * Note that the labels will NOT be HTML-encoded, while the values will.
     * @param array  $options options (name => config) for the checkbox list container tag.
     * The following options are specially handled:
     *
     * - tag: string|false, the tag name of the container element. False to render checkbox without container.
     *   See also [[tag()]].
     * - unselect: string, the value that should be submitted when none of the checkboxes is selected.
     *   You may set this option to be null to prevent default value submission.
     *   If this option is not set, an empty string will be submitted.
     * - encode: boolean, whether to HTML-encode the checkbox labels. Defaults to true.
     *   This option is ignored if `item` option is set.
     * - separator: string, the HTML code that separates items.
     * - itemOptions: array, the options for generating the checkbox tag using [[checkbox()]].
     * - item: callable, a callback that can be used to customize the generation of the HTML code
     *   corresponding to a single item in $items. The signature of this callback must be:
     *
     *   ```php
     *   function ($index, $label, $name, $checked, $value)
     *   ```
     *
     *   where $index is the zero-based index of the checkbox in the whole list; $label
     *   is the label for the checkbox; and $name, $value and $checked represent the name,
     *   value and the checked status of the checkbox input.
     *
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @param string $encoding
     *
     * @return string the generated checkbox list
     * @see Html::activeCheckboxList()
     */
    public function activeCheckboxList($model, $attribute, $items, $options = [], $encoding = null)
    {
        $this->ensureTranslatedArray($items);
        return $this->proxyWithCharset(__FUNCTION__, [$model, $attribute, $items, $options, $encoding]);
    }

    /**
     * Generates a list of radio buttons.
     * A radio button list is like a checkbox list, except that it only allows single selection.
     * The selection of the radio buttons is taken from the value of the model attribute.
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array $items the data item used to generate the radio buttons.
     * The array keys are the radio values, and the array values are the corresponding labels.
     * Note that the labels will NOT be HTML-encoded, while the values will.
     * @param array $options options (name => config) for the radio button list container tag.
     * The following options are specially handled:
     *
     * - tag: string|false, the tag name of the container element. False to render radio button without container.
     *   See also [[tag()]].
     * - unselect: string, the value that should be submitted when none of the radio buttons is selected.
     *   You may set this option to be null to prevent default value submission.
     *   If this option is not set, an empty string will be submitted.
     * - encode: boolean, whether to HTML-encode the checkbox labels. Defaults to true.
     *   This option is ignored if `item` option is set.
     * - separator: string, the HTML code that separates items.
     * - itemOptions: array, the options for generating the radio button tag using [[radio()]].
     * - item: callable, a callback that can be used to customize the generation of the HTML code
     *   corresponding to a single item in $items. The signature of this callback must be:
     *
     *   ```php
     *   function ($index, $label, $name, $checked, $value)
     *   ```
     *
     *   where $index is the zero-based index of the radio button in the whole list; $label
     *   is the label for the radio button; and $name, $value and $checked represent the name,
     *   value and the checked status of the radio button input.
     *
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @param string $encoding
     *
     * @return string the generated radio button list
     * @see Html::activeRadioList()
     */
    public function activeRadioList($model, $attribute, $items, $options = [], $encoding = null)
    {
        $this->ensureTranslatedArray($items);
        return $this->proxyWithCharset(__FUNCTION__, [$model, $attribute, $items, $options, $encoding]);
    }

    /**
     * Renders Bootstrap static form control.
     *
     * By default value will be HTML-encoded using [[encode()]], you may control this behavior
     * via 'encode' option.
     * @param string $value static control value.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. There are also a special options:
     *
     * - encode: boolean, whether value should be HTML-encoded or not.
     *
     * @return string generated HTML
     * @see http://getbootstrap.com/css/#forms-controls-static
     * @see Html::staticControl()
     */
    public function staticControl($value, $options = [])
    {
        $this->ensureTranslated($value);
        return $this->proxy(__FUNCTION__, [$value, $options]);
    }

    /**
     * Generates a Bootstrap static form control for the given model attribute.
     * @param \Reaction\Base\Model $model the model object.
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array $options the tag options in terms of name-value pairs. See [[staticControl()]] for details.
     * @return string generated HTML
     * @see staticControl()
     * @see Html::activeStaticControl()
     */
    public function activeStaticControl($model, $attribute, $options = [])
    {
        if (isset($options['value'])) {
            $this->ensureTranslated($options['value']);
        }
        return $this->proxy(__FUNCTION__, [$model, $attribute, $options]);
    }

    /**
     * Renders the option tags that can be used by [[dropDownList()]] and [[listBox()]].
     * @param string|array|null $selection the selected value(s). String for single or array for multiple selection(s).
     * @param array             $items the option data items. The array keys are option values, and the array values
     * are the corresponding option labels. The array can also be nested (i.e. some array values are arrays too).
     * For each sub-array, an option group will be generated whose label is the key associated with the sub-array.
     * If you have a list of data models, you may convert them into the format described above using
     * [[\yii\helpers\ArrayHelper::map()]].
     *
     * Note, the values and labels will be automatically HTML-encoded by this method, and the blank spaces in
     * the labels will also be HTML-encoded.
     * @param array             $tagOptions the $options parameter that is passed to the [[dropDownList()]] or [[listBox()]] call.
     * This method will take out these elements, if any: "prompt", "options" and "groups". See more details
     * in [[dropDownList()]] for the explanation of these elements.
     *
     * @param string            $encoding
     * @return string the generated list options
     * @see Html::renderSelectOptions()
     */
    public function renderSelectOptions($selection, $items, &$tagOptions = [], $encoding = null)
    {
        $this->ensureTranslatedArray($items);
        return $this->proxyWithCharset(__FUNCTION__, [$selection, $items, &$tagOptions, $encoding]);
    }

    /**
     * Renders the HTML tag attributes.
     *
     * Attributes whose values are of boolean type will be treated as
     * [boolean attributes](http://www.w3.org/TR/html5/infrastructure.html#boolean-attributes).
     *
     * Attributes whose values are null will not be rendered.
     *
     * The values of attributes will be HTML-encoded using [[encode()]].
     *
     * The "data" attribute is specially handled when it is receiving an array value. In this case,
     * the array will be "expanded" and a list data attributes will be rendered. For example,
     * if `'data' => ['id' => 1, 'name' => 'yii']`, then this will be rendered:
     * `data-id="1" data-name="yii"`.
     * Additionally `'data' => ['params' => ['id' => 1, 'name' => 'yii'], 'status' => 'ok']` will be rendered as:
     * `data-params='{"id":1,"name":"yii"}' data-status="ok"`.
     *
     * @param array $attributes attributes to be rendered. The attribute values will be HTML-encoded using [[encode()]].
     * @param string $encoding
     * @return string the rendering result. If the attributes are not empty, they will be rendered
     * into a string with a leading white space (so that it can be directly appended to the tag name
     * in a tag. If there is no attribute, an empty string will be returned.
     * @see addCssClass()
     * @see Html::renderTagAttributes()
     */
    public function renderTagAttributes($attributes, $encoding = null)
    {
        return $this->proxyWithCharset(__FUNCTION__, [$attributes, $encoding]);
    }

    /**
     * Adds a CSS class (or several classes) to the specified options.
     *
     * If the CSS class is already in the options, it will not be added again.
     * If class specification at given options is an array, and some class placed there with the named (string) key,
     * overriding of such key will have no effect. For example:
     *
     * ```php
     * $options = ['class' => ['persistent' => 'initial']];
     * Html::addCssClass($options, ['persistent' => 'override']);
     * var_dump($options['class']); // outputs: array('persistent' => 'initial');
     * ```
     *
     * @param array $options the options to be modified.
     * @param string|array $class the CSS class(es) to be added
     * @see mergeCssClasses()
     * @see removeCssClass()
     * @see Html::addCssClass()
     */
    public function addCssClass(&$options, $class)
    {
        $this->proxy(__FUNCTION__, [&$options, $class]);
    }

    /**
     * Removes a CSS class from the specified options.
     * @param array $options the options to be modified.
     * @param string|array $class the CSS class(es) to be removed
     * @see addCssClass()
     * @see Html::removeCssClass()
     */
    public function removeCssClass(&$options, $class)
    {
        $this->proxy(__FUNCTION__, [&$options, $class]);
    }

    /**
     * Adds the specified CSS style to the HTML options.
     *
     * If the options already contain a `style` element, the new style will be merged
     * with the existing one. If a CSS property exists in both the new and the old styles,
     * the old one may be overwritten if `$overwrite` is true.
     *
     * For example,
     *
     * ```php
     * Html::addCssStyle($options, 'width: 100px; height: 200px');
     * ```
     *
     * @param array $options the HTML options to be modified.
     * @param string|array $style the new style string (e.g. `'width: 100px; height: 200px'`) or
     * array (e.g. `['width' => '100px', 'height' => '200px']`).
     * @param bool $overwrite whether to overwrite existing CSS properties if the new style
     * contain them too.
     * @see removeCssStyle()
     * @see cssStyleFromArray()
     * @see cssStyleToArray()
     * @see Html::addCssStyle()
     */
    public function addCssStyle(&$options, $style, $overwrite = true)
    {
        $this->proxy(__FUNCTION__, [&$options, $style, $overwrite]);
    }

    /**
     * Removes the specified CSS style from the HTML options.
     *
     * For example,
     *
     * ```php
     * Html::removeCssStyle($options, ['width', 'height']);
     * ```
     *
     * @param array $options the HTML options to be modified.
     * @param string|array $properties the CSS properties to be removed. You may use a string
     * if you are removing a single property.
     * @see addCssStyle()
     * @see Html::removeCssStyle()
     */
    public function removeCssStyle(&$options, $properties)
    {
        $this->proxy(__FUNCTION__, [&$options, $properties]);
    }

    /**
     * Converts a CSS style array into a string representation.
     *
     * For example,
     *
     * ```php
     * print_r(Html::cssStyleFromArray(['width' => '100px', 'height' => '200px']));
     * // will display: 'width: 100px; height: 200px;'
     * ```
     *
     * @param array $style the CSS style array. The array keys are the CSS property names,
     * and the array values are the corresponding CSS property values.
     * @return string the CSS style string. If the CSS style is empty, a null will be returned.
     * @see Html::cssStyleFromArray()
     */
    public function cssStyleFromArray(array $style)
    {
        return $this->proxy(__FUNCTION__, [$style]);
    }

    /**
     * Converts a CSS style string into an array representation.
     *
     * The array keys are the CSS property names, and the array values
     * are the corresponding CSS property values.
     *
     * For example,
     *
     * ```php
     * print_r(Html::cssStyleToArray('width: 100px; height: 200px;'));
     * // will display: ['width' => '100px', 'height' => '200px']
     * ```
     *
     * @param string $style the CSS style string
     * @return array the array representation of the CSS style
     * @see Html::cssStyleToArray()
     */
    public function cssStyleToArray($style)
    {
        return $this->proxy(__FUNCTION__, [$style]);
    }

    /**
     * Returns the real attribute name from the given attribute expression.
     *
     * An attribute expression is an attribute name prefixed and/or suffixed with array indexes.
     * It is mainly used in tabular data input and/or input of array type. Below are some examples:
     *
     * - `[0]content` is used in tabular data input to represent the "content" attribute
     *   for the first model in tabular input;
     * - `dates[0]` represents the first array element of the "dates" attribute;
     * - `[0]dates[0]` represents the first array element of the "dates" attribute
     *   for the first model in tabular input.
     *
     * If `$attribute` has neither prefix nor suffix, it will be returned back without change.
     * @param string $attribute the attribute name or expression
     * @return string the attribute name without prefix and suffix.
     * @throws InvalidArgumentException if the attribute name contains non-word characters.
     * @see Html::getAttributeName()
     */
    public function getAttributeName($attribute)
    {
        return $this->proxy(__FUNCTION__, [$attribute]);
    }

    /**
     * Returns the value of the specified attribute name or expression.
     *
     * For an attribute expression like `[0]dates[0]`, this method will return the value of `$model->dates[0]`.
     * See [[getAttributeName()]] for more details about attribute expression.
     *
     * If an attribute value is an instance of [[ActiveRecordInterface]] or an array of such instances,
     * the primary value(s) of the AR instance(s) will be returned instead.
     *
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression
     * @return string|array the corresponding attribute value
     * @throws InvalidArgumentException if the attribute name contains non-word characters.
     * @see Html::getAttributeValue()
     */
    public function getAttributeValue($model, $attribute)
    {
        return $this->proxy(__FUNCTION__, [$model, $attribute]);
    }

    /**
     * Generates an appropriate input name for the specified attribute name or expression.
     *
     * This method generates a name that can be used as the input name to collect user input
     * for the specified attribute. The name is generated according to the [[Model::formName|form name]]
     * of the model and the given attribute name. For example, if the form name of the `Post` model
     * is `Post`, then the input name generated for the `content` attribute would be `Post[content]`.
     *
     * See [[getAttributeName()]] for explanation of attribute expression.
     *
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression
     * @return string the generated input name
     * @throws InvalidArgumentException if the attribute name contains non-word characters.
     * @see Html::getInputName()
     */
    public function getInputName($model, $attribute)
    {
        return $this->proxy(__FUNCTION__, [$model, $attribute]);
    }

    /**
     * Generates an appropriate input ID for the specified attribute name or expression.
     *
     * This method converts the result [[getInputName()]] into a valid input ID.
     * For example, if [[getInputName()]] returns `Post[content]`, this method will return `post-content`.
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for explanation of attribute expression.
     * @return string the generated input ID
     * @throws InvalidArgumentException if the attribute name contains non-word characters.
     * @see Html::getInputId()
     */
    public function getInputId($model, $attribute)
    {
        return $this->proxy(__FUNCTION__, [$model, $attribute]);
    }

    /**
     * Escapes regular expression to use in JavaScript.
     * @param string $regexp the regular expression to be escaped.
     * @return string the escaped result.
     * @see Html::escapeJsRegularExpression()
     */
    public function escapeJsRegularExpression($regexp)
    {
        return $this->proxy(__FUNCTION__, [$regexp]);
    }
}