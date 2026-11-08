import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';
import { emptyPage, PageMeta, Pager, readPageMeta } from '../../shared/pager';

interface Brand {
  id: number;
  nombre: string;
  activo: boolean;
}

@Component({
  selector: 'app-brands',
  imports: [ReactiveFormsModule, Pager],
  template: `
    <h1>Marcas</h1>
    <form [formGroup]="form" (ngSubmit)="create()">
      <input placeholder="Nombre" formControlName="nombre" />
      <button type="submit">Crear</button>
    </form>
    <ul>
      @for (brand of brands(); track brand.id) {
        <li>{{ brand.nombre }}</li>
      }
    </ul>
    <app-pager
      [page]="meta().current_page"
      [lastPage]="meta().last_page"
      [total]="meta().total"
      [perPage]="meta().per_page"
      label="marcas"
      (pageChange)="goTo($event)"
      (perPageChange)="setPerPage($event)"
    />
  `,
})
export class Brands {
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);
  readonly brands = signal<Brand[]>([]);
  readonly meta = signal<PageMeta>(emptyPage());
  readonly form = this.fb.nonNullable.group({
    nombre: ['', Validators.required],
  });

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
      .get<ApiResponse<{ items: Brand[]; meta: PageMeta }>>(`${environment.apiBaseUrl}/brands`, {
        params: {
          page: String(this.meta().current_page),
          per_page: String(this.meta().per_page),
        },
      })
      .subscribe({
        next: (res) => {
          this.brands.set(res.data?.items ?? []);
          this.meta.set(readPageMeta(this.meta(), res.data?.meta));
        },
      });
  }

  create(): void {
    if (this.form.invalid) {
      return;
    }
    this.http.post(`${environment.apiBaseUrl}/brands`, this.form.getRawValue()).subscribe({
      next: () => {
        this.form.reset();
        this.reload();
      },
    });
  }
}
