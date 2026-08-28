<?php

namespace Tests\Unit;

use App\Services\FileService;
use App\Services\PdfService;
use App\Services\QrCodeService;
use App\Services\SmsService;
use Tests\TestCase;

class LibraryServicesTest extends TestCase
{
    public function test_pdf_from_html_returns_valid_pdf(): void
    {
        $pdf = new PdfService(new FileService());

        $binary = $pdf->fromHtml(['html' => '<h1>Invoice</h1>']);

        $this->assertStringStartsWith('%PDF-', $binary);
        $this->assertNotEmpty($binary);
    }

    public function test_qr_code_base64_returns_png(): void
    {
        $qr = new QrCodeService();

        $base64 = $qr->base64(['content' => 'https://wasnaker.lan', 'size' => 120]);

        $this->assertNotEmpty($base64);
        $this->assertSame('89504e470d0a1a0a', bin2hex(substr(base64_decode($base64), 0, 8)));
    }

    public function test_sms_log_driver_sends_without_error(): void
    {
        $sms = new SmsService();

        $result = $sms->send(['to' => '+6281234567890', 'body' => 'Kode OTP: 123456']);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    public function test_sms_requires_to_and_body(): void
    {
        $sms = new SmsService();

        $this->assertFalse($sms->send(['to' => '', 'body' => ''])['success']);
    }
}
