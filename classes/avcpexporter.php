<?php

class AVCPExporter extends AbstarctExporter
{
    protected $xmlWriter;
    protected $tagStyle;
    protected $parentNodeID;
    protected $siteUrl;

    protected static $recursion = 0;

    const USE_GENERIC_TAG = 1;
    const USE_IDENTIFIER_TAG = 2;

    public function __construct( $parentNodeID, $classIdentifier )
    {

        $this->functionName = 'xml';
        parent::__construct( $parentNodeID, $classIdentifier );

        $this->tagStyle = self::USE_GENERIC_TAG;
        if ( isset( $this->options['XMLTagStyle'] ) && $this->options['XMLTagStyle'] == 'custom' )
        {
            $this->tagStyle = self::USE_IDENTIFIER_TAG;
        }

        $this->parentNodeID = $parentNodeID;

        $siteINI = eZINI::instance();
        $this->siteUrl = rtrim( $siteINI->variable( 'SiteSettings', 'SiteURL' ), '/' );


    }

    protected function writeMetadata(){

        /*
         * ESEMPIO DI METADATA (i dati provengono da oggetti di tipo pagina_trasparenza)
         *
        <metadata>
            <titolo>Pubblicazione 1 legge 190</titolo>
            <abstract>Pubblicazione 1 legge 190 anno 1 rif. 2010 aggiornamento del 2015-01-28 07:00:30</abstract>
            <dataPubbicazioneDataset>2015-01-31</dataPubbicazioneDataset>
            <entePubblicatore>Comune di XXX</entePubblicatore>
            <dataUltimoAggiornamentoDataset>2015-01-28</dataUltimoAggiornamentoDataset>
            <annoRiferimento>2014</annoRiferimento><urlFile>http://www.comune.xxx.brescia.it/trasparenza/avcp_dataset_2014.xml</urlFile>
            <licenza>IODL 2.0</licenza>
        </metadata>
        */

        $parentNode = eZContentObjectTreeNode::fetch($this->parentNodeID);
        $parenObject = $parentNode->attribute( 'object' );
        $data_map = $parenObject->dataMap();

        //metadata
        $this->xmlWriter->startElement( 'metadata' );

        //
        $this->xmlWriter->startElement( 'titolo' );
        if($data_map['titolo']){
            $this->xmlWriter->text($data_map['titolo']->content());
        }
        $this->xmlWriter->endElement();

        //
        $this->xmlWriter->startElement( 'abstract' );
        if($data_map['abstract']){
            //FIXME: trattare come XML
            $this->xmlWriter->text($data_map['abstract']->content());
        }
        $this->xmlWriter->endElement();

        //nillable="false"
        $this->xmlWriter->startElement( 'dataPubbicazioneDataset' );
        if($data_map['data_pubbicazione_dataset']){
            $this->xmlWriter->text(date( 'Y-m-d', $data_map['data_pubbicazione_dataset']->DataInt));
        }
        $this->xmlWriter->endElement();

        //nillable="false"
        $this->xmlWriter->startElement( 'entePubblicatore' );
        if($data_map['ente_pubblicatore']){
            $this->xmlWriter->text($data_map['ente_pubblicatore']->content());
        }
        $this->xmlWriter->endElement();

        //
        $this->xmlWriter->startElement( 'dataUltimoAggiornamentoDataset' );
        if($data_map['data_ultimo_aggiornamento_dataset']){
            $this->xmlWriter->text(date( 'Y-m-d', $data_map['data_ultimo_aggiornamento_dataset']->DataInt));
        }
        $this->xmlWriter->endElement();

        //nillable="false
        $this->xmlWriter->startElement( 'annoRiferimento' );
        if($data_map['anno_riferimento']){

            //è un int da classe
            $this->xmlWriter->text($data_map['anno_riferimento']->content());
        }
        $this->xmlWriter->endElement();

        //nillable="false
        $this->xmlWriter->startElement( 'urlFile' );
        //se c'è index.php lo rimuovo
        $url = str_replace("index.php","",$this->siteUrl);
        //FIXME: gestire protocollo https
        $this->xmlWriter->text('http://'.$url.'/exportas/avpc/lotto/'.$parentNode->attribute( 'node_id' ));
        $this->xmlWriter->endElement();

        //valori da select presenti nella classe
        $this->xmlWriter->startElement( 'licenza' );
        if($data_map['licenza']){
            $this->xmlWriter->text($data_map['licenza']->title());
        }
        $this->xmlWriter->endElement();

        $this->xmlWriter->endElement();

    }

    protected function writeObjectProperties( $object )
    {
        $array = array(
            'name' => $object->attribute( 'name' ),
            'id' => $object->attribute( 'id' ),
            'section' => $object->attribute( 'section_id' ),
            'published' => $object->attribute( 'published' ),
            'modified' => $object->attribute( 'modified' ),
            'remote_id' => $object->attribute( 'remote_id' ),
        );
        foreach( $array as $key => $value )
        {
            $this->xmlWriter->writeAttribute( $key, $value );
        }
    }

