<?php

$Module = $Params['Module'];

$ParentNodeID = isset( $Params['ParentNodeID'] ) ? $Params['ParentNodeID'] : false;
$ClassIdentifier = isset( $Params['ClassIdentifier'] ) ? $Params['ClassIdentifier'] : false;
$UserParameters = $Params['UserParameters'];
$tpl = eZTemplate::factory();

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


//numero di errori passati
if(isset($UserParameters['errors']) && $UserParameters['errors']==0){

    $CSVSICOPATExporter->handleDownload();
    eZExecution::cleanExit();

}else{

    $tpl->setVariable( "node_id", $ParentNodeID);
    $tpl->setVariable( "errors", $errors);
    $tpl->setVariable( "export_module", 'csvsicopat');
    $Result = array('content' => $tpl->fetch( 'design:errors/export_errors.tpl' ));

}


?>