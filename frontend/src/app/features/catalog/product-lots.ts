import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

@Component({
  selector: 'app-product-lots',
  imports: [RouterLink],
  template: `
    <p><a routerLink="/app/productos">Volver</a></p>
    <h1>Lotes</h1>
    @if (product(); as p) {
      <p>{{ p.nombre }} — {{ p.stock_actual }} unidades en {{ p.lote_count }} lotes</p>
      @for (variant of p.variantes ?? []; track variant.id) {
        <section>
          <h2>{{ variant.presentation || 'Presentación' }}</h2>
          <p>Disponible {{ variant.on_hand ?? 0 }} · precio S/ {{ variant.sale_price || p.precio_venta || 0 }}</p>
          <p><a [routerLink]="['/app/stock', variant.id]">Ver / ajustar esta presentación</a></p>
          <table>
            <thead>
              <tr>
                <th>Lote</th>
                <th>Vencimiento</th>
                <th>Costo</th>
                <th>Precio venta</th>
                <th>Cantidad</th>
              </tr>
            </thead>
            <tbody>
              @for (batch of variant.batches ?? []; track batch.id) {
                <tr>
                  <td>{{ batch.batch_code }}</td>
                  <td>{{ batch.expiry_date || 's/f' }}</td>
                  <td>{{ batch.cost ?? '—' }}</td>
                  <td>{{ variant.sale_price ?? p.precio_venta ?? '—' }}</td>
                  <td>{{ batch.quantity }}</td>
                </tr>
              } @empty {
                <tr><td colspan="5">Sin lotes</td></tr>
              }
            </tbody>
          </table>
        </section>
      }
    }
  `,
  styles: `
    table { width: 100%; border-collapse: collapse; background: #fff; max-width: 52rem; }
    th, td { text-align: left; padding: 0.4rem 0.5rem; border-bottom: 1px solid #e4e4e7; }
    section { margin-bottom: 1.5rem; }
  `,
})
export class ProductLots {
  private readonly http = inject(HttpClient);
  private readonly route = inject(ActivatedRoute);
  readonly product = signal<any | null>(null);

  constructor() {
    const id = this.route.snapshot.paramMap.get('id');
    this.http.get<ApiResponse<any>>(`${environment.apiBaseUrl}/products/${id}`).subscribe({
      next: (res) => this.product.set(res.data),
    });
  }
}
