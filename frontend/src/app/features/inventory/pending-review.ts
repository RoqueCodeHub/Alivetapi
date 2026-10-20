import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

interface PendingVariant {
  id: number;
  presentation: string;
  unit: string;
  sale_price: number | null;
  cost_price: number | null;
  needs_review: boolean;
  review_note: string | null;
  producto?: { id: number; nombre: string };
}

@Component({
  selector: 'app-pending-review',
  imports: [ReactiveFormsModule, RouterLink],
  template: `
    <h1>Pendientes de revisión</h1>
    <p>Corrige los registros importados antes de la puesta en marcha.</p>
    @if (message()) {
      <p>{{ message() }}</p>
    }
    <ul>
      @for (item of items(); track item.id) {
        <li>
          <strong>{{ item.producto?.nombre }}</strong> — {{ item.presentation }}
          <div>{{ item.review_note }}</div>
          <form [formGroup]="formFor(item.id)" (ngSubmit)="save(item.id)">
            <input placeholder="Presentación" formControlName="presentation" />
            <input placeholder="Unidad" formControlName="unit" />
            <input placeholder="Precio" formControlName="sale_price" />
            <input placeholder="Costo" formControlName="cost_price" />
            <input placeholder="Nota" formControlName="review_note" />
            <label>
              <input type="checkbox" formControlName="needs_review" /> Sigue pendiente
            </label>
            <button type="submit">Guardar</button>
            <a [routerLink]="['/app/productos', item.producto?.id]">Ver producto</a>
          </form>
        </li>
      }
    </ul>
  `,
})
export class PendingReview {
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);
  readonly items = signal<PendingVariant[]>([]);
  readonly message = signal('');
  private readonly forms = new Map<number, FormGroup>();

  constructor() {
    this.load();
  }

  formFor(id: number) {
    return this.forms.get(id)!;
  }

  load(): void {
    this.http
      .get<ApiResponse<{ items: PendingVariant[] }>>(`${environment.apiBaseUrl}/inventory/pending-review`)
      .subscribe({
        next: (res) => {
          const items = res.data?.items ?? [];
          this.forms.clear();
          for (const item of items) {
            this.forms.set(
              item.id,
              this.fb.group({
                presentation: [item.presentation],
                unit: [item.unit],
                sale_price: [item.sale_price],
                cost_price: [item.cost_price],
                review_note: [item.review_note],
                needs_review: [item.needs_review],
              }),
            );
          }
          this.items.set(items);
        },
      });
  }

  save(id: number): void {
    const form = this.forms.get(id);
    if (!form) {
      return;
    }
    this.http
      .put<ApiResponse<PendingVariant>>(`${environment.apiBaseUrl}/inventory/pending-review/${id}`, form.getRawValue())
      .subscribe({
        next: () => {
          this.message.set('Registro actualizado');
          this.load();
        },
      });
  }
}
