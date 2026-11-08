import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';
import { emptyPage, PageMeta, Pager, readPageMeta } from '../../shared/pager';

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
  imports: [ReactiveFormsModule, RouterLink, Pager],
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
    <app-pager
      [page]="meta().current_page"
      [lastPage]="meta().last_page"
      [total]="meta().total"
      [perPage]="meta().per_page"
      label="pendientes"
      (pageChange)="goTo($event)"
      (perPageChange)="setPerPage($event)"
    />
  `,
})
export class PendingReview {
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);
  readonly items = signal<PendingVariant[]>([]);
  readonly meta = signal<PageMeta>(emptyPage());
  readonly message = signal('');
  private readonly forms = new Map<number, FormGroup>();

  constructor() {
    this.load();
  }

  formFor(id: number) {
    return this.forms.get(id)!;
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
      .get<ApiResponse<{ items: PendingVariant[]; meta: PageMeta }>>(`${environment.apiBaseUrl}/inventory/pending-review`, {
        params: {
          page: String(this.meta().current_page),
          per_page: String(this.meta().per_page),
        },
      })
      .subscribe({
        next: (res) => {
          this.meta.set(readPageMeta(this.meta(), res.data?.meta));
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
