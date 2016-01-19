<?php

class CSVSICOPATExporter extends AbstarctExporter
{
    protected $CSVheaders = array();
    protected $parentNodeID;
    protected $id_gruppo=0;
    private static $NO_MANDATORY_DATA = 'NO_MANDATORY_DATA';
    protected $values = array();
    protected $errors = array();
    protected $errorDescriptor = array();


    private static $INVIATO = 'invitato';
    private static $PARTECIPANTE = 'partecipante';
    private static $AGGIUDICATARIO = 'aggiudicatario';
    
    public function __construct( $parentNodeID, $classIdentifier )
    {
        $this->functionName = 'csv';
        parent::__construct( $parentNodeID, $classIdentifier );
        $this->parentNodeID = $parentNodeID;
        $this->createCSVHeader();
        $this->createErrorDescriptor();
    }

    private function createCSVHeader(){
        $this->CSVheaders = array('CIG','FLAG_CONTRATTO_SENZA_CIG','ANNO_PUBBLICAZIONE','OGGETTO','SCELTA_CONTRAENTE','IMPORTO_GARA','IMPORTO_AGGIUDICAZIONE','DATA_INIZIO','DATA_ULTIMAZIONE','IMPORTO_SOMME_LIQUIDATE','FLAG_COMPLETAMENTO','CF_AZIENDA','ID_GRUPPO','TIPO_PARTECIPAZIONE','ATTRIBUTO_INVITATA','ATRIBUTO_PARTECIPANTE','ATTRIBUTO_AGGIUDICATARIA');
    }

