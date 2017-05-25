<?php
/**
 * @license MIT
 * @author Антон Прибора (https://anton-pribora.ru/feedback/)
 */
namespace {
    if (!function_exists('Mailer')) {
        /**
         * @return \ApMailer\Mailer
         */
        function Mailer() {
            static $mailer;
            
            if (is_null($mailer)) {
                $mailer = new ApMailer\Mailer();
            }
            
            return $mailer;
        };
    }
}

namespace ApMailer {
    
    class Mailer
    {
        private $version = '1.0.1';
        
        private $layer;
        
        public function __construct()
        {
            $this->layer = new Layer();
        }
        
        /**
         * @example 
         * $config = [
         *     'defaultFrom' => 'mymail@example.org',
         * //  'onError'     => function($error, $message, $transport) { myErrorLog($error); },
         * //  'afterSend'   => function($text, $message, $layer) { myMailLog($text); },
         *     'transports'  => [
         *         ['file', 'dir'  => __DIR__ .'/mails'],
         * //      ['smtp', 'host' => 'smtp.yandex.ru', 'ssl' => true, 'port' => '465', 'login' => '****@yandex.ru', 'password' => '******'],
         * //      ['smtp', 'host' => 'smtp.gmail.com', 'ssl' => true, 'port' => '465', 'login' => '****@gmail.com', 'password' => '******'],
         * //      ['smtp', 'host' => '192.168.0.1', 'timeout' => 30],
         *     ],
         * ];
         * @param array $config
         */
        public function init(array $config = [])
        {
            $conf  = function($name, $default = null) use (&$config) { return isset($config[$name]) ? $config[$name] : $default;};
            $layer = new Layer();
            
            $defaultFrom = $conf('defaultFrom');
            $onError     = $conf('onError', function($error) { trigger_error($error, \E_USER_WARNING); });
            $afterSend   = $conf('afterSend');
            $transports  = $conf('transports', []);
            
            // Обратный адрес по умолчанию
            if ($defaultFrom) {
                $layer->appendTrigger('beforeSend', function(Message $message) use ($defaultFrom){
                    if (!$message->getSenderEmail()) {
                        $message->setSenderEmail($defaultFrom);
                    }
                });
            }
            
            if ($onError) {
                $layer->appendTrigger('error', function(Message $message, TransportInterface $transport) use ($onError){
                    $error = sprintf('Cообщение %s для %s не было доставлено через %s. Ошибка: %s',
                        $message->getId(),
                        $message->getRecipients(', '),
                        $transport->name(),
                        $transport->getLastError()
                    );
                    
                    call_user_func($onError, $error, $message, $transport);
                });
            }
            
            if ($afterSend) {
                $layer->appendTrigger('afterSend', function(Message $message, Layer $layer) use ($afterSend) {
                    $text = sprintf('Отправлено сообщение %s для %s от %s.%s',
                        $message->getId(),
                        $message->getRecipients(', '),
                        $message->getSenderEmail(),
                        $layer->getErrors() ? ' Ошибки: ' . join(', ', $layer->getErrors()) : ''
                    );
                    
                    call_user_func($afterSend, $text, $message, $layer);
                });
            }
            
            foreach ($transports as $options) {
                $options = (array) $options;
                $type    = array_shift($options);
                
                $layer->appendTranspport(
                    $this->newTransport($type, $options)
                );
            }
            
            $this->layer = $layer;
        }
        
        public function newTransport($type, $config = [])
        {
            $class = __NAMESPACE__ .'\\'. ucfirst(strtolower($type));
            return new $class($config);
        }
        
        public function addTransport(TransportInterface $transport)
        {
            $this->layer->appendTranspport($transport);
            return $this;
        }
        
        public function newHtmlMessage()
        {
            return new Message();
        }
        
        public function newTextMessage()
        {
            return $this->newHtmlMessage()->setContentTypeTextPlain();
        }
        
        public function sendMessage(Message $message)
        {
            return $this->layer->send($message);
        }
        
        public function lastErrors()
        {
            return $this->layer->getErrors();
        }
        
        public function version()
        {
            return $this->version;
        }
    }
    
