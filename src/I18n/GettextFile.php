<?php

namespace Reaction\I18n;

use Reaction\Base\Component;

/**
 * GettextFile is the base class for representing a Gettext message file.
 */
abstract class GettextFile extends Component
{
    /**
     * @var string File path to work with
     */
    public $filePath;

    /**
     * @var array|null Loaded file messages
     */
    protected $_messages;

    /**
     * Loads messages from a file.
     * @param string      $context message context
     * @param string|null $filePath file path
     * @return array message translations. Array keys are source messages and array values are translated messages:
     * source message => translated message.
     */
    abstract public function load($context, $filePath = null);

    /**
     * Saves messages to a file.
     * @param array       $messages message translations. Array keys are source messages and array values are
     * translated messages: source message => translated message. Note if the message has a context,
     * the message ID must be prefixed with the context with chr(4) as the separator.
     * @param string|null $filePath file path
     */
    abstract public function save($messages, $filePath = null);

    /**
     * Get all categories list
     * @return string[]
     */
    public function getCategories()
    {
        if (!isset($this->_messages)) {
            $this->loadAll();
        }
        $categories = is_array($this->_messages) ? array_keys($this->_messages) : [];
        sort($categories);
        return $categories;
    }

    /**
     * Get all messages from file
     * @return array
     */
    abstract protected function loadAll();
}
