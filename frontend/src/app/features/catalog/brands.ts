import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

interface Brand {
  id: number;
  nombre: string;
  activo: boolean;
}

@Component({
  selector: 'app-brands',
  imports: [ReactiveFormsModule],
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
  `,
})
export class Brands {
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);
  readonly brands = signal<Brand[]>([]);
  readonly form = this.fb.nonNullable.group({
    nombre: ['', Validators.required],
  });

  constructor() {
    this.reload();
  }

  reload(): void {
    this.http.get<ApiResponse<Brand[]>>(`${environment.apiBaseUrl}/brands`).subscribe({
      next: (res) => this.brands.set(res.data ?? []),
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
