<?php

include_once __DIR__ .'/lib.php';

// Параметры
$params = [
//  [short, long             , value, description],
    ['-t', '--text'          , true , 'Текст сообщения'],
    ['-s', '--subject'       , true , 'Тема сообщения'],
    ['-r', '--recipient'     , true , 'Получатель письма'],
    ['-f', '--from'          , true , 'Отправитель письма'],
    [''  , '--reply-to'      , true , 'Адрес для ответа на письмо'],
    ['-c', '--config'        , true , 'Путь к конфигу с настройками почтовика'],
    ['-a', '--attach'        , true , 'Вложить файл в письмо как Attachment'],
    ['-i', '--related'       , true , 'Включить файл в письмо как Related (индентификатором при этом будет название файла)'],
    [''  , '--html'          , false, 'Отправить письмо как HTML'],
    ['-d', '--display-eml'   , false, 'Вывести содержимое письма в формате EML'],
    [''  , '--config-example', false, 'Показать пример конфига'],
    ['-h', '--help'          , false, 'Показать справочную информацию'],
    ['-v', '--version'       , false, 'Показать версию приложения'],
    [''  , '--debug'         , false, 'Выводить отладочную информацию'],
];

$assemblyPath = get_included_files()[0];
$assemblyDir  = dirname(get_included_files()[0]);
$assemblyFile = basename($assemblyPath);
$configName   = 'mailer.config.php';

$help = function() use ($params) {
    echo "Отправка писем через консоль, используя SMTP.\n";
    echo "\n";
    echo "Использование: {$GLOBALS['assemblyFile']} [ПАРАМЕТРЫ]\n";
    echo "ПАРАМЕТРЫ\n";
    
    foreach ($params as list($short, $long, $value, $description)) {
        printf("    %-20s  %s\n",
            $short ? "$short, $long" : $long,
            $description
        );
    }
    
    echo "\n";
    echo "Порядок обработки конфигов:\n";
    echo "    1. Файл {$GLOBALS['configName']}, который находится в директории с {$GLOBALS['assemblyFile']}\n";
    echo "    2. Файл {$GLOBALS['configName']}, который находится в текущей рабочей директории\n";
    echo "    3. Файлы, которые указаны через -c и --config\n";
    echo "\n";
    echo "Версия: v". Mailer()->version() ." ($GLOBALS[buildDate])\n";
    echo " Адрес: https://github.com/anton-pribora/ApMailer\n";
    
    exit;
};

$displayVersion = function() {
    echo "v". Mailer()->version() ." ($GLOBALS[buildDate])\n";
    exit;
};

$configExample = function() {
    echo <<<'EXAMPLE'
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

EXAMPLE
;
    exit;
};

$parseOpts = function() use ($params) {
    $shortopts = [];
    $longopts  = [];
    
    foreach ($params as list($short, $long, $value, $description)) {
        if ($short) {
            $shortopts[] = trim($short, '-') . ($value ? ':' : '');
        }
        
        if ($long) {
            $longopts[] = trim($long, '-') . ($value ? ':' : '');
        }
    }
    
    return getopt(join($shortopts), $longopts);
};

$opts = $parseOpts();

$param = function(...$names) use ($opts) {
    $values = [];
    
    foreach ($names as $paramName) {
        if (isset($opts[$paramName])) {
            $values = array_merge($values, (array) $opts[$paramName]);
        }
    }
    
    return $values;
};

$hasParam = function(...$names) use ($opts) {
    foreach ($names as $paramName) {
        if (isset($opts[$paramName])) {
            return true;
        }
    }
    
    return false;
};

$error = function ($message) {
    file_put_contents('php://stderr', $message . PHP_EOL);
};

$warning = function ($message) {
    file_put_contents('php://stderr', $message . PHP_EOL);
};

$output = function ($message) {
    file_put_contents('php://stdout', $message . PHP_EOL);
};

if ($hasParam('debug')) {
    $debug = function ($message) {
        echo "[DEBUG] $message\n";
    };
} else {
    $debug = function () {};
}

if ($hasParam('h', 'help')) {
    $help();
}

if ($hasParam('config-example')) {
    $configExample();
}

if ($hasParam('v', 'version')) {
    $displayVersion();
}

if ($hasParam('html')) {
    $message = Mailer()->newHtmlMessage();
} else {
    $message = Mailer()->newTextMessage();
}

foreach ($param('t', 'text') as $text) {
    if ($text == '-') {
        $message->addContent(file_get_contents('php://stdin'));
    } else {
        $message->addContent($text);
    }
}

foreach ($param('s', 'subject') as $subject) {
    $message->setSubject($subject);
}

foreach ($param('r', 'recipient') as $recipient) {
    $message->addRecipient($recipient);
}

foreach ($param('f', 'from') as $from) {
    $message->setSenderEmail($from);
}

foreach ($param('reply-to') as $replyTo) {
    $message->addReplyTo($replyTo);
}

foreach ($param('a', 'attach') as $attchment) {
    $realpath = realpath($attchment);
    
    if ($realpath) {
        $debug("Вложение файла $attchment -> $realpath");
        $message->addAttachmentFile($realpath);
    } else {
        $warning("Не удалось найти файл $attchment");
    }
}

foreach ($param('i', 'related') as $relation) {
    $realpath = realpath($relation);
    
    if ($realpath) {
        $debug("Включение файла $relation -> $realpath");
        $message->addRelatedFile($realpath);
    } else {
        $warning("Не удалось найти файл $relation");
    }
}

if ($hasParam('d', 'display-eml')) {
    echo $message;
    exit;
}

$config = [];

$configFiles = array_merge([
    "$assemblyDir/mailer.config.php",
    "./mailer.config.php",
], $param('c', 'config'));

foreach ($configFiles as $file) {
    $debug("Ищем конфиг $file");
    $realpath = realpath($file);
    
    if ($realpath) {
        $debug("Загрузка конфига $realpath");
        $result = include $realpath;
        
        if (is_array($result)) {
            $config = array_replace_recursive($config, $result);
        }
    }
}

if (empty($config['transports'])) {
    $error('В конфиге нет информации о том, как доставлять сообщения. Заполните секцию "transports" и попробуйте снова. Используйте --help для справки.');
    exit(2);
}

Mailer()->init($config);

if (Mailer()->sendMessage($message)) {
    $output("Сообщение {$message->getId()} успешно отправлено");
} else {
    $warning("Сообщение {$message->getId()} отправлено с ошибками: ". join('; ', Mailer()->lastErrors()));
}