<?php

class ConvertToSearchableHtmlPage
{
    // apply to for row options
    const WHOLE_ROW = 0;
    const ONLY_CELL = 1;
    // conditions for row options
    const CELL_IS_SET = 1;
    const CELL_IS_EQUAL = 2;
    const CELL_IS_GREATER = 4;
    const CELL_IS_LOWER = 8;
    const CELL_IS_GREATER_OR_EQUAL = 16;
    const CELL_IS_LOWER_OR_EQUAL =32;
    // type conversions
    const CONVERT_TO_INT = 1;
    const CONVERT_TO_FLOAT = 2;

    //
    // Attributes
    //
    /** @var string the delimiter character of the CSV */
    private $delimiter = "\t";

    /**
     * @param $delimiter
     */
    public function setDelimiter($delimiter)
    {
        $this->delimiter = $delimiter;
    }

    /** @var array the imported csv/tsv is put into this array of lines */
    private $tsvLinesArray = [];

    /**
     * @return array
     */
    public function getTsvLinesArray()
    {
        return $this->tsvLinesArray;
    }

    /**
     * @param array $tsvLinesArray
     */
    public function setTsvLinesArray($tsvLinesArray)
    {
        $this->tsvLinesArray = $tsvLinesArray;
    }

    /** @var array the first line of the tsv contains the names of the fields/columns */
    private $fieldNames = [];

    /**
     * @param $numericId
     *
     * @return mixed
     */
    private function getFieldName($numericId)
    {
        return $this->fieldNames[$numericId];
    }

    /** @var array formatting applied to a to a column without condition */
    private $fieldOptions = [];

    /**
     * @param $fieldIndex
     * @param $optionName
     * @param $optionValue
     */
    public function setFieldOption($fieldIndex, $optionName, $optionValue)
    {
        $this->fieldOptions[$fieldIndex] = [ $optionName => $optionValue ];
    }

    /** @var string the resulting fields json that will be passed to the b-table */
    private $fieldsJson = "";

    /** @var array the numeric index of the columns that have to be searchable */
    private $searchableFields = [];

    /**
     * @param $indexes
     */
    public function setSearchableFields($indexes)
    {
        if (!is_array($indexes)) {
            $this->searchableFields = [ $indexes ];
        } else {
            $this->searchableFields = $indexes;
        }
    }

    /** @var string the resulting items json that will be passed to the b-table */
    private $itemsJson = "";

    /** @var string content of the index.html template */
    private $indexHtml = "";

    /** @var mixed the column index that has to be sorted by default */
    private $initialSortFieldIndex = false;

    /**
     * @param $index
     */
    public function setInitialSortFieldIndex($index)
    {
        $this->initialSortFieldIndex = $index;
    }

    /** @var string the column that has to be sorted by default */
    private $initialSortField = "";

    /**
     * @param $field
     */
    public function setInitialSortField($field)
    {
        $this->initialSortField = $field;
    }

    /** @var string the page title in the head of the generated HTML */
    private $pageTitle = "indlookup";

    /**
     * @param $pageTitle
     */
    public function setTitle($pageTitle)
    {
        $this->pageTitle = $pageTitle;
    }

    /** @var array indexes of the columns that have to be hidden in the b-table */
    private $hiddenColumns = [];

    /** @var array  extra formatting applied on fields or row an a certain condition*/
    private $rowOptions = [];

    /**
     * @param int    $column       the field/column on which the test is going to be done
     * @param string $option       the option that has to be set
     * @param int    $condition
     * @param int    $compareTo    the value to which the field has to be compared to
     * @param int    $applyTo      apply the formatting to the field or the whole row
     */
    public function setRowOptions($column, $option, $condition = self::CELL_IS_SET, $compareTo = 0,  $applyTo = self::WHOLE_ROW)
    {
        $this->rowOptions[] = ['column' => $column, 'option' => $option, 'condition' => $condition, 'compareTo' => $compareTo, 'applyTo' => $applyTo ];
    }

