import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

@Component({
  selector: 'app-agenda',
  imports: [ReactiveFormsModule, RouterLink],
  template: `
    <h1>Agenda</h1>
    <form [formGroup]="filters" (ngSubmit)="reload()">
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
    @if (message()) {
      <p>{{ message() }}</p>
    }
  `,
})
export class Agenda {
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);
  readonly items = signal<any[]>([]);
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

  reload(): void {
    const v = this.filters.getRawValue();
    const params: Record<string, string> = {};
    if (v.date) params['date'] = v.date;
    if (v.status) params['status'] = v.status;
    this.http.get<ApiResponse<{ items: any[] }>>(`${environment.apiBaseUrl}/appointments`, { params }).subscribe({
      next: (res) => this.items.set(res.data?.items ?? []),
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
