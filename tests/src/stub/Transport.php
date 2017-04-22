<?php

namespace tests\src\stub;

/**
 * Заглушка для тестирования.
 */
class Transport implements \ApMailer\TransportInterface
{
    /**
     * Успешно отправлено письмо.
     *
     * @var bool
     */
    public $isSending = true;

    /**
     * Был ли вызван метод Transport::send().
     *
     * @var bool
     */
    public $isCallSend = false;

    /**
     * {@inheritdoc}
     */
    public function getLastError()
    {
        return __FUNCTION__;
    }

    /**
     * {@inheritdoc}
     */
    public function send(\ApMailer\Message $message)
    {
        $this->isCallSend = true;
        return $this->isSending;
    }

    /**
     * {@inheritdoc}
     */
    public function name()
    {
        return 'Заглушка для тестов';
    }
}
