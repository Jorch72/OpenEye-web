<?php

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('Etc/UTC');
function t_str($v) {
    return '"' . $v . '"';
}

function t_int($v) {
    return $v;
}

function t_date($v) {
    return date("Y-m-d\TH:i:si\Z", $v);
}

$columns = array("id" => "t_str",
    "timesSeen" => "t_int", "firstSeen" => "t_date", "lastSeen" => "t_date",
    "timesInstall" => "t_int", "firstInstall" => "t_date", "lastInstall" => "t_date",
    "timesUninstall" => "t_int", "firstUninstall" => "t_date", "lastUninstall" => "t_date");

function print_as($data, $result_type) {
    global $columns;
    if ($result_type == "txt") {
        header('Content-Type: text/plain');
        foreach ($columns as $column_name => $column_convert) {
            if (isset($data[$column_name])) {
                print $column_name . ":" . $column_convert($data[$column_name]) . "\n";
            }
        }
    } elseif ($result_type == "csv") {
        header('Content-Type: text/csv');
        print join(",", array_keys($columns)) . "\n";
        $result = array();
        foreach ($columns as $column_name => $column_convert) {
            if (isset($data[$column_name])) {
                $result[] = $column_convert($data[$column_name]);
            } else {
                $result[] = "";
            }
        }
        print join(",", $result) . "\n";
    } elseif ($result_type == "json") {
        header('Content-Type: application/json');
        print json_encode($data, JSON_PRETTY_PRINT);
    } else {
        header("HTTP/1.1 500 We don't do THAT!!!");
    }
}

$result_type= $_GET['t'] ?: "txt";

if (isset($_GET['f'])) {
    $hash = $_GET['f'];
    $redis = new \Predis\Client();

    $result = Array();
    $keys = $redis->keys("file_stats:{$hash}");
    if (!$keys) {
        header("HTTP/1.1 404 LOL NOPE");
    } else {
        $key = $keys[0];
        $result = $redis->hgetall($key);
        $result['id'] = substr($key, 11);
        print_as($result, $result_type);
    }
} else {
    header("HTTP/1.1 500 You get nothing!");
}

?>
