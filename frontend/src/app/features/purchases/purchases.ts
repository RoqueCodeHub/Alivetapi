import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';
import { emptyPage, PageMeta, Pager, readPageMeta } from '../../shared/pager';

@Component({
  selector: 'app-purchases',
  imports: [RouterLink, Pager],
  template: `
    <h1>Compras</h1>
    <p>
      <a routerLink="/app/compras/nueva">Nueva compra</a>
      ·
      <a routerLink="/app/cuentas-por-pagar">Cuentas por pagar</a>
    </p>
    <ul>
      @for (item of items(); track item.id) {
        <li>
          <a [routerLink]="['/app/compras', item.id]">{{ item.comprobante || '#' + item.id }}</a>
          — {{ item.supplier?.name }} — {{ item.status }} — S/ {{ item.total }}
        </li>
      }
    </ul>
    <app-pager
      [page]="meta().current_page"
      [lastPage]="meta().last_page"
      [total]="meta().total"
      [perPage]="meta().per_page"
      label="compras"
      (pageChange)="goTo($event)"
      (perPageChange)="setPerPage($event)"
    />
  `,
})
export class Purchases {
  private readonly http = inject(HttpClient);
  readonly items = signal<any[]>([]);
  readonly meta = signal<PageMeta>(emptyPage());

  constructor() {
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
    this.http
      .get<ApiResponse<{ items: any[]; meta: PageMeta }>>(`${environment.apiBaseUrl}/purchases`, {
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
