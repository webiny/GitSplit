<?php
namespace Webiny\Lib;

class Cli
{
    public function line($msg = '')
    {
        \cli\line($msg);
    }

    public function prompt($question, $default = false, $marker = ': ', $hide = false)
    {
        $color = \cli\Colors::color([
                                    'color' => 'yellow'
                                ]
                            );
        return \cli\prompt($color . $question . ' [' . $default . ']' . "\033[0m", $default, $marker, $hide);
    }
    
    public function menu($items, $default = null, $title = 'Choose an item')
    {
        $color = \cli\Colors::color([
                                    'color' => 'yellow'
                                ]
                            );
        $title = $color . $title;
        if ($default !== null) {
            $title .= ' [' . $items[$default] . ']';
        }
        $title = $title . " \033[0m";
        
        return \cli\menu($items, $default, $title);
    }
    
    public function separator($len = 64, $char = '+')
    {
        cliLine(str_repeat($char, $len));
    }
    
    public function error($msg)
    {
        $color = \cli\Colors::color([
                                    'color'      => 'black',
                                    'background' => 'red'
                                ]
                            );
        \cli\line($color . 'ERROR: ' . $msg . " \033[0m");
    }
}
