import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

@Component({
  selector: 'app-portal-home',
  imports: [ReactiveFormsModule, RouterLink],
  template: `
    <h1>Hola {{ me()?.customer?.name }}</h1>
    <h2>Mascotas</h2>
    <ul>
      @for (pet of me()?.customer?.pets ?? []; track pet.id) {
        <li>
          {{ pet.nombre }} — {{ pet.especie }}
          <a [routerLink]="['/portal/mascotas', pet.id, 'carnet']">Carnet</a>
        </li>
      }
    </ul>
    <h2>Próximas citas</h2>
    <ul>
      @for (item of upcoming(); track item.id) {
        <li>{{ item.scheduled_at }} — {{ item.mascota }} — {{ item.servicio }} ({{ item.status }})</li>
      }
    </ul>
    <h2>Solicitar cita</h2>
    <form [formGroup]="form" (ngSubmit)="request()">
      <select formControlName="pet_id">
        <option value="">Mascota</option>
        @for (pet of me()?.customer?.pets ?? []; track pet.id) {
          <option [value]="pet.id">{{ pet.nombre }}</option>
        }
      </select>
      <select formControlName="clinic_service_id">
        <option value="">Servicio</option>
        @for (svc of services(); track svc.id) {
          <option [value]="svc.id">{{ svc.name }}</option>
        }
      </select>
      <input type="datetime-local" formControlName="scheduled_at" />
      <button type="submit">Enviar solicitud</button>
    </form>
    @if (message()) {
      <p>{{ message() }}</p>
    }
    <h2>Historial de compras</h2>
    <ul>
      @for (sale of purchases(); track sale.id) {
        <li>{{ sale.document }} — S/ {{ sale.total }} — {{ sale.status }}</li>
      }
    </ul>
  `,
})
export class PortalHome {
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);
  readonly me = signal<any>(null);
  readonly upcoming = signal<any[]>([]);
  readonly services = signal<any[]>([]);
  readonly purchases = signal<any[]>([]);
  readonly message = signal('');
  readonly form = this.fb.nonNullable.group({
    pet_id: ['', Validators.required],
    clinic_service_id: ['', Validators.required],
    scheduled_at: ['', Validators.required],
  });

  constructor() {
    this.http.get<ApiResponse<any>>(`${environment.apiBaseUrl}/portal/me`).subscribe({
      next: (res) => this.me.set(res.data),
    });
    this.http.get<ApiResponse<any>>(`${environment.apiBaseUrl}/portal/appointments`).subscribe({
      next: (res) => this.upcoming.set(res.data?.upcoming ?? []),
    });
    this.http.get<ApiResponse<any[]>>(`${environment.apiBaseUrl}/portal/services`).subscribe({
      next: (res) => this.services.set(res.data ?? []),
    });
    this.http.get<ApiResponse<{ items: any[] }>>(`${environment.apiBaseUrl}/portal/purchases`).subscribe({
      next: (res) => this.purchases.set(res.data?.items ?? []),
    });
  }

  request(): void {
    if (this.form.invalid) {
      return;
    }
    const v = this.form.getRawValue();
    this.http
      .post(`${environment.apiBaseUrl}/portal/appointments`, {
        pet_id: Number(v.pet_id),
        clinic_service_id: Number(v.clinic_service_id),
        scheduled_at: v.scheduled_at,
      })
      .subscribe({
        next: () => {
          this.message.set('Solicitud enviada');
          this.http.get<ApiResponse<any>>(`${environment.apiBaseUrl}/portal/appointments`).subscribe({
            next: (res) => this.upcoming.set(res.data?.upcoming ?? []),
          });
        },
        error: (err) => this.message.set(err.message || 'No se pudo solicitar'),
      });
  }
}
