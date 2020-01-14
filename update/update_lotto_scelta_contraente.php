<?php
require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance(array('description' => ("Aggiorna attributo scelta_contraente in classe lotto"),
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true));

$script->startup();

$options = $script->getOptions();
$script->initialize();
$script->setUseDebugAccumulators(true);

try {

    $user = eZUser::fetchByName('admin');
    eZUser::setCurrentlyLoggedInUser($user, $user->attribute('contentobject_id'));

    $remoteTypes = array();
    $localeTypes = array();
    $diff = array();

    $source = 'http://dati.anticorruzione.it/schema/TypesL190.xsd';
    $cli->warning("Estraggo dati da $source");

    $xsdstring = file_get_contents($source);
    $doc = new DOMDocument();
    $doc->loadXML(mb_convert_encoding($xsdstring, 'utf-8', mb_detect_encoding($xsdstring)));
    $xpath = new DOMXPath($doc);
    $xpath->registerNamespace('xsd', 'http://www.w3.org/2001/XMLSchema');
    $elementDefs = $xpath->evaluate("/xsd:schema/xsd:simpleType[@name='sceltaContraenteType']/xsd:restriction/xsd:enumeration");
    foreach ($elementDefs as $elementDef) {
        $type = $elementDef->getAttribute('value');
        list($id, $name) = explode('-', $type);
        $remoteTypes[$id] = $type;
        $cli->output($type);
    }
    $cli->output();

    $cli->warning("Controllo valori presenti in classe lotto");
    $lotto = eZContentClass::fetchByIdentifier('lotto');
    if (!$lotto instanceof eZContentClass) {
        throw new Exception("Classe lotto non trovata", 1);
    }
    $sceltaContraente = $lotto->fetchAttributeByIdentifier('scelta_contraente');
    if (!$sceltaContraente instanceof eZContentClassAttribute) {
        throw new Exception("Attributo scelta_contraente non trovato", 1);
    }

    $content = $sceltaContraente->content();
    $options = $content['options'];
    foreach ($options as $index => $option) {
        $type = $option['name'];
        list($id, $name) = explode('-', $type);
        $localeTypes[$id] = $type;
        if (isset($remoteTypes[$id])) {
            if ($remoteTypes[$id] != $localeTypes[$id]) {
                $diff[$localeTypes[$id]] = $remoteTypes[$id];
                $options[$index]['name'] = $remoteTypes[$id];
            }
            unset($remoteTypes[$id]);
        } else {
            $diff[$type] = "tassonomia rimossa: occorre intervento manuale";
        }
        $cli->output($type);
    }

    $currentCount = 0;
    foreach ($remoteTypes as $id => $value) {
        $diff[] = $remoteTypes[$id];
        if ($currentCount == 0) {
            foreach ($options as $option) {
                $currentCount = max($currentCount, $option['id']);
            }
        }
        $currentCount += 1;
        $options[] = array('id' => $currentCount, 'name' => $remoteTypes[$id]);
    }
    $cli->output();

    if (!empty($diff)) {
        $cli->warning("Trovate " . count($diff) . " differenze");
        foreach ($diff as $wrong => $right) {
            if (is_string($wrong)) {
                $cli->error($wrong, false);
            } else {
                $cli->error('?', false);
            }
            $cli->output(' -> ', false);
            $cli->warning($right);
        }

        $output = new ezcConsoleOutput();
        $question = ezcConsoleQuestionDialog::YesNoQuestion($output, "Applico le modifiche? (y/n)");
        if (ezcConsoleDialogViewer::displayDialog($question) == "y") {
            $doc = new DOMDocument('1.0', 'utf-8');
            $root = $doc->createElement("ezselection");
            $doc->appendChild($root);
            $optionsElement = $doc->createElement("options");
            $root->appendChild($optionsElement);
            foreach ($options as $optionArray) {
                unset($optionNode);
                $optionNode = $doc->createElement("option");
                $optionNode->setAttribute('id', $optionArray['id']);
                $optionNode->setAttribute('name', $optionArray['name']);
                $optionsElement->appendChild($optionNode);
            }
            $xml = $doc->saveXML();            
            $sceltaContraente->setAttribute("data_text5", $xml);
            $sceltaContraente->store();

			$currentSiteaccess = eZSiteAccess::current();
            shell_exec('php extension/ocsearchtools/bin/php/reindex_by_class.php --class=lotto -s' . $currentSiteaccess['name']);
        }
    }

} catch (Exception $e) {
    $cli->error($e->getMessage());
}
$script->shutdown();

/*
<?xml version="1.0" encoding="utf-8"?>
<ezselection><options><option id="0" name="01-PROCEDURA APERTA"/><option id="1" name="02-PROCEDURA RISTRETTA"/><option id="2" name="03-PROCEDURA NEGOZIATA PREVIA PUBBLICAZIONE DEL BANDO"/><option id="3" name="04-PROCEDURA NEGOZIATA SENZA PREVIA PUBBLICAZIONE DEL BANDO"/><option id="4" name="05-DIALOGO COMPETITIVO"/><option id="5" name="06-PROCEDURA NEGOZIATA SENZA PREVIA INDIZIONE DI GARA ART. 221 D.LGS. 163/2006"/><option id="6" name="07-SISTEMA DINAMICO DI ACQUISIZIONE"/><option id="7" name="08-AFFIDAMENTO IN ECONOMIA - COTTIMO FIDUCIARIO"/><option id="8" name="14-PROCEDURA SELETTIVA EX ART 238 C.7, D.LGS. 163/2006"/><option id="9" name="17-AFFIDAMENTO DIRETTO EX ART. 5 DELLA LEGGE N.381/91"/><option id="10" name="21-PROCEDURA RISTRETTA DERIVANTE DA AVVISI CON CUI SI INDICE LA GARA"/><option id="11" name="22-PROCEDURA NEGOZIATA DERIVANTE DA AVVISI CON CUI SI INDICE LA GARA"/><option id="12" name="23-AFFIDAMENTO IN ECONOMIA - AFFIDAMENTO DIRETTO"/><option id="13" name="24-AFFIDAMENTO DIRETTO A SOCIETA' IN HOUSE"/><option id="14" name="25-AFFIDAMENTO DIRETTO A SOCIETA' RAGGRUPPATE/CONSORZIATE O CONTROLLATE NELLE CONCESSIONI DI LL.PP"/><option id="15" name="26-AFFIDAMENTO DIRETTO IN ADESIONE AD ACCORDO QUADRO/CONVENZIONE"/><option id="16" name="27-CONFRONTO COMPETITIVO IN ADESIONE AD ACCORDO QUADRO/CONVENZIONE"/><option id="17" name="28-PROCEDURA AI SENSI DEI REGOLAMENTI DEGLI ORGANI COSTITUZIONALI"/></options></ezselection>
*/