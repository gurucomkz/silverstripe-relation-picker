<?php

namespace Gurucomkz;

use Exception;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\SingleLookupField;
use SilverStripe\Forms\SingleSelectField;
use SilverStripe\Forms\Validator;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\ORM\Relation;
use SilverStripe\ORM\SS_List;
use SilverStripe\View\ArrayData;
use Tangible\Forms\PrettyLiteralField;

/**
 * Provides a tagging interface, storing links between tag DataObjects and a parent DataObject.
 *
 * @package forms
 * @subpackage fields
 */
class RelPickerField extends SingleSelectField
{
    /**
     * @var array
     */
    private static $allowed_actions = [
        'suggest',
    ];

    /**
     * @var int
     */

    protected $hasEmptyDefault = true;

    protected $lazyLoadItemLimit = 10;

    protected $auxQueryStringParams = [];

    protected $lookupFields = [];

    protected $allowCreate = true;

    /**
     * @var string
     */
    protected $titleField = 'Title';

    /**
     * @var DataList
     */
    protected $sourceList;

    /**
     * @var string
     */
    protected $createNewURL;

    /** @skipUpgrade */
    protected $schemaComponent = 'RelPickerField';

    /**
     * @param string $name
     * @param string $title
     * @param null|DataList|array $source
     * @param null|DataList $value
     * @param string $titleField
     */
    public function __construct($name, $title = '', $source = [], $value = null, $titleField = 'Title')
    {
        $this->setTitleField($titleField);
        parent::__construct($name, $title, $source, $value);

        $this->addExtraClass('ss-relpicker-field');
    }

    /**
     * @return int
     */
    public function getLazyLoadItemLimit()
    {
        return $this->lazyLoadItemLimit;
    }

    /**
     * @param int $lazyLoadItemLimit
     *
     * @return static
     */
    public function setLazyLoadItemLimit($lazyLoadItemLimit)
    {
        $this->lazyLoadItemLimit = $lazyLoadItemLimit;

        return $this;
    }

    /**
     * @return string
     */
    public function getTitleField()
    {
        return $this->titleField;
    }

