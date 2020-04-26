# indlookup
Fast javascript lookup in a tsv list

transforms a csv/tsv (from excel?) and converts it in a fast sortable and searchable html table 

does not need a server, just load the output file into a Browser

Composer sample file
```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/msilvoso/indlookup.git"
    }
  ],
  "require": {
    "msilvoso/indlookup": "master@dev"
  }
}
```

Convert script example
```php
#!/usr/bin/env php
<?php
// Example convert file
//
require "vendor/autoload.php";
use IndLookup\ConvertToSearchableHtmlPage;
// instantiate and load our converted Excel file import.tsv from data
$run = new ConvertToSearchableHtmlPage("import.tsv");
// convert column 10 to float for the sorting to be correct
// (b-table sorting does a string compare on strings which causes problems with negative numbers)
$run->convertColumnToFloat(10);
// sort initially by the first field
$run->setInitialSortColumnIndex(0);
// hide a column
$run->hideColumns(11);
// add field options (see b-table of Bootstrapvue) info is blue, danger is red
$run->setColumnOption(0, 'variant', 'primary');
// colorize rows
$run->setRowOptions(11, 'warning',
    ConvertToSearchableHtmlPage::CELL_IS_EQUAL,
    2,
    ConvertToSearchableHtmlPage::WHOLE_ROW
);
// colorize rows
$run->setRowOptions(11, 'danger',
    ConvertToSearchableHtmlPage::CELL_IS_EQUAL,
    1,
    ConvertToSearchableHtmlPage::WHOLE_ROW
);
$run->setRowOptions(10, 'info',
    ConvertToSearchableHtmlPage::CELL_IS_GREATER_OR_EQUAL,
    9,
    ConvertToSearchableHtmlPage::ONLY_CELL
);
$run->setRowOptions(10, 'success',
    ConvertToSearchableHtmlPage::CELL_IS_SET + ConvertToSearchableHtmlPage::CELL_IS_LOWER,
    9,
    ConvertToSearchableHtmlPage::ONLY_CELL
);
// define the columns that will be searchable
$run->setSearchableColumns([0, 2, 7]);
// set title of the page
$run->setTitle("Table search");
// save the resulting html
$run->processAndSave('index.html');
```