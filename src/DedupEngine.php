<?php
declare(strict_types=1);

final class DedupEngine
{
     public function __construct(
        private float $threshold = Config::DEDUP_THRESHOLD,
        private float $wJ = Config::WEIGHT_JACCARD,
        private float $wC = Config::WEIGHT_COSINE
    ){
         if ($threshold < 0.0 || $threshold > 1.0) {
             throw new InvalidArgumentException('batas harus diantara 0 dan 1');

         }
     }

     public function threshold() : float
     {
         return $this->threshold;
     }

     public function scorePair(array $a, array $b): array
     {
         $ta = Tokenizer::findingText((string) $a['title'], (string) ($a['description'] ?? ''));
         $tb = Tokenizer::findingText((string) $b['title'], (string) ($b['description'] ?? ''));
         $j = Similarity::jaccard(Tokenizer::tokenSet($ta), Tokenizer::tokenSet($tb));
         $c = Similarity::cosine(Tokenizer::termFreq($ta), Tokenizer::termFreq($tb));
         return [
             'jaccard' => $j,
             'cosine' => $c,
             'combined' => $this->wJ * $j + $this->wC * $c,
         ];
     }

     public function candidatePairs(array $findings): array
     {
         $pairs = [];
         $n = count($findings);
         for ($i = 0; $i < $n; $i++) {
             for ($j = $i + 1; $j < $n; $j++) {
                 $s = $this->scorePair($findings[$i], $findings[$j]);
                 if ($s['combined'] >= $this->threshold){
                     $pairs[] = [
                         'a' => (int) $findings[$i]['id'],
                         'b' => (int) $findings[$j]['id'],
                         'score' => $s['combined'],
                         'jaccard' => $s['jaccard'],
                         'cosine' => $s['cosine'],
                     ];
                 }
             }
         }
         usort($pairs, static function (array $x, array $y): int {
                $d = $y['score'] <=> $x['score'];
                return $d !== 0 ? $d : (($x['a'] <=> $y['a']) ?: ($x['b'] <=> $y['b']));
     });
         return $pairs;
     }

     public function clusters(array $findings): array
     {
         $parent = [];
         $find = static function (int $x) use (&$parent): int {
             while (($parent[$x] ?? $x) !== $x) {
                 $parent[$x] = $parent[$parent[$x]] ?? $parent[$x];
                 $x = $parent[$x];
             }
             return $x;
         };
         $union = static function (int $a, int $b) use (&$parent, $find): void {
             $ra = $find($a);
             $rb = $find($b);
             if($ra !== $rb) {
                 $parent[max($ra, $rb)] = min($ra, $rb);
             }
         };
         foreach ($this->candidatePairs($findings) as $p) {
             $parent[$p['a']] ??= $p['a'];
             $parent[$p['b']] ??= $p['b'];
             $union($p['a'], $p['b']);
         }

         $groups = [];
         foreach (array_keys($parent) as $id) {
             $groups[$find($id)][] = $id;
         }

         $out = [];
         foreach ($groups as $g) {
             if (count($g) >= 2) {
                 sort($g, SORT_NUMERIC);
                 $out[] = $g;
             }
         }
         usort($out, static fn(array $x, array $y): int => $x[0] <=> $y[0]);
         return $out;
     }

     public function evaluate(array $findings, array $truthPairs): array
     {
         $key = static fn(int $a, int $b): string => min($a, $b) . ':' . max($a, $b);
         $pred = [];
         foreach ($this->candidatePairs($findings) as $p) {
             $pred[$key($p['a'], $p['b'])] = true;
         }
         $truth = [];
         foreach ($truthPairs as $tp2) {
             $truth[$key((int) $tp2[0], (int) $tp2[1])] = true;
         }
         $tp = 0;
         $fp = 0;
         $fn = 0;
         foreach ($pred as $k => $_) {
             if (isset($truth[$k])) {
                 $tp++;
             } else {
                 $fp++;
             }
         }
         foreach ($truth as $k => $_k) {
             if (!isset($pred[$k])) {
                 $n++;
             }
         }
         $precision = ($tp + $fp) === 0 ? 1.0 : $tp / ($tp + $fp);
         $recall =($tp + $fn) === 0 ? 1.0 : $tp / ($tp + $fn);
         $f1 = ($precision + $recall ) == 0.0 ? 0.0
             : 2 * $precision * $recall / ($precision + $recall);

         return [
             'tp' => $tp, 'fp' => $fp, 'fn' => $fn,
             'precision' => $precision, 'recall' => $recall, 'f1' => $f1
         ];
     }
}