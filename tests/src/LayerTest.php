<?php

namespace tests\src;

class LayerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Тестируем запуск триггеров.
     * @group unit
     */
    public function testSendLanchTriggers()
    {
        $layer   = $this->makeLayer();
        $transport = $this->makeStubTransport();
        $transport->isSending = false;
        $layer->appendTranspport($transport);

        $lanchBeforeSend = false;
        $layer->appendTrigger('beforeSend', function () use (&$lanchBeforeSend) {
            $lanchBeforeSend = true;
        });

        $lanchAfterSend = false;
        $layer->appendTrigger('afterSend', function () use (&$lanchAfterSend) {
            $lanchAfterSend = true;
        });

        $lanchError = false;
        $layer->appendTrigger('error', function () use (&$lanchError) {
            $lanchError = true;
        });
        
        $message = $this->makeMessage();

        $layer->send($message);

        $this->assertTrue($lanchBeforeSend, 'Не был запущен trigger beforeSend');
        $this->assertTrue($lanchAfterSend , 'Не был запущен trigger afterSend');
        $this->assertTrue($lanchError     , 'Не был запущен trigger error');
        $this->assertNotEmpty($layer->getErrors());
    }

    /**
     * Тестируем вызов метода send Транспорта.
     */
    public function testSendUsedTransport()
    {
        $layer = $this->makeLayer();

        $successfulTransport = $this->makeStubTransport();
        $layer->appendTranspport($successfulTransport, function () {return true;});

        $notSuccessfulTransport = $this->makeStubTransport();
        $layer->appendTranspport($notSuccessfulTransport, function () {return false;});


        $message = $this->makeMessage();

        $layer->send($message);

        $this->assertTrue($successfulTransport->isCallSend    , 'Не был запущен метод send');
        $this->assertFalse($notSuccessfulTransport->isCallSend, 'Был запущен метод send');
    }

    private function makeMessage()
    {
        return Mailer()->newHtmlMessage();
    }

    private function makeLayer()
    {
        return new \ApMailer\Layer();
    }

    private function makeStubTransport()
    {
        return new \tests\src\stub\Transport();
    }
}

