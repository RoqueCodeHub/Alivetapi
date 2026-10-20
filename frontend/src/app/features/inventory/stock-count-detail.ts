import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

@Component({
  selector: 'app-stock-count-detail',
  imports: [ReactiveFormsModule, RouterLink],
  template: `
    <p><a routerLink="/app/conteos">Volver</a></p>
    <h1>Conteo #{{ count()?.id }}</h1>
    <p>Estado: {{ count()?.status }}</p>
    @if (message()) {
      <p>{{ message() }}</p>
    }
    <ul>
      @for (item of count()?.items ?? []; track item.id) {
        <li>
          {{ item.producto }} {{ item.batch_code }} — sistema {{ item.system_qty }}
          diferencia {{ item.difference ?? '-' }}
          <form [formGroup]="formFor(item.id)" (ngSubmit)="saveLine(item.id)">
            <input type="number" formControlName="counted_qty" />
            <button type="submit">Contar</button>
          </form>
        </li>
      }
    </ul>
    <button type="button" (click)="approve()" [disabled]="count()?.status === 'approved'">Aprobar y generar ajustes</button>
  `,
})
export class StockCountDetail {
  private readonly http = inject(HttpClient);
  private readonly route = inject(ActivatedRoute);
  private readonly fb = inject(FormBuilder);
  readonly count = signal<any>(null);
  readonly message = signal('');
  private readonly forms = new Map<number, FormGroup>();

  constructor() {
    this.load();
  }

  formFor(id: number): FormGroup {
    return this.forms.get(id)!;
  }

  load(): void {
    const id = this.route.snapshot.paramMap.get('id');
    this.http.get<ApiResponse<any>>(`${environment.apiBaseUrl}/stock/counts/${id}`).subscribe({
      next: (res) => {
        this.forms.clear();
        for (const item of res.data?.items ?? []) {
          this.forms.set(
            item.id,
            this.fb.group({
              counted_qty: [item.counted_qty ?? item.system_qty],
            }),
          );
        }
        this.count.set(res.data);
      },
    });
  }

  saveLine(id: number): void {
    const countId = this.route.snapshot.paramMap.get('id');
    const qty = this.forms.get(id)?.getRawValue().counted_qty;
    this.http
      .put<ApiResponse>(`${environment.apiBaseUrl}/stock/counts/${countId}/lines`, {
        items: [{ id, counted_qty: qty }],
      })
      .subscribe({
        next: () => {
          this.message.set('Línea guardada');
          this.load();
        },
      });
  }

  approve(): void {
    const id = this.route.snapshot.paramMap.get('id');
    this.http.post<ApiResponse>(`${environment.apiBaseUrl}/stock/counts/${id}/approve`, {}).subscribe({
      next: () => {
        this.message.set('Aprobado');
        this.load();
      },
      error: (err) => this.message.set(err.message || 'No se pudo aprobar'),
    });
  }
}
