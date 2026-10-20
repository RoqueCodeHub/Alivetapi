import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

@Component({
  selector: 'app-pos',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './pos.html',
  styleUrl: './pos.scss',
})
export class Pos {
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);
  readonly session = signal<any>(null);
  readonly pending = signal<any[]>([]);
  readonly selected = signal<any>(null);
  readonly voucher = signal<any>(null);
  readonly message = signal('');
  readonly payForm = this.fb.nonNullable.group({
    invoice_type: [''],
    efectivo: [0],
    yape: [0],
  });
  readonly openForm = this.fb.nonNullable.group({
    opening_amount: [0, Validators.required],
  });
  readonly closeForm = this.fb.nonNullable.group({
    counted_amount: [0, Validators.required],
    notes: [''],
  });

  constructor() {
    this.reloadSession();
    this.reloadPending();
  }

  open(): void {
    this.http.post<ApiResponse<any>>(`${environment.apiBaseUrl}/cash-sessions/open`, this.openForm.getRawValue()).subscribe({
      next: (res) => {
        this.session.set(res.data);
        this.message.set('Caja abierta');
      },
      error: (err) => this.message.set(err.message || 'No se pudo abrir la caja'),
    });
  }

  close(): void {
    const id = this.session()?.id;
    if (!id) {
      return;
    }
    this.http
      .post<ApiResponse<any>>(`${environment.apiBaseUrl}/cash-sessions/${id}/close`, this.closeForm.getRawValue())
      .subscribe({
        next: (res) => {
          this.session.set(null);
          this.message.set(`Caja cerrada. Diferencia S/ ${res.data?.difference ?? 0}`);
        },
        error: (err) => this.message.set(err.message || 'No se pudo cerrar la caja'),
      });
  }

  reloadSession(): void {
    this.http.get<ApiResponse<any>>(`${environment.apiBaseUrl}/cash-sessions/current`).subscribe({
      next: (res) => this.session.set(res.data),
    });
  }

  reloadPending(): void {
    this.http
      .get<ApiResponse<{ items: any[] }>>(`${environment.apiBaseUrl}/sales`, {
        params: { payment_status: 'pendiente', status: 'pending', per_page: '50' },
      })
      .subscribe({
        next: (res) => this.pending.set(res.data?.items ?? []),
      });
  }

  select(sale: any): void {
    this.http.get<ApiResponse<any>>(`${environment.apiBaseUrl}/sales/${sale.id}`).subscribe({
      next: (res) => {
        this.selected.set(res.data);
        this.voucher.set(null);
        this.payForm.patchValue({ efectivo: Number(res.data.total), yape: 0 });
      },
    });
  }

  pay(): void {
    const sale = this.selected();
    if (!sale) {
      return;
    }
    const pay = this.payForm.getRawValue();
    const payments: { method: string; amount: number }[] = [];
    if (pay.efectivo > 0) {
      payments.push({ method: 'efectivo', amount: pay.efectivo });
    }
    if (pay.yape > 0) {
      payments.push({ method: 'yape', amount: pay.yape });
    }
    this.http
      .post<ApiResponse<any>>(`${environment.apiBaseUrl}/sales/${sale.id}/pay`, {
        invoice_type: pay.invoice_type || null,
        payments,
      })
      .subscribe({
        next: (res) => {
          this.message.set(res.message);
          this.selected.set(null);
          this.reloadPending();
          this.http.get<ApiResponse<any>>(`${environment.apiBaseUrl}/sales/${res.data.id}/receipt`).subscribe({
            next: (ticket) => this.voucher.set(ticket.data),
          });
        },
        error: (err) => this.message.set(err.message || 'No se pudo cobrar'),
      });
  }

  print(): void {
    window.print();
  }
}
