<?php

namespace OpenData\Controllers;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use JsonSchema\Uri\UriRetriever;
use JsonSchema\Validator;
use OpenData\PacketHandlers\IPacketHandler;

class ApiController {

    private static $FLOOD_LIMIT = 100; // during dev

    protected $memcache;

    private $schemas = array();

    /**
     *
     * @var type IPacketHandler[]
     */
    private $packetHandlers = array();

    public function __construct($memcache) {
        $this->memcache = $memcache;
    }

    public function registerPacketHandler(IPacketHandler $handler) {

        $this->packetHandlers[$handler->getPacketType()] = $handler;

        $schema = $handler->getJsonSchema();

        if (!isset($this->schemas[$schema])) {
            $retriever = new UriRetriever();
            $this->schemas[$schema] = $retriever->retrieve('file://' . __DIR__ . '/../Schemas/'.$schema);
        }
    }

    private function stat_increment($redis, $today, $key, $amount = 1) {
        $redis->hincrby("packets:$today", $key, $amount);
        $redis->hincrby("packets:total", $key, $amount);
    }

    private function stat_increment_sub($redis, $today, $category, $key, $amount = 1) {
        $redis->hincrby("packets:$today-$category", $key, $amount);
        $redis->hincrby("packets:total-$category", $key, $amount);
    }

    public function crash(Request $request) {

        try {

            //if ($this->isUserFlooding($request)) {
            //    throw new \Exception('Flood protection - too many reports');
            //}

            $content = $request->get('api_request');

            if ($content == null) {
                throw new \Exception('No content received');
            }

            $data = json_decode(mb_convert_encoding($content, 'UTF-8', 'auto'), true);

            $handler = $this->packetHandlers['crashlog'];

            $data['type'] = 'crashlog';

            $errors = $this->getErrors($data, $handler->getJsonSchema());

            unset($data['type']);

            if ($errors != null) {
                throw new \Exception(implode("\n", $errors));
            }

            return new JsonResponse($handler->execute($data));

        } catch (\Exception $e) {
            return new JsonResponse(array(array(
                'type' => 'error',
                'reportType' => 'crashlog',
                'debug' => array(
                    'statusCode'    => $e->getCode(),
                    'message'       => $e->getMessage(),
                    'stacktrace'    => $e->getTraceAsString()
                )
            )));
        }
    }

    public function error_response($excuse)
    {
        return new JsonResponse(array(array('type' => 'error', 'msg' => $excuse)));
    }

    public function main(Request $request) {

        $content = $request->get('api_request', '[]');

        $redis = new \Predis\Client();
        $today = @date("Y-m-d");

        $raw_size = strlen($content);
        $compressed_size = $request->get('raw_size', $raw_size);

        if ($raw_size == 0) {
            $this->stat_increment($redis, $today, "msg_empty:decompressed");
        }

        if ($compressed_size == 0) {
            $this->stat_increment($redis, $today, "msg_empty:compressed");
        }

        $this->stat_increment($redis, $today, "requests");
        $this->stat_increment($redis, $today, "bytes:decompressed", $raw_size);
        $this->stat_increment($redis, $today, "bytes:compressed", $compressed_size);
        $this->stat_increment_sub($redis, $today, "compressed", sprintf('size:%06d', $compressed_size / 1024));
        $this->stat_increment_sub($redis, $today, "decompressed", sprintf('size:%06d', $raw_size / 1024));
        //if ($this->isUserFlooding($request)) {
        //    return new JsonResponse(array());
        //}

        if ($raw_size > 1024 * 1024) {
            if ($raw_size > 20 * 1024 * 1024) {
                $this->stat_increment($redis, $today, "discarded_big_messages");
                error_log("Too much, man, too much! aborting");
                return $this->error_response("I just can't your data");
            }
        }

        try {
            $data = json_decode(mb_convert_encoding($content, 'UTF-8', 'auto'), true);
        } catch (\Exception $e) {
            $this->stat_increment($redis, $today, "error:json_parse");
            error_log("Failed to parse JSON ");
            return $this->error_response('Failed to parse JSON');
        }

        if (!is_array($data)) {
            return new JsonResponse(array());
        }

        $responses = array();

        $index = 0;

        foreach ($data as $packet) {

            $type = isset($packet['type']) && is_string($packet['type']) ?
                    $packet['type'] : null;

            try {
                if ($type == null) {
                    throw new \Exception('Packet type not defined');
                }
                $this->stat_increment($redis, $today, "req:$type");

                $response = null;

                if (!isset($this->packetHandlers[$type])) {
                    throw new \Exception('Invalid packet type '.$type);
                }

                $handler = $this->packetHandlers[$type];

                $errors = $this->getErrors($packet, $handler->getJsonSchema());

                unset($packet['type']);

                if ($errors != null) {
                    throw new \Exception(implode("\n", $errors));
                }

                if ($response = $handler->execute($packet)) {
                    foreach ($response as $r) {
                        $this->stat_increment($redis, $today, "resp:" . $r['type']);
                    }
                    $responses = array_merge($responses, $response);
                }

            } catch (\Exception $e) {
                error_log("Exception while handling packet type '$type': " . $e);
                $this->stat_increment($redis, $today, "error");
                if ($type) {
                    $this->stat_increment($redis, $today, "error:$type");
                }
                $responses = array_merge($responses, array(array(
                    'type' => 'error',
                    'reportType' => $type == null ? 'unknown' : $type,
                    'reportIndex' => $index,
                    'debug' => array(
                        'statusCode' => $e->getCode(),
                        'message' => $e->getMessage(),
                        'stacktrace' => $e->getTraceAsString()
                    )
                )));
            }

            $index++;

            if ($index > 2048) {
                error_log("Too much entries in packet: " .  count($data));
                break;
            }
        }

        return new JsonResponse($responses);
    }

    private function isUserFlooding(Request $request) {

        if ($this->memcache == null)
            return false;

        $key = sha1($request->getClientIp() . date('Y-m-d-H'));
        $requestCount = $this->memcache->get($key);

        if ($requestCount) {
            if ($requestCount > self::$FLOOD_LIMIT) {
                return true;
            }
            $this->memcache->replace($key, $requestCount + 1, 0, 3600);
        } else {
            $this->memcache->set($key, 1, 0, 3600);
        }

        return false;
    }

    private function getErrors($packet, $schema) {

        // real nasty, but the json validator requires we pass in as an stdClass.
        // so we'll recode it as a class.
        $packet = json_decode(json_encode($packet), false);

        $validator = new Validator();
        $validator->check($packet, $this->schemas[$schema]);

        if (!$validator->isValid()) {
            $errors = array();
            foreach ($validator->getErrors() as $error) {
                $errors[] = sprintf("[%s] %s\n", $error['property'], $error['message']);
            }
            return $errors;
        }

        return null;
    }

}
