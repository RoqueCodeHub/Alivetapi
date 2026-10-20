import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

@Component({
  selector: 'app-portal-carnet',
  imports: [RouterLink],
  template: `
    <p><a routerLink="/portal">Volver</a></p>
    <h1>Carnet de {{ data()?.pet?.nombre }}</h1>
    <h2>Vacunas</h2>
    <ul>
      @for (row of data()?.vaccinations ?? []; track row.id) {
        <li>{{ row.vaccine_name }} — {{ row.applied_at }} — próxima {{ row.next_due_at }}</li>
      }
    </ul>
    <h2>Desparasitaciones</h2>
    <ul>
      @for (row of data()?.dewormings ?? []; track row.id) {
        <li>{{ row.product_name }} — {{ row.applied_at }} — próxima {{ row.next_due_at }}</li>
      }
    </ul>
  `,
})
export class PortalCarnet {
  private readonly http = inject(HttpClient);
  private readonly route = inject(ActivatedRoute);
  readonly data = signal<any>(null);

  constructor() {
    const id = this.route.snapshot.paramMap.get('id');
    this.http.get<ApiResponse<any>>(`${environment.apiBaseUrl}/portal/pets/${id}/carnet`).subscribe({
      next: (res) => this.data.set(res.data),
    });
  }
}
