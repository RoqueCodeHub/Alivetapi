import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

@Component({
  selector: 'app-customers',
  imports: [ReactiveFormsModule, RouterLink],
  template: `
    <h1>Clientes</h1>
    <form [formGroup]="filters" (ngSubmit)="reload()">
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
  `,
})
export class Customers {
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);
  readonly items = signal<any[]>([]);
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

  reload(): void {
    const q = this.filters.getRawValue().q;
    const params: Record<string, string> = {};
    if (q) {
      params['q'] = q;
    }
    this.http.get<ApiResponse<{ items: any[] }>>(`${environment.apiBaseUrl}/customers`, { params }).subscribe({
      next: (res) => this.items.set(res.data?.items ?? []),
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
