<?php

namespace Reaction\I18n;

use Reaction;
use Reaction\Exceptions\InvalidConfigException;

/**
 * GettextPoFile represents a PO Gettext message file.
 */
class GettextPoFile extends GettextFile
{
    /**
     * Loads messages from a PO file.
     * @param string      $context message context
     * @param string|null $filePath file path
     * @return array message translations. Array keys are source messages and array values are translated messages:
     * source message => translated message.
     * @throws InvalidConfigException
     */
    public function load($context, $filePath = null)
    {
        if (isset($filePath)) {
            $this->filePath = $filePath;
        }
        if (!isset($this->_messages)) {
            $this->loadAll();
        }
        return isset($this->_messages[$context]) ? $this->_messages[$context] : [];
    }

    /**
     * Saves messages to a PO file.
     * @param array       $messages message translations. Array keys are source messages and array values are
     * translated messages: source message => translated message. Note if the message has a context,
     * the message ID must be prefixed with the context with chr(4) as the separator.
     * @param string|null $filePath file path
     * @throws InvalidConfigException
     */
    public function save($messages, $filePath = null)
    {
        if (!isset($filePath)) {
            $filePath = $this->filePath;
        }
        if (!isset($filePath)) {
            throw new InvalidConfigException("Not specified '\$filePath' parameter");
        }
        $language = str_replace('-', '_', basename(dirname($filePath)));
        $headers = [
            'msgid ""',
            'msgstr ""',
            '"Project-Id-Version: \n"',
            '"POT-Creation-Date: \n"',
            '"PO-Revision-Date: \n"',
            '"Last-Translator: \n"',
            '"Language-Team: \n"',
            '"Language: ' . $language . '\n"',
            '"MIME-Version: 1.0\n"',
            '"Content-Type: text/plain; charset=' . Reaction::$app->charset . '\n"',
            '"Content-Transfer-Encoding: 8bit\n"',
        ];
        $content = implode("\n", $headers) . "\n\n";
        foreach ($messages as $id => $message) {
            $separatorPosition = strpos($id, chr(4));
            if ($separatorPosition !== false) {
                $content .= 'msgctxt "' . substr($id, 0, $separatorPosition) . "\"\n";
                $id = substr($id, $separatorPosition + 1);
            }
            $content .= 'msgid "' . $this->encode($id) . "\"\n";
            $content .= 'msgstr "' . $this->encode($message) . "\"\n\n";
        }
        file_put_contents($filePath, $content);
    }

    /**
     * Get all categories list
     * @return string[]
     */
    public function getCategories()
    {
        if (!isset($this->_messages)) {
            $this->loadAll();
        }
        $categories = array_keys($this->_messages);
        sort($categories);
        return $categories;
    }

    /**
     * Get all messages from file
     * @return array
     * @throws InvalidConfigException
     */
    protected function loadAll()
    {
        if (!isset($this->filePath)) {
            throw new InvalidConfigException("Not specified '\$filePath' parameter");
        }
        $pattern = '/(msgctxt\s+"(.*?(?<!\\\\))")?\s+' // context
            . 'msgid\s+((?:".*(?<!\\\\)"\s*)+)\s+' // message ID, i.e. original string
            . 'msgstr\s+((?:".*(?<!\\\\)"\s*)+)/'; // translated string
        $content = file_get_contents($this->filePath);
        $matches = [];
        $matchCount = preg_match_all($pattern, $content, $matches);

        $this->_messages = [];

        for ($i = 0; $i < $matchCount; ++$i) {
            $id = $this->decode($matches[3][$i]);
            $message = $this->decode($matches[4][$i]);
            $context = $matches[2][$i];
            if (!isset($this->_messages[$context])) {
                $this->_messages[$context] = [];
            }
            $this->_messages[$context][$id] = $message;
        }

        return $this->_messages;
    }

    /**
     * Encodes special characters in a message.
     * @param string $string message to be encoded
     * @return string the encoded message
     */
    protected function encode($string)
    {
        return str_replace(
            ['"', "\n", "\t", "\r"],
            ['\\"', '\\n', '\\t', '\\r'],
            $string
        );
    }

    /**
     * Decodes special characters in a message.
     * @param string $string message to be decoded
     * @return string the decoded message
     */
    protected function decode($string)
    {
        $string = preg_replace(
            ['/"\s+"/', '/\\\\n/', '/\\\\r/', '/\\\\t/', '/\\\\"/'],
            ['', "\n", "\r", "\t", '"'],
            $string
        );

        return substr(rtrim($string), 1, -1);
    }
}
