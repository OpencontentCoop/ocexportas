<?php

class XMLFullExporter extends AbstarctExporter
{
    private $xmlWriter;
    private $tagStyle;
    
    private static $recursion = 0;
    
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
    }
    
    private function writeObjectProperties( $object )
    {
        $array = array(
            'name' => $object->attribute( 'name' ),
            'contentobject_id' => $object->attribute( 'id' ),
            'section_id' => $object->attribute( 'section_id' ),
            'published' => $object->attribute( 'published' ),
            'modified' => $object->attribute( 'modified' ),
            'current_version' => $object->attribute( 'current_version' ),
            'status' => self::decodeStatus( $object->attribute( 'status' ) ),
            'remote_id' => $object->attribute( 'remote_id' ),
            'owner' => $object->attribute( 'owner' )->attribute( 'name' )
        );
        foreach( $array as $key => $value )
        {
            $this->xmlWriter->writeAttribute( $key, $value );
        }
    }
    
    private function writeNodeProperties( $node )
    {
       $array = array(
            'node_id' => $node->attribute( 'node_id' ),
            'node_remote_id' => $node->attribute( 'remote_id' ),
            'parent_node_id' => $node->attribute( 'parent_node_id' ),
            'parent_object_remote_id' => $node->attribute( 'parent' )->attribute( 'object' )->attribute( 'remote_id' ),
            'parent_node_remote_id' => $node->attribute( 'parent' )->attribute( 'remote_id' ),
            'main_node_id' => $node->attribute( 'main_node_id' ),
            'path_string' => $node->attribute( 'path_string' ),
            'path_identification_string' => $node->attribute( 'path_identification_string' ),
            'is_hidden' => $node->attribute( 'is_hidden' ),
            'is_invisible' => $node->attribute( 'is_invisible' )            
        );
        foreach( $array as $key => $value )
        {
            $this->xmlWriter->writeAttribute( $key, $value );
        }
    }
    
    private static function decodeStatus( $value )
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
        if ( $this->tagStyle == self::USE_GENERIC_TAG )
        {
            $this->xmlWriter->startElement( 'object' );
            $this->xmlWriter->writeAttribute( 'class_identifier', $object->attribute( 'class_identifier' ) );
        }
        else
        {
            $this->xmlWriter->startElement( $object->attribute( 'class_identifier' ) );
        }
        $this->writeObjectProperties( $object );
        if ( $node )
        {
            $this->writeNodeProperties( $node );
        }
            
        foreach( $object->attribute( 'contentobject_attributes' ) as $attribute )
        {
            $attributeIdentifier = $attribute->attribute( 'contentclass_attribute_identifier' );
            $datatypeString = $attribute->attribute( 'data_type_string' );
            
            if ( isset( $this->options['ExcludeAttributeIdentifiers'] ) && in_array( $attributeIdentifier, $this->options['ExcludeAttributeIdentifiers'] ) )
                continue;
            if ( isset( $this->options['ExcludeDatatype'] ) && in_array( $datatypeString, $this->options['ExcludeDatatype'] ) )
                continue;
            
            if ( $this->tagStyle == self::USE_GENERIC_TAG )
            {
                $this->xmlWriter->startElement( 'attribute' );
                $this->xmlWriter->writeAttribute( 'contentclass_attribute_identifier', $attributeIdentifier );
            }
            else
            {
                $this->xmlWriter->startElement( $attributeIdentifier );
            }
            
            $this->xmlWriter->writeAttribute( 'data_type_string', $datatypeString );
            $this->xmlWriter->writeAttribute( 'contentclass_attribute_name', $attribute->attribute( 'contentclass_attribute_name' ) );            
            $this->xmlWriter->writeAttribute( 'has_content', (int) $attribute->hasContent() );
            
            $attributeContent = false;
            switch ( $datatypeString )
            {
                case 'ezobjectrelation':
                {
                    if ( $attribute->hasContent() )
                    {
                        $attributeContent = $attribute->content();
                        if ( self::$recursion == $this->options['XMLRelatedObjectRecursion'] )
                        {
                            if ( $this->tagStyle == self::USE_GENERIC_TAG )
                            {
                                $this->xmlWriter->startElement( 'object' );
                                $this->xmlWriter->writeAttribute( 'class_identifier', $attributeContent->attribute( 'class_identifier' ) );
                            }
                            else
                            {
                                $this->xmlWriter->startElement( $attributeContent->attribute( 'class_identifier' ) );
                            }                            
                            $this->writeObjectProperties( $object );
                            $this->xmlWriter->endElement();
                        }
                        else
                        {
                            self::$recursion++;
                            $this->transformObject( $related );
                            self::$recursion--;
                        }
                        eZContentObject::clearCache( $attributeContent->attribute( 'id' ) );
                    }
                } break;
                
                case 'ezobjectrelationlist':
                {
                    if ( $attribute->hasContent() )
                    {
                        $attributeContent = $attribute->content();
                        $relations = $attributeContent['relation_list'];                        
                        $relatedNames = array();
                        foreach ($relations as $relation)
                        {                            
                            $related = eZContentObject::fetch( $relation['contentobject_id'] );
                            if ( $related )
                            {                                                                    
                                if ( self::$recursion == $this->options['XMLRelatedObjectRecursion'] )
                                {
                                    if ( $this->tagStyle == self::USE_GENERIC_TAG )
                                    {
                                        $this->xmlWriter->startElement( 'object' );
                                        $this->xmlWriter->writeAttribute( 'class_identifier', $related->attribute( 'class_identifier' ) );
                                    }
                                    else
                                    {
                                        $this->xmlWriter->startElement( $related->attribute( 'class_identifier' ) );
                                    }                                      
                                    $this->writeObjectProperties( $related );
                                    $this->xmlWriter->endElement();
                                }
                                else
                                {
                                    self::$recursion++;
                                    $this->transformObject( $related );
                                    self::$recursion--;
                                }                                   
                                eZContentObject::clearCache( $related->attribute( 'id' ) );
                            }
                        }                        
                    }
                } break;
                
                case 'ezxmltext':
                {
                    if ( $attribute->hasContent() )
                    {
                        $this->xmlWriter->writeCData( $attribute->content()->attribute('xml_data') );
                    }                        
                } break;

                case 'ezstring':
                case 'eztext':
                {
                    if ( $attribute->hasContent() )
                    {                            
                        $this->xmlWriter->text( $attribute->toString() );
                    }                        
                } break;
                
                case 'ezmedia':
                case 'ezbinaryfile':
                {
                    if ( $attribute->hasContent() )
                    {
                        $attributeContent = $attribute->content();
                        $filePath = $attributeContent->storedFileInfo();
                        $this->xmlWriter->writeAttribute( 'filepath', $filePath['filepath'] );
                        $this->xmlWriter->writeAttribute( 'filesize', $attributeContent->attribute( 'filesize' ) );
                        $this->xmlWriter->writeAttribute( 'mime_type_category', $attributeContent->attribute( 'mime_type_category' ) );
                        $this->xmlWriter->writeAttribute( 'mime_type_part', $attributeContent->attribute( 'mime_type_part' ) );
                        $this->xmlWriter->writeAttribute( 'original_filename', $attributeContent->attribute( 'original_filename' ) );                            
                    }                        
                } break;
                
                case 'ezimage':
                {
                    if ( $attribute->hasContent() )
                    {
                        $attributeContent = $attribute->content()->attribute( 'original' );                        
                        $this->xmlWriter->writeAttribute( 'width', $attributeContent['width'] );
                        $this->xmlWriter->writeAttribute( 'height', $attributeContent['height'] );
                        $this->xmlWriter->writeAttribute( 'mime_type', $attributeContent['mime_type'] );
                        $this->xmlWriter->writeAttribute( 'filename', $attributeContent['filename'] );
                        $this->xmlWriter->writeAttribute( 'alternative_text', $attributeContent['alternative_text'] );
                        $this->xmlWriter->writeAttribute( 'full_path', $attributeContent['full_path'] );
                        $this->xmlWriter->writeAttribute( 'filesize', $attributeContent['filesize'] );
                    }                        
                } break;
                
                case 'ezdate':
                case 'ezdatetime':
                {
                    if ( $attribute->hasContent() )
                    {                            
                        $this->xmlWriter->writeAttribute( 'timestamp', $attribute->toString() );
                    }                        
                } break;
                
                case 'ezselection':
                {
                    if ( $attribute->hasContent() )
                    {                            
                        $attributeContent = $attribute->toString();
                        $selectedNames = eZStringUtils::explodeStr( $attributeContent, '|' );
                        foreach( $selectedNames as $selectedName )
                            $this->xmlWriter->writeElement( 'item', $selectedName );
                    }                        
                } break;
            
                default:
                {
                    if ( $attribute->hasContent() )
                    {
                        $this->xmlWriter->text( $attribute->toString() );
                    }
                } break;
            }
            
            $this->xmlWriter->endElement();
        }
        eZContentObject::clearCache( $object->attribute( 'id' ) );
        $this->xmlWriter->endElement();
    }
    
    function transformNode( $node )
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
        header( "Content-Disposition: attachment; filename=$filename" );
        header( "Pragma: no-cache" );
        header( "Expires: 0" );

        $count = $this->fetchCount();
        
        if ( $count > 0 )
        {
            $length = 50;        
            $this->setFetchParameters( array( 'Offset' => 0 , 'Limit' => $length ) );
            
            $this->xmlWriter = new XMLWriter();                    
            $this->xmlWriter->openURI( 'php://output' );         
            $this->xmlWriter->startDocument('1.0', 'UTF-8');
            $this->xmlWriter->startElement( 'root' );
            
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
            $this->xmlWriter->flush();
        }
    }
}

?>