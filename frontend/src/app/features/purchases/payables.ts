import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

@Component({
  selector: 'app-payables',
  imports: [RouterLink],
  template: `
    <h1>Cuentas por pagar</h1>
    <ul>
      @for (item of items(); track item.id) {
        <li>
          <a [routerLink]="['/app/compras', item.id]">{{ item.comprobante || '#' + item.id }}</a>
          — {{ item.supplier?.name }} — saldo S/ {{ item.balance }}
        </li>
      }
    </ul>
  `,
})
export class Payables {
  private readonly http = inject(HttpClient);
  readonly items = signal<any[]>([]);

  constructor() {
    this.http.get<ApiResponse<{ items: any[] }>>(`${environment.apiBaseUrl}/purchases/payables`).subscribe({
      next: (res) => this.items.set(res.data?.items ?? []),
    });
  }
}
