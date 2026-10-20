import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

@Component({
  selector: 'app-stock-detail',
  imports: [ReactiveFormsModule, RouterLink],
  template: `
    <p><a routerLink="/app/stock">Volver</a></p>
    <h1>Lotes</h1>
    @if (data(); as stock) {
      <p>{{ stock.variant?.producto?.nombre }} — on hand {{ stock.on_hand }}</p>
      <p>FEFO: el lote que vence primero aparece primero.</p>
      <ul>
        @for (batch of stock.batches; track batch.id) {
          <li>
            {{ batch.batch_code }} — vence {{ batch.expiry_date || 's/f' }} — costo {{ batch.cost ?? '—' }}
            — precio {{ batch.sale_price ?? stock.variant?.sale_price ?? '—' }} — {{ batch.quantity }}
            — {{ batch.estado || '' }}
          </li>
        }
      </ul>
    }
    <h2>Ajuste</h2>
    <form [formGroup]="adjustForm" (ngSubmit)="adjust()">
      <input placeholder="ID lote" formControlName="batch_id" />
      <select formControlName="direction">
        <option value="entrada">Entrada</option>
        <option value="salida">Salida</option>
      </select>
      <input type="number" placeholder="Cantidad" formControlName="quantity" />
      <input placeholder="Motivo" formControlName="reason" />
      <button type="submit">Registrar ajuste</button>
    </form>
    <h2>Abrir caja</h2>
    <form [formGroup]="boxForm" (ngSubmit)="openBox()">
      <input placeholder="ID lote" formControlName="batch_id" />
      <input type="number" placeholder="Cajas" formControlName="boxes" />
      <button type="submit">Convertir a unidades</button>
    </form>
    @if (message()) {
      <p>{{ message() }}</p>
    }
  `,
})
export class StockDetail {
  private readonly http = inject(HttpClient);
  private readonly route = inject(ActivatedRoute);
  private readonly fb = inject(FormBuilder);
  readonly data = signal<any>(null);
  readonly message = signal('');
  readonly adjustForm = this.fb.nonNullable.group({
    batch_id: ['', Validators.required],
    direction: ['entrada'],
    quantity: [1, Validators.required],
    reason: ['', Validators.required],
  });
  readonly boxForm = this.fb.nonNullable.group({
    batch_id: ['', Validators.required],
    boxes: [1, Validators.required],
  });

  constructor() {
    this.load();
  }

  load(): void {
    const id = this.route.snapshot.paramMap.get('id');
    this.http.get<ApiResponse<any>>(`${environment.apiBaseUrl}/stock/${id}`).subscribe({
      next: (res) => this.data.set(res.data),
    });
  }

  adjust(): void {
    this.http.post<ApiResponse>(`${environment.apiBaseUrl}/stock/adjustments`, this.adjustForm.getRawValue()).subscribe({
      next: () => {
        this.message.set('Ajuste registrado');
        this.load();
      },
      error: (err) => this.message.set(err.message || 'Error'),
    });
  }

  openBox(): void {
    this.http.post<ApiResponse>(`${environment.apiBaseUrl}/stock/open-box`, this.boxForm.getRawValue()).subscribe({
      next: () => {
        this.message.set('Caja convertida');
        this.load();
      },
      error: (err) => this.message.set(err.message || 'Error'),
    });
  }
}
