<?php
    $tsvIso = file_get_contents("data/indépendants_cl_moy_avril.txt");
    $tsv = mb_convert_encoding($tsvIso, 'UTF-8',
        mb_detect_encoding($tsvIso, 'UTF-8, ISO-8859-1', true));
    $lines = explode("\r\n", $tsv);

    $header = str_getcsv(
        preg_replace("/é/","e",
            mb_convert_case(
            array_shift($lines)
            , MB_CASE_LOWER)
        )
        , "\t");

    //fields
    $fieldLines = [];
    foreach ($header as $headerField) {
        $tempArray = [ 'key' => $headerField, 'sortable' => false ];
        if ($headerField === 'matricule') {
            $tempArray['variant'] = 'info';
        }
        if ($headerField === 'nb_ass') {
            $tempArray['variant'] = 'danger';
        }
        $fieldLines[] = $tempArray;
    }
    // items
    $jsonLines = [];
    foreach ($lines as $line) {
        $valueFields = str_getcsv($line, "\t");
        $assocFields = [];
        foreach ($valueFields as $key => $field) {
            $assocFields[$header[$key]] = $field;
        }
        $jsonLines[] = $assocFields;
    }

    // create the json
    $fieldsJson = json_encode($fieldLines);
    $itemsJson = json_encode($jsonLines);

    $indexHtml = file_get_contents('index.html');

    $indexHtml = preg_replace('/FIELDSJSONREPLACE/', $fieldsJson, $indexHtml);
    $indexHtml = preg_replace('/ITEMSJSONREPLACE/', $itemsJson, $indexHtml);

    file_put_contents('public/index.html', $indexHtml);