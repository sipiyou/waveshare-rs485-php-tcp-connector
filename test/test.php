<?php

require '../class/classWaveshare.php';

$host = '192.168.1.200'; // Replace with your waveshare's IP or hostname
$port = 4196;            // Replace with your waveshare's port

$waveshareClient = new WaveshareClient ('logMe', $host, $port, 2);

if ($waveshareClient->connect()) {
    while (1) {

        // read data as binary array:
        $binArray = $waveshareClient->readDataAsBinaryArray();

        if ($binArray !== null) {
            echo $waveshareClient->hexDump ($binArray)."\n";
        }

        // read data as string:
        /*
        $string = $waveshareClient->readDataAsString();
        if ($string !== null)
            echo $string;
        */
    }
} else {
    echo "cannot connect to waveshare";
}

function logMe(string $message, string $logFile = null) {
    echo $message."\n";
}

?>