    /**
     * Организует отправку почты через несколько транспортов.
     * 
     * @example
     * Пример использования:
     * 
     * $layer     = new Layer();
     * $transport = new Transport\PhpMail;
     * 
     * $layer->appendTransport($transport);
     * 
     * // Обратный адрес по умолчанию
     * $layer->appendTrigger('beforeSend', function(Message $message){
     *     if ( !$message->getSenderEmail() ) {
     *         $message->setSenderEmail('default@email');
     *     }
     * });
     *
     * // Логирование ошибок
     * $layer->appendTrigger('error', function(Message $message, TransportInterface $transport){
     *     log('Cообщение %s для %s не было доставлено. Ошибка: %s',
     *         $message->getId(),
     *         $message->getRecipients(', '),
     *         $transport->getLastError()
     *     );
     * });
     *
     * // Логирование отправленных сообщений
     * $layer->appendTrigger('afterSend', function(Message $message){
     *     log('Отправлено сообщение %s для %s от %s.',
     *         $message->getId(),
     *         $message->getRecipients(', '),
     *         $message->senderEmail()
     *     );
     * });
     * 
     * $message = new Message;
     * $messate->addRecipient('foo@bar');
     * $message->setContent('Привет, мир!');
     * 
     * if ( !$layer->send($message) ) {
     *     var_dump($layer->getErrors());
     * }
     * 
     */
    class Layer
    {
        use Triggers;
        
        private $transports = [];
        private $errors     = [];
        
        public function appendTranspport(TransportInterface $transport, callable $filter = null)
        {
            $this->transports[] = [$transport, $filter];
            return $this;
        }
        
        public function send(Message $message)
        {
            $this->errors = [];
            
            $this->launchTriggers('beforeSend', $message, $this);

            /* @var $transport TransportInterface */
            foreach ($this->transports as list($transport, $filter)) {
                if ($filter) {
                    if (!$filter($message)) {
                        continue;
                    }
                }
                
                if (!$transport->send($message)) {
                    $this->launchTriggers('error', $message, $transport, $this);
                    $this->errors[] = $transport->name() .': '. $transport->getLastError();
                }
            }
            
            $this->launchTriggers('afterSend', $message, $this);
            
            return empty($this->errors);
        }
        
        public function getErrors()
        {
            return $this->errors;
        }
    }
    
    trait Triggers
    {
        private $triggers = [];
        
        public function appendTrigger($category, callable $function)
        {
            if (!isset($this->triggers[$category])) {
                $this->triggers[$category] = [];
            }
            
            $this->triggers[$category][] = $function;
            
            return $this;
        }
        
        private function launchTriggers($category, ...$args)
        {
            if (isset($this->triggers[$category])) {
                foreach ( $this->triggers[$category] as $function ) {
                    $function(...$args);
                }
            }
        }
    }
    
    interface TransportInterface
    {
        /**
         * Название транспорта.
         *
         * @return string
         */
        public function name();

        /**
         * Отправление сообщения.
         *
         * @param \ApMailer\Message $message
         * @return bool
         */
        public function send(Message $message);

        /**
         * Последняя ошибка.
         * @return string
         */
        public function getLastError();
    }
    
    class Exception extends \Exception
    {
    }
    
    function lastPhpErrorMessage($default = '')
    {
        $error = error_get_last();
        return isset($error['message']) ? $error['message'] : $default;
    }
    
    class Smtp implements TransportInterface
    {
        private $login    = null;
        private $password = null;
        private $host     = null;
        private $port     = 25;
        private $smtpAuth = true;
        private $timeout  = 10;
        private $useSSL   = false;
        
        private $lastError     = null;
        private $lastErrorCode = null;
        
        private $socket = null;
        
        public function __construct(array $options = null)
        {
            if ( $options ) {
                $opt  = function($name, $default = null) use ($options) { return isset($options[$name]) ? $options[$name] : $default; };
                $bool = function($name, $default = false) use ($opt) { return in_array((string) $opt($name, $default), ['true', '1']); };
                
                $this->login    = $opt('login'   , $this->login);
                $this->password = $opt('password', $this->password);
                $this->host     = $opt('host'    , $this->host);
                $this->port     = $opt('port'    , $this->port);
                $this->timeout  = $opt('timeout' , $this->timeout);
                
                $this->useSSL   = $bool('ssl' , $this->useSSL);
                $this->smtpAuth = $bool('auth', strlen($this->login) > 0);
            }
        }

