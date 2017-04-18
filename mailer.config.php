<?php

return [
    'defaultFrom' => 'mymail@example.org',
//  'onError'     => function($error, $message, $transport) { myErrorLog($error); },
//  'afterSend'   => function($text, $message, $layer) { myMailLog($text); },
    'transports'  => [
        ['file', 'dir'  => __DIR__ .'/mails'],
//      ['smtp', 'host' => 'smtp.yandex.ru', 'ssl' => true, 'port' => '465', 'login' => '****@yandex.ru', 'password' => '******'],
//      ['smtp', 'host' => 'smtp.gmail.com', 'ssl' => true, 'port' => '465', 'login' => '****@gmail.com', 'password' => '******'],
//      ['smtp', 'host' => '192.168.0.1', 'timeout' => 30],
    ],
];