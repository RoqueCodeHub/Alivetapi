import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

@Component({
  selector: 'app-stock-alerts',
  template: `
    <h1>Alertas de inventario</h1>
    <h2>Stock mínimo</h2>
    <ul>
      @for (row of low(); track row.variant_id) {
        <li>{{ row.producto }} — {{ row.on_hand }}/{{ row.min_stock }}</li>
      }
    </ul>
    <h2>Vencido</h2>
    <ul>
      @for (row of exp().vencido; track row.batch_id) {
        <li>{{ row.producto }} lote {{ row.batch_code }} ({{ row.expiry_date }})</li>
      }
    </ul>
    <h2>Vence en 30 días</h2>
    <ul>
      @for (row of exp().vence_30; track row.batch_id) {
        <li>{{ row.producto }} lote {{ row.batch_code }} ({{ row.expiry_date }})</li>
      }
    </ul>
    <h2>Vence en 60 días</h2>
    <ul>
      @for (row of exp().vence_60; track row.batch_id) {
        <li>{{ row.producto }} lote {{ row.batch_code }} ({{ row.expiry_date }})</li>
      }
    </ul>
    <h2>Vence en 90 días</h2>
    <ul>
      @for (row of exp().vence_90; track row.batch_id) {
        <li>{{ row.producto }} lote {{ row.batch_code }} ({{ row.expiry_date }})</li>
      }
    </ul>
  `,
})
export class StockAlerts {
  private readonly http = inject(HttpClient);
  readonly low = signal<any[]>([]);
  readonly exp = signal<any>({ vencido: [], vence_30: [], vence_60: [], vence_90: [] });

  constructor() {
    this.http.get<ApiResponse<any[]>>(`${environment.apiBaseUrl}/alerts/low-stock`).subscribe({
      next: (res) => this.low.set(res.data ?? []),
    });
    this.http.get<ApiResponse<any>>(`${environment.apiBaseUrl}/alerts/expiring`).subscribe({
      next: (res) => this.exp.set(res.data ?? this.exp()),
    });
  }
}
