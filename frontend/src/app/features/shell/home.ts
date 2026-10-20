import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-home',
  imports: [ReactiveFormsModule, RouterLink],
  template: `
    <h1>Inicio</h1>
    @if (auth.hasPermission('sales.charge')) {
      <p><a routerLink="/app/pos">Caja</a> — cobrar las ventas que registró cualquier usuario.</p>
    } @else if (auth.hasPermission('sales.create')) {
      <p><a routerLink="/app/ventas">Ventas</a> — registra la venta. La cobra la cajera.</p>
    }
    @if (auth.hasPermission('reports.view_all')) {
      <p>Ventas hoy S/ {{ dash()?.today?.total ?? 0 }} ({{ dash()?.today?.count ?? 0 }})
        vs ayer {{ dash()?.comparison?.today?.percent }}%</p>
      <p>Semana S/ {{ dash()?.week?.total ?? 0 }} — Mes S/ {{ dash()?.month?.total ?? 0 }}</p>
      <p>Utilidad período S/ {{ dash()?.profit?.profit ?? 0 }} — Inventario S/ {{ dash()?.inventory_value?.value ?? 0 }}</p>
      <p><a routerLink="/app/reportes">Ver reportes y gráficos</a></p>
      <p><a routerLink="/app/recordatorios">Recordatorios y alertas</a></p>
    } @else {
      <p>Ventas de hoy S/ {{ dash()?.today?.total ?? 0 }} ({{ dash()?.today?.count ?? 0 }})</p>
      @if (auth.hasPermission('sales.charge')) {
        @if (dash()?.cash; as box) {
          <p>Caja abierta — esperado S/ {{ box.expected_amount }}
            <a routerLink="/app/pos">Ir a caja</a></p>
        } @else {
          <p>Sin caja abierta. <a routerLink="/app/pos">Abrir caja</a></p>
        }
      }
      <form [formGroup]="stockForm" (ngSubmit)="searchStock()">
        <input placeholder="Buscar stock / SKU" formControlName="q" />
        <button type="submit">Buscar</button>
      </form>
      <ul>
        @for (hit of hits(); track hit.variant_id || hit.variant?.id) {
          <li>{{ hit.nombre || hit.variant?.producto?.nombre }} {{ hit.presentation || hit.variant?.presentation }}
            — {{ hit.on_hand }}</li>
        }
      </ul>
    }
  `,
})
export class Home {
  readonly auth = inject(AuthService);
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);
  readonly dash = signal<any>(null);
  readonly hits = signal<any[]>([]);
  readonly stockForm = this.fb.nonNullable.group({ q: [''] });

  constructor() {
    if (this.auth.hasPermission('reports.view') || this.auth.hasPermission('reports.view_all')) {
      this.http.get<ApiResponse<any>>(`${environment.apiBaseUrl}/reports/dashboard`).subscribe({
        next: (res) => this.dash.set(res.data),
      });
    }
  }

  searchStock(): void {
    const q = this.stockForm.getRawValue().q;
    const url = this.auth.hasPermission('sales.create')
      ? `${environment.apiBaseUrl}/pos/search`
      : `${environment.apiBaseUrl}/stock`;
    this.http.get<ApiResponse<any>>(`${url}`, { params: { q } }).subscribe({
      next: (res) => {
        const data = res.data;
        this.hits.set(Array.isArray(data) ? data : data?.items ?? []);
      },
    });
  }
}
