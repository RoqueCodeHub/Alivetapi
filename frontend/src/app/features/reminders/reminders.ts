import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

@Component({
  selector: 'app-reminders',
  imports: [ReactiveFormsModule],
  template: `
    <h1>Recordatorios</h1>
    <form [formGroup]="filters" (ngSubmit)="reload()">
      <select formControlName="audience">
        <option value="">Todos</option>
        <option value="customer">Clientes</option>
        <option value="internal">Internos</option>
      </select>
      <select formControlName="type">
        <option value="">Tipo</option>
        <option value="vacuna">Vacuna</option>
        <option value="desparasitacion">Desparasitación</option>
        <option value="cita">Cita</option>
        <option value="vencimiento">Vencimiento</option>
        <option value="stock_minimo">Stock mínimo</option>
        <option value="diferencia_caja">Diferencia de caja</option>
      </select>
      <button type="submit">Filtrar</button>
    </form>
    <ul>
      @for (item of items(); track item.id) {
        <li>
          <strong>{{ item.title }}</strong> — {{ item.message }}
          @if (item.whatsapp_url) {
            <a [href]="item.whatsapp_url" target="_blank" rel="noopener">WhatsApp</a>
          }
          @if (item.status !== 'dismissed') {
            <button type="button" (click)="dismiss(item.id)">Descartar</button>
          }
        </li>
      }
    </ul>
  `,
})
export class Reminders {
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);
  readonly items = signal<any[]>([]);
  readonly filters = this.fb.nonNullable.group({
    audience: [''],
    type: [''],
  });

  constructor() {
    this.reload();
  }

  reload(): void {
    const v = this.filters.getRawValue();
    const params: Record<string, string> = {};
    if (v.audience) params['audience'] = v.audience;
    if (v.type) params['type'] = v.type;
    this.http.get<ApiResponse<{ items: any[] }>>(`${environment.apiBaseUrl}/reminders`, { params }).subscribe({
      next: (res) => this.items.set(res.data?.items ?? []),
    });
  }

  dismiss(id: number): void {
    this.http.post(`${environment.apiBaseUrl}/reminders/${id}/dismiss`, {}).subscribe({
      next: () => this.reload(),
    });
  }
}
