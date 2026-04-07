<?php
require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance(array(
  'description' => ("Esporto i dati di uno specifico survey"),
  'use-session' => false,
  'use-modules' => true,
  'use-extensions' => true,
));

$script->startup();

$options = $script->getOptions('[object_id:][content_class_attribute_id:][language_code:]',
  '',
  array(
    'object_id' => "Identificatore dell'oggetto",
    'content_class_attribute_id' => "Identificatore dell'attributo della classe",
    'language_code' => "Codice lingua",
  )
);
$script->initialize();

try {

  if (isset($options['object_id'])) {
    $contentObjectID = $options['object_id'];
  } else {
    throw new Exception("Specificare object_id");
  }

  if (isset($options['content_class_attribute_id'])) {
    $contentClassAttributeID = $options['content_class_attribute_id'];
  } else {
    throw new Exception("Specificare content_class_attribute_id");
  }

  if (isset($options['language_code'])) {
    $languageCode = $options['language_code'];
  } else {
    throw new Exception("Specificare language_code");
  }

  $user = eZUser::fetchByName('admin');
  if ( !$user ) {
    $cli->error( "Script Error! Cannot get user Admin user" );
    $script->shutdown( 1 );
  }
  eZUser::setCurrentlyLoggedInUser( $user, $user->attribute( 'contentobject_id' ) );


  $exporter = new SurveyExporter( $contentObjectID, $contentClassAttributeID, $languageCode );

  if ($exporter->canExport()) {

    $storageDirName = 'ocexportas';
    $filename = "survey_{$contentObjectID}_{$contentClassAttributeID}_{$languageCode}.csv";
    $storage = eZSys::storageDirectory() . eZSys::fileSeparator() . $storageDirName;

    if (!is_dir($storage)) {
      eZDir::mkdir($storage, false, true);
    }

    $output = fopen($storage . eZSys::fileSeparator() . $filename, 'w');

    fputcsv($output, $exporter->getExpandedQuestionList());

    $answers = $exporter->getExpandedAnswerList();
    foreach($answers as $answer){
      fputcsv($output, $answer);
    }

    $cli->output("Done");
    $cli->output("Path del file: " . $storage . eZSys::fileSeparator() . $filename);
  } else {
    $cli->output("Il survey non può essere esportato.");
  }


  $script->shutdown();
} catch (Exception $e) {
  $errCode = $e->getCode();
  $errCode = $errCode != 0 ? $errCode : 1; // If an error has occured, script must terminate with a status other than 0
  $script->shutdown($errCode, $e->getMessage());
}
