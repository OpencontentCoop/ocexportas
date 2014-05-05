<?php

/**
 *
 * php extension/sqliimport/bin/php/sqlidoimport.php -s<siteaccess> --source-handlers="importasxml" --options="importasxml::file=<full_path_to_file>[,var_dir=<full_path_to_var_dir>]"
 */

class ImportAsXMLImportHandler extends SQLIImportAbstractHandler implements ISQLIImportHandler
{
    protected $rowIndex = 0;

    protected $rowCount;

    protected $currentFile;
    protected $parentNodeID;
    protected $rootNodeID;
    protected $varDir;
    
    //@todo
    protected $template;
    protected $templates = array();
    
    public $errors = array();

    public function __construct( SQLIImportHandlerOptions $options = null )
    {
        parent::__construct( $options );        
        $this->cli->setUseStyles( true );
        $this->options = $options;
    }

    public function initialize()
    {
    	$this->currentFile = $this->options['file'];
        
        if ( isset( $this->options['var_dir'] ) )
        {
            $varDir = $this->options['var_dir'];
            $this->varDir = rtrim( $varDir, '/' ) . '/';
        }
        
        if ( isset( $this->options['parent'] ) )
        {
            $this->rootNodeID = $this->options['parent'];
        }
        
        if ( isset( $this->options['template'] ) )
        {
            if ( isset( $this->templates[$this->options['template']] ) )
            {
                $this->template = $this->templates[$this->options['template']];
            }
        }
    	
        $xmlOptions = new SQLIXMLOptions( array(
            'xml_path'      => $this->currentFile,
            'xml_parser'    => 'simplexml'
        ) );
        $xmlParser = new SQLIXMLParser( $xmlOptions );
        $this->dataSource = $xmlParser->parse();
    }
    
    public function getProcessLength()
    {
        if( !isset( $this->rowCount ) )
        {
            $this->rowCount = count( $this->dataSource->object );            
        }
        return $this->rowCount;
    }

    public function getNextRow()
    {
        if( $this->rowIndex < $this->rowCount )
        {
            $row = $this->dataSource->object[$this->rowIndex];
            $this->rowIndex++;
        }
        else
        {
            $row = false; // We must return false if we already processed all rows
        }

        return $row;
    }

    protected function protectRemote( $remoteID )
    {
        return '_' . $remoteID;
    }
    
    protected function protectClass( $classIdentifier )
    {        
        if ( $this->template && isset( $this->template['class'][$classIdentifier] ) )
        {
            return $this->template['class'][$classIdentifier];
        }
        return $classIdentifier;
    }
    
    protected function protectAttribute( $classIdentifier, $attributeIdentifier )
    {
        if ( $this->template && isset( $this->template['class'][$classIdentifier] ) )
        {
            if ( isset( $this->template['attributes'][$attributeIdentifier] ) )
            {
                return $this->template['attributes'][$attributeIdentifier];
            }
        }       
        return $attributeIdentifier;
    }
    
    protected function changeDate( eZContentObject $object, array $properties )
    {
        if ( isset( $properties['published'] ) )
        {
            $object->setAttribute( 'published', $properties['published'] );
            $object->store();            
        }        
    }
    