        /**
         * {@inheritdoc}
         */
        public function name()
        {
            return __CLASS__;
        }

        /**
         * {@inheritdoc}
         */
        public function getLastError()
        {
            return $this->lastError;
        }

        /**
         * {@inheritdoc}
         */
        public function send(Message $message)
        {
            $this->lastError = null;
            
            try {
                $this->openSocket();
                
                if ($this->smtpAuth) {
                    $this->smtpSend(250, 'EHLO [192.168.0.1]', 'Соединение с сервером с авторизацией');
                    $this->smtpSend(334, 'AUTH LOGIN', 'Авторизация');
                    $this->smtpSend(334, base64_encode($this->login), 'Авторизация');
                    $this->smtpSend(235, base64_encode($this->password), 'Авторизация');
                }
                else {
                    $this->smtpSend(250, 'HELO [192.168.0.1]', 'Соединение с сервером без авторизации');
                }
                
                $this->smtpSend(250, "MAIL FROM:<{$message->getSenderEmail()}>", 'Объявление отправителя');
                
                foreach ($message->getRecipientsEmailOnly() as $recipient) {
                    $this->smtpSend(250, "RCPT TO:<{$recipient}>", 'Объявление получателя');
                }
                
                $this->smtpSend(354, 'DATA', 'Отправление письма');
                $this->smtpSend(250, $message . "\r\n.", 'Отправление письма');
                $this->smtpSend(221, 'QUIT', 'Завершение');
                
                $this->closeSocket();
            }
            catch (Exception $e) {
                $this->closeSocket();
                
                $this->lastError     = $e->getCode() .': '. $e->getMessage();
                $this->lastErrorCode = $e->getCode();
                
                return false;
            }
            
            return true;
        }
        
        private function openSocket()
        {
            $host = '';
            
            if ($this->useSSL) {
                $host .= 'ssl://';
            }
            
            $host .= "{$this->host}:{$this->port}";
            
            $this->socket = @stream_socket_client($host, $errorNumber, $errorDescription, 2);
            
            if (!is_resource($this->socket)) {
                throw new Exception(sprintf('Не удалось подключиться к %s:%s. Ошибка %s: %s.',
                    (string) $this->host,
                    (string) $this->port,
                    (string) $errorNumber,
                    (string) $errorDescription
                ), 100);
            }
            
            stream_set_timeout($this->socket, $this->timeout);
            
            $line = $this->socketRead();
            
            if (!preg_match('/^220\s/', $line)) {
                throw new Exception('Не удалось установить соединение. Сервер вернул неожиданный ответ: '. $line, 101);
            }
        }
        
        private function smtpSend($expectedCode, $command, $stage)
        {
            $this->socketWrite($command ."\r\n");
            
            while (!feof($this->socket)) {
                $line = trim($this->socketRead());
                
                if (preg_match('/^(\d{3})(?:\s+(.*))?$/', $line, $matches)) {
                    list(, $code, $text) = $matches;
                    
                    if ($code != $expectedCode) {
                        throw new Exception(sprintf('На этапе "%s" ожидался код %s, но сервер вернул %s',
                            $stage,
                            $expectedCode,
                            $line
                        ), 102);
                    }
                    
                    return true;
                }
            }
            
            throw new Exception('Cервер неожиданно закрыл соединение', 104);
        }
        
        private function closeSocket()
        {
            if (is_resource($this->socket)) {
                fclose($this->socket);
            }
        }
        
        private function socketWrite($data)
        {
            if (!@fwrite($this->socket, $data)) {
                throw new Exception(sprintf('Не удалось записать данные в сокет: %s. Ошибка: %s',
                    $data,
                    lastPhpErrorMessage('неизвестная ошибка сокета')
                ), 103);
            }
        }
        