    /**
     * @param array $valueFields  the currently processed line
     *
     * @return array
     */
    private function getRowOptions($valueFields)
    {
        $resultingOptions = [];
        foreach ($this->rowOptions as $rowOption) {
            if ($this->rowColumnOptionCondition(
                $valueFields[$rowOption['column']],
                $rowOption['condition'],
                $rowOption['compareTo']
            )) {
                if ($rowOption['applyTo'] == self::WHOLE_ROW) {
                    $resultingOptions['_rowVariant'] = $rowOption['option'];
                } else {
                    $resultingOptions['_cellVariants'][$this->getFieldName($rowOption['column'])] = $rowOption['option'];
                }
            }
        }
        return $resultingOptions;
    }


    //
    // methods
    //
    /**
     * ConvertToSearchableHtmlPage constructor.
     *
     * @param string $tsvFilename
     * @param string $delimiter
     * @param string $htmlTemplate
     */
    public function __construct($tsvFilename = 'input.tsv', $delimiter = "\t", $htmlTemplate = 'index.template.html')
    {
        if ($htmlTemplate) {
            $this->loadHtmlTemplate($htmlTemplate);
        }
        if ($tsvFilename) {
            $this->importTsv($tsvFilename);
        }
        $this->setDelimiter($delimiter);
    }

    /**
     * Convert the column to int
     *
     * @param $index int index of the column to convert
     */
    public function convertColumnToInt($index)
    {
        $this->convertColumn($index, self::CONVERT_TO_INT);
    }

    /**
     * Convert the column to float
     *
     * @param $index int index of the column to convert
     */
    public function convertColumnToFloat($index)
    {
        $this->convertColumn($index, self::CONVERT_TO_FLOAT);
    }

    /**
     * By default all values are of type string, which creates problems when sorting
     * Convert to number
     *
     * @param $index
     * @param $type
     */
    private function convertColumn($index, $type)
    {
        $convertedTsv = $this->getTsvLinesArray();
        foreach($convertedTsv as $key => $value) {
            switch($type) {
                case self::CONVERT_TO_INT:
                    $convertedTsv[$key][$index] = (int)$value[$index];
                    break;
                case self::CONVERT_TO_FLOAT:
                    $convertedTsv[$key][$index] = (float)$value[$index];
                    break;
            }
        }
        $this->setTsvLinesArray($convertedTsv);
    }

    /**
     * @param string $filename
     */
    public function loadHtmlTemplate($filename = 'index.template.html')
    {
        $this->indexHtml = file_get_contents($filename);
    }

    /**
     * @param string $filename
     */
    public function saveGeneratedHtml($filename = 'output.html')
    {
        file_put_contents("$filename", $this->indexHtml);
    }

    /**
     * @param string $filename
     */
    public function importTsv($filename = 'import.tsv')
    {
        $tsvIso = file_get_contents("$filename");
        $tsv = mb_convert_encoding($tsvIso, 'UTF-8',
            mb_detect_encoding($tsvIso, 'UTF-8, ISO-8859-1', true));
        $tsvLines = explode("\r\n", $tsv);

        $firstLine = array_shift($tsvLines);
        $this->extractFieldNames($firstLine);

        // parse csv/tsv to array
        $parsedTsvLines = [];
        foreach ($tsvLines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $parsedTsvLines[] = str_getcsv($line, $this->delimiter);
        }
        $this->setTsvLinesArray($parsedTsvLines);
    }

    /**
     * extract the names of the columns from the first row
     */
    private function extractFieldNames($firstLine)
    {
        $this->fieldNames = str_getcsv(
            $this->normalizeChars($firstLine)
            , $this->delimiter
        );
    }

    /**
     * @param $columns
     */
    public function hideColumns($columns)
    {
        if (!is_array($columns)) {
            $this->hiddenColumns = [ $columns ];
        } else {
            $this->hiddenColumns = $columns;
        }
    }

