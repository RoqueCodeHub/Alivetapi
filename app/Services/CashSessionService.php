<?php

namespace App\Services;

use App\Exceptions\SaleException;
use App\Models\CashSession;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CashSessionService
{
    public function current(User $user): ?CashSession
    {
        return CashSession::query()
            ->where('user_id', $user->id)
            ->whereNull('closed_at')
            ->latest('id')
            ->first();
    }

    public function open(User $user, float $openingAmount): CashSession
    {
        if ($this->current($user)) {
            throw new SaleException('Ya tienes una caja abierta.');
        }

        return CashSession::create([
            'user_id' => $user->id,
            'opened_at' => now(),
            'opening_amount' => round($openingAmount, 2),
        ]);
    }

    public function close(CashSession $session, float $counted, ?string $notes, User $user): CashSession
    {
        if ($session->user_id !== $user->id && ! $user->can('reports.view_all')) {
            throw new SaleException('No puedes cerrar la caja de otro usuario.');
        }
        if (! $session->isOpen()) {
            throw new SaleException('La caja ya está cerrada.');
        }

        return DB::transaction(function () use ($session, $counted, $notes) {
            $expected = $this->expectedAmount($session->fresh(['sales.payments']));
            $counted = round($counted, 2);
            $session->update([
                'closed_at' => now(),
                'expected_amount' => $expected,
                'counted_amount' => $counted,
                'difference' => round($counted - $expected, 2),
                'close_notes' => $notes,
            ]);

            return $session->fresh('user');
        });
    }

    public function expectedAmount(CashSession $session): float
    {
        $session->loadMissing(['sales.payments', 'sales.returns']);
        $net = 0.0;
        foreach ($session->sales as $sale) {
            if ($sale->isVoided() || $sale->isPending()) {
                continue;
            }
            $net += $sale->cashNet();
            foreach ($sale->returns as $return) {
                if ($return->refund_method === 'efectivo') {
                    $net -= (float) $return->refund_total;
                }
            }
        }

        return round((float) $session->opening_amount + $net, 2);
    }
}
