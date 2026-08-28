<?php

declare(strict_types=1);

namespace App\Services;

/**
 * NumberToWord — konversi angka ke terbilang (Indonesian & Indian format).
 *
 * Diadopsi dari `App_number_to_word.php` PerfexCRM.
 * Dipakai untuk: invoice PDF (terbilang jumlah), dokumen keuangan.
 *
 * REF: docs/analisis-library-perfex.md (App_number_to_word — ✅ ADOPT)
 */
class NumberToWord
{
    /** Indonesian satuan */
    private const ID_UNITS = [
        0 => 'nol', 1 => 'satu', 2 => 'dua', 3 => 'tiga', 4 => 'empat',
        5 => 'lima', 6 => 'enam', 7 => 'tujuh', 8 => 'delapan', 9 => 'sembilan',
    ];

    /** Indonesian belasan */
    private const ID_TEENS = [
        10 => 'sepuluh', 11 => 'sebelas', 12 => 'dua belas', 13 => 'tiga belas',
        14 => 'empat belas', 15 => 'lima belas', 16 => 'enam belas',
        17 => 'tujuh belas', 18 => 'delapan belas', 19 => 'sembilan belas',
    ];

    /** Indonesian puluhan */
    private const ID_TENS = [
        2 => 'dua puluh', 3 => 'tiga puluh', 4 => 'empat puluh', 5 => 'lima puluh',
        6 => 'enam puluh', 7 => 'tujuh puluh', 8 => 'delapan puluh', 9 => 'sembilan puluh',
    ];

    /** Indonesian skala */
    private const ID_SCALES = [
        1_000_000_000 => 'miliar',
        1_000_000 => 'juta',
        1_000 => 'ribu',
        100 => 'ratus',
    ];

    /** Indian numbering system scales */
    private const IN_SCALES = [
        1_00_00_000 => 'crore',
        1_00_000 => 'lakh',
        1_000 => 'thousand',
        100 => 'hundred',
    ];

    /**
     * Konversi angka ke terbilang Indonesian (format Rupiah standar).
     *
     * @param int|float|string $number
     * @param string $currency  suffix mata uang (default: 'rupiah')
     * @return string  contoh: "satu juta dua ratus tiga puluh ribu empat ratus lima puluh enam rupiah"
     */
    public function convert(int|float|string $number, string $currency = 'rupiah'): string
    {
        $num = (int) floor((float) $number);

        if ($num === 0) {
            return 'nol ' . $currency;
        }

        return $this->convertId($num) . ' ' . $currency;
    }

    /**
     * Konversi format Indian (lakh/crore) — opsional untuk multi-currency.
     *
     * @param int|float|string $number
     * @param string $currency
     * @return string
     */
    public function convertIndian(int|float|string $number, string $currency = ''): string
    {
        $num = (int) floor((float) $number);

        if ($num === 0) {
            return 'zero' . ($currency ? ' ' . $currency : '');
        }

        return $this->convertIn($num) . ($currency ? ' ' . $currency : '');
    }

    /**
     * Konversi angka Indonesia (rekursif per skala).
     */
    private function convertId(int $num): string
    {
        if ($num < 10) {
            return self::ID_UNITS[$num];
        }
        if ($num < 20) {
            return self::ID_TEENS[$num];
        }
        if ($num < 100) {
            $tens = (int) ($num / 10);
            $units = $num % 10;
            $prefix = self::ID_TENS[$tens];
            return $units ? $prefix . ' ' . self::ID_UNITS[$units] : $prefix;
        }
        if ($num < 200) {
            return 'seratus' . ($num % 100 ? ' ' . $this->convertId($num % 100) : '');
        }
        if ($num < 1000) {
            $hundreds = (int) ($num / 100);
            $rem = $num % 100;
            $prefix = self::ID_UNITS[$hundreds] . ' ratus';
            return $rem ? $prefix . ' ' . $this->convertId($rem) : $prefix;
        }

        foreach (self::ID_SCALES as $scale => $name) {
            if ($num >= $scale) {
                $count = (int) ($num / $scale);
                $rem = $num % $scale;
                $prefix = ($count === 1 && $scale >= 1000) ? 'se' . $name : $this->convertId($count) . ' ' . $name;
                return $rem ? $prefix . ' ' . $this->convertId($rem) : $prefix;
            }
        }

        return '';
    }

    /**
     * Konversi Indian numbering system (rekursif per skala).
     */
    private function convertIn(int $num): string
    {
        if ($num < 20) {
            $words = [
                0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four',
                5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine',
                10 => 'ten', 11 => 'eleven', 12 => 'twelve', 13 => 'thirteen',
                14 => 'fourteen', 15 => 'fifteen', 16 => 'sixteen',
                17 => 'seventeen', 18 => 'eighteen', 19 => 'nineteen',
            ];
            return $words[$num];
        }
        if ($num < 100) {
            $tensWords = [2 => 'twenty', 3 => 'thirty', 4 => 'forty', 5 => 'fifty',
                6 => 'sixty', 7 => 'seventy', 8 => 'eighty', 9 => 'ninety'];
            $tens = (int) ($num / 10);
            $units = $num % 10;
            return $tensWords[$tens] . ($units ? ' ' . $this->convertIn($units) : '');
        }
        if ($num < 1000) {
            $h = (int) ($num / 100);
            $r = $num % 100;
            return $this->convertIn($h) . ' hundred' . ($r ? ' ' . $this->convertIn($r) : '');
        }

        foreach (self::IN_SCALES as $scale => $name) {
            if ($num >= $scale) {
                $c = (int) ($num / $scale);
                $r = $num % $scale;
                return $this->convertIn($c) . ' ' . $name . ($r ? ' ' . $this->convertIn($r) : '');
            }
        }

        return '';
    }
}