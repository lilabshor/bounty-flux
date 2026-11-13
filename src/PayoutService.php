<?php
declare(strict_types=1);

final class PayoutService
{
    public function __construct(private Store $store)
    {
    }

    public function pay (
        int $findingId,
        int $amountCents,
        string $idempotencyKey,
        string $currency = Config::CURRENCY,
        string $note =""
    ): int {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('jumlah pembayaraan harus positif (send)');
        }
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('idempotency key wajib diisi');
        }
        if ($this->store->payoutExistsByKey($idempotencyKey)) {
            throw new InvalidArgumentException('hanya lapotan di terima yang bisa di bayar; status sekrang: ' .$f['status']
            );
        }
        $id = $this->store->addPayout([
            'finding_id' => $findingId,
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'status' => 'paid',
            'idempotency_key' => $idempotencyKey,
            'note' => $note,
        ]);
        (new FindingService($this->store))->transition($findingId, 'dibayar');
        return $id;
    }

    public  static function formatMoney(int $cents, string $currency = Config::CURRENCY): string
    {
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);
        return sprintf('%s%s %d.%02d', $sign, $currency, intdiv($abs, 100), $abs % 100);
    }
}