    /**
     * create the JSON that will be passed to the b-table for the fieldnames
     */
    public function prepareFieldNamesJson()
    {
        $fields = [];
        foreach ($this->fieldNames as $numericKey => $headerField) {
            if (in_array($numericKey, $this->hiddenColumns)) {
                continue;
            }
            $tempArray = ['key' => $headerField, 'sortable' => true];

            if (isset($this->fieldOptions[$numericKey])) {
                $tempArray = array_merge($tempArray, $this->fieldOptions[$numericKey]);
            }

            if ($this->initialSortFieldIndex !== false && $this->initialSortFieldIndex === $numericKey) {
                $this->setInitialSortField($headerField);
            }

            $fields[] = $tempArray;
        }
        $this->fieldsJson = json_encode($fields);
    }

    /**
     * create the JSON that will be passed to the b-table for the rows
     */
    public function prepareItemsJson()
    {
        $jsonLines = [];
        foreach ($this->getTsvLinesArray() as $line) {
            $assocFields = [];
            foreach ($line as $key => $field) {
                $assocFields[$this->fieldNames[$key]] = $field;
            }

            // searchable fields - create Index field
            $assocFields['normalized_search_field'] = "";
            if (count($this->searchableFields) > 0) {
                foreach($this->searchableFields as $index) {
                    $assocFields['normalized_search_field'] .= $this->normalizeChars($line[$index], true);
                }
            }

            // row Options
            $assocFields = array_merge($assocFields, $this->getRowOptions($line));

            $jsonLines[] = $assocFields;
        }
        $this->itemsJson = json_encode($jsonLines);
    }

    /**
     * @param $value
     * @param $condition
     * @param mixed $compareTo the value to which to compare
     *                         Type juggling -> the content of the field is compared to a number when this is an number
     * @return bool
     */
    private function rowColumnOptionCondition($value, $condition, $compareTo)
    {
        $empty = $value !== "";
        $result = $empty; // initialize with "true if non empty"

        switch( $condition & 1022 ) { // remove bit 1 if present -> do not test for "is set"
            // these are mutually exclusive
            case self::CELL_IS_EQUAL:
                $result = $value == $compareTo;
                break;
            case self::CELL_IS_GREATER:
                $result = $value > $compareTo;
                break;
            case self::CELL_IS_LOWER:
                $result = $value < $compareTo;
                break;
            case self::CELL_IS_GREATER_OR_EQUAL:
                $result = $value >= $compareTo;
                break;
            case self::CELL_IS_LOWER_OR_EQUAL:
                $result = $value <= $compareTo;
                break;
        }
        if ( $condition & 1 ) {
            $result = $result && $empty; // and check if non empty
        }
        return $result;
    }

    public function replacePlaceholdersInHtmlTemplate()
    {
        $this->indexHtml = preg_replace('/FIELDSJSONREPLACE/', $this->fieldsJson, $this->indexHtml);
        $this->indexHtml = preg_replace('/ITEMSJSONREPLACE/', $this->itemsJson, $this->indexHtml);
        $this->indexHtml = preg_replace('/SORTBYFIELDREPLACE/', $this->initialSortField, $this->indexHtml);
        $this->indexHtml = preg_replace('/TITLEREPLACE/', $this->pageTitle, $this->indexHtml);
    }

    /**
     * main precessing method
     */
    public function process()
    {
        $this->prepareFieldNamesJson();
        $this->prepareItemsJson();
        $this->replacePlaceholdersInHtmlTemplate();
    }

    /**
     * main precessing method & save generated html
     *
     * @param string $filename
     */
    public function processAndSave($filename = 'output.html')
    {
        $this->process();
        $this->saveGeneratedHtml($filename);
    }

    /**
     * convert to lowercase + remove accents (transliteration). for instance é becomes e
     *
     * @param      $stringToNormalize
     * @param bool $removeSpaces
     *
     * @return false|string
     */
    private function normalizeChars($stringToNormalize, $removeSpaces = false)
    {
        if ($removeSpaces) {
            $stringToNormalize = preg_replace('/\s/', '', $stringToNormalize);
        }
        $stringToNormalize = mb_convert_case($stringToNormalize, MB_CASE_LOWER);
        $stringToNormalize = iconv('UTF-8', 'ASCII//TRANSLIT', $stringToNormalize);
        return $stringToNormalize;
    }
}