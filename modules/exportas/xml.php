<?php
$Module = $Params['Module'];
$ParentNodeID = isset( $Params['ParentNodeID'] ) ? $Params['ParentNodeID'] : false;
$ClassIdentifier = isset( $Params['ClassIdentifier'] ) ? $Params['ClassIdentifier'] : false;

$exporterClass = 'XMLExporter';
if ( eZINI::instance( 'exportas.ini' )->hasVariable( 'Settings', 'XMLExportClass' ) )
{
    $exporterClass = eZINI::instance( 'exportas.ini' )->variable( 'Settings', 'XMLExportClass' );   
}

try
{    
    $XMLExporter = new $exporterClass( $ParentNodeID, $ClassIdentifier );        
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

ob_get_clean(); //chiudo l'ob_start del'index.php

$XMLExporter->handleDownload();

eZExecution::cleanExit();

?>