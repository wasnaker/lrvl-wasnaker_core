<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Pondasi i18n: tiap locale wajib punya file pesan dengan key yang sama.
 *
 * ponytail: tanpa DB — suite Feature repo ini pakai RefreshDatabase yang
 * gagal di sqlite (migrasi modul Customer pakai information_schema/MySQL).
 * Perilaku endpoint + middleware diverifikasi live via curl.
 */
class LocaleMessagesTest extends TestCase
{
    public function test_every_available_locale_has_all_message_keys(): void
    {
        $keys = array_keys(require lang_path('en/messages.php'));

        foreach (config('app.available_locales') as $locale) {
            $file = lang_path("$locale/messages.php");
            $this->assertFileExists($file, "lang/$locale/messages.php hilang");

            $messages = require $file;
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $messages, "lang/$locale kurang key '$key'");
                $this->assertNotSame('', (string) $messages[$key]);
            }
        }
    }

    public function test_translation_switches_with_app_locale(): void
    {
        app()->setLocale('ko');
        $this->assertSame('인증되지 않았습니다.', __('messages.unauthenticated'));

        app()->setLocale('zh');
        $this->assertSame('未认证。', __('messages.unauthenticated'));

        // Locale tak dikenal -> fallback ke en, bukan crash.
        app()->setLocale('xx');
        $this->assertSame('Unauthenticated.', __('messages.unauthenticated'));
    }
}
