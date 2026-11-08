import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';
import { emptyPage, PageMeta, Pager, readPageMeta } from '../../shared/pager';

@Component({
  selector: 'app-clinic-services',
  imports: [ReactiveFormsModule, Pager],
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
    <app-pager
      [page]="meta().current_page"
      [lastPage]="meta().last_page"
      [total]="meta().total"
      [perPage]="meta().per_page"
      label="servicios"
      (pageChange)="goTo($event)"
      (perPageChange)="setPerPage($event)"
    />
  `,
})
export class ClinicServices {
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);
  readonly items = signal<any[]>([]);
  readonly meta = signal<PageMeta>(emptyPage());
  readonly form = this.fb.nonNullable.group({
    code: ['', Validators.required],
    name: ['', Validators.required],
    sale_price: [0, Validators.required],
    staff_role: ['veterinario'],
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
      .get<ApiResponse<{ items: any[]; meta: PageMeta }>>(`${environment.apiBaseUrl}/services`, {
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
    this.http.post(`${environment.apiBaseUrl}/services`, this.form.getRawValue()).subscribe({
      next: () => {
        this.form.reset({ staff_role: 'veterinario', sale_price: 0 });
        this.reload();
      },
    });
  }
}
