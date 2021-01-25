<?php

namespace IndLookup;

define("ROOT", dirname(__DIR__));

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
    const CELL_IS_LOWER_OR_EQUAL = 32;
    // type conversions
    const CONVERT_TO_INT = 1;
    const CONVERT_TO_FLOAT = 2;

    //
    // Attributes
    //
    /** @var string the delimiter character of the CSV */
    private $delimiter;

    /**
     * @return string
     */
    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

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
    private $columnNames = [];

    /**
     * @param $numericId
     *
     * @return mixed
     */
    private function getColumnName($numericId)
    {
        return $this->columnNames[$numericId];
    }

    /** @var array formatting applied to a to a column without condition */
    private $columnOptions = [];

    /**
     * @param $columnIndex
     * @param $optionName
     * @param $optionValue
     */
    public function setColumnOption($columnIndex, $optionName, $optionValue)
    {
        $this->columnOptions[$columnIndex] = [$optionName => $optionValue];
    }

    /** @var string the resulting fields json that will be passed to the b-table */
    private $columnsJson = "";

    /** @var array the numeric index of the columns that have to be searchable */
    private $searchableColumns = [];

    /**
     * @param $indexes
     */
    public function setSearchableColumns($indexes)
    {
        if (!is_array($indexes)) {
            $this->searchableColumns = [$indexes];
        } else {
            $this->searchableColumns = $indexes;
        }
    }

    /** @var string the resulting items json that will be passed to the b-table */
    private $itemsJson = "";

    /** @var string content of the index.html template */
    private $indexHtml = "";

    /** @var mixed the column index that has to be sorted by default */
    private $initialSortColumnIndex = false;

    /**
     * @param $index
     */
    public function setInitialSortColumnIndex($index)
    {
        $this->initialSortColumnIndex = $index;
    }

    /** @var string the column that has to be sorted by default */
    private $initialSortColumn = "";

    /**
     * @param $column
     */
    public function setInitialSortColumn($column)
    {
        $this->initialSortColumn = $column;
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

    /** @var array  extra formatting applied on columns or row an a certain condition */
    private $rowOptions = [];

    /**
     * @param int    $column    the field/column on which the test is going to be done
     * @param string $option    the option that has to be set
     * @param int    $condition
     * @param int    $compareTo the value to which the column has to be compared to
     * @param int    $applyTo   apply the formatting to the column or the whole row
     */
    public function setRowOptions($column, $option, $condition = self::CELL_IS_SET, $compareTo = 0, $applyTo = self::WHOLE_ROW)
    {
        $this->rowOptions[] = ['column' => $column, 'option' => $option, 'condition' => $condition, 'compareTo' => $compareTo, 'applyTo' => $applyTo];
    }

    /**
     * @param array $valueColumns the currently processed line
     *
     * @return array
     */
    private function getRowOptions($valueColumns)
    {
        $resultingOptions = [];
        foreach ($this->rowOptions as $rowOption) {
            if ($this->rowColumnOptionCondition(
                $valueColumns[$rowOption['column']],
                $rowOption['condition'],
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

    /** @var array  extra templating info applied to fields */
    private $templates = [];

    /**
     * @return array
     */
    public function getTemplates()
    {
        return join('', $this->templates);
    }

    /**
     * @param array $templates
     */
    public function addTemplate($template)
    {
        $this->templates[] = $template;
    }

    /**
     * renderRawHtml adds the needed template to the b-table to render raw html for a column
     * the column parameter can be the name of the column or the index
     *
     * @param $column mixed
     */
    public function renderRawHtml($column)
    {
        if (is_numeric($column)) {
            $colName = $this->getColumnName($column);
        } else {
            $colName = $column;
        }
        $this->addTemplate('<template #cell('.$colName.')="data"><span v-html="data.value"></span></template>');
    }

    /** @var int initial item limit to display */
    private $itemLimit = 301;

    /**
     * @param int $itemLimit
     */
    public function setItemLimit($itemLimit)
    {
        $this->itemLimit = $itemLimit;
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
    public function __construct($tsvFilename,
        $delimiter = "\t", $htmlTemplate = 'index.template.html')
    {
        if (!file_exists($htmlTemplate)) {
            if (file_exists(ROOT.'/template/'.$htmlTemplate)) {
                $htmlTemplate = ROOT.'/template/'.$htmlTemplate;
            } elseif ($htmlTemplate == "striped") {
                $htmlTemplate = ROOT.'/template/index.striped.template.html';
            }
        }
        $this->setDelimiter($delimiter);
        $this->loadHtmlTemplate($htmlTemplate);
        $this->importTsv($tsvFilename);
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
        foreach ($convertedTsv as $key => $value) {
            switch ($type) {
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
        $this->extractColumnNames($firstLine);

        // parse csv/tsv to array
        $parsedTsvLines = [];
        foreach ($tsvLines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $parsedTsvLines[] = str_getcsv($line, $this->getDelimiter());
        }
        $this->setTsvLinesArray($parsedTsvLines);
    }

    /**
     * extract the names of the columns from the first row
     */
    private function extractColumnNames($firstLine)
    {
        $this->columnNames = str_getcsv(
            $this->normalizeChars($firstLine)
            , $this->getDelimiter()
        );
    }

    /**
     * @param $columns
     */
    public function hideColumns($columns)
    {
        if (!is_array($columns)) {
            $this->hiddenColumns = [$columns];
        } else {
            $this->hiddenColumns = $columns;
        }
    }

    /**
     * Replace a string in every cell of a column
     * Initially added to replace newlines/carriage return by '<br>'
     *
     * @param $index integer
     * @param $search string|string[]
     * @param $replace string|string[]
     */
    public function replaceStringInColumn($index, $search, $replace)
    {
        $convertedTsv = $this->getTsvLinesArray();
        foreach ($convertedTsv as $key => $value) {
            $convertedTsv[$key][$index] = str_replace($search, $replace, $convertedTsv[$key][$index]);
        }
        $this->setTsvLinesArray($convertedTsv);
    }

    /**
     * create the JSON that will be passed to the b-table for the column names
     */
    public function prepareColumnNamesJson()
    {
        $columns = [];
        foreach ($this->columnNames as $numericKey => $headerColumn) {
            if (in_array($numericKey, $this->hiddenColumns)) {
                continue;
            }
            $tempArray = ['key' => $headerColumn, 'sortable' => true];

            if (isset($this->columnOptions[$numericKey])) {
                $tempArray = array_merge($tempArray, $this->columnOptions[$numericKey]);
            }

            if ($this->initialSortColumnIndex !== false && $this->initialSortColumnIndex === $numericKey) {
                $this->setInitialSortColumn($headerColumn);
            }

            $columns[] = $tempArray;
        }
        $this->columnsJson = json_encode($columns);
    }

    /**
     * create the JSON that will be passed to the b-table for the rows
     */
    public function prepareItemsJson()
    {
        $jsonLines = [];
        foreach ($this->getTsvLinesArray() as $line) {
            $assocColumns = [];
            foreach ($line as $key => $column) {
                $assocColumns[$this->columnNames[$key]] = $column;
            }

            // searchable columns - create Index column
            $assocColumns['normalized_search_column'] = "";
            if (count($this->searchableColumns) > 0) {
                foreach ($this->searchableColumns as $index) {
                    $assocColumns['normalized_search_column'] .= $this->normalizeChars($line[$index], true);
                }
            }

            // row Options
            $assocColumns = array_merge($assocColumns, $this->getRowOptions($line));

            $jsonLines[] = $assocColumns;
        }
        $this->itemsJson = json_encode($jsonLines);
    }

    /**
     * @param       $value
     * @param       $condition
     * @param mixed $compareTo the value to which to compare
     *                         Type juggling -> the content of the column is compared to a number when this is an number
     *
     * @return bool
     */
    private function rowColumnOptionCondition($value, $condition, $compareTo)
    {
        $empty = $value !== "";
        $result = $empty; // initialize with "true if non empty"

        switch ($condition & 1022) { // remove bit 1 if present -> do not test for "is set"
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
        if ($condition & 1) {
            $result = $result && $empty; // and check if non empty
        }
        return $result;
    }

    public function replacePlaceholdersInHtmlTemplate()
    {
        // "columns" are called "fields" in b-table
        $this->indexHtml = preg_replace('/FIELDSJSONREPLACE/', $this->columnsJson, $this->indexHtml);
        $this->indexHtml = preg_replace('/ITEMSJSONREPLACE/', $this->itemsJson, $this->indexHtml);
        $this->indexHtml = preg_replace('/SORTBYFIELDREPLACE/', $this->initialSortColumn, $this->indexHtml);
        $this->indexHtml = preg_replace('/TITLEREPLACE/', $this->pageTitle, $this->indexHtml);
        $this->indexHtml = preg_replace('/ITEMLIMITREPLACE/', "$this->itemLimit", $this->indexHtml);
        $this->indexHtml = preg_replace('/BTABLETEMPLATESREPLACE/', $this->getTemplates(), $this->indexHtml);
    }

    /**
     * main precessing method
     */
    public function process()
    {
        $this->prepareColumnNamesJson();
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