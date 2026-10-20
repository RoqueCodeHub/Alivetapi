import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

@Component({
  selector: 'app-purchases',
  imports: [RouterLink],
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
  `,
})
export class Purchases {
  private readonly http = inject(HttpClient);
  readonly items = signal<any[]>([]);

  constructor() {
    this.http.get<ApiResponse<{ items: any[] }>>(`${environment.apiBaseUrl}/purchases`).subscribe({
      next: (res) => this.items.set(res.data?.items ?? []),
    });
  }
}
