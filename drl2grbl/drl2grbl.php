<?php
define('ZUP', 1);
define('ZDOWN', - 2.5);
define('FREZASTEP', 0.5);
define('FREZADRILL' , FALSE);

$l_content = file_get_contents($GLOBALS['argv'][1]);
if ($l_content === false) {
    exit(1);
}

$l_content = str_replace("\r", "", $l_content);

$l_spindleon = "M3 S10000";
$l_spindleoff = "M5";

$l_tools = array();
$l_currenttool = null;

$l_freza = 0;
$l_currentposition = null;

$l_mode = 0;

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
    $GLOBALS['l_tools'][$GLOBALS['l_currenttool']][($GLOBALS['l_mode'] ? 'freza' : 'drills')][] = $a_command . $a_position . (($a_speed) ? "F" . $a_speed : "");
}

foreach (explode(PHP_EOL, $l_content) as $l_row) {
    $l_parts = array();
    if (preg_match("/^METRIC/", $l_row)) {
        $l_ratio = 1;
    } elseif (preg_match("/^T([0-9]+)C([0-9.]+)/", $l_row, $l_parts)) {
        $l_tools[$l_parts[1]] = array(
            'description' => $l_parts[2]
        );
    } elseif (preg_match("/^T([0-9]+)$/", $l_row, $l_parts)) {
        $l_currenttool = $l_parts[1];
    } elseif (preg_match("/^X([-0-9.]+)Y([-0-9.]+)$/", $l_row, $l_parts)) {
        $l_currentposition = $l_row;

        command("G00", $l_row);
        zdrill();
        zsafe();
    } elseif (preg_match("/^M15$/", $l_row, $l_parts)) {
        $l_freza = array(
            $l_currentposition
        );
        $l_mode = 1;
    } elseif (preg_match("/^M16$/", $l_row, $l_parts)) {
        $l_mode=! FREZADRILL; 
        // Koncim s frezovanim
        foreach ($l_freza as $l_point) {
            command("G00", $l_point);
            zdrill();
            zsafe();
        }

        $l_mode=1;
        for ($l_z = - 0.2; $l_z > ZDOWN; $l_z -= FREZASTEP) {
            $l_frezax = $l_freza;
            command("G00", array_shift($l_frezax));
            command("G01", "Z" . $l_z);
            while ($l_point = array_shift($l_frezax)) {
                command("G01", $l_point);
            }
        }
        zsafe();
        $l_mode = 0;
    } elseif (preg_match("/^G00(X[-0-9.]+Y[-0-9.]+)$/", $l_row, $l_parts)) {
        $l_currentposition = $l_parts[1];

        command("G00", $l_parts[1]);
    } elseif (preg_match("/^G01(X[-0-9.]+Y[-0-9.]+)$/", $l_row, $l_parts)) {
        $l_currentposition = $l_parts[1];

        if ($l_mode) {
            $l_freza[] = $l_parts[1];
        } else {
            command("G01", $l_parts[1]);
        }
    }
}

print_r($l_tools);

foreach ($l_tools as $l_number => $l_tool) {
    $l_filename = $GLOBALS['argv'][1];
    $l_parts = pathinfo($l_filename);
    $l_newfile = $l_parts['dirname'] . DIRECTORY_SEPARATOR . $l_parts['filename'] . "-T" .$l_number."-". $l_tool['description'];
    unset($l_tool['description']);
    
    foreach ($l_tool as $l_tool => $l_commands) {
        $l_filename = $l_newfile . "-" . $l_tool . ".nc";
        echo $l_filename.PHP_EOL;

        array_unshift($l_commands, $l_spindleon);
        array_unshift($l_commands, "F40");
        array_unshift($l_commands, "G00Z".ZUP);
        array_unshift($l_commands, "G90");
        array_push($l_commands, $l_spindleoff);
        array_push($l_commands, "G00Z" . ZUP);
        array_push($l_commands, "G00X0Y0");
        array_push($l_commands, "G53G00Z0");

        file_put_contents($l_filename, implode(PHP_EOL, $l_commands));
    }
}
