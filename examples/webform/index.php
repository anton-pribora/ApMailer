<?php

error_reporting(E_ALL);

chdir(__DIR__);

include '../../src/lib.php';

function param($name, $default = NULL) {
    return isset($_REQUEST[$name]) ? $_REQUEST[$name] : $default;
}

function html($data) {
    return htmlentities($data);
}

$errors   = [];
$messages = [];

$defaultConfig = [
    'defaultFrom' => 'mymail@example.org',
    'onError'     => function($error) use (&$errors) { $errors[] = $error; },
    'afterSend'   => function($text) use (&$messages) { $messages[] = $text; },
    'transports'  => [
        'file' => ['file', 'dir'  => 'mails'],
        'smtp' => ['smtp', 'host' => 'smtp.yandex.ru', 'ssl' => 'true', 'port' => '465', 'login' => '****@yandex.ru', 'password' => '******'],
    ],
];

$config = array_replace_recursive($defaultConfig, param('config', []));

$configValue = function ($key, $default = NULL) use ($config) {
    $key = '["'. strtr($key, ['.' => '"]["']) .'"]';
    return eval("return isset(\$config$key) ? \$config$key : \$default;");
};

$disableSmtp    = param('disableSmtp', true);
$messageFrom    = param('from'       , '');
$messageTo      = param('to'         , 'test@example.org');
$messageReplyTo = param('reply-to'   , 'another-mail@example.org');
$messageSubject = param('subject'    , 'Re: очень важная новость');
$messageText    = param('text'       , '<p>Дорогой друг,</p><p>Спешу поделиться радостным известием, к нам едет ревизор!</p>');

if ($disableSmtp) {
    unset($config['transports']['smtp']);
}

if (param('send')) {
    Mailer()->init($config);
    
    $message = Mailer()->newHtmlMessage();
    
    if ($messageSubject) {
        $message->setSubject($messageSubject);
    }
    
    if ($messageFrom) {
        $message->setSenderEmail($messageFrom);
    }
    
    if ($messageTo) {
        $message->addRecipient($messageTo);
    }
    
    if ($messageReplyTo) {
        $message->addReplyTo($messageReplyTo);
    }
    
    $message->addContent(file_get_contents('mail-header.html'));
    $message->addContent($messageText);
    $message->addContent(file_get_contents('mail-footer.html'));
    $message->addRelatedFile('signature.png');
    
    if (isset($_FILES['attachment']['size']) && $_FILES['attachment']['size'] > 0) {
        $message->addAttachmentFile(
            $_FILES['attachment']['tmp_name'], 
            $_FILES['attachment']['name'], 
            $_FILES['attachment']['type']
        );
    }
    
    Mailer()->sendMessage($message);
}

?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <title>Отправить письмо через ApMail</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/css/bootstrap.min.css" integrity="sha384-rwoIResjU2yc3z8GV/NPeZWAv56rSmLldC3R/AZzGRnGxQQKnKkoFVhFQhNUwEyJ" crossorigin="anonymous">
    <script src="https://code.jquery.com/jquery-3.1.1.slim.min.js" integrity="sha384-A7FZj7v+d/sdmMqp/nOQwliLvUsJfDHW+k9Omg/a/EheAdgtzNs3hpfag6Ed950n" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tether/1.4.0/js/tether.min.js" integrity="sha384-DztdAPBWPRXSA/3eYEEUWrWCy7G5KFbe8fFjk5JAIxUYHKkDx6Qin1DkWx51bBrb" crossorigin="anonymous"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js" integrity="sha384-vBWWzlZJ8ea9aCX4pEW3rVHjgjt7zpkNpZk+02D9phzyeVkE+jo0ieGizqPLForn" crossorigin="anonymous"></script>
    <script src="https://cdn.tinymce.com/4/tinymce.min.js" crossorigin="anonymous"></script>
    <script type="text/javascript">
tinymce.init({
  selector: 'textarea',
  menubar: false,
  browser_spellcheck: true,
  plugins: [
    'advlist autolink lists link image charmap print preview anchor',
    'searchreplace visualblocks code fullscreen',
    'insertdatetime table contextmenu paste code'
  ],
  toolbar: 'undo redo | insert | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image',
});
</script>
  </head>
