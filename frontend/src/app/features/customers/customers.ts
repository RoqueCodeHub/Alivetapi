import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';
import { emptyPage, PageMeta, Pager, readPageMeta } from '../../shared/pager';

@Component({
  selector: 'app-customers',
  imports: [ReactiveFormsModule, RouterLink, Pager],
  template: `
    <h1>Clientes</h1>
    <form [formGroup]="filters" (ngSubmit)="search()">
      <input placeholder="Buscar" formControlName="q" />
      <button type="submit">Buscar</button>
    </form>
    <form [formGroup]="form" (ngSubmit)="create()">
      <input placeholder="Nombre" formControlName="name" />
      <select formControlName="document_type">
        <option value="DNI">DNI</option>
        <option value="RUC">RUC</option>
      </select>
      <input placeholder="N° documento" formControlName="document_number" />
      <input placeholder="Teléfono" formControlName="phone" />
      <button type="submit">Crear</button>
    </form>
    <ul>
      @for (item of items(); track item.id) {
        <li>
          <a [routerLink]="['/app/clientes', item.id]">{{ item.name }}</a>
          — {{ item.document_number || 's/doc' }} — {{ item.phone || 's/tel' }}
        </li>
      }
    </ul>
    <app-pager
      [page]="meta().current_page"
      [lastPage]="meta().last_page"
      [total]="meta().total"
      [perPage]="meta().per_page"
      label="clientes"
      (pageChange)="goTo($event)"
      (perPageChange)="setPerPage($event)"
    />
  `,
})
export class Customers {
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);
  readonly items = signal<any[]>([]);
  readonly meta = signal<PageMeta>(emptyPage());
  readonly filters = this.fb.nonNullable.group({ q: [''] });
  readonly form = this.fb.nonNullable.group({
    name: ['', Validators.required],
    document_type: ['DNI'],
    document_number: [''],
    phone: [''],
  });

  constructor() {
    this.reload();
  }

  search(): void {
    this.meta.update((m) => ({ ...m, current_page: 1 }));
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
    const q = this.filters.getRawValue().q;
    const params: Record<string, string> = {
      page: String(this.meta().current_page),
      per_page: String(this.meta().per_page),
    };
    if (q) {
      params['q'] = q;
    }
    this.http.get<ApiResponse<{ items: any[]; meta: PageMeta }>>(`${environment.apiBaseUrl}/customers`, { params }).subscribe({
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
    this.http.post(`${environment.apiBaseUrl}/customers`, this.form.getRawValue()).subscribe({
      next: () => {
        this.form.reset({ document_type: 'DNI' });
        this.reload();
      },
    });
  }
}
