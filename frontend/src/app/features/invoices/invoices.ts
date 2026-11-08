import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';
import { emptyPage, PageMeta, Pager, readPageMeta } from '../../shared/pager';

@Component({
  selector: 'app-invoices',
  imports: [Pager],
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
    <app-pager
      [page]="meta().current_page"
      [lastPage]="meta().last_page"
      [total]="meta().total"
      [perPage]="meta().per_page"
      label="comprobantes"
      (pageChange)="goTo($event)"
      (perPageChange)="setPerPage($event)"
    />
  `,
})
export class Invoices {
  private readonly http = inject(HttpClient);
  readonly items = signal<any[]>([]);
  readonly meta = signal<PageMeta>(emptyPage());
  readonly series = signal<any[]>([]);
  readonly req = signal<any>(null);
  readonly hint = signal('');

  constructor() {
    this.reload();
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
    this.http
      .get<ApiResponse<{ items: any[]; meta: PageMeta }>>(`${environment.apiBaseUrl}/invoices`, {
        params: {
          page: String(this.meta().current_page),
          per_page: String(this.meta().per_page),
        },
      })
      .subscribe({
        next: (res) => {
          this.items.set(res.data?.items ?? []);
          this.meta.set(readPageMeta(this.meta(), res.data?.meta));
        },
      });
  }
}
