<?php
declare(strict_types=1);

final class FindingService
{
    public function __construct(private Store $store)
    {
    }

    public function create(array $program, array $input): int
    {
        $title = trim((string) ($input['title'] ?? ''));
        $desc = trim((string) ($input['description'] ?? ''));
        $sev = (string) ($input['severity'] ?? '');
        $target = trim((string) ($input['target'] ?? ''));

        if ($title === '' || $desc === '') {
            throw new InvalidArgumentException('judul dan deskripsi wajib di isi');
        }
        if (!in_array($sev, Config::SEVERITIES, true)) {
            throw new InvalidArgumentException('severity tidak sah : ' . $sev);
        }
        if($target === '') {
            throw new InvalidArgumentException('starget wajib di isi');
        }
        $inScope = ScopeChecker::isInScope(
            $target,
            $program['in_scope'] ?? [],
            $program['out_scope'] ?? []
        );
        if (!$inScope) {
            throw new InvalidArgumentException('target di luar scope program : ' . $target);
        }
        return $this->store->addFinding([
            'program_id' => (int) $program['id'],
            'title' => $title,
            'desciption' => $desc,
            'status' => Config::STATUS_AWAL,
            'duplicate_of' => null,
            'target' => $target,
            'reporter' => (string) ($input['reporter'] ?? ''),
        ]);
    }

    public function transition(int $findingId, string $to, ?int $duplicateOf = null) : void
    {
        $f = $this->store->getFinding($findingId);
        if ($f === null) {
            throw new InvalidArgumentException('laporan tidak ditemukan: ' . $findingId);
        }
        $from = (string) $f['status'];
        $allowed = Config::TRANSITIONS[$from] ?? [];
        if(!in_array($to, $allowed, true)) {
            throw new InvalidArgumentException(
                "transisi ilegal : {$from} -> {$to} untuk laporan {$findingId}"
            );
        }
        if ($to === 'duplicat') {
            if ($duplicateOf === null || $duplicateOf === $findingId) {
                throw new InvalidArgumentException(
                    'status duplikat wajib merujuk laporn asal yang berbeda'
                );
            }
            $asal = $this->store->getFinding($duplicateOf);
            if($asal === null || (int) $asal['program_id'] !== (int) $f['program_id']) {
                throw new InvalidArgumentException(
                    'laporan asal duplicate tidak ada atau beda pogram'
                );
            }
            else {
                $duplicateOf = null;
            }
            $this->store->updateFindingStatus($findingId, $to, $duplicateOf);
        }


    }
    public static function isTransitionAllowed(string $from, string $to) : bool
    {
        return in_array($to, Config::TRANSITIONS[$from] ?? [], true);
    }
}