<?php

/** @var eZModule $Module */
$Module = $Params['Module'];

$contentObjectID = $Params['ContentObjectID'];
$contentClassAttributeID = $Params['ContentClassAttributeID'];
$languageCode = $Params['LanguageCode'];


$exporter = new SurveyExporter( $contentObjectID, $contentClassAttributeID, $languageCode );

if ($exporter->canExport()){
    ob_get_clean(); //chiudo l'ob_start dell'index.php
    if (eZHTTPTool::instance()->hasGetVariable('format')){
        if (eZHTTPTool::instance()->getVariable('format') == 'raw'){

            $exporter->handleRawResultsView();
            eZExecution::cleanExit();

        }elseif (eZHTTPTool::instance()->getVariable('format') == 'table'){

            $exporter->handleTableView();
            eZExecution::cleanExit();

        }
    }

    $exporter->handleCsvDownload();
    eZExecution::cleanExit();

}else{
    return $Module->handleError(eZError::KERNEL_NOT_FOUND, 'kernel');
}

