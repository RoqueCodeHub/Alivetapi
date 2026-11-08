import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';
import { emptyPage, PageMeta, Pager, readPageMeta } from '../../shared/pager';

@Component({
  selector: 'app-agenda',
  imports: [ReactiveFormsModule, RouterLink, Pager],
  template: `
    <h1>Agenda</h1>
    <form [formGroup]="filters" (ngSubmit)="search()">
      <input type="date" formControlName="date" />
      <select formControlName="status">
        <option value="">Estado</option>
        <option value="pendiente">Pendiente</option>
        <option value="atendida">Atendida</option>
        <option value="cancelada">Cancelada</option>
      </select>
      <button type="submit">Filtrar</button>
    </form>
    <form [formGroup]="form" (ngSubmit)="create()">
      <input type="number" placeholder="ID mascota" formControlName="pet_id" />
      <select formControlName="clinic_service_id">
        <option value="">Servicio</option>
        @for (s of services(); track s.id) {
          <option [value]="s.id">{{ s.name }}</option>
        }
      </select>
      <input type="number" placeholder="ID staff" formControlName="staff_user_id" />
      <input type="datetime-local" formControlName="scheduled_at" />
      <button type="submit">Agendar</button>
    </form>
    <ul>
      @for (item of items(); track item.id) {
        <li>
          {{ item.scheduled_at }} — {{ item.mascota }} — {{ item.servicio }} — {{ item.status }}
          @if (item.pet_id) {
            <a [routerLink]="['/app/mascotas', item.pet_id]">Historia</a>
          }
          @if (item.status === 'pendiente') {
            <button type="button" (click)="attend(item.id)">Atender</button>
            <button type="button" (click)="cancel(item.id)">Cancelar</button>
          }
        </li>
      }
    </ul>
    <app-pager
      [page]="meta().current_page"
      [lastPage]="meta().last_page"
      [total]="meta().total"
      [perPage]="meta().per_page"
      label="citas"
      (pageChange)="goTo($event)"
      (perPageChange)="setPerPage($event)"
    />
    @if (message()) {
      <p>{{ message() }}</p>
    }
  `,
})
export class Agenda {
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);
  readonly items = signal<any[]>([]);
  readonly meta = signal<PageMeta>(emptyPage());
  readonly services = signal<any[]>([]);
  readonly message = signal('');
  readonly filters = this.fb.nonNullable.group({ date: [''], status: [''] });
  readonly form = this.fb.nonNullable.group({
    pet_id: [0, Validators.required],
    clinic_service_id: ['', Validators.required],
    staff_user_id: [0, Validators.required],
    scheduled_at: ['', Validators.required],
  });

  constructor() {
    this.http.get<ApiResponse<any[]>>(`${environment.apiBaseUrl}/services`).subscribe({
      next: (res) => this.services.set(res.data ?? []),
    });
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
    const v = this.filters.getRawValue();
    const params: Record<string, string> = {
      page: String(this.meta().current_page),
      per_page: String(this.meta().per_page),
    };
    if (v.date) params['date'] = v.date;
    if (v.status) params['status'] = v.status;
    this.http.get<ApiResponse<{ items: any[]; meta: PageMeta }>>(`${environment.apiBaseUrl}/appointments`, { params }).subscribe({
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
    this.http.post(`${environment.apiBaseUrl}/appointments`, this.form.getRawValue()).subscribe({
      next: () => this.reload(),
      error: (err) => this.message.set(err.message || 'No se agendó'),
    });
  }

  attend(id: number): void {
    this.http.post(`${environment.apiBaseUrl}/appointments/${id}/attend`, { type: 'consulta' }).subscribe({
      next: () => this.reload(),
    });
  }

  cancel(id: number): void {
    this.http.post(`${environment.apiBaseUrl}/appointments/${id}/cancel`, {}).subscribe({
      next: () => this.reload(),
    });
  }
}
