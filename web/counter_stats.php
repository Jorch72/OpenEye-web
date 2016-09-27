<?php

require_once __DIR__ . '/../vendor/autoload.php';

function start_header($result_type) {
    switch ($result_type) {
        case "txt":
            header('Content-Type: text/plain');
         return true;
        case "csv":
            header('Content-Type: text/csv');
            return true;
        case "json":
            header('Content-Type: application/json');
            return true;
        default:
            header("HTTP/1.1 500 We don't do THAT!!!");
            return false;
    }
}

function print_map($tab_data, $result_type) {
    switch ($result_type) {
        case "txt":
            foreach ($tab_data as $k => $v) {
               print "$k\t$v\n";
            }
            break;
        case "csv":
            foreach ($tab_data as $k => $v) {
                print "\"$k\",$v\r\n";
            }
            break;
        case "json":
            print json_encode($tab_data, JSON_PRETTY_PRINT);
            break;
        default:
            break;
    }
}

function print_table($tab_data, $result_type) {
    switch ($result_type) {
        case "txt":
            foreach ($tab_data as $v) {
               print "$v\n";
            }
            break;
        case "csv":
            foreach ($tab_data as $v) {
                print "\"$v\"\r\n";
            }
            break;
        case "json":
            print json_encode($tab_data, JSON_PRETTY_PRINT);
            break;
        default:
            break;
    }
}

function select_sort(&$tab_data, $sort_type) {
    switch ($sort_type) {
        case "kd":
        case "key_desc":
            krsort($tab_data);
            break;
        case "ka":
        case "key_asc":
            ksort($tab_data);
            break;
        case "vd":
        case "value_desc":
            arsort($tab_data);
            break;
        case "va":
        case "value_asc":
            asort($tab_data);
            break;
        default:
            break;
    }
}

$dimensions = array (
  'dates' => 1,
  'counters' => 2
);

$result_type = $_GET['t'] ?: "txt";

if (isset($_GET['d'])) {
    $dimension = $_GET['d'];
    if (!isset($dimensions[$dimension])) {
        header("HTTP/1.1 500 Unknown dimension");
    } else {
        if (start_header($result_type)) {
            header("Access-Control-Allow-Origin: *");
            $redis = new \Predis\Client();
            $dimension_id = $dimensions[$dimension];
            $keys = $redis->keys("counters:*");

            $result = array();
            foreach ($keys as $key) {
                preg_match("/^counters:(.+):(.+)$/", $key, $matches);
                array_push($result, $matches[$dimension_id]);
            }
            $result = array_values(array_unique($result));
            sort($result);
            print_table($result, $result_type);
        }
    }
} else if (isset($_GET['p']) && isset($_GET['k'])) {
    $sort_type = isset($_GET['s']) ? $_GET['s'] : "";
    $period = $_GET['p'];
    $key = $_GET['k'];
    if (start_header($result_type)) {
        header("Access-Control-Allow-Origin: *");
        $redis = new \Predis\Client();
        $key = "counters:$period:$key";
        $values = $redis->hgetall($key);
        select_sort($values, $sort_type);
        print_map($values, $result_type);
    }
} else {
    header("HTTP/1.1 500 You get nothing!");
}

?>
