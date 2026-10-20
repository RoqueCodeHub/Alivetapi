import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

@Component({
  selector: 'app-clinic-services',
  imports: [ReactiveFormsModule],
  template: `
    <h1>Servicios clínicos</h1>
    <form [formGroup]="form" (ngSubmit)="create()">
      <input placeholder="Código" formControlName="code" />
      <input placeholder="Nombre" formControlName="name" />
      <input type="number" placeholder="Precio" formControlName="sale_price" />
      <select formControlName="staff_role">
        <option value="veterinario">Veterinario</option>
        <option value="groomer">Groomer</option>
        <option value="any">Cualquiera</option>
      </select>
      <button type="submit">Crear</button>
    </form>
    <ul>
      @for (item of items(); track item.id) {
        <li>{{ item.name }} — S/ {{ item.sale_price }} ({{ item.staff_role }})</li>
      }
    </ul>
  `,
})
export class ClinicServices {
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);
  readonly items = signal<any[]>([]);
  readonly form = this.fb.nonNullable.group({
    code: ['', Validators.required],
    name: ['', Validators.required],
    sale_price: [0, Validators.required],
    staff_role: ['veterinario'],
  });

  constructor() {
    this.reload();
  }

  reload(): void {
    this.http.get<ApiResponse<any[]>>(`${environment.apiBaseUrl}/services`).subscribe({
      next: (res) => this.items.set(res.data ?? []),
    });
  }

  create(): void {
    if (this.form.invalid) {
      return;
    }
    this.http.post(`${environment.apiBaseUrl}/services`, this.form.getRawValue()).subscribe({
      next: () => {
        this.form.reset({ staff_role: 'veterinario', sale_price: 0 });
        this.reload();
      },
    });
  }
}
