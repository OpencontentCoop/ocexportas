<?php

class CustomExporterAsFactory
{
    /**
     * @param $handlerIdentifier
     * @param $parentNodeID
     * @param $classIdentifier
     *
     * @return AbstarctExporter
     */
    public static function factory( $handlerIdentifier, $parentNodeID, $classIdentifier )
    {
        $className = self::getCustomExportHandlerClassName( $handlerIdentifier );
        return new $className( $parentNodeID, $classIdentifier );
    }

    /**
     * @param $handlerIdentifier
     * @return string
     * @throws Exception
     */
    protected static function getCustomExportHandlerClassName( $handlerIdentifier )
    {
        $exporters = eZINI::instance( 'exportas.ini' )->group( 'CustomExporters' );
        if ( isset( $exporters[$handlerIdentifier] ) )
        {
            return $exporters[$handlerIdentifier];
        }
        throw new Exception( "Custom exporter $handlerIdentifier not found" );
    }
}