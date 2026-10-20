import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

@Component({
  selector: 'app-stock-counts',
  imports: [RouterLink],
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
  `,
})
export class StockCounts {
  private readonly http = inject(HttpClient);
  readonly items = signal<any[]>([]);
  readonly message = signal('');

  constructor() {
    this.load();
  }

  load(): void {
    this.http.get<ApiResponse<{ items: any[] }>>(`${environment.apiBaseUrl}/stock/counts`).subscribe({
      next: (res) => this.items.set(res.data?.items ?? []),
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
