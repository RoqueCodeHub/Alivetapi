import { HttpClient } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';
import { Pager, pageWindow } from '../../shared/pager';

@Component({
  selector: 'app-kardex',
  imports: [RouterLink, Pager],
  template: `
    <p><a routerLink="/app/stock">Volver al stock</a></p>
    <h1>Kardex</h1>
    <button type="button" (click)="exportCsv()">Exportar</button>
    <table>
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Usuario</th>
          <th>Movimiento</th>
          <th>Cantidad</th>
          <th>Saldo</th>
          <th>Motivo</th>
          <th>Referencia</th>
        </tr>
      </thead>
      <tbody>
        @for (line of view().rows; track line.id) {
          <tr>
            <td>{{ line.fecha }}</td>
            <td>{{ line.usuario }}</td>
            <td>{{ line.movimiento }}</td>
            <td>{{ line.cantidad }}</td>
            <td>{{ line.saldo }}</td>
            <td>{{ line.motivo }}</td>
            <td>{{ line.referencia }}</td>
          </tr>
        }
      </tbody>
    </table>
    <app-pager
      [page]="view().meta.current_page"
      [lastPage]="view().meta.last_page"
      [total]="view().meta.total"
      [perPage]="pageSize()"
      label="movimientos"
      (pageChange)="page.set($event)"
      (perPageChange)="setPerPage($event)"
    />
  `,
})
export class Kardex {
  private readonly http = inject(HttpClient);
  private readonly route = inject(ActivatedRoute);
  readonly lines = signal<any[]>([]);
  readonly page = signal(1);
  readonly pageSize = signal(10);
  readonly view = computed(() => pageWindow(this.lines(), this.page(), this.pageSize()));

  constructor() {
    const id = this.route.snapshot.paramMap.get('id');
    this.http.get<ApiResponse<any[]>>(`${environment.apiBaseUrl}/variants/${id}/kardex`).subscribe({
      next: (res) => this.lines.set(res.data ?? []),
    });
  }

  setPerPage(size: number): void {
    this.pageSize.set(size);
    this.page.set(1);
  }

  exportCsv(): void {
    const id = this.route.snapshot.paramMap.get('id');
    this.http.get(`${environment.apiBaseUrl}/variants/${id}/kardex/export`, { responseType: 'blob' }).subscribe({
      next: (blob) => {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `kardex-${id}.csv`;
        a.click();
        URL.revokeObjectURL(url);
      },
    });
  }
}
