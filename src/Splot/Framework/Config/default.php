<?php

return array(

    'timezone' => 'Europe/London',

    'log_file' => false,
    'log_threshold' => 'debug',

    'debugger' => array(
        'enabled' => true
    ),

    'cache' => array(
        'enabled' => true,
        'stores' => array(),
        'caches' => array()
    ),

    'router' => array(
        'host' => 'localhost',
        'protocol' => 'http://',
        'port' => 80,
        'use_request' => true
    )

);