        private function socketRead()
        {
            $data = @fgets($this->socket);
            
            if ($data === false) {
                throw new Exception(sprintf('Не удалось считать данные из сокета. Ошибка: %s',
                    lastPhpErrorMessage('неизвестная ошибка сокета')
                ), 104);
            }
            
            return $data;
        }
    }
    
    class Phpmail implements TransportInterface
    {
        private $lastError = null;
        
        public function __construct(array $options = null)
        {
            if ($options) {
                // Если появятся опции, то прописывать их тут!
            }
        }
        
        public function name()
        {
            return __CLASS__;
        }
        
        public function send(Message $message)
        {
            $this->lastError = null;
            
            $recipients = $message->getRecipients(', ');
            $subject    = $message->getSubject();
            
            list($headers, $body) = explode("\r\n\r\n" , (string) $message, 2);
            
            $result = @mail($recipients, $subject, $body, $headers);
            
            if (!$result) {
                $this->lastError = lastPhpErrorMessage('неизвестная ошибка');
            }
            
            return $result;
        }
        
        public function getLastError()
        {
            return $this->lastError;
        }
    }
    
    class File implements TransportInterface
    {
        private $saveDir      = null;
        private $createDir    = TRUE;
        private $lastMailPath = null;
        private $lastError    = null;
        
        public function __construct(array $options = null)
        {
            if ($options) {
                $this->saveDir = isset($options['dir']) ? rtrim($options['dir'], '/') : null;
            }
        }
        
        public function name()
        {
            return __CLASS__;
        }
        
        
        public function setSaveDir($path)
        {
            $this->saveDir = $path;
            return $this;
        }
        
        public function getSaveDir()
        {
            return $this->saveDir;
        }
        
        public function getLastMailPath()
        {
            return $this->lastMailPath;
        }
        
        public function send(Message $message)
        {
            $this->lastError = null;
            
            $filename = $this->saveDir .'/'. uniqid(date('Y-m-d_H:i:s_')) .'.eml';
            $this->lastMailPath = $filename;
            
            if ($this->saveDir && !file_exists($this->saveDir) && $this->createDir) {
                if (!@mkdir($this->saveDir, 0755, true)) {
                    $this->lastError = "Не удалось создать папку {$this->saveDir} по причине: ". lastPhpErrorMessage('неизвестно');
                    return false;
                }
            }
            
            $result = @file_put_contents($filename, (string) $message);
            
            if ($result === false) {
                $this->lastError = lastPhpErrorMessage('неизвестная ошибка');
            }
            
            return $result !== false;
        }
        
        public function getLastError()
        {
            return $this->lastError;
        }
    }
    
    /**
     * Формирует EML-сообщение. Соблюдается правильная структура сообщения при вложении и подключении файлов.
     *
     * Порядок составления документа:
     * ------------------------------
     * multipart/mixed           Составной документ (вложения + текст)
     * |- multipart/alternative  Сообщение в нескольких форматах (простой текст + html)
     * |  |- text/plain          Простой текст
     * |  |- multipart/related   Составной документ (html + файлы)
     * |     |- text/html        HTML текст
     * |     |- Relate file A    Зависимый файл (не показывается во вложениях)
     * |     |- Relate file B    Зависимый файл (не показывается во вложениях)
     * |- Attachemnt A           Независимый файл (показывается во вложениях)
     * |- Attachment B           Независимый файл (показывается во вложениях)
     */
    class Message
    {
        private $charset     = 'UTF-8';
    
        private $subject     = null;
        private $recipients  = [];
        private $copyTo      = [];
        private $hiddenCopy  = [];
        private $content     = null;
        private $senderEmail = null;
    
        private $headers     = [];
        private $related     = [];
        private $attachments = [];
    
        public function __construct()
        {
            $this->headers = new Headers();
    
            $this->content = new Part();
            $this->setContentType('text/html; charset='. $this->charset);
            $this->headers
                ->set('Mime-Version', '1.0')
                ->set('Date'        , date(DATE_RFC1123))
                ->set('Message-ID'  , sprintf('<%s.%s@%s>', (string) time(), uniqid(), gethostname()))
            ;
        }
    
        /**
         * @return Headers
         */
        public function getHeaders()
        {
            return $this->headers;
        }
    
