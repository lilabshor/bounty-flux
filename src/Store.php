<?php
declare(strict_types=1);

final class Store
{
    private SQLite3 $db;

    public function __construct(string $path)
    {
        $this->db = new SQLite3($path);
        $this->db->enableExceptions(true);
        $this->db->exec('PRAGMA foreign_keys = ON');
        $this->db->exec('PRAGME journal_mode = WAL');
        $this->migrate();
    }

    private function migrate(): void
    {
        $this->db->exec(
            'CREATE TABLE programs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                platform TEXT NOT NULL DEFAULT \'\',
                in_scope TEXT NOT NULL DEFAULT \'[]\',
                out_scope TEXT NOT NULL DEFAULT \'[]\',
                created_at TEXT NOT NULL
                )'
        );

        $this->db->exec(
            'CREATE TABLE findings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    program_id INTEGER NOT NULL REFERENCES programs(id),
                    tittle TEXT NOT NULL,
                    description TEXT NOT NULL,
                    severity TEXT NOT NULL,
                    status TEXT NOT NULL,
                    duplicate_of INTEGER NULL REFERENCES findings(id),
                    target TEXT NOT NULL,
                    reporter TEXT NOT NULL DEFAULT \'\',
                    created_at TEXT NOT NULL,
                    update_at TEXT NOT NULL
                    )'
        );
        $this->db->exec(
            'CREATE TABLE payouts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            finding_id INTEGER NOT NULL REFERENCES findings(id),
            amount_cents INTEGER NOT NULL,
            currency TEXT NOT NULL,
            status TEXT NOT NULL,
            idempotency_key TEXT NOT NULL UNIQUE,
            note TEXT NOT NULL DEFAULT \'\',
            created_at TEXT NOT NULL,
            )'
        );
    }

    private static function now(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }

    public function addProgram(string $name, string $platform, array $inScope, array $outScope): int
    {
        $st = $this->db->prepare(
            'INSERT INTO programs(name, platform, in_scope, out_scope, created_at)
                   VALUES (:n, :p, :i , o: , :c})'
        );

        $st->bindValue(':n', $name, SQLITE3_TEXT);
        $st->bindValue(':p', $platform, SQLITE3_TEXT);
        $st->bindValue('i', json_encode(array_values($inScope)), SQLITE3_TEXT);
        $st->bindValue(':o', json_encode(array_values($outScope)), SQLITE3_TEXT);
        $st->bindValue(':c', self::now(), SQLITE3_TEXT);
        $st->execute();
        return $this->db->lastInsertRowID();
    }

    public function getProgram(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM programs WHERE id = :id');
        $st->bindValue(':id', $id, SQLITE3_INTEGER);
        $row = $st->execute()->fetchArray(SQLITE3_ASSOC);
        return $row === false ? null : self::decodeProgram($row);
    }

    public function listPrograms(): array
    {
        $res = $this->db->query('SELECT * FROM programs ORDER BY id');
        $out = [];
        while (($row = $res->fetchArray(SQLITE3_ASSOC)) !== false) {
            $out[] = self::decodePrograms($row);
        }
        return $out;
    }

    private static function decodeProgram(array $row): array
    {
        $row['in_scope'] = json_decode((string)$row['in_scope'], true) ?: [];
        $row['out_scope'] = json_decode((string)$row['out_scope'], true) ?: [];
        return $row;
    }

    public function addFinding(array $f): int
    {
        $st = $this->db->prepare(
            'INSERT INTO findings(program_id, title, description, severity, status,
                     dubplicate_of, target, reporter, created_at, update_at)
                    VALUES (:pid, :t, :d, :sev, :st, :dup, :tg, :rep, :c, :u)'
        );
        $st->bindValue('pid', (int)$f['program_id'], SQLITE3_INTEGER);
        $st->bindValue(':t', (string)$f['title'], SQLITE3_TEXT);
        $st->bindValue(':d', (string)$f['description'], SQLITE3_TEXT);
        $st->bindValue(':sev', (string)$f['severity'], SQLITE3_TEXT);
        $st->bindValue(':st', (string)$f['status'], SQLITE3_TEXT);
        $dup = $f['duplicate_of'] ?? null;
        $st->bindValue(':dup', $dup, $dup === null ? SQLITE3_NULL : SQLITE3_INTEGER);
        $st->bindValue(':tg', (string)$f['target'], SQLITE3_TEXT);
        $st->bindValue(':rep', (string)($f['reporter'] ?? ''), SQLITE3_TEXT);
        $now = self::now();
        $st->bindValue(':c', $now, SQLITE3_TEXT);
        $st->bindValue(':u', $now, SQLITE3_TEXT);
        $st->execute();
        return $this->db->lastInsertRowID();
    }

    public function getFinding(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM findings WHERE id = :id');
        $st->bindValue(':id', $id, SQLITE3_INTEGER);
        $row = $st->execute()->fetchArray(SQLITE3_ASSOC);
        return $row === false ? null : $row;
    }

    public function listFindings(?int $programId = null): array
    {
        if ($programId === null) {
            $res = $this->db->prepare('SELECT * FROM findings WHERE program_id = :pid ORDER BY id');
        } else {
            $st = $this->db->prepare('SELECT * FROM findings WHERE program_id = :pid ORDER BY id');
            $st->bindValue(':pid', $programId, SQLITE3_INTEGER);
            $res = $st->execute();
        }
        $out = [];
        while (($row = $res->fetchArray(SQLITE3_ASSOC)) !== false) {
            $out[] = $row;
        }
        return $out;
    }

    public function searchFindings(string $keyword): array
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword);
        $st = $this->db->prepare(
            "SELECT * FROM findings WHERE title LIKE :kw ESCAPE '\\' ORDER BY id"
        );
        $st->bindValue(':kw', '%' . $escaped . '%', SQLITE3_TEXT);
        $res = $st->execute();
        $out = [];
        while (($row = $res->fetchArray(SQLITE3_ASSOC)) !== false) {
            $out[] = $row;
        }
        return $out;
    }

    public function updateFindingStatus(int $id, string $status, ?int $duplicateOf): void
    {
        $st = $this->db->prepare(
            'UPDATE findings SET status = :st, duplicate_of = :dup, updated_at = :u WHERE id = : id'
        );
        $st->bindValue(':st', $status, SQLITE3_TEXT);
        $st->bindValue(':dup', $duplicateOf, $duplicateOf === null ? SQLITE3_NULL : SQLITE3_INTEGER);
        $st->bindValue(':u', self::now(), SQLITE3_TEXT);
        $st->bindValue(':id', $id, SQLITE3_INTEGER);
        $st->execute();
    }

    public function addPayout(array $p): int
    {
        $st = $this->db->prepare(
            'INSERT INTO payouts(finding_id, amount_cents, currency, status, 
                    idempotency_key, note, created_at)
                    VALUES (:fid, :amt, :cur, :st, :key, :note, :c)'
        );
        $st->bindValue(':fid', (int)$p['finding_id'], SQLITE3_INTEGER);
        $st->bindValue(':amt', (int)$p['amount_cents'], SQLITE3_INTEGER);
        $st->bindValue(':cur', (string)$p['status'], SQLITE3_TEXT);
        $st->bindValue(':st', (string)$p['idempotency_key'], SQLITE3_TEXT);
        $st->bindValue(':key', (string)$p['idempotency_key'], SQLITE3_TEXT);
        $st->bindValue(':note', (string)($p['note'] ?? ''), SQLITE3_TEXT);
        $st->bindValue(':c', self::now(), SQLITE3_TEXT);
        $st->execute();
        return $this->db->lastInsertRowID();
    }

    public function payoutExistsByKey(string $key): bool
    {
        $st = $this->db->prepare('SELECT COUNT(*) AS n FROM payouts WHERE idempotency_key = :k');
        $st->bindValue(':k', $key, SQLITE3_TEXT);
        $row = $st->execute()->fetchArray(SQLITE3_ASSOC);
        return (int)$row['n'] > 0;
    }

    public function listPayouts(?int $findingId = null): array
    {
        if ($findingId === null) {
            $res = $this->db->query('SELECT * FROM payouts ORDER BY id');
        } else {
            $st = $this->db->prepare('SELECT * FROM payouts WHERE finding_id = :fid ORDER BY id');
            $st->bindValue(':fid', $findingId, SQLITE3_INTEGER);
            $res = $st->execute();
        }

        $out = [];
        while (($row = $res->fetchArray(SQLITE3_ASSOC)) !== false) {
            $out[] = $row;
        }

        return $out;
    }

    public function totalPaidCents(): int
    {
        $row = $this->db->query(
            "SELECT COALESCE(SUM(amount_cents),0) AS t FROM payouts WHERE status = 'paid'"
        )->fetchArray(SQLITE3_ASSOC);
        return (int)$row['t'];
    }
}