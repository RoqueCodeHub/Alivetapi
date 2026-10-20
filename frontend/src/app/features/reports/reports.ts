import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

@Component({
  selector: 'app-reports',
  imports: [ReactiveFormsModule],
  template: `
    <h1>Reportes</h1>
    <form [formGroup]="filters" (ngSubmit)="load()">
      <input type="date" formControlName="from" />
      <input type="date" formControlName="to" />
      <select formControlName="category_id">
        <option value="">Categoría</option>
        @for (c of categories(); track c.id) {
          <option [value]="c.id">{{ c.nombre }}</option>
        }
      </select>
      <input type="number" placeholder="ID cajero" formControlName="cashier_id" />
      <button type="submit">Filtrar</button>
    </form>
    <p>
      <button type="button" (click)="exportFile('csv')">CSV</button>
      <button type="button" (click)="exportFile('xlsx')">Excel</button>
      <button type="button" (click)="exportFile('pdf')">PDF</button>
    </p>
    <h2>Ventas por día</h2>
    @if (bars(data()?.sales_by_day, 'total'); as chart) {
      <svg viewBox="0 0 400 120" width="100%" height="120">
        @for (bar of chart; track bar.label) {
          <rect [attr.x]="bar.x" [attr.y]="bar.y" [attr.width]="bar.w" [attr.height]="bar.h" fill="#2563eb"></rect>
        }
      </svg>
    }
    <h2>Top productos</h2>
    <ul>
      @for (row of data()?.top_products ?? []; track row.variant_id) {
        <li>{{ row.description }} — {{ row.quantity }} — S/ {{ row.total }}</li>
      }
    </ul>
    <h2>Por categoría</h2>
    <ul>
      @for (row of data()?.by_category ?? []; track row.categoria) {
        <li>{{ row.categoria }} — S/ {{ row.total }}</li>
      }
    </ul>
    <h2>Por cajero</h2>
    <ul>
      @for (row of data()?.by_cashier ?? []; track row.user_id) {
        <li>{{ row.cajero }} — S/ {{ row.total }}</li>
      }
    </ul>
    <h2>Por pago</h2>
    <ul>
      @for (row of data()?.by_payment ?? []; track row.method) {
        <li>{{ row.method }} — S/ {{ row.total }}</li>
      }
    </ul>
    <h2>Por hora</h2>
    <ul>
      @for (row of data()?.by_hour ?? []; track row.hour) {
        <li>{{ row.hour }}h — S/ {{ row.total }}</li>
      }
    </ul>
    <p>Utilidad S/ {{ data()?.profit?.profit }} — Inventario S/ {{ data()?.inventory_value?.value }}</p>
    <h2>Baja rotación</h2>
    <ul>
      @for (row of data()?.slow_movers ?? []; track row.variant_id) {
        <li>{{ row.producto }} {{ row.presentation }} ({{ row.on_hand }})</li>
      }
    </ul>
  `,
})
export class Reports {
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);
  readonly data = signal<any>(null);
  readonly categories = signal<any[]>([]);
  readonly filters = this.fb.nonNullable.group({
    from: [''],
    to: [''],
    category_id: [''],
    cashier_id: [''],
  });

  constructor() {
    this.http.get<ApiResponse<any[]>>(`${environment.apiBaseUrl}/categories`).subscribe({
      next: (res) => this.categories.set(this.flatten(res.data ?? [])),
    });
    this.load();
  }

  params(): Record<string, string> {
    const v = this.filters.getRawValue();
    const params: Record<string, string> = {};
    if (v.from) params['from'] = v.from;
    if (v.to) params['to'] = v.to;
    if (v.category_id) params['category_id'] = String(v.category_id);
    if (v.cashier_id) params['cashier_id'] = String(v.cashier_id);
    return params;
  }

  flatten(items: any[], prefix = ''): { id: number; nombre: string }[] {
    const out: { id: number; nombre: string }[] = [];
    for (const node of items) {
      out.push({ id: node.id, nombre: prefix + node.nombre });
      if (node.children?.length) {
        out.push(...this.flatten(node.children, prefix + '— '));
      }
    }
    return out;
  }

  load(): void {
    this.http
      .get<ApiResponse<any>>(`${environment.apiBaseUrl}/reports/dashboard`, { params: this.params() })
      .subscribe({ next: (res) => this.data.set(res.data) });
  }

  bars(rows: any[] | undefined, field: string): { x: number; y: number; w: number; h: number; label: string }[] {
    if (!rows?.length) {
      return [];
    }
    const max = Math.max(...rows.map((r) => Number(r[field] || 0)), 1);
    const w = 400 / rows.length;
    return rows.map((r, i) => {
      const h = (Number(r[field] || 0) / max) * 100;
      return { x: i * w + 2, y: 110 - h, w: Math.max(2, w - 4), h, label: String(r.date || i) };
    });
  }

  exportFile(format: 'csv' | 'xlsx' | 'pdf'): void {
    this.http
      .get(`${environment.apiBaseUrl}/reports/export`, {
        params: { ...this.params(), report: 'top-products', format },
        responseType: 'blob',
      })
      .subscribe({
        next: (blob) => {
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = `top-productos.${format}`;
          a.click();
          URL.revokeObjectURL(url);
        },
      });
  }
}
