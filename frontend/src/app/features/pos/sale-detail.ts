import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-sale-detail',
  imports: [ReactiveFormsModule, RouterLink],
  template: `
    <p><a routerLink="/app/ventas">Volver</a></p>
    <h1>Ticket {{ receipt()?.document }}</h1>
    @if (receipt(); as ticket) {
      <p>{{ ticket.fecha }} — {{ ticket.cajero }} — {{ ticket.status }}</p>
      <ul>
        @for (item of ticket.items; track $index) {
          <li>{{ item.description }} x{{ item.quantity }} = S/ {{ item.line_total }}</li>
        }
      </ul>
      <p>Total S/ {{ ticket.total }} — vuelto S/ {{ ticket.change_amount }}</p>
      @if (ticket.invoice; as inv) {
        <p>CPE {{ inv.document }} ({{ inv.document_type }}) — IGV S/ {{ inv.igv }} — {{ inv.status }}</p>
      }
    }
    @if (auth.hasPermission('invoicing.emit') && receipt()?.status === 'completed' && !receipt()?.invoice) {
      <button type="button" (click)="emit('boleta')">Emitir boleta</button>
      <button type="button" (click)="emit('factura')">Emitir factura</button>
    }
    @if (auth.hasPermission('sales.void') && receipt()?.status === 'completed') {
      <form [formGroup]="voidForm" (ngSubmit)="voidSale()">
        <input placeholder="Motivo de anulación" formControlName="reason" />
        <button type="submit">Anular</button>
      </form>
    }
    @if (receipt()?.status === 'completed') {
      <form [formGroup]="returnForm" (ngSubmit)="returnSale()">
        <input placeholder="ID ítem" formControlName="sale_item_id" />
        <input type="number" formControlName="quantity" />
        <input placeholder="Motivo" formControlName="reason" />
        <button type="submit">Devolver</button>
      </form>
    }
    @if (message()) {
      <p>{{ message() }}</p>
    }
  `,
})
export class SaleDetail {
  private readonly http = inject(HttpClient);
  private readonly route = inject(ActivatedRoute);
  private readonly fb = inject(FormBuilder);
  readonly auth = inject(AuthService);
  readonly receipt = signal<any>(null);
  readonly message = signal('');
  readonly voidForm = this.fb.nonNullable.group({
    reason: ['', Validators.required],
  });
  readonly returnForm = this.fb.nonNullable.group({
    sale_item_id: ['', Validators.required],
    quantity: [1, Validators.required],
    reason: ['', Validators.required],
  });

  constructor() {
    this.load();
  }

  load(): void {
    const id = this.route.snapshot.paramMap.get('id');
    this.http.get<ApiResponse<any>>(`${environment.apiBaseUrl}/sales/${id}/receipt`).subscribe({
      next: (res) => this.receipt.set(res.data),
    });
  }

  emit(type: 'boleta' | 'factura'): void {
    const id = this.route.snapshot.paramMap.get('id');
    this.http.post<ApiResponse<any>>(`${environment.apiBaseUrl}/sales/${id}/invoices`, { type }).subscribe({
      next: (res) => {
        this.message.set(res.message);
        this.load();
      },
      error: (err) => this.message.set(err.message || 'No se pudo emitir'),
    });
  }

  voidSale(): void {
    const id = this.route.snapshot.paramMap.get('id');
    this.http.post(`${environment.apiBaseUrl}/sales/${id}/void`, this.voidForm.getRawValue()).subscribe({
      next: () => {
        this.message.set('Anulada');
        this.load();
      },
      error: (err) => this.message.set(err.message || 'Error'),
    });
  }

  returnSale(): void {
    const id = this.route.snapshot.paramMap.get('id');
    const value = this.returnForm.getRawValue();
    this.http
      .post(`${environment.apiBaseUrl}/sales/${id}/returns`, {
        reason: value.reason,
        items: [{ sale_item_id: value.sale_item_id, quantity: value.quantity }],
      })
      .subscribe({
        next: () => {
          this.message.set('Devolución registrada');
          this.load();
        },
        error: (err) => this.message.set(err.message || 'Error'),
      });
  }
}
