<?php

include __DIR__ .'/../../src/lib_php5.4.php';

ini_set('date.timezone', 'UTC');
ini_set('mbstring.internal_encoding', 'UTF-8');

$errors   = [];
$messages = [];

$config = [
    'defaultFrom' => 'mymail@example.org',
    'onError'     => function($error) use (&$errors) { $errors[] = $error; },
    'afterSend'   => function($text) use (&$messages) { $messages[] = $text; },
    'transports'  => [
        'file' => ['file', 'dir'  => 'mails'],
        'smtp' => ['smtp', 'host' => 'smtp.yandex.ru', 'ssl' => 'true', 'port' => '465', 'login' => '****@yandex.ru', 'password' => '******'],
    ],
];

Mailer()->init($config);

$message = Mailer()->newHtmlMessage();

$message->setSubject("Тема сообщения");
$message->addRecipient("Получатель@почта.ру");
$message->setContent("Привет, мир!");

Mailer()->sendMessage($message);

var_dump($errors, $messages);