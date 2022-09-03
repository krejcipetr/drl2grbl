<?php
define('ZUP', 1);
define('ZDOWN', - 2.5);
define('FREZASTEP', 0.5);
define('FREZADRILL', FALSE);

$l_content = file_get_contents($GLOBALS['argv'][1]);
if ($l_content === false) {
    exit(1);
}

$l_content = str_replace("\r", "", $l_content);

$l_spindleon = "M3 S10000";
$l_spindleoff = "M5";

$l_tools = array();

$l_mode = 'G00';

$l_formatofnumbers = array();
$l_formatofnumbers['x'] = '46';
$l_formatofnumbers['y'] = '46';

function zdrill()
{
    command('G01', 'Z' . ZDOWN);
}

function zsafe()
{
    command('G00', 'Z' . ZUP);
}

function command($a_command, $a_position, $a_speed = '')
{
    $GLOBALS['l_tools'][$GLOBALS['l_currenttool']][] = $a_command . $a_position . (($a_speed) ? "F" . $a_speed : "");
}

foreach (explode(PHP_EOL, $l_content) as $l_row) {
    echo $l_row, PHP_EOL;
    $l_parts = array();
    if (preg_match("/^%MOMM*%/", $l_row, $l_parts)) {
        $l_ratio = 1;
    } elseif (preg_match("/^%FSLAX([0-9]+)Y([0-9]+)\\*%/", $l_row, $l_parts)) {
        $l_formatofnumbers['x'] = $l_parts[1][1];
        $l_formatofnumbers['y'] = $l_parts[2][1];
    } elseif (preg_match("/^%ADD([0-9]+)C,(.+)\\*%/", $l_row, $l_parts)) {
        $l_tools[$l_parts[1]] = array(
            'description' => $l_parts[2]
        );
    } elseif (preg_match("/^D([0-9]+)\\*$/", $l_row, $l_parts)) {
        $l_currenttool = $l_parts[1];
    } elseif (preg_match("/^G01\\*$/", $l_row, $l_parts)) {
        $l_mode = 'G01';
    } elseif (preg_match("/^G02\\*$/", $l_row, $l_parts)) {
        $l_mode = 'G02';
    } elseif (preg_match("/^G03\\*$/", $l_row, $l_parts)) {
        $l_mode = 'G03';
    } elseif (preg_match("/^X([-0-9.]+)Y([-0-9.]+)(I([-0-9.]+)J([-0-9.]+))?D([0-9]+)\\*$/", $l_row, $l_parts)) {
        $l_com = 'G00';
        switch ($l_parts[6]) {
            case '01':
                $l_com = $l_mode;
                break;
            case '02':
                $l_com = 'G00';
                break;
        }
        // Convert numbers
        $l_parts[1] = substr_replace($l_parts[1], ".", - $l_formatofnumbers['x'], 0);
        $l_parts[2] = substr_replace($l_parts[2], ".", - $l_formatofnumbers['y'], 0);
        $l_parts[4] = substr_replace($l_parts[4], ".", - $l_formatofnumbers['x'], 0);
        $l_parts[5] = substr_replace($l_parts[5], ".", - $l_formatofnumbers['y'], 0);

        if ($l_com == 'G00') {
            zsafe();
        }
        else {
            zdrill();
        }
        command($l_com, 'X' . $l_parts[1] . 'Y' . $l_parts[2] . (($l_parts[3]) ? 'I' . $l_parts[4] . 'J' . $l_parts[5] : ''));
    }
}

foreach ($l_tools as $l_commands) {
    $l_filename = $GLOBALS['argv'][1];
    $l_parts = pathinfo($l_filename);
    $l_newfile = $l_parts['dirname'] . DIRECTORY_SEPARATOR . $l_parts['filename'] . "-" . $l_commands['description'];
    unset($l_commands['description']);

    $l_filename = $l_newfile . ".nc";
    echo $l_filename . PHP_EOL;

    array_unshift($l_commands, $l_spindleon);
    array_unshift($l_commands, "F40");
    array_unshift($l_commands, "G00Z" . ZUP);
    array_unshift($l_commands, "G90");
    array_push($l_commands, "G00Z" . ZUP);
    array_push($l_commands, $l_spindleoff);
    array_push($l_commands, "G00X0Y0");
    array_push($l_commands, "G53G00Z0");

    file_put_contents($l_filename, implode(PHP_EOL, $l_commands));
}
