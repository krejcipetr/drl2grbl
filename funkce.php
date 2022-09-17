<?php
define("SPINON", "M3 S10000");
define("SPINOFF", "M5");

$GLOBALS['ratio'] = 1;

$GLOBALS['rotation'] = 0;
$GLOBALS['currenttool'] = 'default';
$GLOBALS['tools'] = array(
    $GLOBALS['currenttool'] => array(
        'description' => $GLOBALS['currenttool']
    )
);

define('ZUP', 1);
define('ZDOWN', - 1.6);
define('FREZASTEP', 0.5);
define('FREZADRILL', FALSE);
define('WORKINGSPEED', 20);

if (! defined("YINVERZE")) {
    define('YINVERZE', 1);
}

if (! defined("ZTEMPLATE")) {
    define('ZTEMPLATE', 0);
}

function correct(&$l_x, &$l_y)
{
    if (! $GLOBALS['rotation']) {
        return;
    }
    $l_distance = sqrt($l_x * $l_x + $l_y * $l_y);
    if ($l_distance) {
        if ($l_x == 0 && $l_y > 0) {
            $l_newrotation = deg2rad(90);
        } elseif ($l_x == 0 && $l_y < 0) {
            $l_newrotation = deg2rad(180);
        } elseif ($l_x < 0) {
            $l_newrotation = deg2rad(180) + atan($l_y / $l_x);
        } else {
            $l_newrotation = atan($l_y / $l_x);
        }
        $l_newrotation += $GLOBALS['rotation'];
        $l_x = $l_distance * cos($l_newrotation);
        $l_y = $l_distance * sin($l_newrotation);
    }
}

function command($a_command, $a_position, $a_speed = '')
{
    $l_parts = array();

    if (preg_match("/X([-0-9.]+)/", $a_position, $l_parts)) {
        $l_x = floatval($l_parts[1]) * $GLOBALS['ratio'];
    }
    if (preg_match("/Y([-0-9.]+)/", $a_position, $l_parts)) {
        $l_y = floatval($l_parts[1]) * $GLOBALS['ratio'] * YINVERZE;
    }
    if (preg_match("/Z([-0-9.]+)/", $a_position, $l_parts)) {
        $l_z = (floatval($l_parts[1]) * $GLOBALS['ratio']);
    }
    if (preg_match("/Z(%f)/", $a_position, $l_parts)) {
        $l_z = "%f";
    }
    if (preg_match("/I([-0-9.]+)/", $a_position, $l_parts)) {
        $l_i = floatval($l_parts[1]) * $GLOBALS['ratio'];
    }
    if (preg_match("/J([-0-9.]+)/", $a_position, $l_parts)) {
        $l_j = (floatval($l_parts[1]) * $GLOBALS['ratio']);
    }
    if (preg_match("/R([-0-9.]+)/", $a_position, $l_parts)) {
        $l_r = (floatval($l_parts[1]) * $GLOBALS['ratio']);
    }
    if (preg_match("/K([-0-9.]+)/", $a_position, $l_parts)) {
        $l_k = (floatval($l_parts[1]) * $GLOBALS['ratio']);
    }

    // Je konrektce otoceni?
    if (isset($l_x) && isset($l_y)) {
        correct($l_x, $l_y);
    }

    $l_position = "";
    if (isset($l_x)) {
        $l_position .= sprintf("X%01.2f", $l_x);
    }
    if (isset($l_y)) {
        $l_position .= sprintf("Y%01.2f", $l_y);
    }
    if (isset($l_i)) {
        $l_position .= sprintf("I%01.2f", $l_i);
    }
    if (isset($l_j)) {
        $l_position .= sprintf("J%01.2f", $l_j);
    }
    if (isset($l_r)) {
        $l_position .= sprintf("R%01.2f", $l_j);
    }
    if (isset($l_k)) {
        $l_position .= sprintf("K%01.2f", $l_j);
    }
    if (isset($l_z)) {
        if (is_numeric($l_z)) {
            $l_position .= sprintf("Z%01.2f", $l_z);
        } else {
            $l_position .= "Z%f";
        }
    }

    $GLOBALS['tools'][$GLOBALS['currenttool']][] = $a_command . $l_position . (($a_speed) ? "F" . $a_speed : "");
}

