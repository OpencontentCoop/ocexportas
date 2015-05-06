<?php

abstract class AbstarctExporter
{
    public $fetchParameters = array();
    public $userParameters = array();
    public $options = array();

    /**
     * @var eZINI
     */
    public $ini;

    /**
     * @var string
     */
    public $filename;
    
    protected $functionName;

    /**
     * @var eZContentObjectTreeNode
     */
    protected $mainNode;

    /**
     * @var eZContentObject
     */
    protected $mainObject;

    /**
     * @var eZContentClass
     */
    protected $mainClass;
    
    abstract function transformNode( eZContentObjectTreeNode $node );
    
    abstract function handleDownload();

    public function setUserParameter( array $parameters = null )
    {
        $this->userParameters = $parameters;
        foreach( $this->userParameters as $key => $value )
        {
            switch( $key )
            {
                case 'year':
                case 'anno':
                {
                    $start = mktime( 0, 0, 0, 1, 1, intval( $value ) );
                    $end = mktime( 23, 59, 59, 12, 31, intval( $value ) );
                    $this->fetchParameters['AttributeFilter'] = array(
                        array( 'published', 'between', array( $start, $end ) )
                    );
                } break;
            }

        }
    }
    
    public function __construct( $parentNodeID, $classIdentifier )
    {        
        if ( !$parentNodeID && !$classIdentifier )
        {
            throw new InvalidArgumentException( "Arguments not found" ); 
        }
        
        $this->ini = eZINI::instance( 'exportas.ini' );
        $this->setOptions( $this->ini->group( 'Settings' ) );

        if ( method_exists( 'OCOpenDataTools', 'getFieldBlacklist' ) )
        {
            $fieldBlacklist = OCOpenDataTools::getFieldBlacklist();
            $this->options['ExcludeAttributeIdentifiers'] = array_keys( $fieldBlacklist );
        }

        if ( method_exists( 'OCOpenDataTools', 'getDatatypeBlackList' ) )
        {
            $datatypeBlacklist = OCOpenDataTools::getDatatypeBlackList();
            $this->options['ExcludeDatatype'] = array_keys( $datatypeBlacklist );
        }
        
        $this->setClassIdentifier( $classIdentifier );
        $this->setParentNode( $parentNodeID );
        $this->setFetchParameters();
        
        $checkAccess = $this->checkAccess( $this->functionName );
        if (  $checkAccess !== true )
        {
            eZDebug::writeError( $checkAccess, __METHOD__ );
            throw new Exception( 'Current user can not export this csv' );            
        }        
    }
    
    public function setClassIdentifier( $classIdentifier )
    {
        if ( $classIdentifier )
        {
            if ( method_exists( 'OCOpenDataTools', 'getClassBlacklist' ) )
            {
                $classBlacklist = OCOpenDataTools::getClassBlacklist();
                if ( isset( $classBlacklist[$classIdentifier] ) )
                {
                    throw new InvalidArgumentException( "Class $classIdentifier not allowed" );
                }
            }
            $this->mainClass = eZContentClass::fetchByIdentifier( $classIdentifier );
            if ( !$this->mainClass instanceof eZContentClass )
            {
               throw new InvalidArgumentException( "Class $classIdentifier not found" ); 
            }
            if ( $this->ini->hasGroup( 'SettingsForClassIdentifier_' . $classIdentifier ) )
                $this->setOptions( $this->ini->group( 'SettingsForClassIdentifier_' . $classIdentifier ) );
        }
        else
        {
            throw new InvalidArgumentException( "Class not found" );
        }
    }
    
    public function setParentNode( $parentNodeID )
    {
        if ( !$parentNodeID )
        {
            $parentNodeID = eZINI::instance( 'content.ini' )->variable( 'NodeSettings', 'RootNode' );            
        }
        $parentNode = eZContentObjectTreeNode::fetch( $parentNodeID );
        if ( !$parentNode instanceof eZContentObjectTreeNode )
        {
            throw new InvalidArgumentException( "Node $parentNodeID not found" );
        }
        $pathString = $parentNode->attribute( 'path_identification_string' );
        $pathStringArray = explode( '/', $pathString );
        $this->filename = array_pop( $pathStringArray );
        if ( empty( $pathString ) && $this->mainClass !== null )
        {
            $this->filename = $this->mainClass->attribute( 'identifier' );
        }
        $this->setOptions( array( 'path_string' => $pathString ) ); 
        $this->mainNode = $parentNode;
        $this->mainObject = $parentNode->attribute( 'object' );        
    }
    