<body>
  <div class="container">
    <h1>Отправить письмо через ApMail</h1>
    <form action="" method="post" enctype="multipart/form-data">
      <fieldset>
        <legend>Настройки конфига</legend>
        
        <div class="form-group row">
          <label class="col-2 col-form-label">defaultFrom</label>
          <div class="col-10">
            <input class="form-control" type="text" name="config[defaultFrom]" value="<?=html($configValue('defaultFrom'))?>">
          </div>
        </div>
        
        <div class="form-group row">
          <label class="col-2 col-form-label">Транспорт File</label>
          <div class="col-10">
            <input type="hidden" name="config[transports][file][0]" value="file">
            <input class="form-control" type="text" name="config[transports][file][dir]" value="<?=html($configValue('transports.file.dir'))?>">
            <p class="help-block small text-muted">Путь для хранения писем</p>
          </div>
        </div>

        <div class="form-group row">
          <label class="col-2 col-form-label">Транспорт SMTP</label>
          <div class="col-10">
            <input type="hidden" name="config[transports][smtp][0]" value="smtp">
            <label>
              SSL
              <select class="form-control" name="config[transports][smtp][ssl]">
                <option value="true" <?=$configValue('transports.smtp.ssl') == 'true' ? 'selected' : ''?>>Да</option>
                <option value="false" <?=$configValue('transports.smtp.ssl') == 'false' ? 'selected' : ''?>>Нет</option>
              </select>
            </label>
            <label>
              Хост
              <input class="form-control" type="text" name="config[transports][smtp][host]" value="<?=html($configValue('transports.smtp.host'))?>">
            </label>
            <label>
              Порт
              <input class="form-control" type="text" name="config[transports][smtp][port]" value="<?=html($configValue('transports.smtp.port'))?>">
            </label>
            <label>
              Логин
              <input class="form-control" type="text" name="config[transports][smtp][login]" value="<?=html($configValue('transports.smtp.login'))?>">
            </label>
            <label>
              Пароль
              <input class="form-control" type="text" name="config[transports][smtp][password]" value="<?=html($configValue('transports.smtp.password'))?>">
            </label>
            <label class="form-check-label">
              <input type="hidden" name="disableSmtp" value="0">
              <input class="form-check-input" type="checkbox" name="disableSmtp" id="disableSmtpBox" value="true" <?=$disableSmtp ? 'checked' : ''?> autocomplete="off">
              Не использовать SMTP при отправке
            </label>
          </div>
        </div>
        
      </fieldset>
      
<?php foreach ($messages as $text) {?>
<div class="alert alert-info">
  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
    <span aria-hidden="true">&times;</span>
  </button>
  <div><?php echo $text?></div>
</div>
<?php }?>

<?php foreach ($errors as $text) {?>
<div class="alert alert-danger">
  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
    <span aria-hidden="true">&times;</span>
  </button>
  <div><?php echo $text?></div>
</div>
<?php }?>
      
      <fieldset>
        <legend>Письмо</legend>
        
        <div class="form-group row">
          <label class="col-2 col-form-label">Адрес отправителя</label>
          <div class="col-10">
            <input class="form-control" type="text" name="from" value="<?=html($messageFrom)?>">
            <p class="help-block small text-muted">Если значение не указано, то будет использоваться defaultFrom</p>
          </div>
        </div>

        <div class="form-group row">
          <label class="col-2 col-form-label">Кому</label>
          <div class="col-10">
            <input class="form-control" type="text" name="to" value="<?=html($messageTo)?>">
          </div>
        </div>
        
        <div class="form-group row">
          <label class="col-2 col-form-label">Адрес для ответа (Reply-To)</label>
          <div class="col-10">
            <input class="form-control" type="text" name="reply-to" value="<?=html($messageReplyTo)?>">
          </div>
        </div>
        
        <div class="form-group row">
          <label class="col-2 col-form-label">Тема сообщения</label>
          <div class="col-10">
            <input class="form-control" type="text" name="subject" value="<?=html($messageSubject)?>">
          </div>
        </div>
        
        <div class="form-group row">
          <label class="col-2 col-form-label">Текст сообщения</label>
          <div class="col-10">
            <textarea class="form-control" name="text" rows="10"><?=html($messageText)?></textarea>
          </div>
        </div>
        
        <div class="form-group row">
          <label class="col-2 col-form-label">Вложить файл</label>
          <div class="col-10">
            <input class="form-control-file" type="file" name="attachment">
          </div>
        </div>
        
        <div class="form-group row">
          <label class="col-2 col-form-label"></label>
          <div class="col-10">
            <input class="btn btn-primary" type="submit" name="send" value="Отправить">
          </div>
        </div>
        
      </fieldset>
    </form>
  </div>
</body>
</html>