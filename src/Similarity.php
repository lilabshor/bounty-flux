<?php
declare(strict_types=1);

final class Similarity
{
    public static function jaccard(array $setA, array $setB): float
    {
        $a = array_flip($setA);
        $b = array_flip($setB);
        $inter = 0;
        foreach ($a as $tok => $_) {
            if (isset($b[$tok])){
                $inter++;
            }
        }

        $union = count($a) + count($b) - $inter;
        if ($union === 0) {
            return 1.0;
        }
        return $inter / $union;
    }

    public static function cosine(array $tfA, array $tfB) : float
    {
        $dot = 0.0;
        foreach ($tfA as $tok => $n) {
            if (isset($tfB[$tok])) {
                $dot += $n * $tfB[$tok];
            }
        }
        $normA = 0.0;
        foreach($tfA as $n) {
            $normA += $n * $n;
        }
        $normB = 0.0;
        foreach ($tfB as $n) {
            $normB += $n * $n;
        }
        if ($normA == 0.0 && $normB == 0.0) {
            return 1.0;
        }
        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }
        return $dot / (sqrt($normA) * sqrt($normB));
    }

    public static function combined (
        string $textA,
        string $textB,
        float $wJ = Config::WEIGHT_JACCARD,
        float $wC = config::WEIGHT_COSINE
    ): float {
        $j = self::jaccard(Tokenizer::tokenSet($textA), Tokenizer::tokenSet($textB));
        $c = self::cosine(Tokenizer::termFreq($textA), Tokenizer::termFreq($textB));
        return $wJ * $j + $wC * $c;
    }

    public static function explain(string $textA, string $textB): array
    {
        $j = self::jaccard(Tokenizer::tokenSet($textA), Tokenizer::tokenSet($textB));
        $c = self::cosine(Tokenizer::termFreq($textA), Tokenizer::termFreq($textB));
        return [
            'jaccard' => $j,
            'cosine' => $c,
            'combined' => Config::WEIGHT_JACCARD * $j + Config::WEIGHT_COSINE * $c,
        ];

    }

}