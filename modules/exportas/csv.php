<?php
$Module = $Params['Module'];
$ParentNodeID = isset( $Params['ParentNodeID'] ) ? $Params['ParentNodeID'] : false;
$ClassIdentifier = isset( $Params['ClassIdentifier'] ) ? $Params['ClassIdentifier'] : false;

try
{    
    $CSVExporter = new CSVExporter( $ParentNodeID, $ClassIdentifier );        
}
catch ( InvalidArgumentException $e )
{
    eZDebug::writeError( $e->getMessage(), __FILE__ );
    return $Module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );    
}
catch ( Exception $e )
{
    eZDebug::writeError( $e->getMessage(), __FILE__ );
    return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );    
}

ob_get_clean(); //chiudo l'ob_start dell'index.php

$CSVExporter->handleDownload();

eZExecution::cleanExit();

?>