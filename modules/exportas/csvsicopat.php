<?php
$Module = $Params['Module'];
$ParentNodeID = isset( $Params['ParentNodeID'] ) ? $Params['ParentNodeID'] : false;
$ClassIdentifier = isset( $Params['ClassIdentifier'] ) ? $Params['ClassIdentifier'] : false;
$UserParameters = $Params['UserParameters'];
try
{

    $CSVSICOPATExporter = new CSVSICOPATExporter( $ParentNodeID, $ClassIdentifier );
    $CSVSICOPATExporter->setUserParameter( $UserParameters );
    $CSVSICOPATExporter->setModule( $Module );

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

$errors = $CSVSICOPATExporter->createValues();

//if(!empty($errors)){
//$tpl->setVariable( "errors", $errors);
//$tpl->fetch( 'design:errors/csv_sicopat_errors.tpl' );
//}else{
    $CSVSICOPATExporter->handleDownload();
//}

eZExecution::cleanExit();

?>