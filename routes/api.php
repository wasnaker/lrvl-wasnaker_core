<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\BroadcastController;
use App\Http\Controllers\ExcelController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\GdprController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\MetaController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\NumberToWordController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\QrCodeController;
use App\Http\Controllers\RelationController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

// Seluruh API bisnis + infrastruktur di-versi-kan (tanpa kecuali).
// v1 = kontrak stabil pertama; breaking change berikutnya → v2, dst.
Route::prefix('v1')->group(function () {

Route::get('/health', [ApiController::class, 'health']);

Route::name('login')->get('/login', [ApiController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [ApiController::class, 'user']);

    // Settings (key-value API, bukan CRUD klasik)
    Route::get('/settings/{key}', [SettingController::class, 'show']);
    Route::put('/settings/{key}', [SettingController::class, 'upsert']);
    Route::delete('/settings/{key}', [SettingController::class, 'destroy']);
    Route::post('/settings/bulk', [SettingController::class, 'bulk']);

    // Activity Logs (resource REST, multi-tenant)
    Route::apiResource('activity-logs', ActivityLogController::class)->only([
        'index', 'show', 'store', 'destroy',
    ]);

    // Custom Meta (polymorphic, key-value per entity)
    Route::get('/meta/{type}/{id}', [MetaController::class, 'index']);
    Route::post('/meta/{type}/{id}', [MetaController::class, 'store']);
    Route::get('/meta/{type}/{id}/{key}', [MetaController::class, 'show']);
    Route::put('/meta/{type}/{id}/{key}', [MetaController::class, 'update']);
    Route::delete('/meta/{type}/{id}/{key}', [MetaController::class, 'destroy']);

    // Relations (inti resolver; tipe di-register module via hook)
    Route::get('/relations/types', [RelationController::class, 'types']);
    Route::get('/relations/{type}/{id}', [RelationController::class, 'show']);

    // NumberToWord (terbilang angka untuk invoice/PDF)
    Route::post('/number-to-word/convert', [NumberToWordController::class, 'convert']);
    Route::post('/number-to-word/convert-indian', [NumberToWordController::class, 'convertIndian']);

    // Modules (discovery & management, dari App_modules Perfex)
    Route::get('/modules', [ModuleController::class, 'index']);
    Route::get('/modules/enabled', [ModuleController::class, 'enabled']);
    Route::get('/modules/{name}', [ModuleController::class, 'show']);
    Route::get('/modules/{name}/status', [ModuleController::class, 'status']);
    Route::post('/modules/{name}/enable', [ModuleController::class, 'enable']);
    Route::post('/modules/{name}/disable', [ModuleController::class, 'disable']);

    // Files (upload Laravel Storage + metadata, mirip tblfiles Perfex)
    Route::get('/files/limits', [FileController::class, 'limits']);
    Route::post('/files', [FileController::class, 'store']);
    Route::get('/files/{id}', [FileController::class, 'show']);
    Route::get('/files/{id}/download', [FileController::class, 'download']);
    Route::get('/files/{id}/preview', [FileController::class, 'preview']);
    Route::delete('/files/{id}', [FileController::class, 'destroy']);

    // Mail (pengganti App_mailer / App_Email Perfex)
    Route::post('/mail/send', [MailController::class, 'send']);
    Route::post('/mail/notify', [MailController::class, 'notify']);
    Route::post('/mail/notify-many', [MailController::class, 'notifyMany']);
    Route::post('/mail/retry', [MailController::class, 'retryQueue']);
    Route::post('/mail/cleanup', [MailController::class, 'cleanUpQueue']);
    Route::get('/mail/queue', [MailController::class, 'queueStatus']);

    // Payment Gateway abstraction (gateways/ library)
    Route::get('/payment/gateways', [PaymentController::class, 'index']);
    Route::post('/payment/intent', [PaymentController::class, 'createIntent']);

    // GDPR / data privacy (gdpr/ library)
    Route::get('/gdpr/export', [GdprController::class, 'export']);
    Route::post('/gdpr/anonymize', [GdprController::class, 'anonymize']);
    Route::post('/gdpr/delete', [GdprController::class, 'delete']);

    // PDF (pdf/ + App_bulk_pdf_export library)
    Route::post('/pdf/generate', [PdfController::class, 'generate']);
    Route::post('/pdf/from-html', [PdfController::class, 'fromHtml']);
    Route::post('/pdf/bulk-export', [PdfController::class, 'bulkExport']);

    // SMS (sms/ library — Twilio/Clickatell/Msg91 abstraction)
    Route::post('/sms/send', [SmsController::class, 'send']);
    Route::get('/sms/drivers', [SmsController::class, 'drivers']);

    // QR Code (Endroid_qrcode library)
    Route::post('/qr-code/generate', [QrCodeController::class, 'generate']);

    // Excel import/export (import/ library)
    Route::post('/excel/export', [ExcelController::class, 'export']);
    Route::post('/excel/import', [ExcelController::class, 'import']);

    // Tags (App_tags / spatie laravel-tags)
    Route::get('/tags', [TagController::class, 'index']);
    Route::post('/tags', [TagController::class, 'store']);
    Route::delete('/tags/{id}', [TagController::class, 'destroy']);

    // Broadcasting (App_pusher → Laravel Broadcasting/Reverb)
    // Auth channel private via /api/v1/broadcasting/auth (didaftarkan withBroadcasting)
    Route::get('/broadcast/config', [BroadcastController::class, 'config']);
    Route::post('/broadcast/test', [BroadcastController::class, 'sendTest']);
});
});
