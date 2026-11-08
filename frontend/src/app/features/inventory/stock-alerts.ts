import { HttpClient } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';
import { Pager, pageWindow } from '../../shared/pager';

@Component({
  selector: 'app-stock-alerts',
  imports: [Pager],
  template: `
    <h1>Alertas de inventario</h1>
    <h2>Stock mínimo</h2>
    <ul>
      @for (row of lowView().rows; track row.variant_id) {
        <li>{{ row.producto }} — {{ row.on_hand }}/{{ row.min_stock }}</li>
      }
    </ul>
    <app-pager
      [page]="lowView().meta.current_page"
      [lastPage]="lowView().meta.last_page"
      [total]="lowView().meta.total"
      [perPage]="pageSize()"
      label="alertas"
      (pageChange)="lowPage.set($event)"
      (perPageChange)="setPerPage($event)"
    />
    <h2>Vencido</h2>
    <ul>
      @for (row of expiredView().rows; track row.batch_id) {
        <li>{{ row.producto }} lote {{ row.batch_code }} ({{ row.expiry_date }})</li>
      }
    </ul>
    <app-pager
      [page]="expiredView().meta.current_page"
      [lastPage]="expiredView().meta.last_page"
      [total]="expiredView().meta.total"
      [perPage]="pageSize()"
      label="lotes"
      (pageChange)="expiredPage.set($event)"
      (perPageChange)="setPerPage($event)"
    />
    <h2>Vence en 30 días</h2>
    <ul>
      @for (row of d30View().rows; track row.batch_id) {
        <li>{{ row.producto }} lote {{ row.batch_code }} ({{ row.expiry_date }})</li>
      }
    </ul>
    <app-pager
      [page]="d30View().meta.current_page"
      [lastPage]="d30View().meta.last_page"
      [total]="d30View().meta.total"
      [perPage]="pageSize()"
      label="lotes"
      (pageChange)="d30Page.set($event)"
      (perPageChange)="setPerPage($event)"
    />
    <h2>Vence en 60 días</h2>
    <ul>
      @for (row of d60View().rows; track row.batch_id) {
        <li>{{ row.producto }} lote {{ row.batch_code }} ({{ row.expiry_date }})</li>
      }
    </ul>
    <app-pager
      [page]="d60View().meta.current_page"
      [lastPage]="d60View().meta.last_page"
      [total]="d60View().meta.total"
      [perPage]="pageSize()"
      label="lotes"
      (pageChange)="d60Page.set($event)"
      (perPageChange)="setPerPage($event)"
    />
    <h2>Vence en 90 días</h2>
    <ul>
      @for (row of d90View().rows; track row.batch_id) {
        <li>{{ row.producto }} lote {{ row.batch_code }} ({{ row.expiry_date }})</li>
      }
    </ul>
    <app-pager
      [page]="d90View().meta.current_page"
      [lastPage]="d90View().meta.last_page"
      [total]="d90View().meta.total"
      [perPage]="pageSize()"
      label="lotes"
      (pageChange)="d90Page.set($event)"
      (perPageChange)="setPerPage($event)"
    />
  `,
})
export class StockAlerts {
  private readonly http = inject(HttpClient);
  readonly pageSize = signal(10);
  readonly lowPage = signal(1);
  readonly expiredPage = signal(1);
  readonly d30Page = signal(1);
  readonly d60Page = signal(1);
  readonly d90Page = signal(1);
  readonly lowAll = signal<any[]>([]);
  readonly expAll = signal<any>({ vencido: [], vence_30: [], vence_60: [], vence_90: [] });
  readonly lowView = computed(() => pageWindow(this.lowAll(), this.lowPage(), this.pageSize()));
  readonly expiredView = computed(() => pageWindow<any>(this.expAll().vencido ?? [], this.expiredPage(), this.pageSize()));
  readonly d30View = computed(() => pageWindow<any>(this.expAll().vence_30 ?? [], this.d30Page(), this.pageSize()));
  readonly d60View = computed(() => pageWindow<any>(this.expAll().vence_60 ?? [], this.d60Page(), this.pageSize()));
  readonly d90View = computed(() => pageWindow<any>(this.expAll().vence_90 ?? [], this.d90Page(), this.pageSize()));

  constructor() {
    this.http.get<ApiResponse<any[]>>(`${environment.apiBaseUrl}/alerts/low-stock`).subscribe({
      next: (res) => this.lowAll.set(res.data ?? []),
    });
    this.http.get<ApiResponse<any>>(`${environment.apiBaseUrl}/alerts/expiring`).subscribe({
      next: (res) => this.expAll.set(res.data ?? this.expAll()),
    });
  }

  setPerPage(size: number): void {
    this.pageSize.set(size);
    this.lowPage.set(1);
    this.expiredPage.set(1);
    this.d30Page.set(1);
    this.d60Page.set(1);
    this.d90Page.set(1);
  }
}
