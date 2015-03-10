<?php
/**
 * Webiny Framework (http://www.webiny.com/framework)
 *
 * @copyright Copyright Webiny LTD
 */

namespace Webiny\GithubSubtreeTool\Lib;

class System
{
    /**
     * Deletes the given file or folder.
     *
     * @param $resource
     */
    public static function removeResource($resource)
    {
        self::command('rm -rf ' . $resource);
    }

    /**
     * Creates a directory.
     *
     * @param $dir
     */
    public static function createDir($dir)
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    /**
     * Executes a system command.
     *
     * @param $command
     */
    public static function command($command)
    {
        system($command);
    }

    /**
     * Returns current microtime.
     *
     * @return array
     */
    public static function getTime()
    {
        $timer = explode(' ', microtime());
        $timer = $timer[1] + $timer[0];

        return $timer;
    }

    /**
     * Displays time in min/sec.
     *
     * @param $seconds
     *
     * @return string
     */
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
