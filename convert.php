#!/usr/bin/env php
<?php
// Example convert file
//
require_once "ConvertToSearchableHtmlPage.php";
// instatiate and load our covnerted Excel file import.tsv from data
$run = new ConvertToSearchableHtmlPage("data/import.tsv");
// sort initialy by the first field
$run->setInitialSortFieldIndex(0);
// add field options (see b-table of Bootstrapvue) info is blue, danger is red
$run->setFieldOption(0,'variant','info');
$run->setFieldOption(9,'variant','danger');
// define the columns thta will be searchable
$run->setSearchableFields([0,2,7]);
// set title of the page
$run->setTitle("Recherche dans la table");
// save the resulting html
$run->processAndSave('public/index.html');