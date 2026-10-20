import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

@Component({
  selector: 'app-pet-clinic',
  imports: [ReactiveFormsModule, RouterLink],
  template: `
    <p><a routerLink="/app/agenda">Agenda</a></p>
    <h1>{{ data()?.pet?.nombre }}</h1>
    <h2>Historia clínica</h2>
    <form [formGroup]="recordForm" (ngSubmit)="addRecord()">
      <input placeholder="Diagnóstico" formControlName="diagnostico" />
      <input placeholder="Tratamiento" formControlName="tratamiento" />
      <input type="number" placeholder="Peso" formControlName="peso" />
      <input placeholder="Exámenes" formControlName="examenes" />
      <button type="submit">Registrar consulta</button>
    </form>
    <ul>
      @for (row of data()?.records ?? []; track row.id) {
        <li>{{ row.created_at }} — {{ row.diagnostico }} — {{ row.tratamiento }} ({{ row.peso }} kg)</li>
      }
    </ul>
    <h2>Carnet de vacunas</h2>
    <form [formGroup]="vaxForm" (ngSubmit)="addVax()">
      <input placeholder="Vacuna" formControlName="vaccine_name" />
      <input type="date" formControlName="applied_at" />
      <input type="date" formControlName="next_due_at" />
      <button type="submit">Registrar vacuna</button>
    </form>
    <ul>
      @for (row of data()?.vaccinations ?? []; track row.id) {
        <li>{{ row.vaccine_name }} — {{ row.applied_at }} — próxima {{ row.next_due_at }}</li>
      }
    </ul>
    <h2>Desparasitación</h2>
    <form [formGroup]="dewForm" (ngSubmit)="addDew()">
      <input placeholder="Producto" formControlName="product_name" />
      <input type="date" formControlName="applied_at" />
      <input type="date" formControlName="next_due_at" />
      <button type="submit">Registrar</button>
    </form>
    <ul>
      @for (row of data()?.dewormings ?? []; track row.id) {
        <li>{{ row.product_name }} — {{ row.applied_at }} — próxima {{ row.next_due_at }}</li>
      }
    </ul>
  `,
})
export class PetClinic {
  private readonly http = inject(HttpClient);
  private readonly route = inject(ActivatedRoute);
  private readonly fb = inject(FormBuilder);
  readonly data = signal<any>(null);
  readonly recordForm = this.fb.nonNullable.group({
    diagnostico: [''],
    tratamiento: [''],
    peso: [null as number | null],
    examenes: [''],
  });
  readonly vaxForm = this.fb.nonNullable.group({
    vaccine_name: ['', Validators.required],
    applied_at: [''],
    next_due_at: [''],
  });
  readonly dewForm = this.fb.nonNullable.group({
    product_name: ['', Validators.required],
    applied_at: [''],
    next_due_at: [''],
  });

  constructor() {
    this.load();
  }

  petId(): string {
    return this.route.snapshot.paramMap.get('id') || '';
  }

  load(): void {
    this.http.get<ApiResponse<any>>(`${environment.apiBaseUrl}/pets/${this.petId()}/clinical-history`).subscribe({
      next: (res) => this.data.set(res.data),
    });
  }

  addRecord(): void {
    this.http.post(`${environment.apiBaseUrl}/pets/${this.petId()}/medical-records`, this.recordForm.getRawValue()).subscribe({
      next: () => {
        this.recordForm.reset();
        this.load();
      },
    });
  }

  addVax(): void {
    this.http.post(`${environment.apiBaseUrl}/pets/${this.petId()}/vaccinations`, this.vaxForm.getRawValue()).subscribe({
      next: () => {
        this.vaxForm.reset();
        this.load();
      },
    });
  }

  addDew(): void {
    this.http.post(`${environment.apiBaseUrl}/pets/${this.petId()}/dewormings`, this.dewForm.getRawValue()).subscribe({
      next: () => {
        this.dewForm.reset();
        this.load();
      },
    });
  }
}
