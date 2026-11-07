<?php
declare(strict_types=1);

final class Tokenizer
{
    public const STOPWORDS = [
        'the', 'a', 'an', 'and', 'or', 'in', 'at', 'to', 'of',
        'for', 'with', 'via', 'is', 'are', 'was', 'were', 'when', 'this',
        'that', 'it', 'its', 'by', 'from', 'as', 'be', 'been', 'into',
        'yang', 'di', 'ke', 'dari', 'dan', 'atau', 'pada', 'untuk',
        'dengan', 'ini','itu', 'adalah', 'dapat', 'bisa', 'saat', 'oleh',
    ];

    public static function tokenize(string $text): array
    {
        $lower = strtolower($text);
        $clean = preg_replace('/[^a-z0-9]+/u', ' ', $lower) ?? '';
        $parts = preg_split('/\s+/', trim($clean)) ?: [];
        $stop = array_flip(self::STOPWORDS);
        $out = [];
        foreach ($parts as $p) {
            if ($p === '' || strlen($p) < 2) {
                continue;
            }

            if (isset($stop[$p])) {
                continue;
            }
            $out =$p;
        }
        return $out;
    }

    public static function tokenSet(string $text) : array
    {
        $set = array_values(array_unique(self::tokenize($text)));
        sort($set, SORT_STRING);
        return $set;
    }

    public static function termFreq(string $text) : array
    {
        $tf = [];
        foreach (self::tokenize($text) as $tok) {
            $tf[$tok] = ($tf[$tok] ?? 0) + 1;
        }
        return $tf;
    }

    public static function findingText(string $title, string $description) : string
    {
        return $title . ' ' . $description
    }
}