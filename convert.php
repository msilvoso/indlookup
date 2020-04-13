<?php

class ConvertTsvToSearchableHtmlPage
{
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

    public function __construct($filename = '')
    {
        $this->loadHtml();
        if ($filename) {
            $this->importTsv($filename);
        }
    }

    public function loadHtml($filename = 'index.html')
    {
        $this->indexHtml = file_get_contents($filename);
    }

    public function saveHtml($filename = 'index.html')
    {
        file_put_contents("public/$filename", $this->indexHtml);
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
        $tsvIso = file_get_contents("data/$filename");
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

    public function prepareFieldNamesJson()
    {
        $fields = [];
        foreach ($this->fieldNames as $numericKey => $headerField) {

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

            // searchable fields
            $assocFields['normalized_search_field'] = "";
            if (count($this->searchableFields) > 0) {
                foreach($this->searchableFields as $index) {
                    $assocFields['normalized_search_field'] .= $this->normalizeChars($valueFields[$index], true);
                }
            }

            $jsonLines[] = $assocFields;
        }
        $this->itemsJson = json_encode($jsonLines);
    }

    public function convertHtml()
    {
        $this->indexHtml = preg_replace('/FIELDSJSONREPLACE/', $this->fieldsJson, $this->indexHtml);
        $this->indexHtml = preg_replace('/ITEMSJSONREPLACE/', $this->itemsJson, $this->indexHtml);
        $this->indexHtml = preg_replace('/SORTBYFIELDREPLACE/', $this->initialSortField, $this->indexHtml);
    }

    public function processAndSave()
    {
        $this->extractFieldNames();
        $this->prepareFieldNamesJson();
        $this->prepareItemsJson();
        $this->convertHtml();
        $this->saveHtml();
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

$run = new ConvertTsvToSearchableHtmlPage("import.tsv");
$run->setInitialSortFieldIndex(0);
$run->setFieldOption(0,'variant','info');
$run->setFieldOption(9,'variant','danger');
$run->setSearchableFields([0,2]);
$run->processAndSave();