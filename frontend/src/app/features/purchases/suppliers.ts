import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

interface Supplier {
  id: number;
  name: string;
  ruc?: string;
  phone?: string;
  email?: string;
}

@Component({
  selector: 'app-suppliers',
  imports: [ReactiveFormsModule],
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
  `,
})
export class Suppliers {
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);
  readonly items = signal<Supplier[]>([]);
  readonly form = this.fb.nonNullable.group({
    name: ['', Validators.required],
    ruc: [''],
    phone: [''],
    email: [''],
  });

  constructor() {
    this.reload();
  }

  reload(): void {
    this.http.get<ApiResponse<Supplier[]>>(`${environment.apiBaseUrl}/suppliers`).subscribe({
      next: (res) => this.items.set(res.data ?? []),
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