    function transformNode( eZContentObjectTreeNode $node )
    {

        $row = array();

        $object = $node->attribute( 'object' );

        if(!$object instanceof eZContentObject)
        {
            return null;
        }

        $data_map = $object->dataMap();

        //CIG
        //cig nillable="false" //xsd:maxLength value="10"
        $cig = self::$NO_MANDATORY_DATA;
        if ( $data_map['cig'] )
        {
            $cig = $data_map['cig']->content();
        }
        $row[] = $cig;

        //---------------------------------------------------------------------------
        //FLAG_CONTRATTO_SENZA_CIG
        if($cig){
            $row[] = 'N';
        }else{
            $row[] = 'S';
        }

        //---------------------------------------------------------------------------
        //ANNO_PUBBLICAZIONE
        $parentNode = eZContentObjectTreeNode::fetch($this->parentNodeID);
        $parenObject = $parentNode->attribute( 'object' );
        $parent_data_map = $parenObject->dataMap();

        //nillable="false
        $anno_pubblicazione = self::$NO_MANDATORY_DATA;
        if($parent_data_map['anno_riferimento']){

            //è un int da classe
            if($parent_data_map['anno_riferimento']->content()){
                $anno_pubblicazione = $parent_data_map['anno_riferimento']->content();
            }
        }
        $row[] = $anno_pubblicazione;

        //---------------------------------------------------------------------------
        //OGGETTO
        //oggetto nillable="false" //xsd:maxLength value="250"
        $oggetto = '';
        if ( $data_map['oggetto'] )
        {
            $oggetto = $data_map['oggetto']->content();
        }
        $row[] = $oggetto;

        //---------------------------------------------------------------------------
        //SCELTA_CONTRAENTE
        //sceltaContraente nillable="false" //select a scelta obbligata di valori indicati nella documentazione
        $scelta_contraente = self::$NO_MANDATORY_DATA;
        if ( $data_map['scelta_contraente'] )
        {
            if ( $data_map['scelta_contraente']->title())
            {
                $scelta_contraente = $data_map['scelta_contraente']->title();
                //tengo solo numeri come previsto da documentazione
                $scelta_contraente = preg_replace( '/[^0-9]/', '', $scelta_contraente );
            }
        }
        $row[] = $scelta_contraente;

        //---------------------------------------------------------------------------
        //IMPORTO_GARA
        $importo_gara = self::$NO_MANDATORY_DATA;
        if ( $data_map['importo_gara'] )
        {
            if ( $data_map['importo_gara']->content())
            {
                //FIXME: check formato importi secondo doc (solo numeri, rimuovere simboli tipo euro e mettere separatore decimali)
                $importo_gara = $data_map['importo_gara']->content();
            }
        }
        $row[] = $importo_gara;

        //---------------------------------------------------------------------------
        //IMPORTO_AGGIUDICAZIONE
        $importo_aggiudicazione='';
        if ( $data_map['importo_aggiudicazione'] )
        {
            //FIXME: check formato importi secondo doc (solo numeri, rimuovere simboli tipo euro e mettere separatore decimali)
            $importo_aggiudicazione = $data_map['importo_aggiudicazione']->content();
        }
        $row[] = $importo_aggiudicazione;

        //---------------------------------------------------------------------------
        //DATA_INIZIO
        $data_inizio = '';
        if ( $data_map['data_inizio'] )
        {
          $data_inizio = date( 'd/m/Y', $data_map['data_inizio']->DataInt );
        }
        $row[] = $data_inizio;

        //---------------------------------------------------------------------------
        //DATA_ULTIMAZIONE
        $data_ultimazione = '';
        if ( $data_map['data_ultimazione'] )
        {
            $data_ultimazione = date( 'd/m/Y', $data_map['data_ultimazione']->DataInt );
        }
        $row[] = $data_ultimazione;

        //---------------------------------------------------------------------------
        //IMPORTO_SOMME_LIQUIDATE
        $importo_somme_liquidate='';
        if ( $data_map['importo_somme_liquidate'] )
        {
            //FIXME: check formato importi secondo doc (solo numeri, rimuovere simboli tipo euro e mettere separatore decimali)
            $importo_somme_liquidate = $data_map['importo_somme_liquidate']->content();
        }
        $row[] = $importo_somme_liquidate;

        //---------------------------------------------------------------------------
        //FLAG_COMPLETAMENTO
        $flag_completamento='';
        if ( $data_map['flag_completamento'] )
        {
            $flag_completamento = $data_map['flag_completamento']->content();
        }
        if($flag_completamento==1){
            $row[] = 'S';
        }else{
            $row[] = 'N';
        }

        //valorizzo i campi CF_AZIENDA;ID_GRUPPO;TIPO_PARTECIPAZIONE;ATTRIBUTO_INVITATA;ATRIBUTO_PARTECIPANTE;ATTRIBUTO_AGGIUDICATARIA
        //ripetendo le righe qualora ci siano più figure dello stesso tipo

        $anagrafiche = array();
        $this->id_gruppo++;
        $size = 0;

        $invitati_matrix = $data_map['invitati']->content();
        $partecipanti_matrix = $data_map['partecipanti']->content();
        $aggiudicatario_matrix = $data_map['aggiudicatari']->content();

        $invitati_matrix_sequential = $invitati_matrix->Matrix['rows']['sequential'];
        $invitati_partecipanti_matrix = $partecipanti_matrix->Matrix['rows']['sequential'];
        $invitati_aggiudicatario_matrix = $aggiudicatario_matrix->Matrix['rows']['sequential'];

        $size+=sizeof($invitati_matrix_sequential);
        $size+=sizeof($invitati_partecipanti_matrix);
        $size+=sizeof($invitati_aggiudicatario_matrix);

        //---------------------------------------------------------------------------
        //invitati
        foreach ( $invitati_matrix_sequential as $invitato )
        {
            $this->getDataFromMatrix($anagrafiche[], $invitato, self::$INVIATO, $size);
        }
        //---------------------------------------------------------------------------
        //partecipanti
        foreach ( $invitati_partecipanti_matrix as $partecipante )
        {
            $this->getDataFromMatrix($anagrafiche[], $partecipante, self::$PARTECIPANTE, $size);
        }

        //---------------------------------------------------------------------------
        //aggiudicatari
        foreach ( $invitati_aggiudicatario_matrix as $aggiudicatario )
        {
            $this->getDataFromMatrix($anagrafiche[], $aggiudicatario, self::$AGGIUDICATARIO, $size);
        }

        //DUPLICAZIONE RIGHE
        //creo tante righe quanto sono le anagrafiche
        foreach ( $anagrafiche as $anagrafica ){
            $values[] = array_merge($row, $anagrafica);
        }

        //gestione eventuali errori
        $this->manageErrors($values, $object);

        return $values;
    }

    private function getDataFromMatrix(&$anagrafiche, $row, $type, $size){

        $columns = $row['columns'];

        //CF_AZIENDA
        $cf = self::$NO_MANDATORY_DATA;
        //CF
        //lunghezza massima 16
        if ( $columns[0] )
        {
            $cf = $columns[0];
        }
        //CF estero
        else if ( $columns[1] )
        {
            $cf = $columns[1];
        }

        $anagrafiche[] = $cf;

        //ID_GRUPPO (valorizzato solo se ci sono più soggetti)
        $id_gruppo = '';
        if($size>1){
           $id_gruppo = $this->id_gruppo;
        }
        $anagrafiche[] = $id_gruppo;

        //ruolo (TIPO_PARTECIPAZIONE)
        $tipo_partecipazione = '';

        //se la ci sono più soggetti il tipo partecipazione è obblicatorio
        if($size>1){
            $tipo_partecipazione = self::$NO_MANDATORY_DATA;
        }

        if ( $columns[3] )
        {
            $tipo_partecipazione = preg_replace( '/[^0-9]/', '', $columns[3]);
        }

        $anagrafiche[] = $tipo_partecipazione;

        $this->completeWithType($anagrafiche, $type);
    }

