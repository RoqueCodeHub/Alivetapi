import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

@Component({
  selector: 'app-invoices',
  template: `
    <h1>Facturación electrónica</h1>
    <p>{{ hint() }}</p>
    <p>IGV {{ req()?.igv }}% — driver {{ req()?.driver }} — RUC emisor {{ req()?.issuer_ruc_configured ? 'sí' : 'no' }}</p>
    <h2>Series</h2>
    <ul>
      @for (row of series(); track row.series) {
        <li>{{ row.series }} {{ row.description }} — siguiente {{ row.next_number }}</li>
      }
    </ul>
    <h2>Comprobantes</h2>
    <ul>
      @for (item of items(); track item.id) {
        <li>
          {{ item.document }} {{ item.document_type }} — S/ {{ item.total }} (IGV {{ item.igv }}) — {{ item.status }}
          {{ item.pse_ticket ? 'ticket ' + item.pse_ticket : '' }}
        </li>
      }
    </ul>
  `,
})
export class Invoices {
  private readonly http = inject(HttpClient);
  readonly items = signal<any[]>([]);
  readonly series = signal<any[]>([]);
  readonly req = signal<any>(null);
  readonly hint = signal('');

  constructor() {
    this.http.get<ApiResponse<{ items: any[] }>>(`${environment.apiBaseUrl}/invoices`).subscribe({
      next: (res) => this.items.set(res.data?.items ?? []),
    });
    this.http.get<ApiResponse<any[]>>(`${environment.apiBaseUrl}/invoices/series`).subscribe({
      next: (res) => this.series.set(res.data ?? []),
    });
    this.http.get<ApiResponse<any>>(`${environment.apiBaseUrl}/invoices/requirements`).subscribe({
      next: (res) => {
        this.req.set(res.data);
        this.hint.set(res.data?.notes?.contador ?? '');
      },
    });
  }
}
