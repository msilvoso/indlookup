#!/usr/bin/env php
<?php
// Example convert file
//
require_once "ConvertToSearchableHtmlPage.php";
// instatiate and load our covnerted Excel file import.tsv from data
$run = new ConvertToSearchableHtmlPage("data/import.tsv");
// sort initialy by the first field
$run->setInitialSortFieldIndex(0);
// hide a column
$run->hideColumns(11);
// add field options (see b-table of Bootstrapvue) info is blue, danger is red
$run->setFieldOption(0,'variant','primary');
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
// define the columns thta will be searchable
$run->setSearchableFields([0,2,7]);
// set title of the page
$run->setTitle("Recherche dans la table");
// save the resulting html
$run->processAndSave('public/index.html');