        public function setContentType($type)
        {
            $this->content->setContentType($type);
            return $this;
        }
    
        public function setContentTypeTextPlain()
        {
            $this->content->setContentType('text/plain; charset='. $this->charset);
            return $this;
        }
    
        public function getContentType()
        {
            return $this->content->getContentType();
        }
    
        public function setContent($data)
        {
            $this->content->setContent($data);
            return $this;
        }
        
        public function addContent($data)
        {
            $this->content->addContent($data);
            return $this;
        }
    
        public function setSubject($subject)
        {
            $this->subject = $subject;
            $this->headers->set('Subject', $subject, true);
            return $this;
        }
    
        public function getSubject()
        {
            return $this->subject;
        }
    
        public function addRecipient($mail, $name = null)
        {
            if ($name) {
                $this->recipients[] = "$name <$mail>";
                $this->headers->add('To', $this->headers->encode($name) ." <$mail>");
            }
            else {
                $this->recipients[] = $mail;
                $this->headers->add('To', $mail);
            }
    
            return $this;
        }
    
        public function addCopyTo($mail, $name = null)
        {
            if ($name) {
                $this->copyTo[] = $this->headers->encode($name) ." <$mail>";
                $this->headers->add('Cc', $this->headers->encode($name) ." <$mail>");
            }
            else {
                $this->hiddenCopy[] = $mail;
                $this->headers->add('Cc', $mail);
            }
    
            return $this;
        }
    
        public function addHiddenCopy($mail, $name = null)
        {
            if ($name) {
                $this->hiddenCopy[] = $this->headers->encode($name) ." <$mail>";
                $this->headers->add('Bcc', $this->headers->encode($name) ." <$mail>");
            }
            else {
                $this->hiddenCopy[] = $mail;
                $this->headers->add('Bcc', $mail);
            }
    
            return $this;
        }
    
        public function addRelatedString($data, $id, $contentType = null)
        {
            if (is_null($contentType)) {
                $contentType = finfo_buffer(finfo_open(FILEINFO_MIME), $data);
            }
    
            $part = new Part();
            $part->setContentType($contentType)
                ->setContentDisposition('inline')
                ->setContentId($id)
                ->setContent($data)
            ;
    
            $this->related[] = $part;

            return $this;
        }
    
        public function addRelatedFile($path, $id = null, $contentType = null)
        {
            if (is_null($id)) {
                $id = basename($path);
            }
    
            if (is_null($contentType)) {
                $contentType = finfo_file(finfo_open(FILEINFO_MIME), $path);
            }
    
            $part = new Part();
            $this->related[] = $part;
    
            $part->setContentType($contentType)
                ->setContentDisposition('inline')
                ->setContentId($id)
                ->includeContent($path)
            ;
    
            return $this;
        }
    
        public function addAttachmentString($data, $fileName, $contentType = null)
        {
            if (is_null($contentType)) {
                $contentType = finfo_buffer(finfo_open(\FILEINFO_MIME), $data);
            }
    
            $part = new Part();
            $this->attachments[] = $part;
    
            $part->setContentType($contentType)
                ->setContentDisposition('attachment; filename="'. $this->headers->encode($fileName) .'"')
                ->setContent($data)
            ;
    
            return $this;
        }
    
        public function addAttachmentFile($path, $fileName = null, $contentType = null)
        {
            if (is_null($fileName)) {
                $fileName = basename($path);
            }
    
            if (is_null($contentType)) {
                $contentType = finfo_file(finfo_open(\FILEINFO_MIME), $path);
            }
    
            $part = new Part();
            $this->attachments[] = $part;
    
            $part->setContentType($contentType)
                ->setContentDisposition('attachment; filename="'. $this->headers->encode($fileName) .'"')
                ->includeContent($path)
            ;
    
            return $this;
        }
    
        public function getId()
        {
            return $this->headers->getFirst('Message-ID');
        }
    
        public function getRecipients($separator = null)
        {
            $recipients = array_merge($this->recipients, $this->copyTo, $this->hiddenCopy);
    
            // Если адреса кривые, можно расскомментировать следующую строку. Там не менее кривая функция, которая исправляет кривые адреса.
            // $recipients = Functions::mail2array($recipients);
    
            if (isset($separator)) {
                return join($separator, $recipients);
            }
    
            return $recipients;
        }
    
