<?php

class CSVExporter extends AbstarctExporter
{
    private $CSVheaders = array();    
    
    public function __construct( $parentNodeID, $classIdentifier )
    {
        $this->functionName = 'csv';        
        parent::__construct( $parentNodeID, $classIdentifier );
    }
    
    function transformNode( $node )
    {                
        if ( $node instanceof eZContentObjectTreeNode )
        {
            $object = $node->attribute( 'object' );
            $values = array();
            foreach( $object->attribute( 'contentobject_attributes' ) as $attribute )
            {
                $attributeIdentifier = $attribute->attribute( 'contentclass_attribute_identifier' );
                $datatypeString = $attribute->attribute( 'data_type_string' );
                
                if ( isset( $this->options['ExcludeAttributeIdentifiers'] ) && in_array( $attributeIdentifier, $this->options['ExcludeAttributeIdentifiers'] ) )
                    continue;
                if ( isset( $this->options['ExcludeDatatype'] ) && in_array( $datatypeString, $this->options['ExcludeDatatype'] ) )
                    continue;
                
                $attributeName = $attribute->attribute( 'contentclass_attribute_name' );
                if ( !isset( $this->CSVheaders[$attributeIdentifier] ) )
                {
                    $this->CSVheaders[$attributeIdentifier] = $attributeName;
                }
                
                switch ( $datatypeString )
                {
                    case 'ezobjectrelation':
                    {
                        $attributeStringContent = $attribute->content()->attribute('name');
                    } break;
                    
                    case 'ezobjectrelationlist':
                    {
                        $attributeContent = $attribute->content();
                        $relations = $attributeContent['relation_list'];
                        
                        $relatedNames = array();
                        foreach ($relations as $relation)
                        {
                            $related = eZContentObject::fetch( $relation['contentobject_id'] );
                            if ( $related )
                            {
                                $relatedNames[] = $related->attribute( 'name' );
                                eZContentObject::clearCache( $related->attribute( 'id' ) );
                            }
                        }
                        $attributeStringContent = implode( ',', $relatedNames );
                    } break;
                    
                    case 'ezxmltext':
                    {
                        $text = str_replace( '"', "'", $attribute->content()->attribute('output')->outputText() );
                        $text = strip_tags( $text );
                        $text = str_replace( ';', ',', $text );
                        $text = str_replace( array("\n","\r"), "", $text );
                        $attributeStringContent = $text;
                    } break;
                    
                    case 'ezbinaryfile':
                    {
                        $attributeStringContent = '';
                        if ( $attribute->hasContent() )
                        {
                            $file = $attribute->content();
                            $filePath = "content/download/{$attribute->attribute('contentobject_id')}/{$attribute->attribute('id')}/{$attribute->content()->attribute( 'original_filename' )}";
                            $attributeStringContent = eZSys::hostname() . '/' . $filePath;
                        }
                    } break;

                    // Modifica Raffaele 09/04/2015 Altrimenti l'output è il timestamp
                    case 'ezdate':
                    {
                        $attributeStringContent = '';
                        if ( $attribute->hasContent() )
                        {
                            $attributeStringContent = strftime('%d/%m/%Y', $attribute->toString());
                        }
                    } break;

                    default:
                        $attributeStringContent = '';
                        if ( $attribute->hasContent() )
                            $attributeStringContent = $attribute->toString();
                        break;
                }
                
                $values[] = $attributeStringContent;
            }
            
            eZContentObject::clearCache( $object->attribute( 'id' ) );            
        }
        return $values;
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

        $count = $this->fetchCount();
        $length = 50;
        // Modifica Raffaele 09/04/2015 Sovrascrive l'attribute filter
        //$this->setFetchParameters( array( 'Offset' => 0 , 'Limit' => $length ) );
        $this->fetchParameters['Offset'] = 0;
        $this->fetchParameters['Limit'] = $length;

        $output = fopen('php://output', 'w');
        $runOnce = false;
        do
        {
            $items = $this->fetch();

            foreach ( $items as $item )
            {            
                $values = $this->transformNode( $item );
                if ( !$runOnce )
                {
                    fputcsv( $output, array_values( $this->CSVheaders ), $this->options['CSVDelimiter'], $this->options['CSVEnclosure'] );
                    $runOnce = true;
                }
                fputcsv( $output, $values, $this->options['CSVDelimiter'], $this->options['CSVEnclosure'] );
                flush();
            }            
            $this->fetchParameters['Offset'] += $length;
            
        } while ( count( $items ) == $length );
    }
}

?>