    /**
     * @param string $titleField
     *
     * @return $this
     */
    public function setTitleField($titleField)
    {
        $this->titleField = $titleField;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getAllowCreate()
    {
        return $this->allowCreate;
    }

    /**
     * @param boolean $val
     *
     * @return $this
     */
    public function setAllowCreate($val)
    {
        $this->allowCreate = !!$val;

        return $this;
    }

    /**
     * @return array
     */
    public function getLookupFields()
    {
        return $this->lookupFields;
    }

    /**
     * @param string $lookupFields
     *
     * @return $this
     */
    public function setLookupFields(array $lookupFields)
    {
        $this->lookupFields = $lookupFields;

        return $this;
    }

    /**
     * @return array
     */
    public function getAuxQueryStringParams()
    {
        return $this->auxQueryStringParams;
    }

    /**
     * @param array $auxQueryStringParams
     *
     * @return $this
     */
    public function setAuxQueryStringParams(array $auxQueryStringParams)
    {
        $this->auxQueryStringParams = $auxQueryStringParams;

        return $this;
    }

    /**
     * Get the DataList source. The 4.x upgrade for SelectField::setSource starts to convert this to an array.
     * If empty use getSource() for array version
     *
     * @return DataList
     */
    public function getSourceList()
    {
        return $this->sourceList;
    }

    /**
     * Set the model class name for tags
     *
     * @param DataList $sourceList
     * @return self
     */
    public function setSourceList($sourceList)
    {
        $this->sourceList = $sourceList;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function Field($properties = [])
    {
        $this->addExtraClass('entwine');

        return $this->customise($properties)->renderWith(self::class);
    }

    /**
     * Provide TagField data to the JSON schema for the frontend component
     *
     * @return array
     */
    public function getSchemaDataDefaults()
    {
        $options = $this->getOptions(true);
        $schema = array_merge(
            parent::getSchemaDataDefaults(),
            [
                'name' => $this->getName(),
                'lazyLoad' => true,
                'creatable' => false,
                'multi' => false,
                'value' => $options->count() ? $options->toNestedArray()[0] : null,
                'disabled' => $this->isDisabled() || $this->isReadonly(),
                'optionUrl' => $this->getSuggestURL(),
                'createNewUrl' => $this->getCreateNewURL(),
            ]
        );

        return $schema;
    }


    public function setCreateNewURL($url)
    {
        $this->createNewURL = $url;
        return $this;
    }

    /**
     * @return string
     */
    public function getCreateNewURL()
    {
        if (!$this->allowCreate) {
            return null;
        }

        if ($this->createNewURL) {
            return $this->createNewURL;
        }
        $source = $this->getSourceList();
        if (!$source) {
            return null;
        }

        $dataClass = $source->dataClass();
        if (!method_exists($dataClass, 'getCMSEditLink')) {
            return null;
        }

        /** @var DataObject */
        $singleton = singleton($dataClass);
        if (!$singleton->canCreate()) {
            return null;
        }
        return $singleton->getCMSEditLink();
    }

    /**
     * @return string
     */
    protected function getSuggestURL()
    {
        return Controller::join_links($this->Link(), 'suggest') . (count($this->auxQueryStringParams) ? '?' . http_build_query($this->auxQueryStringParams) : '');
    }

    /**
     * @return ArrayList
     */
    protected function getOptions($onlySelected = false)
    {
        $options = ArrayList::create();
        $source = $this->getSourceList();

        // No source means we have no options
        if (!$source) {
            return ArrayList::create();
        }

        $value = $this->Value();

        // If we have no values and we only want selected options we can bail here
        if (!$value && $onlySelected) {
            return ArrayList::create();
        }

        // Convert an array of values into a datalist of options

        $values = $value ? $source->filter('ID', $value) : ArrayList::create();

        // Prep a function to parse a dataobject into an option
        $addOption = function (DataObject $item) use ($options, $value) {
            $options->push(ArrayData::create([
                'Title' => $item->Title,
                'Value' => $item->ID,
                'Selected' => $value == $item->ID,
            ]));
        };

        // Only parse the values if we only want the selected items in the values list (this is for lazy-loading)
        if ($onlySelected) {
            $values->each($addOption);
            return $options;
        }

        $source->each($addOption);
        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function setValue($value, $source = null)
    {
        if ($source instanceof DataObject) {
            $name = $this->getName();

            if ($source->hasMethod($name)) {
                $value = $source->$name()->column($this->getTitleField());
            }
        }

        if (!is_array($value)) {
            return parent::setValue($value);
        }

        return parent::setValue(array_filter($value));
    }

    /**
     * Gets the source array if required
     *
     * Note: this is expensive for a SS_List
     *
     * @return array
     */
    public function getSource()
    {
        if (is_null($this->source)) {
            $this->source = $this->getListMap($this->getSourceList());
        }
        return $this->source;
    }

    /**
     * Intercept DataList source
     *
     * @param mixed $source
     * @return $this
     */
    public function setSource($source)
    {
        // When setting a datalist force internal list to null
        if ($source instanceof DataList) {
            $this->source = null;
            $this->setSourceList($source);
        } else {
            parent::setSource($source);
        }
        return $this;
    }

    /**
     * @param DataObject|DataObjectInterface $record DataObject to save data into
     * @throws Exception
     */
    public function getAttributes()
    {
        return array_merge(
            parent::getAttributes(),
            [
                'name' => $this->getName(),
                'style' => 'width: 100%',
                'data-schema' => json_encode($this->getSchemaData()),
            ]
        );
    }

    /**
     * Returns a JSON string of tags, for lazy loading.
     *
     * @param  HTTPRequest $request
     * @return HTTPResponse
     */
    public function suggest(HTTPRequest $request)
    {
        $tags = $this->getTags($request->requestVar('term'));

        $response = HTTPResponse::create();
        $response->addHeader('Content-Type', 'application/json');
        $response->setBody(json_encode(['items' => $tags]));

        return $response;
    }

    /**
     * Returns array of arrays representing tags.
     *
     * @param  string $term
     * @return array
     */
    protected function getTags($term)
    {
        $source = $this->getSourceList();
        if (!$source) {
            return [];
        }

        $titleField = $this->getTitleField();

        $fields = count($this->lookupFields) ? $this->lookupFields : [ $titleField ];

        $query = $source->dataQuery()
            ->query()
            ->addWhere([
                'CONCAT_WS(\' \',"'.implode('","', $fields).'") LIKE ?' => '%'.str_replace(' ', '%', trim($term)).'%'
            ])
            ->addOrderBy('ID', 'DESC')
            ->setLimit($this->getLazyLoadItemLimit())
            ->execute();

        // Map into a distinct list
        $mkTitle = function ($entry) use ($fields) {
            $r = [];
            foreach ($fields as $f) {
                if ($v = trim($entry[$f])) {
                    $r[] = $v;
                }
            }
            return implode(', ', $r);
        };

        $items = [];
        foreach ($query as $entry) {
            $items[$entry['ID']] = [
                'Title' => $mkTitle($entry), # needed to let display values (it removes non-matching with the input)
                'Value' => $entry['ID'],
            ];
        }

        return array_values($items);
    }

    /**
     * DropdownField assumes value will be a scalar so we must
     * override validate. This only applies to Silverstripe 3.2+
     *
     * @param Validator $validator
     * @return bool
     */
    public function validate($validator)
    {
        return true;
    }

    /**
     * Prevent the default, which would return "tag"
     *
     * @return string
     */
    public function Type()
    {
        return '';
    }

    public function getSchemaStateDefaults()
    {
        $data = parent::getSchemaStateDefaults();

        // Add options to 'data'
        $data['lazyLoad'] = true;
        $data['multi'] = false;
        $data['optionUrl'] = $this->getSuggestURL();
        $data['creatable'] = false;
        $options = $this->getOptions(true);
        $data['value'] = $options->count() ? $options->toNestedArray()[0] : null;

        return $data;
    }

    /**
     * @return SingleLookupField
     */
    public function performReadonlyTransformation()
    {
        /** @var SingleLookupField $field */
        $field = $this->castedCopy(ReadonlyField::class);

        $source = $this->getSourceList();
        $value = $this->Value();
        if ($source && $value) {
            $valueobject = $source->filter('ID', $value)->first();
            if ($valueobject) {
                return new PrettyLiteralField($this->getName(), $this->title, $valueobject->Title);
            }
        }

        return $field;
    }
}
