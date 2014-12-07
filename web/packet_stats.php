<?php

require_once __DIR__ . '/../vendor/autoload.php';

$result_type= $_GET['t'] ?: "txt";

if (!isset($_GET['p'])) {
    header("HTTP/1.1 500 You get nothing!");
} else {
    $period = $_GET['p'];
    $redis = new \Predis\Client();

    $key = "packets:$period";
    $type = $redis->type($key);
    if ($type != "hash") {
        header("HTTP/1.1 500 LOL NOPE");
    } else {
        $values = $redis->hgetall($key);
        ksort($values);

        if ($result_type == "txt") {
            header('Content-Type: text/plain');
            foreach ($values as $k => $v) {
                print "$k\t$v\n";
            }
        } elseif ($result_type == "csv") {
            header('Content-Type: text/csv');
            foreach ($values as $k => $v) {
                print "\"$k\",$v\r\n";
            }
        } elseif ($result_type == "json") {
            header('Content-Type: application/json');
            print json_encode($values, JSON_PRETTY_PRINT);
        } else {
            header("HTTP/1.1 500 We don't do THAT!!!");
        }

    }
}

?>
