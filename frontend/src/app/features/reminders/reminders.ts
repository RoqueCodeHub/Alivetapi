import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';
import { emptyPage, PageMeta, Pager, readPageMeta } from '../../shared/pager';

@Component({
  selector: 'app-reminders',
  imports: [ReactiveFormsModule, Pager],
  template: `
    <h1>Recordatorios</h1>
    <form [formGroup]="filters" (ngSubmit)="search()">
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
    <app-pager
      [page]="meta().current_page"
      [lastPage]="meta().last_page"
      [total]="meta().total"
      [perPage]="meta().per_page"
      label="recordatorios"
      (pageChange)="goTo($event)"
      (perPageChange)="setPerPage($event)"
    />
  `,
})
export class Reminders {
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);
  readonly items = signal<any[]>([]);
  readonly meta = signal<PageMeta>(emptyPage());
  readonly filters = this.fb.nonNullable.group({
    audience: [''],
    type: [''],
  });

  constructor() {
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
    if (v.audience) params['audience'] = v.audience;
    if (v.type) params['type'] = v.type;
    this.http.get<ApiResponse<{ items: any[]; meta: PageMeta }>>(`${environment.apiBaseUrl}/reminders`, { params }).subscribe({
      next: (res) => {
        this.items.set(res.data?.items ?? []);
        this.meta.set(readPageMeta(this.meta(), res.data?.meta));
      },
    });
  }

  dismiss(id: number): void {
    this.http.post(`${environment.apiBaseUrl}/reminders/${id}/dismiss`, {}).subscribe({
      next: () => this.reload(),
    });
  }
}
