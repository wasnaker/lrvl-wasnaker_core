<?php

declare(strict_types=1);

namespace App\Support\Helpers;

/**
 * Helper string yang diadopsi dari func_helper.php PerfexCRM.
 *
 * Fungsi yang sudah ada di Laravel (Str::startsWith, Arr::flatten, dst.)
 * tidak diulang di sini; gunakan Laravel native langsung.
 *
 * Fungsi di bawah ini tidak tersedia di Laravel native dan tetap dibutuhkan
 * oleh business logic hasil porting.
 *
 * REF: docs/porting-helper-implementasi.md (func_helper.php)
 */
class Str
{
    /**
     * Apakah string dimulai dengan substring tertentu.
     *
     * @deprecated Gunakan Illuminate\Support\Str::startsWith() langsung.
     */
    public static function startsWith(string $haystack, string $needle): bool
    {
        return \Illuminate\Support\Str::startsWith($haystack, $needle);
    }

    /**
     * Apakah string diakhiri dengan substring tertentu.
     *
     * @deprecated Gunakan Illuminate\Support\Str::endsWith() langsung.
     */
    public static function endsWith(string $haystack, string $needle): bool
    {
        return \Illuminate\Support\Str::endsWith($haystack, $needle);
    }

    /**
     * Return substring setelah posisi pertama `needle`.
     *
     * Contoh: Str::strafter('foo-bar-baz', 'foo-') → 'bar-baz'
     * Kalau needle tidak ditemukan, return string kosong.
     */
    public static function strafter(string $haystack, string $needle): string
    {
        $pos = strpos($haystack, $needle);

        if ($pos === false) {
            return '';
        }

        return substr($haystack, $pos + strlen($needle));
    }

    /**
     * Return substring sebelum posisi pertama `needle`.
     *
     * Contoh: Str::strbefore('foo-bar-baz', '-bar') → 'foo'
     * Kalau needle tidak ditemukan, return string asal utuh.
     */
    public static function strbefore(string $haystack, string $needle): string
    {
        $pos = strpos($haystack, $needle);

        if ($pos === false) {
            return $haystack;
        }

        return substr($haystack, 0, $pos);
    }

    /**
     * Ambil substring di antara dua delimiter.
     *
     * Contoh: Str::get_string_between('foo[bar]baz', '[', ']') → 'bar'
     * Kalau delimiter tidak ditemukan, return null.
     */
    public static function get_string_between(string $input, string $start, string $end): ?string
    {
        $pos = strpos($input, $start);

        if ($pos === false) {
            return null;
        }

        $afterStart = substr($input, $pos + strlen($start));
        $endPos = strpos($afterStart, $end);

        if ($endPos === false) {
            return null;
        }

        return substr($afterStart, 0, $endPos);
    }

    /**
     * Slugify string → lowercase, ganti spasi/underscore dengan dash,
     * hilangkan karakter non-alphanumeric (selain dash), ratakan dash ganda.
     *
     * Di Perfex dikenal sebagai `sluq_it`.
     *
     * Contoh: Str::sluq_it('Hello  World!') → 'hello-world'
     */
    public static function sluq_it(string $str): string
    {
        // Lowercase dan trim
        $str = strtolower(trim($str));

        // Hilangkan karakter non-alphanumeric, kecuali spasi dan dash
        $str = preg_replace('/[^\w\s-]/u', '', $str);

        // Ganti spasi dan underscore dengan dash
        $str = preg_replace('/[\s_]+/u', '-', $str);

        // Ratakan dash berulang dan trim dash di ujung
        $str = preg_replace('/-+/u', '-', $str);

        return trim($str, '-');
    }

    /**
     * Apakah nilai ada di array multidimensi (rekursif,ゆるい比较).
     *
     * @param mixed $needle Nilai yang dicari
     * @param array $haystack Array (bisa nested) untuk dicari
     * @param bool $strict Gunakan === (true) atau == (false)
     */
    public static function in_array_multidimensional(mixed $needle, array $haystack, bool $strict = false): bool
    {
        foreach ($haystack as $item) {
            if (is_array($item)) {
                if (self::in_array_multidimensional($needle, $item, $strict)) {
                    return true;
                }
            } elseif ($strict ? $item === $needle : $item == $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Flatten array multidimensi ke satu dimensi (rekursif, shallow-first).
     *
     * Perbedaan dengan Arr::flatten: fungsi ini mempertahankan key string
     * yang bukan numeric (di-level yang di-flatten).
     */
    public static function array_flatten(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = array_merge($result, self::array_flatten($value));
            } else {
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * Konversi array (asosiatif) ke stdClass object.
     * Array kosong menghasilkan null.
     */
    public static function array_to_object(array $array): ?object
    {
        if (empty($array)) {
            return null;
        }

        return json_decode(json_encode($array, JSON_THROW_ON_ERROR));
    }

    /**
     * Similarity dua string dalam persen (0–100).
     *
     * Wrapper atas similar_text() PHP.
     */
    public static function similarity(string $str1, string $str2): float
    {
        similar_text($str1, $str2, $percent);

        return (float) $percent;
    }
}
