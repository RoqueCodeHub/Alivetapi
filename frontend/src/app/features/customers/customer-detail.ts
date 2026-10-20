import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

@Component({
  selector: 'app-customer-detail',
  imports: [ReactiveFormsModule, RouterLink],
  template: `
    <p><a routerLink="/app/clientes">Volver</a></p>
    <h1>{{ customer()?.name }}</h1>
    <p>{{ customer()?.document_type }} {{ customer()?.document_number }} — Tel. {{ customer()?.phone }}</p>
    <h2>Portal del cliente</h2>
    <form [formGroup]="portalForm" (ngSubmit)="enablePortal()">
      <input type="email" placeholder="Correo portal" formControlName="email" />
      <input type="password" placeholder="Contraseña" formControlName="password" />
      <button type="submit">Habilitar acceso</button>
    </form>
    @if (portalMsg()) {
      <p>{{ portalMsg() }}</p>
    }
    <h2>Mascotas</h2>
    <form [formGroup]="petForm" (ngSubmit)="addPet()">
      <input placeholder="Nombre" formControlName="nombre" />
      <input placeholder="Especie" formControlName="especie" />
      <input placeholder="Raza" formControlName="raza" />
      <select formControlName="sexo">
        <option value="desconocido">Sexo</option>
        <option value="macho">Macho</option>
        <option value="hembra">Hembra</option>
      </select>
      <input type="date" formControlName="fecha_nacimiento" />
      <input type="number" placeholder="Peso" formControlName="peso" />
      <input placeholder="Alergias" formControlName="alergias" />
      <button type="submit">Agregar mascota</button>
    </form>
    <ul>
      @for (pet of customer()?.pets ?? []; track pet.id) {
        <li>
          {{ pet.nombre }} — {{ pet.especie }} {{ pet.raza }} — {{ pet.sexo }}
          <a [routerLink]="['/app/mascotas', pet.id]">Historia clínica</a>
        </li>
      }
    </ul>
    <h2>Compras</h2>
    <ul>
      @for (sale of sales(); track sale.id) {
        <li>
          <a [routerLink]="['/app/ventas', sale.id]">{{ sale.document }}</a>
          — S/ {{ sale.total }} — {{ sale.status }}
        </li>
      }
    </ul>
  `,
})
export class CustomerDetail {
  private readonly http = inject(HttpClient);
  private readonly route = inject(ActivatedRoute);
  private readonly fb = inject(FormBuilder);
  readonly customer = signal<any>(null);
  readonly sales = signal<any[]>([]);
  readonly portalMsg = signal('');
  readonly portalForm = this.fb.nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required, Validators.minLength(8)]],
  });
  readonly petForm = this.fb.nonNullable.group({
    nombre: ['', Validators.required],
    especie: [''],
    raza: [''],
    sexo: ['desconocido'],
    fecha_nacimiento: [''],
    peso: [null as number | null],
    alergias: [''],
  });

  constructor() {
    this.load();
  }

  load(): void {
    const id = this.route.snapshot.paramMap.get('id');
    this.http.get<ApiResponse<any>>(`${environment.apiBaseUrl}/customers/${id}`).subscribe({
      next: (res) => this.customer.set(res.data),
    });
    this.http.get<ApiResponse<{ items: any[] }>>(`${environment.apiBaseUrl}/customers/${id}/purchases`).subscribe({
      next: (res) => this.sales.set(res.data?.items ?? []),
    });
  }

  enablePortal(): void {
    if (this.portalForm.invalid) {
      return;
    }
    const id = this.route.snapshot.paramMap.get('id');
    this.http.post(`${environment.apiBaseUrl}/customers/${id}/portal`, this.portalForm.getRawValue()).subscribe({
      next: () => this.portalMsg.set('Acceso habilitado'),
      error: (err) => this.portalMsg.set(err.message || 'No se pudo habilitar'),
    });
  }

  addPet(): void {
    const id = this.route.snapshot.paramMap.get('id');
    if (this.petForm.invalid || !id) {
      return;
    }
    this.http.post(`${environment.apiBaseUrl}/customers/${id}/pets`, this.petForm.getRawValue()).subscribe({
      next: () => {
        this.petForm.reset({ sexo: 'desconocido' });
        this.load();
      },
    });
  }
}
