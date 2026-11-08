import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';
import { emptyPage, PageMeta, Pager, readPageMeta } from '../../shared/pager';

@Component({
  selector: 'app-stock-list',
  imports: [ReactiveFormsModule, RouterLink, Pager],
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
    <app-pager
      [page]="meta().current_page"
      [lastPage]="meta().last_page"
      [total]="meta().total"
      [perPage]="meta().per_page"
      label="registros"
      (pageChange)="goTo($event)"
      (perPageChange)="setPerPage($event)"
    />
  `,
})
export class StockList {
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);
  readonly items = signal<any[]>([]);
  readonly meta = signal<PageMeta>(emptyPage());
  readonly filters = this.fb.nonNullable.group({ q: [''] });

  constructor() {
    this.search();
  }

  search(): void {
    this.meta.update((m) => ({ ...m, current_page: 1 }));
    this.reload();
  }

  goTo(page: number): void {
    if (page < 1 || page > this.meta().last_page) {
      return;
    }
    this.meta.update((m) => ({ ...m, current_page: page }));
    this.reload();
  }

  setPerPage(size: number): void {
    this.meta.update((m) => ({ ...m, per_page: size, current_page: 1 }));
    this.reload();
  }

  reload(): void {
    const q = this.filters.getRawValue().q;
    const params: Record<string, string> = {
      page: String(this.meta().current_page),
      per_page: String(this.meta().per_page),
    };
    if (q) {
      params['q'] = q;
    }
    this.http.get<ApiResponse<{ items: any[]; meta: PageMeta }>>(`${environment.apiBaseUrl}/stock`, { params }).subscribe({
      next: (res) => {
        this.items.set(res.data?.items ?? []);
        this.meta.set(readPageMeta(this.meta(), res.data?.meta));
      },
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