    protected function writeNodeProperties( $node )
    {
        $array = array(
            'node_id' => $node->attribute( 'node_id' ),
            'parent_node_id' => $node->attribute( 'parent_node_id' )
        );
        foreach( $array as $key => $value )
        {
            $this->xmlWriter->writeAttribute( $key, $value );
        }
    }

    protected static function decodeStatus( $value )
    {
        switch ( $value )
        {
            case 0:
                return 'draft';
            case 1:
                return 'published';
            case 2:
                return 'archived';
            default:
                return 'unknow';
        }
    }

    function transformObject( $object, $node = false )
    {

        if($object instanceof eZContentObject)
        {

            $data_map = $object->dataMap();

            //lotto
            $this->xmlWriter->startElement( 'lotto' );

            //---------------------------------------------------------------------------
            //cig nillable="false" //xsd:maxLength value="10"
            $this->xmlWriter->startElement( 'cig' );
            if ( $data_map['cig'] )
            {
                $this->xmlWriter->text( $data_map['cig']->content() );
            }
            $this->xmlWriter->endElement();

            //---------------------------------------------------------------------------
            //strutturaProponente nillable="false"
            $this->xmlWriter->startElement( 'strutturaProponente' );

            //codice_fiscale_piva nillable="false" //lunghezza massima 11 (è una partita iva???)
            $this->xmlWriter->startElement( 'codiceFiscaleProp' );
            if ( $data_map['codice_fiscale_piva_proponente'] )
            {
                $this->xmlWriter->text( $data_map['codice_fiscale_piva_proponente']->content() );
            }
            $this->xmlWriter->endElement();


            //denominazione_proponente nillable="false" //xsd:maxLength value="250"
            $this->xmlWriter->startElement( 'denominazione' );
            if ( $data_map['denominazione_proponente'] )
            {
                $this->xmlWriter->text( $data_map['denominazione_proponente']->content() );
            }
            $this->xmlWriter->endElement();

            $this->xmlWriter->endElement();

            //---------------------------------------------------------------------------
            //oggetto nillable="false" //xsd:maxLength value="250"
            $this->xmlWriter->startElement( 'oggetto' );
            if ( $data_map['oggetto'] )
            {
                $this->xmlWriter->writeCData( $data_map['oggetto']->content() );
            }
            $this->xmlWriter->endElement();

            //sceltaContraente nillable="false" //select a scelta obbligata di valori indicati nell'XSD
            $this->xmlWriter->startElement( 'sceltaContraente' );
            if ( $data_map['scelta_contraente'] )
            {
                $this->xmlWriter->text( $data_map['scelta_contraente']->title() );
            }
            $this->xmlWriter->endElement();

            //---------------------------------------------------------------------------
            //partecipanti
            $this->createDataFromMatrix($node, 'partecipanti', 'partecipante');

            //---------------------------------------------------------------------------
            //aggiudicatari
            $this->createDataFromMatrix($node, 'aggiudicatari', 'aggiudicatario');

            //importoAggiudicazione
            if ( $data_map['importo_aggiudicazione'] )
            {
                $this->xmlWriter->startElement( 'importoAggiudicazione' );
                $importo_somme_liquidate = $data_map['importo_somme_liquidate']->content();

                if($importo_somme_liquidate instanceof eZPrice){
                    $this->xmlWriter->text($importo_somme_liquidate->Price);
                }
                $this->xmlWriter->endElement();
            }

            //---------------------------------------------------------------------------
            //tempiCompletamento
            if ( $data_map['data_inizio'] || $data_map['data_ultimazione'] )
            {
                $this->xmlWriter->startElement( 'tempiCompletamento' );

                if ( $data_map['data_inizio'] )
                {
                    $this->xmlWriter->startElement( 'dataInizio' );
                    $this->xmlWriter->text(
                        date( 'Y-m-d', $data_map['data_inizio']->DataInt )
                    );
                    $this->xmlWriter->endElement();
                }
                if ( $data_map['data_ultimazione'] )
                {
                    $this->xmlWriter->startElement( 'dataUltimazione' );
                    $this->xmlWriter->text(
                        date( 'Y-m-d', $data_map['data_ultimazione']->DataInt )
                    );
                    $this->xmlWriter->endElement();
                }

                $this->xmlWriter->endElement();
            }

            //importoSommeLiquidate
            if ( $data_map['importo_somme_liquidate'] )
            {

                $this->xmlWriter->startElement( 'importoSommeLiquidate' );

                $importo_somme_liquidate = $data_map['importo_somme_liquidate']->content();

                if($importo_somme_liquidate instanceof eZPrice){
                    $this->xmlWriter->text($importo_somme_liquidate->Price);
                }
                $this->xmlWriter->endElement();
            }

            eZContentObject::clearCache( $object->attribute( 'id' ) );

            //chiusura lotto
            $this->xmlWriter->endElement();

        }
    }