        public function getRecipientsEmailOnly()
        {
            return Functions::mail2array($this->getRecipients());
        }
    
        public function setSenderEmail($email, $name = null)
        {
            $this->senderEmail = $email;
    
            if ($name) {
                $this->headers->set('From', $this->headers->encode($name) ." <$email>");
            }
            else {
                $this->headers->set('From', "<$email>");
            }
    
            return $this;
        }
    
        public function getSenderEmail()
        {
            return $this->senderEmail;
        }
    
        public function __toString()
        {
            return $this->toEml();
        }
    
        public function toEml()
        {
            // Объявляем части письма
            $message = $this->content;
    
            if ($this->related) {
                $related = new MultiPart('related');
                $related->addPart($message);
    
                foreach ( $this->related as $part ) {
                    $related->addPart($part);
                }
    
                $message = $related;
            }
    
            if ($this->content->isHtml()) {
                $alternative = new MultiPart('alternative');
    
                $alternativeText = new Part();
    
                $text = preg_replace('/^[\s\S]*<body[^>]*>/i', '', $this->content->getContent());
                $text = preg_replace('/<a[^>]+href=[\'"]([^>]+)[\'"][^>]*>([^>]*)<\\/a>/', '$2 $1', $text);
                $text = trim(strip_tags($text));
    
                $alternativeText->setContentType('text/plain; charset='. $this->charset);
                $alternativeText->setContent($text);
    
                $alternative->addPart($alternativeText);
                $alternative->addPart($message);
    
                $message = $alternative;
            }
    
            if ($this->attachments) {
                $mixed = new MultiPart('mixed');
                $mixed->addPart($message);
    
                foreach ($this->attachments as $part) {
                    $mixed->addPart($part);
                }
    
                $message = $mixed;
            }
    
            return $this->headers . $message;
        }
    }
    
    class Headers
    {
        private $storage = [];
        
        public function set($header, $value, $encodeValue = false)
        {
            if ($encodeValue) {
                $value = $this->encode($value);
            }
            
            $this->storage[$header] = $value;
            return $this;
        }
        
        public function add($header, $value, $encodeValue = false) 
        {
            if ( $encodeValue ) {
                $value = $this->encode($value);
            }
            
            if ( !isset($this->storage[$header]) ) {
                $this->storage[$header] = $value;
            }
            elseif ( is_array($this->storage[$header]) ) {
                $this->storage[$header][] = $value;
            }
            else {
                $this->storage[$header]   = [$this->storage[$header]];
                $this->storage[$header][] = $value;
            }
            
            return $this;
        }
        
        public function has($header)
        {
            return isset($this->storage[$header]);
        }
        
        public function remove($header)
        {
            unset($this->storage[$header]);
            return $this;
        }
        
        public function getFirstOrArray($header)
        {
            if (!$this->has($header)) {
                return null;
            }
            
            return $this->storage[$header];
        }
        
        public function getFirst($header)
        {
            if (!$this->has($header)) {
                return null;
            }
            
            if (is_array($this->storage[$header]) ) {
                return current($this->storage[$header]);
            }
            
            return $this->storage[$header];
        }
        
        public function getArray($header)
        {
            if (!$this->has($header)) {
                return [];
            }
            
            if (is_array($this->storage[$header])) {
                return $this->storage[$header];
            }
            
            return [$this->storage[$header]];
        }
        
        
        public function __toString()
        {
            $result = '';
            
            foreach ($this->storage as $header => $value) {
                if (is_array($value)) {
                    foreach ($value as $subValue) {
                        $result .= "$header: $subValue\r\n";
                    }
                }
                else {
                    $result .= "$header: $value\r\n";
                }
            }
            
            return $result;
        }
        
        public function encodeNonAscii($str)
        {
            return preg_replace_callback('/[[:^ascii:]].*[[:^ascii:]]+/u', function ($str) {
                return mb_encode_mimeheader($str[0]);
            }, $str);
        }
        