    private function setFetchParameters( $override = array() )
    {
        $params = $this->ini->hasGroup( 'DefaultFetchParams' ) ? $this->ini->group( 'DefaultFetchParams' ) : array();
        
        if ( $this->mainClass )
        {
            if ( $this->ini->hasGroup( 'DefaultFetchParamsForClassIdentifier_' . $this->mainClass->attribute( 'identifier' ) ) )
            {
                $params = $this->ini->group( 'DefaultFetchParamsForClassIdentifier_' . $this->mainClass->attribute( 'identifier' ) ) + $params;
            }
            
            $params = array( 'ClassFilterType' => 'include',
                             'ClassFilterArray' => array( $this->mainClass->attribute( 'identifier' ) ) ) + $params;
        }
        $this->fetchParameters = $override + $params;
    }
    
    public function fetch()
    {                        
        return $this->mainNode->subTree( $this->fetchParameters );                
    }
    
    public function fetchCount()
    {                        
        return $this->mainNode->subTreeCount( $this->fetchParameters );                
    }
    
    public function setOptions( $args = array() )
    {
        $this->options =  $args + $this->options;
        return $this;
    }
    
    protected function checkAccess()
    {        
        
        $user = eZUser::currentUser();
        $userID = $user->attribute( 'contentobject_id' );

        $accessResult = $user->hasAccessTo( 'exportas' , $this->functionName );
        $accessWord = $accessResult['accessWord'];
        
        if ( $accessWord == 'yes' )
        {
            return true;
        }
        else if ( $accessWord == 'no' )
        {
            return false;
        }
        else
        {
            $policies  =& $accessResult['policies'];
            $access = 'denied';
            foreach ( array_keys( $policies ) as $pkey  )
            {
                $limitationArray =& $policies[ $pkey ];
                if ( $access == 'allowed' )
                {
                    break;
                }

                $limitationList = array();
                if ( isset( $limitationArray['Subtree' ] ) )
                {
                    $checkedSubtree = false;
                }
                else
                {
                    $checkedSubtree = true;
                    $accessSubtree = false;
                }
                if ( isset( $limitationArray['Node'] ) )
                {
                    $checkedNode = false;
                }
                else
                {
                    $checkedNode = true;
                    $accessNode = false;
                }
                foreach ( array_keys( $limitationArray ) as $key  )
                {
                    $access = 'denied';
                    switch( $key )
                    {
                        case 'Class':
                        {
                            if ( !$this->mainClass )
                            {
                                $access = 'denied';
                                $limitationList = array( 'Limitation' => $key,
                                                         'Required' => $limitationArray[$key] );
                            }
                            elseif ( in_array( $this->mainClass->attribute( 'id' ), $limitationArray[$key]  ) )
                            {
                                $access = 'allowed';
                            }
                            else
                            {
                                $access = 'denied';
                                $limitationList = array( 'Limitation' => $key,
                                                         'Required' => $limitationArray[$key] );
                            }
                            
                        } break;

                        case 'Section':
                        case 'User_Section':
                        {
                            if ( in_array( $this->mainObject->attribute( 'section_id' ), $limitationArray[$key]  ) )
                            {
                                $access = 'allowed';
                            }
                            else
                            {
                                $access = 'denied';
                                $limitationList = array( 'Limitation' => $key,
                                                         'Required' => $limitationArray[$key] );
                            }
                        } break;                        

                        case 'Node':
                        {
                            $accessNode = false;
                            $mainNodeID = $this->mainObject->attribute( 'main_node_id' );
                            foreach ( $limitationArray[$key] as $nodeID )
                            {
                                $node = eZContentObjectTreeNode::fetch( $nodeID, false, false );
                                $limitationNodeID = $node['main_node_id'];
                                if ( $mainNodeID == $limitationNodeID )
                                {
                                    $access = 'allowed';
                                    $accessNode = true;
                                    break;
                                }
                            }                            
                            $checkedNode = true;
                        } break;

                        default:
                        {
                            if ( strncmp( $key, 'StateGroup_', 11 ) === 0 )
                            {
                                if ( count( array_intersect( $limitationArray[$key],
                                                             $this->mainObject->attribute( 'state_id_array' ) ) ) == 0 )
                                {
                                    $access = 'denied';
                                    $limitationList = array ( 'Limitation' => $key,
                                                              'Required' => $limitationArray[$key] );
                                }
                                else
                                {
                                    $access = 'allowed';
                                }
                            }
                        }
                    }
                    if ( $access == 'denied' )
                    {
                        break;
                    }
                }

                $policyList[] = array( 'PolicyID' => $pkey,
                                       'LimitationList' => $limitationList );
            }

            if ( $access == 'denied' )
            {
                return array( 'FunctionRequired' => array ( 'Module' => 'exportas',
                                                            'Function' => $this->functionName  ),
                              'PolicyList' => $policyList );
            }
            else
            {
                return true;
            }
        }
    }
}

?>