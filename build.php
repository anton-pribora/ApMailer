#!/usr/bin/php -d phar.readonly=0
<?php

$target = __DIR__ .'/mailer.phar';

@unlink($target);

$phar = new Phar($target);

$defaultStub = $phar->createDefaultStub('app.php');

$phar->buildFromDirectory(__DIR__ . '/src');

$stub  = "#!/usr/bin/env php\n";
$stub .= "<?php \$buildDate = '". date('r') ."';?>\n";
$stub .= $defaultStub;

$phar->setStub($stub);

chmod($target, 0755);