<?php
/**
 * Webiny Framework (http://www.webiny.com/framework)
 *
 * @copyright Copyright Webiny LTD
 */

namespace Webiny\GithubSubtreeTool\Lib;

class Cli
{
    /**
     * Print a message and end with a new line.
     *
     * @param string $msg
     */
    public function line($msg = '')
    {
        \cli\line($msg);
    }

    /**
     * Prompt the user to input something.
     *
     * @param        $question
     * @param bool   $default
     * @param string $marker
     * @param bool   $hide
     *
     * @return string
     */
    public function prompt($question, $default = false, $marker = ': ', $hide = false)
    {
        $color = \cli\Colors::color([
                'color' => 'yellow'
            ]);

        return \cli\prompt($color . $question . ' [' . $default . ']' . "\033[0m", $default, $marker, $hide);
    }

    /**
     * Menu selection.
     *
     * @param        $items
     * @param null   $default
     * @param string $title
     *
     * @return string
     */
    public function menu($items, $default = null, $title = 'Choose an item')
    {
        $color = \cli\Colors::color([
                'color' => 'yellow'
            ]);
        $title = $color . $title;
        if ($default !== null) {
            $title .= ' [' . $items[$default] . ']';
        }
        $title = $title . " \033[0m";


        while (true) {
            $choice = \cli\menu($items, $default, $title);
            $this->line();
            if (is_int($choice)) {
                if (in_array($choice, array_keys($items))) {
                    break;
                }
            }
        }

        return $choice;
    }

    /**
     * Prints a separator.
     *
     * @param int    $len
     * @param string $char
     */
    public function separator($len = 64, $char = '+')
    {
        $this->line(str_repeat($char, $len));
    }

    /**
     * Print an error message.
     *
     * @param $msg
     */
    public function error($msg)
    {
        $color = \cli\Colors::color([
                'color'      => 'black',
                'background' => 'red'
            ]);
        \cli\line($color . 'ERROR: ' . $msg . " \033[0m");
    }

    /**
     * Terminal color to highlight text (green).
     *
     * @return string
     */
    public function colorHighlight()
    {
        return \cli\Colors::color([
                'color'      => 'black',
                'background' => 'green',
                'style'      => 1
            ]);
    }

    /**
     * Red color.
     *
     * @return string
     */
    public function colorRed()
    {
        return \cli\Colors::color([
                'color'      => 'black',
                'background' => 'red',
                'style'      => 1
            ]);
    }

    /**
     * End the coloring.
     *
     * @return string
     */
    public function colorEnd()
    {
        return "\033[0m";
    }

    /**
     * Print the welcome header.
     */
    public function header()
    {
        $this->line();
        $this->line(self::colorHighlight() . 'WELCOME TO WEBINY SUBTREE TOOL' . self::colorEnd());
        $this->line('Released under MIT license.');
        $this->line('http://www.webiny.com/ | https://www.github.com/Webiny/');
        $this->line();
        $this->line('Use at your own risk.');
        $this->line('Make sure you know what you are doing,');
        $this->line('or you might mess things up - don\'t blame us then.');
        $this->line();
        $this->line('All bugs and improvements report to:');
        $this->line('https://www.github.com/Webiny/GithubSubtreeTool/');
        $this->separator();
        $this->line();
        $this->line();
    }

    /**
     * Print a table.
     *
     * @param $header
     * @param $rows
     */
    public function table($header, $rows)
    {
        $table = new \cli\Table();
        $table->setHeaders($header);
        $table->setRows($rows);
        $table->display();
    }

}
