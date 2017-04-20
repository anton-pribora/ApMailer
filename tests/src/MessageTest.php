<?php

namespace tests\src;

class MessageTest extends \PHPUnit\Framework\TestCase
{
    private function newMessage()
    {
        return Mailer()->newHtmlMessage();
    }
    
    public function testToEmlAddRecipient()
    {
        $message = $this->newMessage();
        $message->addRecipient('mymail@example.org', 'John Doe');
        $eml = $message->toEml();
        $this->assertContains('To: John Doe <mymail@example.org>', $eml);

        $message = Mailer()->newTextMessage();
        $message->addRecipient('mymail@example.org');
        $eml = $message->toEml();
        $this->assertContains('To: mymail@example.org', $eml);
    }

    public function testToEmlAddRelatedString()
    {
        $message = $this->newMessage();
        $message->addRelatedString('Тестовая строка', 'test_1');

        $eml = $message->toEml();

        $this->assertContains('Content-disposition: inline', $eml);
        $this->assertContains('Content-ID: <test_1>', $eml);
        $this->assertContains(base64_encode('Тестовая строка'), $eml);
    }

    public function testToEmlAddRelatedFile()
    {
        $message = $this->newMessage();
        $filePath = __DIR__ . '/fixture/test_attachment_file.txt';
        $message->addRelatedFile($filePath);

        $eml = $message->toEml();

        $this->assertContains('Content-disposition: inline', $eml);
        $this->assertContains('Content-ID: <test_attachment_file.txt>', $eml);
        $this->assertContains(base64_encode(file_get_contents($filePath)), $eml);
    }

    public function testToEmlIsHtmlContent()
    {
        $message = $this->newMessage();
        $htmlContent = file_get_contents(__DIR__ . '/fixture/content_is_html__for_email.html');
        $message->setContent($htmlContent);

        $eml = $message->toEml();

        $this->assertContains('Content-type: multipart/alternative', $eml);
        $this->assertContains(chunk_split(base64_encode($htmlContent)), $eml);
    }
}
