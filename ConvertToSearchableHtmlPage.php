<?php

class ConvertToSearchableHtmlPage
{
    const WHOLE_ROW = 0;
    const ONLY_CELL = 1;
    const CELL_IS_SET = 1;
    const CELL_IS_EQUAL = 2;
    const CELL_IS_GREATER = 4;
    const CELL_IS_LOWER =8;
    const CELL_IS_GREATER_OR_EQUAL = 16;
    const CELL_IS_LOWER_OR_EQUAL =32;

    private $delimiter = "\t";
    private $tsvLinesArray = [];
    private $fieldNames = [];
    private $fieldOptions = [];
    private $fieldsJson = "";
    private $searchableFields = [];
    private $itemsJson = "";
    private $indexHtml = "";
    private $initialSortFieldIndex = false;
    private $initialSortField = "";
    private $pageTitle = "indlookup";
    private $hiddenColumns = [];
    private $rowOptions = [];

    public function __construct($tsvFilename = 'input.tsv', $delimiter = "\t", $htmlTemplate = 'index.template.html')
    {
        if ($htmlTemplate) {
            $this->loadHtml($htmlTemplate);
        }
        if ($tsvFilename) {
            $this->importTsv($tsvFilename);
        }
        $this->setDelimiter($delimiter);
    }

    public function setDelimiter($delimiter)
    {
        $this->delimiter = $delimiter;
    }

    public function loadHtml($filename = 'index.template.html')
    {
        $this->indexHtml = file_get_contents($filename);
    }

    public function saveHtml($filename = 'output.html')
    {
        file_put_contents("$filename", $this->indexHtml);
    }

    public function setFieldOption($fieldIndex, $optionName, $optionValue)
    {
        $this->fieldOptions[$fieldIndex] = [ $optionName => $optionValue ];
    }

    public function setInitialSortFieldIndex($index)
    {
        $this->initialSortFieldIndex = $index;
    }

    public function setInitialSortField($field)
    {
        $this->initialSortField = $field;
    }

    public function setTitle($pageTitle)
    {
        $this->pageTitle = $pageTitle;
    }

    public function setSearchableFields($indexes)
    {
        if (!is_array($indexes)) {
            $this->searchableFields = [ $indexes ];
        } else {
            $this->searchableFields = $indexes;
        }
    }

    public function importTsv($filename = 'import.tsv')
    {
        $tsvIso = file_get_contents("$filename");
        $tsv = mb_convert_encoding($tsvIso, 'UTF-8',
            mb_detect_encoding($tsvIso, 'UTF-8, ISO-8859-1', true));
        $this->tsvLinesArray = explode("\r\n", $tsv);
    }

    public function extractFieldNames()
    {
        $this->fieldNames = str_getcsv(
            $this->normalizeChars(array_shift($this->tsvLinesArray))
            , $this->delimiter
        );
    }

    public function hideColumns($columns)
    {
        if (!is_array($columns)) {
            $this->hiddenColumns = [ $columns ];
        } else {
            $this->hiddenColumns = $columns;
        }
    }

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

    public function setRowOptions($column, $option, $test = self::CELL_IS_SET, $compareTo = 0,  $applyTo = self::WHOLE_ROW)
    {
        $this->rowOptions[] = [ 'column' => $column, 'option' => $option, 'test' => $test, 'compareTo' => $compareTo, 'applyTo' => $applyTo ];
    }

    private function getColumnName($numericId)
    {
        return $this->fieldNames[$numericId];
    }

    private function rowColumnOptionTest($value, $test, $compareTo)
    {
        $empty = $value != "";
        $result = $empty; // initialize with "true if non empty"

        switch( $test & 1022 ) { // remove 1 if present
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
        if ( $test & 1 ) {
            $result = $result && $empty; // and check if non empty
        }
        return $result;
    }

    private function getRowOptions($valueFields)
    {
        $resultingOptions = [];
        foreach ($this->rowOptions as $rowOption) {
            if ($this->rowColumnOptionTest(
                $valueFields[$rowOption['column']],
                $rowOption['test'],
                $rowOption['compareTo']
            )) {
                if ($rowOption['applyTo'] == self::WHOLE_ROW) {
                    $resultingOptions['_rowVariant'] = $rowOption['option'];
                } else {
                    $resultingOptions['_cellVariants'][$this->getColumnName($rowOption['column'])] = $rowOption['option'];
                }
            }
        }
        return $resultingOptions;
    }

    public function prepareItemsJson()
    {

        $jsonLines = [];
        foreach ($this->tsvLinesArray as $line) {
            if (trim($line) === '') {
                continue;
            }
            $valueFields = str_getcsv($line, $this->delimiter);
            $assocFields = [];
            foreach ($valueFields as $key => $field) {
                $assocFields[$this->fieldNames[$key]] = $field;
            }

            // searchable fields - create Index field
            $assocFields['normalized_search_field'] = "";
            if (count($this->searchableFields) > 0) {
                foreach($this->searchableFields as $index) {
                    $assocFields['normalized_search_field'] .= $this->normalizeChars($valueFields[$index], true);
                }
            }

            // row Options
            $assocFields = array_merge($assocFields, $this->getRowOptions($valueFields));

            $jsonLines[] = $assocFields;
        }
        $this->itemsJson = json_encode($jsonLines);
    }

    public function convertHtml()
    {
        $this->indexHtml = preg_replace('/FIELDSJSONREPLACE/', $this->fieldsJson, $this->indexHtml);
        $this->indexHtml = preg_replace('/ITEMSJSONREPLACE/', $this->itemsJson, $this->indexHtml);
        $this->indexHtml = preg_replace('/SORTBYFIELDREPLACE/', $this->initialSortField, $this->indexHtml);
        $this->indexHtml = preg_replace('/TITLEREPLACE/', $this->pageTitle, $this->indexHtml);
    }

    public function process()
    {
        $this->extractFieldNames();
        $this->prepareFieldNamesJson();
        $this->prepareItemsJson();
        $this->convertHtml();
    }

    public function processAndSave($filename = 'output.html')
    {
        $this->process();
        $this->saveHtml($filename);
    }

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