    private function completeWithType(&$anagrafiche, $type)
    {

        //ATTRIBUTO_AGGIUDICATARIA
        if ( $type == self::$INVIATO )
        {
            $anagrafiche[] = 'S';
            $anagrafiche[] = 'N';
            $anagrafiche[] = 'N';
        }

        if ( $type == self::$PARTECIPANTE )
        {
            $anagrafiche[] = 'N';
            $anagrafiche[] = 'S';
            $anagrafiche[] = 'N';
        }

        if ( $type == self::$AGGIUDICATARIO )
        {
            $anagrafiche[] = 'N';
            $anagrafiche[] = 'N';
            $anagrafiche[] = 'S';
        }
    }


    function createValues()
    {

        $items = $this->fetch();

        foreach ( $items as $item )
        {
            $this->values = array_merge($this->values, $this->transformNode( $item ));
        }

        return $this->errors;
    }


    function handleDownload()
    {

        $filename = $this->filename . '.csv';

        header( 'X-Powered-By: eZ Publish' );
        header( 'Content-Description: File Transfer' );
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( "Content-Disposition: attachment; filename=$filename" );
        header( "Pragma: no-cache" );
        header( "Expires: 0" );

        //$count = $this->fetchCount();
        $length = 50;

        $this->fetchParameters['Offset'] = 0;
        $this->fetchParameters['Limit'] = $length;

        $output = fopen('php://output', 'w');
        $runOnce = false;

            foreach ( $this->values as $value )
            {

                if ( !$runOnce )
                {
                    fputcsv(
                        $output,
                        array_values( $this->CSVheaders ),
                        $this->options['CSVDelimiter'],
                        $this->options['CSVEnclosure']
                    );
                    $runOnce = true;
                }
                fputcsv(
                    $output,
                    $value,
                    $this->options['CSVDelimiter'],
                    $this->options['CSVEnclosure']
                );
                flush();
        }
        $this->fetchParameters['Offset'] += $length;

    }


    private function manageErrors($values, $object){

        $object_id = $object->ID;

        foreach ( $values as $value ){

            //recupero le posizioni degli errori
            $errors_positions = array_keys($value, self::$NO_MANDATORY_DATA);

            foreach ( $errors_positions as $errors_position )
            {
                //prendo l'identificatore dell'errore
                $error_identifier = $this->CSVheaders[$errors_position];
                $error_description = $this->errorDescriptor[$error_identifier];
                $errors = array();

                if (array_key_exists($object_id, $this->errors)) {
                    $errors = $this->errors[$object_id];
                }

                array_push($errors, $error_description);
                $errors = array_unique($errors);
                $this->errors[$object_id] = $errors;
            }
        }
    }

    private function createErrorDescriptor(){

        $this->errorDescriptor = array('CIG' => "CIG è un campo obbligatorio. Va sempre indicato il CIG oppure l'identificativo PAT assegnato da SICOPAT, oppure, se si tratta di primo inserimento di un contratto senza CIG, inserire un identificativo di 10 caratteri che inizi con 9 (ES 9000000001, 9000000002, ecc. ) e che risulti univoco all’interno del file. L'associazione con l'identificativo assegnato dal sistema SICOPAT verrà specificata nell'esito del caricamento.",
                                       'FLAG_CONTRATTO_SENZA_CIG' => "",
                                       'ANNO_PUBBLICAZIONE' => 'Anno di pubblicazione è un campo obbligatorio. Sono ammessi 4 caratteri numerici.',
                                       'OGGETTO' => "",
                                       'SCELTA_CONTRAENTE' => "Scelta contraente è un campo obbligatorio. Sono ammessi 2 caratteri numerici (01 oppure 1 oppure 14, 17, ecc.)",
                                       'IMPORTO_GARA' => "Importo gara è un campo obbligatorio. Sono ammessi solo numeri senza separatori di migliaia e con il punto come separatore di decimali (Max 2 cifre decimali). (es: 1234567.89).",
                                       'IMPORTO_AGGIUDICAZIONE' => "Importo aggiudicazione: Sono ammessi solo numeri senza separatori di migliaia e con il punto come separatore di decimali (Max 2 cifre decimali). (es: 1234567.89).",
                                       'DATA_INIZIO' => "Data inizio: Formato: GG/MM/AAAA.",
                                       'DATA_ULTIMAZIONE' => "Data ultimazione: Formato: GG/MM/AAAA.",
                                       'IMPORTO_SOMME_LIQUIDATE' => "Importo somme liquidate: Sono ammessi solo numeri senza separatori di migliaia e con il punto come separatore di decimali (Max 2 cifre decimali). (es: 1234567.89).",
                                       'FLAG_COMPLETAMENTO' => "",
                                       'CF_AZIENDA' => "Codice fiscale è un campo obbligatorio. Sono ammessi 11 caratteri numerici.",
                                       'ID_GRUPPO' => "",
                                       'TIPO_PARTECIPAZIONE' => "Tipo partecipazione: Sono ammessi 1 carattere numerico tra 1,2,3,4 e 5.",
                                       'ATTRIBUTO_INVITATA' => "",
                                       'ATRIBUTO_PARTECIPANTE' => "",
                                       'ATTRIBUTO_AGGIUDICATARIA' => ""
        );
    }
}

?>