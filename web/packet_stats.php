<?php

require_once __DIR__ . '/../vendor/autoload.php';

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
        header('Content-Type: text/plain');
        $values = $redis->hgetall($key);
        ksort($values);
        foreach ($values as $k => $v) {
            print "$k\t$v\n";
        }

    }
}

?>
