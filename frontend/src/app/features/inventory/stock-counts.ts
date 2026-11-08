import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';
import { emptyPage, PageMeta, Pager, readPageMeta } from '../../shared/pager';

@Component({
  selector: 'app-stock-counts',
  imports: [RouterLink, Pager],
  template: `
    <h1>Inventario físico</h1>
    <button type="button" (click)="create()">Crear conteo</button>
    @if (message()) {
      <p>{{ message() }}</p>
    }
    <ul>
      @for (item of items(); track item.id) {
        <li>
          #{{ item.id }} — {{ item.status }}
          <a [routerLink]="['/app/conteos', item.id]">Abrir</a>
        </li>
      }
    </ul>
    <app-pager
      [page]="meta().current_page"
      [lastPage]="meta().last_page"
      [total]="meta().total"
      [perPage]="meta().per_page"
      label="conteos"
      (pageChange)="goTo($event)"
      (perPageChange)="setPerPage($event)"
    />
  `,
})
export class StockCounts {
  private readonly http = inject(HttpClient);
  readonly items = signal<any[]>([]);
  readonly meta = signal<PageMeta>(emptyPage());
  readonly message = signal('');

  constructor() {
    this.load();
  }

  goTo(page: number): void {
    if (page < 1 || page > this.meta().last_page) {
      return;
    }
    this.meta.update((m) => ({ ...m, current_page: page }));
    this.load();
  }

  setPerPage(size: number): void {
    this.meta.update((m) => ({ ...m, per_page: size, current_page: 1 }));
    this.load();
  }

  load(): void {
    this.http
      .get<ApiResponse<{ items: any[]; meta: PageMeta }>>(`${environment.apiBaseUrl}/stock/counts`, {
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

  create(): void {
    this.http.post<ApiResponse>(`${environment.apiBaseUrl}/stock/counts`, { notes: 'Conteo' }).subscribe({
      next: () => {
        this.message.set('Conteo creado');
        this.load();
      },
      error: (err) => this.message.set(err.message || 'No se pudo crear'),
    });
  }
}
