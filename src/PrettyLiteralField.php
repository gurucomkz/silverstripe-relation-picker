<?php

namespace Gurucomkz\RelPickerField;

use SilverStripe\Forms\DatalessField;
use SilverStripe\Forms\FormField;

class PrettyLiteralField extends DatalessField
{

    private static $casting = [
        'Value' => 'HTMLFragment',
    ];

    /**
     * @var string|FormField
     */
    protected $content;

    public function __construct($name, $title, $content)
    {
        $this->setContent($content);
        parent::__construct($name, $title, $content);
    }

    /**
     * Sets the content of this field to a new value.
     *
     * @param string|FormField $content
     *
     * @return $this
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Synonym of {@link setContent()} so that LiteralField is more compatible with other field types.
     *
     * @param string|FormField $content
     * @param mixed $data
     * @return $this
     */
    public function setValue($content, $data = null)
    {
        $this->setContent($content);

        return $this;
    }
}
