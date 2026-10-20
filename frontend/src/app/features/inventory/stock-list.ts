import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

@Component({
  selector: 'app-stock-list',
  imports: [ReactiveFormsModule, RouterLink],
  template: `
    <h1>Inventario</h1>
    <form [formGroup]="filters" (ngSubmit)="search()">
      <input placeholder="Buscar" formControlName="q" />
      <button type="submit">Buscar</button>
      <button type="button" (click)="exportCsv()">Exportar Excel/CSV</button>
    </form>
    <ul>
      @for (row of items(); track row.variant.id) {
        <li>
          {{ row.variant.producto?.nombre }} — {{ row.variant.presentation }}
          ({{ row.on_hand }})
          @if (row.low_stock) {
            <strong> stock bajo</strong>
          }
          <a [routerLink]="['/app/stock', row.variant.id]">Lotes</a>
          <a [routerLink]="['/app/kardex', row.variant.id]">Kardex</a>
        </li>
      }
    </ul>
  `,
})
export class StockList {
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);
  readonly items = signal<any[]>([]);
  readonly filters = this.fb.nonNullable.group({ q: [''] });

  constructor() {
    this.search();
  }

  search(): void {
    const q = this.filters.getRawValue().q;
    const params: Record<string, string> = {};
    if (q) {
      params['q'] = q;
    }
    this.http.get<ApiResponse<{ items: any[] }>>(`${environment.apiBaseUrl}/stock`, { params }).subscribe({
      next: (res) => this.items.set(res.data?.items ?? []),
    });
  }

  exportCsv(): void {
    this.http.get(`${environment.apiBaseUrl}/stock/export`, { responseType: 'blob' }).subscribe({
      next: (blob) => {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'inventario.csv';
        a.click();
        URL.revokeObjectURL(url);
      },
    });
  }
}
