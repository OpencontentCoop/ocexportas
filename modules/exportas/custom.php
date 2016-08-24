<?php
/** @var eZModule $Module */
$Module = $Params['Module'];
$ExportHandlerIdentifier = $Params['ExportHandlerIdentifier'];
$ParentNodeID = isset( $Params['ParentNodeID'] ) ? $Params['ParentNodeID'] : false;
$ClassIdentifier = isset( $Params['ClassIdentifier'] ) ? $Params['ClassIdentifier'] : false;
$UserParameters = $Params['UserParameters'];

try {
    $CustomExporter = CustomExporterAsFactory::factory($ExportHandlerIdentifier, $ParentNodeID, $ClassIdentifier);
    $CustomExporter->setUserParameter($UserParameters);
    $CustomExporter->setModule($Module);

} catch (InvalidArgumentException $e) {
    eZDebug::writeError($e->getMessage(), __FILE__);

    return $Module->handleError(eZError::KERNEL_NOT_FOUND, 'kernel');

} catch (Exception $e) {
    eZDebug::writeError($e->getMessage(), __FILE__);

    return $Module->handleError(eZError::KERNEL_ACCESS_DENIED, 'kernel');
}

ob_get_clean(); //chiudo l'ob_start dell'index.php

$CustomExporter->handleDownload();

eZExecution::cleanExit();

?>