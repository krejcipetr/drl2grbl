<?php

define("ZTEMPLATE", TRUE);

require_once 'funkce.php';

correctionSet($argv[2]);

$l_content = file_get_contents($GLOBALS['argv'][1]);
if ($l_content === false) {
    exit(1);
}
$l_content = str_replace("\r", "", $l_content);

$l_mode = 'G00';

$l_formatofnumbers = array();
$l_formatofnumbers['x'] = '46';
$l_formatofnumbers['y'] = '46';

foreach (explode(PHP_EOL, $l_content) as $l_row) {
    $l_parts = array();
    if (preg_match("/^%MOIN*%/", $l_row, $l_parts)) {
        $GLOBALS['ratio'] = 2.54;
    } elseif (preg_match("/^%MOMM*%/", $l_row, $l_parts)) {
        $GLOBALS['ratio'] = 1;
    } elseif (preg_match("/^%FSLAX([0-9]+)Y([0-9]+)\\*%/", $l_row, $l_parts)) {
        $l_formatofnumbers['x'] = $l_parts[1][1];
        $l_formatofnumbers['y'] = $l_parts[2][1];
    } elseif (preg_match("/^%ADD([0-9]+)C,(.+)\\*%/", $l_row, $l_parts)) {
        $GLOBALS['tools'][$l_parts[1]] = array(
            'description' => $l_parts[2]
        );
    } elseif (preg_match("/^D([0-9]+)\\*$/", $l_row, $l_parts)) {
        $GLOBALS['currenttool'] = $l_parts[1];
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
        $l_parts[1] = floatval(substr_replace($l_parts[1], ".", - $l_formatofnumbers['x'], 0));
        $l_parts[2] = floatval(substr_replace($l_parts[2], ".", - $l_formatofnumbers['y'], 0));
        $l_parts[4] = floatval(substr_replace($l_parts[4], ".", - $l_formatofnumbers['x'], 0));
        $l_parts[5] = floatval(substr_replace($l_parts[5], ".", - $l_formatofnumbers['y'], 0));

        if ($l_com == 'G00') {
            zsafe();
        } else {
            zdrill();
        }
        command($l_com, 'X' . $l_parts[1] . 'Y' . $l_parts[2] . (($l_parts[3]) ? 'I' . $l_parts[4] . 'J' . $l_parts[5] : ''));
    }
}

save($GLOBALS['argv'][1]);
