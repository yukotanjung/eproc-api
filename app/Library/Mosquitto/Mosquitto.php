<?php

namespace App\Library\Mosquitto;

use PhpMqtt\Client\ConnectionSettings;

use PhpMqtt\Client\MqttClient;

class Mosquitto

{

    static function publish($topic = "topic", $message = '', $client_id = "mqtt-client")

    {

        $server   = config('mqtt.host');

        $port     = config('mqtt.port');

        $username = config('mqtt.username');

        $password = config('mqtt.password');

        $mqtt = new MqttClient($server, $port, $client_id);

        $connectionSettings = (new ConnectionSettings)

            ->setUsername($username)

            ->setPassword($password);



        $mqtt->connect($connectionSettings, true);

        $mqtt->publish($topic, $message, 2, true);

        $mqtt->loop(true, true);

        $mqtt->disconnect();

    }

    static function retainPublish($topic = "topic", $message = '', $client_id = "mqtt-client")

    {

        $server   = config('mqtt.host');

        $port     = config('mqtt.port');

        $username = config('mqtt.username');

        $password = config('mqtt.password');

        $mqtt = new MqttClient($server, $port, $client_id);

        $connectionSettings = (new ConnectionSettings)

            ->setUsername($username)

            ->setPassword($password);



        $mqtt->connect($connectionSettings, true);

        $mqtt->publish($topic, $message, 2, true);

        $mqtt->loop(true, true);

        $mqtt->disconnect();

    }

    static function subscribe($topic = "topic", $callback, $client_id = "mqtt-client")

    {

        $server   = config('mqtt.host');

        $port     = config('mqtt.port');

        $username = config('mqtt.username');

        $password = config('mqtt.password');

        $mqtt = new MqttClient($server, $port, $client_id);

        $connectionSettings = (new ConnectionSettings)

            ->setUsername($username)

            ->setPassword($password);

        $mqtt->connect($connectionSettings, true);

        $mqtt->subscribe($topic, function ($topic, $message) use ($callback) {

            call_user_func($callback, $topic, $message);

        }, 2);

        $mqtt->loop(true, true);

        $mqtt->disconnect();

    }

}
