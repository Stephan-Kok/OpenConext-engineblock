<?php

class EngineBlock_Corto_Exception_InvalidSfoLoaLevel extends EngineBlock_Exception
{
    public function __construct($message, $severity = self::CODE_NOTICE, Exception $previous = null)
    {
        parent::__construct($message, $severity, $previous);
    }
}
