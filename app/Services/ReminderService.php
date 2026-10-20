<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Batch;
use App\Models\CashSession;
use App\Models\Cliente;
use App\Models\Deworming;
use App\Models\Reminder;
use App\Models\Vaccination;
use App\Mail\ReminderMail;
use Illuminate\Support\Facades\Mail;

class ReminderService
{
    public function __construct(private readonly StockService $stock)
    {
    }

    public function generate(?int $horizonDays = 7): int
    {
        $created = 0;
        $created += $this->fromVaccinations($horizonDays);
        $created += $this->fromDewormings($horizonDays);
        $created += $this->fromAppointments();
        $created += $this->fromExpiry();
        $created += $this->fromLowStock();
        $created += $this->fromCashDifferences();

        return $created;
    }

    public function sendPending(): int
    {
        $sent = 0;
        $pending = Reminder::query()
            ->where('status', Reminder::STATUS_PENDING)
            ->where('audience', Reminder::AUDIENCE_CUSTOMER)
            ->orderBy('id')
            ->get();

        foreach ($pending as $reminder) {
            if (! $reminder->email) {
                continue;
            }
            try {
                Mail::to($reminder->email)->send(new ReminderMail($reminder));
                $reminder->update([
                    'status' => Reminder::STATUS_SENT,
                    'sent_at' => now(),
                    'channel' => 'email',
                    'error' => null,
                ]);
                $sent++;
            } catch (\Throwable $e) {
                $reminder->update([
                    'status' => Reminder::STATUS_FAILED,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    public function listForUser($user, ?string $audience = null, ?string $type = null)
    {
        return Reminder::query()
            ->with('cliente.persona')
            ->when($audience, fn ($q) => $q->where('audience', $audience))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when(! $user->can('reports.view_all') && ! $user->can('inventory.view'), function ($q) {
                $q->where('audience', Reminder::AUDIENCE_CUSTOMER);
            })
            ->orderByDesc('due_at')
            ->orderByDesc('id')
            ->paginate(30);
    }

    public function dismiss(Reminder $reminder): Reminder
    {
        $reminder->update(['status' => Reminder::STATUS_DISMISSED]);

        return $reminder->fresh();
    }

    public function whatsappUrl(?string $phone, string $text): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (! $digits) {
            return null;
        }
        if (strlen($digits) === 9) {
            $digits = '51'.$digits;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($text);
    }

    private function fromVaccinations(int $days): int
    {
        $until = now()->addDays($days)->toDateString();
        $rows = Vaccination::query()
            ->with('pet.cliente.persona')
            ->whereNotNull('next_due_at')
            ->whereDate('next_due_at', '<=', $until)
            ->get();
        $n = 0;
        foreach ($rows as $row) {
            $cliente = $row->pet?->cliente;
            $msg = 'Recordatorio: vacuna '.$row->vaccine_name.' de '.$row->pet?->nombre.' vence el '.$row->next_due_at->toDateString().'.';
            if ($this->upsertCustomer(Reminder::TYPE_VACUNA, 'vaccination', $row->id, $row->next_due_at->toDateString(), $cliente, 'Vacuna próxima', $msg)) {
                $n++;
            }
        }

        return $n;
    }

    private function fromDewormings(int $days): int
    {
        $until = now()->addDays($days)->toDateString();
        $rows = Deworming::query()
            ->with('pet.cliente.persona')
            ->whereNotNull('next_due_at')
            ->whereDate('next_due_at', '<=', $until)
            ->get();
        $n = 0;
        foreach ($rows as $row) {
            $cliente = $row->pet?->cliente;
            $msg = 'Recordatorio: desparasitación ('.$row->product_name.') de '.$row->pet?->nombre.' vence el '.$row->next_due_at->toDateString().'.';
            if ($this->upsertCustomer(Reminder::TYPE_DESPARASITACION, 'deworming', $row->id, $row->next_due_at->toDateString(), $cliente, 'Desparasitación próxima', $msg)) {
                $n++;
            }
        }

        return $n;
    }

    private function fromAppointments(): int
    {
        $from = now()->startOfDay();
        $to = now()->addDays(2)->endOfDay();
        $rows = Appointment::query()
            ->with(['pet', 'cliente.persona', 'service'])
            ->where('status', Appointment::STATUS_PENDING)
            ->whereBetween('scheduled_at', [$from, $to])
            ->get();
        $n = 0;
        foreach ($rows as $row) {
            $due = $row->scheduled_at->toDateString();
            $msg = 'Cita de '.$row->pet?->nombre.' ('.$row->service?->name.') el '.$row->scheduled_at->toDateTimeString().'.';
            if ($this->upsertCustomer(Reminder::TYPE_CITA, 'appointment', $row->id, $due, $row->cliente, 'Cita veterinaria', $msg)) {
                $n++;
            }
        }

        return $n;
    }

    private function fromExpiry(): int
    {
        $n = 0;
        $alerts = $this->stock->expiryAlerts();
        foreach (array_merge($alerts['vencido'] ?? [], $alerts['vence_30'] ?? []) as $row) {
            $due = $row['expiry_date'] ?? now()->toDateString();
            $title = ($row['expiry_date'] ?? '') < now()->toDateString() ? 'Lote vencido' : 'Lote por vencer';
            $msg = ($row['producto'] ?? 'Producto').' lote '.$row['batch_code'].' vence '.$due.' ('.$row['quantity'].' ud).';
            if ($this->upsertInternal(Reminder::TYPE_VENCIMIENTO, 'batch', (int) $row['batch_id'], $due, $title, $msg)) {
                $n++;
            }
        }

        return $n;
    }

    private function fromLowStock(): int
    {
        $n = 0;
        foreach ($this->stock->lowStock() as $variante) {
            $onHand = (int) $variante->batches->sum('quantity');
            $msg = ($variante->producto?->nombre ?? $variante->sku).' stock '.$onHand.' / mínimo '.$variante->min_stock.'.';
            if ($this->upsertInternal(Reminder::TYPE_STOCK, 'variant', $variante->id, now()->toDateString(), 'Stock mínimo', $msg)) {
                $n++;
            }
        }

        return $n;
    }

    private function fromCashDifferences(): int
    {
        $n = 0;
        $sessions = CashSession::query()
            ->with('user')
            ->whereNotNull('closed_at')
            ->where('closed_at', '>=', now()->subDays(7))
            ->where('difference', '!=', 0)
            ->get();
        foreach ($sessions as $session) {
            $due = $session->closed_at->toDateString();
            $msg = 'Caja #'.$session->id.' ('.$session->user?->name.') diferencia S/ '.$session->difference.'.';
            if ($this->upsertInternal(Reminder::TYPE_CAJA, 'cash_session', $session->id, $due, 'Diferencia de caja', $msg)) {
                $n++;
            }
        }

        return $n;
    }

    private function upsertCustomer(string $type, string $refType, int $refId, string $due, ?Cliente $cliente, string $title, string $message): bool
    {
        $email = $cliente?->persona?->email;
        $whatsapp = $cliente?->persona?->telefono;
        $exists = Reminder::query()
            ->where('type', $type)
            ->where('reference_type', $refType)
            ->where('reference_id', $refId)
            ->whereDate('due_at', $due)
            ->exists();
        if ($exists) {
            return false;
        }
        Reminder::create([
            'type' => $type,
            'audience' => Reminder::AUDIENCE_CUSTOMER,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'cliente_id' => $cliente?->id,
            'title' => $title,
            'message' => $message,
            'due_at' => $due,
            'email' => $email,
            'whatsapp' => $whatsapp,
            'whatsapp_url' => $this->whatsappUrl($whatsapp, $message),
            'status' => Reminder::STATUS_PENDING,
            'channel' => $email ? 'email' : 'whatsapp',
        ]);

        return true;
    }

    private function upsertInternal(string $type, string $refType, int $refId, string $due, string $title, string $message): bool
    {
        $exists = Reminder::query()
            ->where('type', $type)
            ->where('reference_type', $refType)
            ->where('reference_id', $refId)
            ->whereDate('due_at', $due)
            ->exists();
        if ($exists) {
            return false;
        }
        Reminder::create([
            'type' => $type,
            'audience' => Reminder::AUDIENCE_INTERNAL,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'title' => $title,
            'message' => $message,
            'due_at' => $due,
            'status' => Reminder::STATUS_PENDING,
            'channel' => 'internal',
        ]);

        return true;
    }
}
