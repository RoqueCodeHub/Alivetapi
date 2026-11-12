import { HttpClient } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';
import { emptyPage, PageMeta, Pager, readPageMeta } from '../../shared/pager';

interface PetRow {
  id: number;
  nombre: string;
  especie: string | null;
  raza: string | null;
  sexo: string | null;
  fecha_nacimiento: string | null;
  peso: string | number | null;
  alergias: string | null;
}

@Component({
  selector: 'app-customer-detail',
  imports: [ReactiveFormsModule, RouterLink, Pager],
  changeDetection: ChangeDetectionStrategy.OnPush,
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
      <button type="submit">{{ editingPetId() ? 'Guardar mascota' : 'Agregar mascota' }}</button>
      @if (editingPetId()) {
        <button type="button" (click)="cancelPetEdit()">Cancelar</button>
      }
    </form>
    @if (petError()) {
      <p>{{ petError() }}</p>
    }
    <ul>
      @for (pet of pets(); track pet.id) {
        <li>
          {{ pet.nombre }} — {{ pet.especie }} {{ pet.raza }} — {{ pet.sexo }}
          <a [routerLink]="['/app/mascotas', pet.id]">Historia clínica</a>
          <button type="button" (click)="editPet(pet)">Editar</button>
          <button type="button" (click)="removePet(pet)">Eliminar</button>
        </li>
      }
    </ul>
    <app-pager
      [page]="petsMeta().current_page"
      [lastPage]="petsMeta().last_page"
      [total]="petsMeta().total"
      [perPage]="petsMeta().per_page"
      label="mascotas"
      (pageChange)="goPets($event)"
      (perPageChange)="setPetsPerPage($event)"
    />
    <h2>Compras</h2>
    <ul>
      @for (sale of sales(); track sale.id) {
        <li>
          <a [routerLink]="['/app/ventas', sale.id]">{{ sale.document }}</a>
          — S/ {{ sale.total }} — {{ sale.status }}
        </li>
      }
    </ul>
    <app-pager
      [page]="salesMeta().current_page"
      [lastPage]="salesMeta().last_page"
      [total]="salesMeta().total"
      [perPage]="salesMeta().per_page"
      label="compras"
      (pageChange)="goSales($event)"
      (perPageChange)="setSalesPerPage($event)"
    />
  `,
})
export class CustomerDetail {
  private readonly http = inject(HttpClient);
  private readonly route = inject(ActivatedRoute);
  private readonly fb = inject(FormBuilder);
  readonly customer = signal<any>(null);
  readonly pets = signal<PetRow[]>([]);
  readonly petsMeta = signal<PageMeta>(emptyPage());
  readonly sales = signal<any[]>([]);
  readonly salesMeta = signal<PageMeta>(emptyPage());
  readonly portalMsg = signal('');
  readonly petError = signal('');
  readonly editingPetId = signal<number | null>(null);
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
    this.loadPets();
    this.loadSales();
  }

  goPets(page: number): void {
    if (page < 1 || page > this.petsMeta().last_page) {
      return;
    }
    this.petsMeta.update((meta) => ({ ...meta, current_page: page }));
    this.loadPets();
  }

  setPetsPerPage(size: number): void {
    this.petsMeta.update((meta) => ({ ...meta, per_page: size, current_page: 1 }));
    this.loadPets();
  }

  editPet(pet: PetRow): void {
    this.editingPetId.set(pet.id);
    this.petError.set('');
    this.petForm.setValue({
      nombre: pet.nombre,
      especie: pet.especie ?? '',
      raza: pet.raza ?? '',
      sexo: pet.sexo || 'desconocido',
      fecha_nacimiento: pet.fecha_nacimiento ?? '',
      peso: pet.peso === null || pet.peso === '' ? null : Number(pet.peso),
      alergias: pet.alergias ?? '',
    });
  }

  cancelPetEdit(): void {
    this.editingPetId.set(null);
    this.petError.set('');
    this.petForm.reset({ sexo: 'desconocido' });
  }

  removePet(pet: PetRow): void {
    if (!confirm(`¿Eliminar a ${pet.nombre}?`)) {
      return;
    }
    this.petError.set('');
    this.http.delete<ApiResponse<null>>(`${environment.apiBaseUrl}/pets/${pet.id}`).subscribe({
      next: () => {
        if (this.editingPetId() === pet.id) {
          this.cancelPetEdit();
        }
        this.loadPets();
      },
      error: (err) => this.petError.set(err?.error?.message || 'No se pudo eliminar la mascota'),
    });
  }

  goSales(page: number): void {
    if (page < 1 || page > this.salesMeta().last_page) {
      return;
    }
    this.salesMeta.update((m) => ({ ...m, current_page: page }));
    this.loadSales();
  }

  setSalesPerPage(size: number): void {
    this.salesMeta.update((m) => ({ ...m, per_page: size, current_page: 1 }));
    this.loadSales();
  }

  private loadPets(): void {
    const id = this.route.snapshot.paramMap.get('id');
    this.http
      .get<ApiResponse<{ items: PetRow[]; meta: PageMeta }>>(`${environment.apiBaseUrl}/customers/${id}/pets`, {
        params: {
          page: String(this.petsMeta().current_page),
          per_page: String(this.petsMeta().per_page),
        },
      })
      .subscribe({
        next: (res) => {
          this.pets.set(res.data?.items ?? []);
          this.petsMeta.set(readPageMeta(this.petsMeta(), res.data?.meta));
        },
      });
  }

  private loadSales(): void {
    const id = this.route.snapshot.paramMap.get('id');
    this.http
      .get<ApiResponse<{ items: any[]; meta: PageMeta }>>(`${environment.apiBaseUrl}/customers/${id}/purchases`, {
        params: {
          page: String(this.salesMeta().current_page),
          per_page: String(this.salesMeta().per_page),
        },
      })
      .subscribe({
        next: (res) => {
          this.sales.set(res.data?.items ?? []);
          this.salesMeta.set(readPageMeta(this.salesMeta(), res.data?.meta));
        },
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
    this.petError.set('');
    const editing = this.editingPetId();
    const request = editing
      ? this.http.put<ApiResponse<PetRow>>(`${environment.apiBaseUrl}/pets/${editing}`, this.petForm.getRawValue())
      : this.http.post<ApiResponse<PetRow>>(`${environment.apiBaseUrl}/customers/${id}/pets`, this.petForm.getRawValue());
    request.subscribe({
      next: () => {
        this.cancelPetEdit();
        if (!editing) {
          this.petsMeta.update((meta) => ({ ...meta, current_page: 1 }));
        }
        this.loadPets();
      },
      error: (err) => this.petError.set(err?.error?.message || 'No se pudo guardar la mascota'),
    });
  }
}
