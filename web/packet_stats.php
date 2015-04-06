<?php

require_once __DIR__ . '/../vendor/autoload.php';

function print_as($tab_data, $result_type) {
    if ($result_type == "txt") {
        header('Content-Type: text/plain');
        foreach ($tab_data as $k => $v) {
            print "$k\t$v\n";
        }
    } elseif ($result_type == "csv") {
        header('Content-Type: text/csv');
        foreach ($tab_data as $k => $v) {
            print "\"$k\",$v\r\n";
        }
    } elseif ($result_type == "json") {
        header('Content-Type: application/json');
        print json_encode($tab_data, JSON_PRETTY_PRINT);
    } else {
        header("HTTP/1.1 500 We don't do THAT!!!");
    }
}

$result_type= $_GET['t'] ?: "txt";

if (isset($_GET['p'])) {
    $period = $_GET['p'];
    $redis = new \Predis\Client();

    $key = "packets:$period";
    $type = $redis->type($key);
    if ($type != "hash") {
        header("HTTP/1.1 404 LOL NOPE");
    } else {
        $values = $redis->hgetall($key);
        ksort($values);
        print_as($values, $result_type);
    }
} elseif (isset($_GET['k'])) {
    $property = $_GET['k'];
    $redis = new \Predis\Client();
    
    $result = Array();
    $keys = $redis->keys('packets:????-??-??');
    sort($keys);
    foreach ($keys as $key) {
        $result[substr($key, 8)] = $redis->hget($key, $property);
    }
    print_as($result, $result_type);
} else {
    header("HTTP/1.1 500 You get nothing!");
}

?>