    private function createDataFromMatrix($node, $root_node, $child_node){

        $this->xmlWriter->startElement( $root_node );

        $data_map = $node->attribute( 'data_map' );
        $matrix = $data_map[$root_node]->content();
        $ragg = array();

        foreach ( $matrix->Matrix['rows']['sequential'] as $row )
        {

            $columns = $row['columns'];

            //se è stato indicato che fa parte di un gruppo, lo gestisco con i raggruppamenti
            if($columns[3]){
                array_push($ragg, $row);
                continue;
            }

            //caso singolo
            $this->xmlWriter->startElement( $child_node );

            //lunghezza massima 11
            if ( $columns[0] )
            {
                $this->xmlWriter->startElement( 'codiceFiscale' );
                $this->xmlWriter->text( $columns[0] );
                $this->xmlWriter->endElement();
            }
            if ( $columns[1] )
            {
                $this->xmlWriter->startElement( 'identificativoFiscaleEstero' );
                $this->xmlWriter->text( $columns[1] );
                $this->xmlWriter->endElement();
            }
            //minOccurs="1" xsd:maxLength value="250"
            //FIXME: limitare a 250, serve dare errore se non è valorizzato perchè a FE è una colonna di una matrice
            $this->xmlWriter->startElement( 'ragioneSociale' );
            if ( $columns[2] )
            {
                $this->xmlWriter->writeCData( $columns[2] );
            }
            $this->xmlWriter->endElement();

            $this->xmlWriter->endElement();

        }

        //gestione raggruppamento
        if(sizeof($ragg)>0){

            $this->xmlWriter->startElement( 'raggruppamento' );

            foreach ( $ragg as $row )
            {
                $columns = $row['columns'];

                $this->xmlWriter->startElement( 'membro' );

                //lunghezza massima 11
                if ( $columns[0] )
                {
                    $this->xmlWriter->startElement( 'codiceFiscale' );
                    $this->xmlWriter->text( $columns[0] );
                    $this->xmlWriter->endElement();
                }
                if ( $columns[1] )
                {
                    $this->xmlWriter->startElement( 'identificativoFiscaleEstero' );
                    $this->xmlWriter->text( $columns[1] );
                    $this->xmlWriter->endElement();
                }

                //minOccurs="1" xsd:maxLength value="250"
                //FIXME: limitare a 250, serve dare errore se non è valorizzato perchè a FE è una colonna di una matrice
                $this->xmlWriter->startElement( 'ragioneSociale' );
                if ( $columns[2] )
                {
                    $this->xmlWriter->writeCData( $columns[2] );
                }
                $this->xmlWriter->endElement();

                //ruolo
                $this->xmlWriter->startElement( 'ruolo' );
                if ( $columns[4] )
                {
                    $this->xmlWriter->text(preg_replace( '/[^0-9]/', '', $columns[4]));
                }
                $this->xmlWriter->endElement();

                $this->xmlWriter->endElement();
            }

            $this->xmlWriter->endElement();
        }

        $this->xmlWriter->endElement();
    }

    function transformNode( eZContentObjectTreeNode $node )
    {
        if ( $node instanceof eZContentObjectTreeNode )
        {
            $object = $node->attribute( 'object' );
            self::$recursion = 0;
            $this->transformObject( $object, $node );
        }
    }

    function handleDownload()
    {

        @set_time_limit(0);
        $filename = $this->filename . '.xml';

        header( 'X-Powered-By: eZ Publish' );
        header( 'Content-Description: File Transfer' );
        header( 'Content-Type: text/xml; charset=utf-8' );
        header( "Content-Disposition: inline; filename=$filename" );
        header( "Pragma: no-cache" );
        header( "Expires: 0" );

        $count = $this->fetchCount();

        if ( $count > 0 )
        {
            $length = 50;
            $this->fetchParameters['Offset'] = 0;
            $this->fetchParameters['Limit'] = $length;

            $this->xmlWriter = new XMLWriter();
            $this->xmlWriter->openURI( 'php://output' );
            $this->xmlWriter->startDocument('1.0', 'UTF-8');

            $this->xmlWriter->startElement( 'legge190:pubblicazione' );
            $this->xmlWriter->writeAttribute( 'xsi:schemaLocation', 'legge190_1_0 datasetAppaltiL190.xsd');
            $this->xmlWriter->writeAttribute( 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            $this->xmlWriter->writeAttribute( 'xmlns:legge190', 'legge190_1_0');

            //metedata
            $this->writeMetadata();

            $this->xmlWriter->startElement( 'data' );

            do
            {
                $items = $this->fetch();

                foreach ( $items as $item )
                {
                    $this->transformNode( $item );
                }
                $this->xmlWriter->flush();
                $this->fetchParameters['Offset'] += $length;

            } while ( count( $items ) == $length );

            $this->xmlWriter->endElement();

            $this->xmlWriter->endElement();

            $this->xmlWriter->flush();
        }
    }
}

?>