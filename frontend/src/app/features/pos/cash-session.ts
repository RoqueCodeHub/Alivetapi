import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

@Component({
  selector: 'app-cash-session',
  imports: [ReactiveFormsModule],
  template: `
    <h1>Caja</h1>
    @if (session(); as box) {
      <p>Abierta desde {{ box.opened_at }} — inicial S/ {{ box.opening_amount }}</p>
      <p>Esperado S/ {{ box.expected_amount }}</p>
      <form [formGroup]="closeForm" (ngSubmit)="close(box.id)">
        <input type="number" placeholder="Monto contado" formControlName="counted_amount" />
        <input placeholder="Notas" formControlName="notes" />
        <button type="submit">Cerrar caja</button>
      </form>
    } @else {
      <form [formGroup]="openForm" (ngSubmit)="open()">
        <input type="number" placeholder="Monto inicial" formControlName="opening_amount" />
        <button type="submit">Abrir caja</button>
      </form>
    }
    @if (message()) {
      <p>{{ message() }}</p>
    }
    <h2>Ventas por pagar</h2>
    <ul>
      @for (sale of pending(); track sale.id) {
        <li>{{ sale.document }} — S/ {{ sale.total }} — {{ sale.cajero || 'otro usuario' }}</li>
      } @empty {
        <li>No hay ventas pendientes.</li>
      }
    </ul>
  `,
})
export class CashSessionPage {
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);
  readonly session = signal<any>(null);
  readonly pending = signal<any[]>([]);
  readonly message = signal('');
  readonly openForm = this.fb.nonNullable.group({
    opening_amount: [0, Validators.required],
  });
  readonly closeForm = this.fb.nonNullable.group({
    counted_amount: [0, Validators.required],
    notes: [''],
  });

  constructor() {
    this.reload();
  }

  reload(): void {
    this.http.get<ApiResponse<any>>(`${environment.apiBaseUrl}/cash-sessions/current`).subscribe({
      next: (res) => this.session.set(res.data),
    });
    this.http
      .get<ApiResponse<{ items: any[] }>>(`${environment.apiBaseUrl}/sales`, {
        params: { payment_status: 'pendiente', per_page: '50' },
      })
      .subscribe({
        next: (res) => this.pending.set(res.data?.items ?? []),
      });
  }

  open(): void {
    this.http.post<ApiResponse<any>>(`${environment.apiBaseUrl}/cash-sessions/open`, this.openForm.getRawValue()).subscribe({
      next: (res) => {
        this.session.set(res.data);
        this.message.set('Caja abierta');
      },
      error: (err) => this.message.set(err.message || 'Error'),
    });
  }

  close(id: number): void {
    this.http
      .post<ApiResponse<any>>(`${environment.apiBaseUrl}/cash-sessions/${id}/close`, this.closeForm.getRawValue())
      .subscribe({
        next: (res) => {
          this.message.set(`Cerrada. Diferencia S/ ${res.data.difference}`);
          this.session.set(null);
        },
        error: (err) => this.message.set(err.message || 'Error'),
      });
  }
}
