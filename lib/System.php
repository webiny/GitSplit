<?php
namespace Webiny\Lib;

class System
{
    public static function removeResource($folder)
    {
        self::command('rm -rf '.$folder);
    }
    
    public static function command($command)
    {
        system($command);
    }
    
    
    public static function getTime()
    {
        $timer = explode(' ', microtime());
        $timer = $timer[1] + $timer[0];
        return $timer;
    }
    
    public static function displayTime($seconds)
    {
        $seconds = round($seconds);
        if ($seconds < 60) {
            return $seconds . ' sec';
        }
        $minutes = round($seconds / 60);
        $seconds = $seconds % 60;
        return $minutes . ' min ' . $seconds . ' sec';
    }
}
