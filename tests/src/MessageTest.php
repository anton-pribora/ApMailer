<?php

namespace tests\src;

class MessageTest extends \PHPUnit\Framework\TestCase
{
    public function testToEmlAddRecipient()
    {
        $message = new \ApMail\Message();
        $message->addRecipient('test@test.test', 'Ivanov');
        $eml = $message->toEml();
        $this->assertContains('To: Ivanov <test@test.test>', $eml);

        $message = new \ApMail\Message();
        $message->addRecipient('test@test.test');
        $eml = $message->toEml();
        $this->assertContains('To: test@test.test', $eml);
    }

    public function testToEmlAddRelatedString()
    {
        $message = new \ApMail\Message();
        $message->addRelatedString('Тестовая строка', 'test_1');

        $eml = $message->toEml();

        $this->assertContains('Content-disposition: inline', $eml);
        $this->assertContains('Content-ID: <test_1>', $eml);
        $this->assertContains(base64_encode('Тестовая строка'), $eml);
    }

    public function testToEmlAddRelatedFile()
    {
        $message = new \ApMail\Message();
        $filePath = __DIR__ . '/fixture/test_attachment_file.txt';
        $message->addRelatedFile($filePath);

        $eml = $message->toEml();

        $this->assertContains('Content-disposition: inline', $eml);
        $this->assertContains('Content-ID: <test_attachment_file.txt>', $eml);
        $this->assertContains(base64_encode(file_get_contents($filePath)), $eml);
    }

    public function testToEmlIsHtmlContent()
    {
        $message = new \ApMail\Message();
        $htmlContent = file_get_contents(__DIR__ . '/fixture/content_is_html__for_email.html');
        $message->setContent($htmlContent);

        $eml = $message->toEml();

        $this->assertContains('Content-type: multipart/alternative', $eml);
        $this->assertContains(chunk_split(base64_encode($htmlContent)), $eml);
    }
}
