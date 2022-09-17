<?php
require_once 'funkce.php';

correctionSet($argv[2]);

$l_content = file_get_contents($GLOBALS['argv'][1]);
if ($l_content === false) {
    exit(1);
}
$l_content = str_replace("\r", "", $l_content);

$l_mode = 0;
$l_freza = 0;

foreach (explode(PHP_EOL, $l_content) as $l_row) {
    $l_parts = array();
    if (preg_match("/^INCH/", $l_row)) {
        $GLOBALS['ratio'] = 2.54;
    } elseif (preg_match("/^METRIC/", $l_row)) {
        $GLOBALS['ratio'] = 1;
    } elseif (preg_match("/^T([0-9]+)C([0-9.]+)/", $l_row, $l_parts)) {
        $GLOBALS['tools'][$l_parts[1]] = array(
            'description' => floatval($l_parts[2]) * $GLOBALS['ratio']
        );
    } elseif (preg_match("/^T([0-9]+)$/", $l_row, $l_parts)) {
        $GLOBALS['currenttool'] = $l_parts[1];
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
        $l_mode = ! FREZADRILL;
        // Koncim s frezovanim
        foreach ($l_freza as $l_point) {
            command("G00", $l_point);
            zdrill();
            zsafe();
        }

        $l_mode = 1;
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

save($GLOBALS['argv'][1]);
