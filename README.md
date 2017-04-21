# ApMailer

Библиотека для отправки писем через SMTP. Основная идея данного приложения - простое подключение и быстрое использование функции отправки писем как из консоли, так и из PHP-кода.

Для работы требуется **PHP 5.6+** с включенным модулем **mbstring**.

Пример письма:

![Mail example](/examples/screenshots/mail-1.png)

Если во время отправки письма возникнут какие-то ошибки, их можно отловить с помощью хуков без использваония `try ... catch`. И получить вот такие сообщения:

![Messages](/examples/screenshots/messages.png)

Для более подробной информации обратитесь к примеру с [веб-формой](/examples/webform/).

## Использование в PHP

Подключите библиотеку в своём проекте, проинициализируйти её, и можно отправлять письма:
```` php
<?php
include 'phar:///path/to/mailer.phar/lib.php';

Mailer()->init(include '/path/to/mailer.config.php');

$message = Mailer()->newTextMessage();
$message->setContent('Hello world!')
    ->setSubject('Mail test')
    ->addRecipient('myemail@example.org');
    
if (Mailer()->sendMessage($message)) {
    echo 'Сообщение успешно отправлено.';
} else {
    echo 'Во время отправки возникли какие-то ошибки, проверьте логи для большей информации.';
}
````

## Использование в консоли

```` shell
$ ./mailer.phar --help
Отправка писем через консоль, используя SMTP.

Использование: mailer.phar [ПАРАМЕТРЫ]
ПАРАМЕТРЫ
    -t, --text            Текст сообщения
    -s, --subject         Тема сообщения
    -r, --recipient       Получатель письма
    -f, --from            Отправитель письма
    -c, --config          Путь к конфигу с настройками почтовика
    -a, --attach          Вложить файл в письмо как Attachment
    -i, --related         Включить файл в письмо как Related (индентификатором при этом будет название файла)
    --html                Отправить письмо как HTML
    -d, --display-eml     Вывести содержимое письма в формате EML
    --config-example      Показать пример конфига
    -h, --help            Показать справочную информацию
    -v, --version         Показать версию приложения
    
Порядок обработки конфигов:
    1. Файл mailer.config.php, который находится в директории с mailer.phar
    2. Файл mailer.config.php, который находится в текущей рабочей директории
    3. Файлы, которые указаны через -c и --config
````

### Примеры использования

Отправить пустое письмо без темы:
```` shell
$ ./mailer.phar -r yourmail@example.com
````

Отправить письмо с темой и данными из `stdin`:
```` shell
$ uname -a | ./mailer.phar -r yourmail@example.com -s 'My system' -t- 
````

Отправить письмо как HTML-шаблон:
```` shell
$ uname -a | ./mailer.phar -r yourmail@example.com -s 'My system' --html \
  -t '<html><body><pre><code>' \
  -t - \
  -t '</code></pre>' \
  -t "<hr> `date`" \
  -t '</body></html>'
````

Отправить письмо с вложениями:
```` shell
$ uname -a | ./mailer.phar -r yourmail@example.com -s 'My system' -t- -a file1.dat -a file2.dat
````

Отправить письмо в шаблоне с картинками:
```` shell
$ uname -a | ./mailer.phar -r yourmail@example.com -s 'My system' --html \
  -t '<html><body><pre><code>' \
  -t - \
  -t '</code></pre>' \
  -t '<img src="cid:myimg.png">' -i ./myimg.png \
  -t '</body></html>'
````

Не отправлять письмо, а вывести его в `stdout`:
```` shell
$ uname -a | ./mailer.phar -r yourmail@example.com -s 'My system' --html \
  -t '<html><body><pre><code>' \
  -t - \
  -t '</code></pre>' \
  -t '<img src="cid:myimg.png">' -i ./myimg.png \
  -t '</body></html>' \
  -d
````

Пример конфига:
````shell
$ ./mailer.phar --config-example
Пример конфигурационного файла для почтовика.

<?php

return [
    'defaultFrom' => 'mymail@example.org',
    'onError'     => function($error, $message, $transport) { echo $error; },
    'afterSend'   => function($text, $message, $layer) { echo $text; },
    'transports'  => [
        // Сохранение всех писем в папке
        ['file', 'dir'  => __DIR__ .'/mails'],
        
        // Отправка писем через Yandex, используя SSL и авторизацию
        ['smtp', 'host' => 'smtp.yandex.ru', 'ssl' => true, 'port' => '465', 'login' => '****@yandex.ru', 'password' => '******'],
    ],
];
````