    public function process( $row )
    { 
        try
        {
            $properties = $this->decodeProperties( $row );
            $attributes = $this->decodeAttributes( $row );            
            $parentObject = eZContentObject::fetchByRemoteID( $this->protectRemote( $properties['parent_object_remote_id'] ) );            
            if ( $parentObject instanceof eZContentObject )
            {
                $this->parentNodeID = $parentObject->attribute( 'main_node_id' );
            }
            else
            {
                $this->parentNodeID = $this->rootNodeID;   
            }
            
            if ( $this->parentNodeID === null )
            {                
                throw new Exception( "Parent node {$properties['name']} not found: " . $this->protectRemote( $properties['parent_object_remote_id'] ) );                
            }
            
            $exists = eZContentObject::fetchByRemoteID( $this->protectRemote( $properties['remote_id'] ) );
            
            if ( $exists )
            {                
                $this->changeDate( $exists, $properties );
                $content = SQLIContent::fromContentObject( $exists );
                $content->addPendingClearCacheIfNeeded();
                $content->flush();
            }
            
            if ( $exists )
            {                                
                if ( $exists->attribute( 'class_identifier' ) != $this->protectClass( $properties['class_identifier'] ) )
                {
                    throw new Exception( "Cannot override {$properties['name']} " .  $this->protectRemote( $properties['remote_id'] ) );
                }
            }
            
            $contentOptions = new SQLIContentOptions( array(
                'class_identifier' => $this->protectClass( $properties['class_identifier'] ),
                'remote_id' => $this->protectRemote( $properties['remote_id'] )
            ));        
            $content = SQLIContent::create( $contentOptions );
            
            foreach( $attributes as $key => $valueArray )
            {                                
                $identifier = $this->protectAttribute( $properties['class_identifier'], $key );
                if ( isset( $content->fields->{$identifier} ) )
                {
                    $content->fields->{$identifier} = $this->decodeAttribute( $key, $valueArray, $content->fields->{$identifier} );
                }
            }
            
            $content->addLocation( SQLILocation::fromNodeID( $this->parentNodeID ) );
	        $publisher = SQLIContentPublisher::getInstance();
	        $publisher->publish( $content );
            $this->changeDate( $content->getRawContentObject(), $properties );
            unset( $content );            
        }
        catch( Exception $e )
        {
            $this->errors[] = $e->getMessage();
        }                
    }
    
    protected function decodeProperties( $row )
    {
        $rowAttributes = $row->attributes();
        $properties = array();
        foreach( $rowAttributes as $key => $value )
        {
            $properties[$key] = (string) $value;
        }
        return $properties;
    }
    
    protected function decodeAttributes( $row )
    {
        $attributes = array();
        foreach( $row->attribute as $attribute  )
        {
            $attributeRowAttributes = $attribute->attributes();
            $attributeProperties = array();
            foreach( $attributeRowAttributes as $key => $value )
            {
                $attributeProperties[$key] = (string) $value;
            }
            $attributeContent = $attribute;
            $attributes[$attributeProperties['contentclass_attribute_identifier']] = array( 'properties' => $attributeProperties, 'content' => $attributeContent );
        }
        return $attributes;
    }
    
    protected function decodeAttribute( $key, $values, $sqliContentAttribute )
    {
        $properties = $values['properties'];
        $content = $values['content'];
        if ( $properties['has_content'] == 1 )
        {
            switch( $properties['data_type_string'] )
            {
                case 'ezobjectrelation':
                {
                    //@todo;
                } break;
                
                case 'ezobjectrelationlist':
                {
                    foreach( $content as $object )
                    {
                        $relations = array();
                        $itemProperties = $this->decodeProperties( $object );
                        $object = eZContentObject::fetchByRemoteID( $this->protectRemote( $itemProperties['remote_id'] ) );
                        if ( $object instanceof eZContentObject )
                        {
                            $relations[] = $object->attribute( 'id' );
                        }
                        else
                        {
                            throw new Exception( "{$itemProperties['remote_id']} in attribute $key not found" );
                        }
                    }
                    return implode( '-', $relations );
                } break;
                
                case 'ezbinaryfile':
                {
                    $filePath = $this->varDir . $properties['filepath'];
                    return $filePath;
                    
                } break;
                
                case 'ezmedia':
                {                    
                    $filePath = $this->varDir . $properties['filepath'];
                    return $filePath;
                    
                } break;
                
                case 'ezimage':
                {
                    return $this->varDir . $properties['full_path'];
                } break;
                
                case 'ezdate':
                case 'ezdatetime':
                {
                    return (string) $content;
                } break;
                
                case 'ezselection':
                {
                    //@todo
                    throw new Exception( "ezselection not implemented!" );
                } break;
                
                default:
                    return (string) $content;
            }
        }
        return null;
    }
    
    public function cleanup()
    { 
        foreach( $this->errors as $error )
        {
            //$this->cli->error( $error );
            eZLog::write( $error, 'importas.log' );
        }
        return false;
    }
        
        
    public function getHandlerName()
    {
        return 'ImportAsXML Handler';
    }
    
    public function getHandlerIdentifier()
    {
        return 'importasxml';
    }
    
    public function getProgressionNotes()
    {
        return 'Currently importing : ' . $this->currentFile;
    }
}
