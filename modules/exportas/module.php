<?php
$Module = array( 'name' => 'ExportAs' );

$ViewList = array();
$ViewList['csv'] = array(
    'functions' => array( 'csv' ),
    'script' => 'csv.php',
	'params' => array( 'ClassIdentifier', 'ParentNodeID' ) );

$ViewList['xml'] = array(
    'functions' => array( 'xml' ),
    'script' => 'xml.php',
	'params' => array( 'ClassIdentifier', 'ParentNodeID' ) );
    
$ClassID = array(
    'name'=> 'Class',
    'values'=> array(),
    'class' => 'eZContentClass',
    'function' => 'fetchList',
    'parameter' => array( 0, false, false, array( 'name' => 'asc' ) )
    );

$SectionID = array(
    'name'=> 'Section',
    'values'=> array(),
    'class' => 'eZSection',
    'function' => 'fetchList',
    'parameter' => array( false )
    );

$Node = array(
    'name'=> 'Node',
    'values'=> array()
    );
    
$FunctionList['csv'] = array( 'Class' => $ClassID,
                                   'Section' => $SectionID,                                   
                                   'Node' => $Node );

$FunctionList['xml'] = array( 'Class' => $ClassID,
                                   'Section' => $SectionID,                                   
                                   'Node' => $Node );
?>