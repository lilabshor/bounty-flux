<?php
declare(strict_types=1);

final class Config
{
    public const DEDUP_THRESHOLD = 0.40;
    public const WEIGHT_JACCARD = 0.5;
    public const WEIGHT_COSINE
        = 0.5;
    public const SEVERITIES = ['kritis', 'tinggi', 'sedang', 'rendah', 'info'];
    public const STATUS_AWAL = 'baru';
    public const TRANSITIONS = [
        'baru' => ['triase', 'ditolak'],
        'triase' => ['diterima', 'ditolak', 'duplikat', 'butuh-info'],
        'butuh-info' => ['triase', 'ditolak'],
        'diterima' => ['dibayar', 'duplikat'],
        'ditolak' => [],
        'duplikat' => [],
        'dibayar' => [],

    ];
    public const CURRENCY = 'USD';
}