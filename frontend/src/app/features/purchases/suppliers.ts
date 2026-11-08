import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';
import { emptyPage, PageMeta, Pager, readPageMeta } from '../../shared/pager';

interface Supplier {
  id: number;
  name: string;
  ruc?: string;
  phone?: string;
  email?: string;
}

@Component({
  selector: 'app-suppliers',
  imports: [ReactiveFormsModule, Pager],
  template: `
    <h1>Proveedores</h1>
    <form [formGroup]="form" (ngSubmit)="create()">
      <input placeholder="Nombre" formControlName="name" />
      <input placeholder="RUC" formControlName="ruc" />
      <input placeholder="Teléfono" formControlName="phone" />
      <input placeholder="Email" formControlName="email" />
      <button type="submit">Crear</button>
    </form>
    <ul>
      @for (item of items(); track item.id) {
        <li>{{ item.name }} — {{ item.ruc || 's/RUC' }}</li>
      }
    </ul>
    <app-pager
      [page]="meta().current_page"
      [lastPage]="meta().last_page"
      [total]="meta().total"
      [perPage]="meta().per_page"
      label="proveedores"
      (pageChange)="goTo($event)"
      (perPageChange)="setPerPage($event)"
    />
  `,
})
export class Suppliers {
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);
  readonly items = signal<Supplier[]>([]);
  readonly meta = signal<PageMeta>(emptyPage());
  readonly form = this.fb.nonNullable.group({
    name: ['', Validators.required],
    ruc: [''],
    phone: [''],
    email: [''],
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
      .get<ApiResponse<{ items: Supplier[]; meta: PageMeta }>>(`${environment.apiBaseUrl}/suppliers`, {
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
    if (this.form.invalid) {
      return;
    }
    this.http.post(`${environment.apiBaseUrl}/suppliers`, this.form.getRawValue()).subscribe({
      next: () => {
        this.form.reset();
        this.reload();
      },
    });
  }
}