function zdrill()
{
    if (ZTEMPLATE) {
        command('G01', 'Z%f');
    } else {
        command('G01', 'Z' . ZDOWN);
    }
}

function zsafe()
{
    command('G00', 'Z' . ZUP);
}

function correctionSet($a_def)
{
    if (! empty($a_def)) {
        list ($l_x, $l_y, $l_x1, $l_y1) = explode(',', $a_def);
        $l_distance1 = sqrt($l_x * $l_x + $l_y * $l_y);
        $l_distance2 = sqrt($l_x1 * $l_x1 + $l_y1 * $l_y1);

        if (abs($l_distance1 - $l_distance2) > 0.2) {
            die(sprintf("Rotation correction over limit 0.2 (%f vs %f = %f)", $l_distance1, $l_distance2, abs($l_distance1 - $l_distance2)));
        }

        $GLOBALS['rotation'] = atan(floatval($l_y1) / floatval($l_x1));
        $GLOBALS['rotation'] -= atan(floatval($l_y) / floatval($l_x));
        echo "Corrention: " . (rad2deg($GLOBALS['rotation'])), PHP_EOL;
        echo (sprintf("Rotation correction distance (%f vs %f = %f)", $l_distance1, $l_distance2, abs($l_distance1 - $l_distance2))), PHP_EOL;
    }
}

function save($a_source)
{
    $l_filename = $a_source;
    $l_parts = pathinfo($l_filename);
    
    
    $l_totalcommands = array();
    
    foreach ($GLOBALS['tools'] as $l_number => $l_commands) {
        $l_newfile = $l_parts['dirname'] . DIRECTORY_SEPARATOR . $l_parts['filename'] . "-T" . $l_number . "-" . $l_commands['description'];
        unset($l_commands['description']);

        if (count($l_commands) == 0) {
            continue;
        }

        $l_filename = $l_newfile . ".nc";
        echo $l_filename . PHP_EOL;

        if (ZTEMPLATE) {
            $l_comm = array();
            for ($l_z = - FREZASTEP; $l_z > ZDOWN; $l_z -= FREZASTEP) {
                $l_modified = $l_commands;
                array_walk($l_modified, function (&$v) use ($l_z) {
                    $v = sprintf($v, $l_z);
                });
                $l_comm[] = "";
                $l_comm = array_merge($l_comm, $l_modified);
            }
        } else {
            $l_comm = $l_commands;
        }
        
        $l_totalcommands = array_merge($l_comm);

        array_unshift($l_comm, SPINON);
        array_unshift($l_comm, "F20");
        array_unshift($l_comm, "G00Z" . ZUP);
        array_unshift($l_comm, "G90");
        array_unshift($l_comm, "G21");
        array_push($l_comm, "G00Z" . ZUP);
        array_push($l_comm, SPINOFF);
        array_push($l_comm, "G00X0Y0");
        array_push($l_comm, "G53G00Z0");

        file_put_contents($l_filename, implode(PHP_EOL, $l_comm));
    }
    
    array_unshift($l_totalcommands, SPINON);
    array_unshift($l_totalcommands, "F".WORKINGSPEED);
    array_unshift($l_totalcommands, "G00Z" . ZUP);
    array_unshift($l_totalcommands, "G90");
    array_unshift($l_totalcommands, "G21");
    array_push($l_totalcommands, "G00Z" . ZUP);
    array_push($l_totalcommands, SPINOFF);
    array_push($l_totalcommands, "G00X0Y0");
    array_push($l_totalcommands, "G53G00Z0");
    
    $l_newfile = $l_parts['dirname'] . DIRECTORY_SEPARATOR . $l_parts['filename'] . "-predrill.nc";
    file_put_contents($l_filename, implode(PHP_EOL, $l_totalcommands));
}