        public function encode($str)
        {
            return mb_encode_mimeheader($str);
        }
    }
    
    class MultiPart extends Part
    {
        private $boundary = null;
        
        private $parts = [];
        
        public function __construct($type)
        {
            parent::__construct();
            $this->setContentType('multipart/'. $type .'; boundary="'. $this->getBoundary() .'"');
        }
        
        public function addPart(Part $part)
        {
            $this->parts[] = $part;
            return $this;
        }
        
        public function getContent()
        {
            $result = '';
            
            foreach ($this->parts as $part) {
                $result .= '--'. $this->getBoundary() ."\r\n";
                $result .= (string) $part ."\r\n";
            }
            
            $result .= '--'. $this->getBoundary() ."--\r\n\r\n";
            
            return $result;
        }
        
        public function getBoundary()
        {
            if (is_null($this->boundary)) {
                $this->boundary = str_pad(sha1(uniqid()), 46, '-', STR_PAD_LEFT);
            }
            
            return $this->boundary;
        }
        
        public function __toString()
        {
            return $this->headers ."\r\n". $this->getContent();
        }
    }
    
    class Part
    {
        protected $headers = null;
        protected $content = null;
        
        public function __construct()
        {
            $this->headers = new Headers();
        }
        
        public function isHtml()
        {
            return preg_match('~html~i', $this->getContentType());
        }
        
        public function isAttachmentOrRelation()
        {
            return !is_null($this->getContentDisposition());
        }
        
        public function setHeader($header, $value)
        {
            $this->headers->set($header, $value);
            return $this;
        }
        
        public function setHeaders($headers)
        {
            foreach ($headers as $header => $value) {
                $this->setHeader($header, $value);
            }
            
            return $this;
        }
        
        public function setContentType($type)
        {
            $this->headers->set('Content-type', $type);
            return $this;
        }
        
        public function getContentType()
        {
            return $this->headers->getFirst('Content-type');
        }
        
        public function setContentId($id)
        {
            $this->headers->set('Content-ID', "<$id>");
            return $this;
        }
        
        public function setContentDisposition($disposition)
        {
            $this->headers->set('Content-disposition', $disposition);
            return $this;
        }
        
        public function getContentDisposition()
        {
            return $this->headers->getFirst('Content-disposition');
        }
        
        public function setContent($content)
        {
            $this->content = $content;
            return $this;
        }
        
        public function addContent($content)
        {
            $this->content .= $content;
            return $this;
        }
        
        public function getContent()
        {
            return $this->content;
        }
        
        public function includeContent($path)
        {
            $this->content .= file_get_contents($path);
            return $this;
        }
        
        public function __toString()
        {
            $content = $this->encodeContent();
            return $this->headers ."\r\n{$content}\r\n";
        }
        
        private function encodeContent()
        {
            $this->headers->set('Content-Transfer-Encoding', 'base64');
            return trim(chunk_split(base64_encode((string) $this->getContent())));
        }
    }
    
    class Functions
    {
        /**
         * Преобразование списка адресов
         *
         * @param $mails string
         * @return array
         */
        static public function mail2array($mails)
        {
            $result = array();
            
            if (is_array($mails)) {
                foreach ($mails as $mail) {
                    $array = self::mail2array($mail);
                    
                    if ($array) {
                        $result = array_merge($result, $array);
                    }
                }
            } else {
                $mails = preg_replace([
                    '/\s*@\s*/',
                    '/\r?\n/',
                    '/\\.(\\W|$)/u',
                    '/[<>]/',
                ], [
                    '@',
                    ', ',
                    ',\\1',
                    ''
                ], $mails);
                
                foreach (preg_split('~[ ,;:/]+~', $mails) as $mail) {
                    $mail = trim($mail);
                    
                    if (self::isValidEmail($mail)) {
                        $result[] = $mail;
                    }
                }
            }
            
            return array_unique($result);
        }
    
        /**
         * Проверка почтового адреса на валидность
         *
         * @param string $address
         * @return bool
         */
        static protected function isValidEmail($address)
        {
            return (bool) preg_match('/^[\w+\._-]+@[\w+\._-]+$/iu', $address);
        }
    }
}