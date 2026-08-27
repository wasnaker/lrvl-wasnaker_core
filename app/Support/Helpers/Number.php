<?php

declare(strict_types=1);

namespace App\Support\Helpers;

/**
 * Helper formatter angka & mata uang yang diadopsi dari sales_helper.php
 * PerfexCRM (bagian formatter saja).
 *
 * Fungsi business logic (perhitungan pajak, update kolom total, dst.) tidak
 * termasuk di sini — akan dipindahkan ke module Service (mis.
 * modules/Sales/Services/InvoiceService.php) nanti.
 *
 * REF: docs/porting-helper-implementasi.md (sales_helper.php — format portion)
 */
class Number
{
    /**
     * Format angka dengan ribuan & decimal.
     *
     * Contoh: Number::formatNumber(1500000, 2) → '1,500,000.00'
     *         Number::formatNumber(null) → ''
     */
    public static function formatNumber(null|float|int|string $number, ?int $decimals = null): string
    {
        if ($number === null || $number === '') {
            return '';
        }

        $number = (float) $number;

        if ($decimals === null) {
            $decimals = 2;
        }

        return number_format($number, $decimals, '.', ',');
    }

    /**
     * Format nilai uang kerepresentasi string mata uang.
     *
     * Contoh: Number::formatMoney(1500000, 'IDR') → 'Rp 1.500.000,00'
     *         Number::formatMoney(0, 'IDR', true) → ''   (blank jika nol)
     */
    public static function formatMoney(null|float|int|string $amount, string $currencyCode = 'IDR', bool $blankZero = false): string
    {
        if ($amount === null || $amount === '') {
            if ($blankZero) {
                return '';
            }

            return '0';
        }

        $amount = (float) $amount;
        $decimals = self::getDecimalPlaces($currencyCode);
        $formatted = number_format($amount, $decimals, ',', '.');
        $symbol = self::getCurrencySymbol($currencyCode);

        return $symbol . ' ' . $formatted;
    }

    /**
     * Dapatkan jumlah decimal places untuk kode mata uang tertentu.
     *
     * Aturan dasar (bisa diganti dengan model Currency nanti):
     *   JPY, KRW → 0 (tidak ada decimal)
     *   default → 2
     */
    public static function getDecimalPlaces(string $currencyCode): int
    {
        return match (strtoupper($currencyCode)) {
            'JPY', 'KRW' => 0,
            default => 2,
        };
    }

    /**
     * Dapatkan simbol mata uang berdasarkan kode ISO.
     *
     * Aturan dasar (bisa diganti dengan model Currency nanti):
     *   IDR → Rp, USD → $, EUR → €, GBP → £, JPY → ¥, KRW → ₩
     */
    public static function getCurrencySymbol(string $currencyCode): string
    {
        return match (strtoupper($currencyCode)) {
            'IDR' => 'Rp',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'KRW' => '₩',
            default => $currencyCode,
        };
    }

    /**
     * Apakah sistem menggunakan multiple currencies yang aktif.
     *
     * Saat ini placeholder: selalu false.
     * Nanti diimplementasi dengan mengecek Currency model / SettingService
     * untuk currency yang aktif.
     */
    public static function isUsingMultipleCurrencies(): bool
    {
        return false;
    }

    /**
     * Format persen ke string.
     *
     * Contoh: Number::formatPercent(15.5, 1) → '15.5%'
     */
    public static function formatPercent(null|float|int|string $value, ?int $decimals = null): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $value = (float) $value;

        if ($decimals === null) {
            $decimals = 1;
        }

        return number_format($value, $decimals, '.', ',') . '%';
    }

    /**
     * Parse format mata uang ke nilai numeric.
     *
     * Mengembalikan null jika tidak bisa dipars.
     * Contoh: Number::parseMoney('Rp 1.500.000,00') → 1500000.00
     */
    public static function parseMoney(null|string $text): ?float
    {
        if ($text === null || $text === '') {
            return null;
        }

        // Hilangkan simbol mata uang umum dan separator ribuan
        $cleaned = preg_replace('/[Rp$€£¥₩\s]/u', '', $text);
        $cleaned = str_replace('.', '', $cleaned);
        $cleaned = str_replace(',', '.', $cleaned);

        $result = filter_var($cleaned, FILTER_VALIDATE_FLOAT);

        if ($result === false) {
            return null;
        }

        return $result;
    }
}
