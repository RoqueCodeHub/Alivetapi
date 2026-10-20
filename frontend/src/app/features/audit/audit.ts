import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

@Component({
  selector: 'app-audit',
  template: `
    <h1>Auditoría y seguridad</h1>
    <p>{{ notes() }}</p>
    <h2>Accesos</h2>
    <ul>
      @for (item of logins(); track item.id) {
        <li>{{ item.created_at }} — {{ item.email }} — {{ item.success ? 'ok' : 'fallo' }} — {{ item.ip }}</li>
      }
    </ul>
    <h2>Cambios</h2>
    <ul>
      @for (item of logs(); track item.id) {
        <li>{{ item.created_at }} — {{ item.user }} — {{ item.action }} {{ item.model }} #{{ item.model_id }}</li>
      }
    </ul>
    <h2>Respaldos</h2>
    <ul>
      @for (item of backups(); track item.name) {
        <li>{{ item.name }} ({{ item.size }} bytes) {{ item.modified_at }}</li>
      }
    </ul>
  `,
})
export class Audit {
  private readonly http = inject(HttpClient);
  readonly logs = signal<any[]>([]);
  readonly logins = signal<any[]>([]);
  readonly backups = signal<any[]>([]);
  readonly notes = signal('');

  constructor() {
    this.http.get<ApiResponse<{ items: any[] }>>(`${environment.apiBaseUrl}/audit-logs`).subscribe({
      next: (res) => this.logs.set(res.data?.items ?? []),
    });
    this.http.get<ApiResponse<{ items: any[] }>>(`${environment.apiBaseUrl}/login-logs`).subscribe({
      next: (res) => this.logins.set(res.data?.items ?? []),
    });
    this.http.get<ApiResponse<any[]>>(`${environment.apiBaseUrl}/backups`).subscribe({
      next: (res) => this.backups.set(res.data ?? []),
    });
    this.http.get<ApiResponse<{ notes: Record<string, string> }>>(`${environment.apiBaseUrl}/security-status`).subscribe({
      next: (res) =>
        this.notes.set(
          Object.entries(res.data?.notes ?? {})
            .map(([k, v]) => `${k}: ${v}`)
            .join(' · '),
        ),
    });
  }
}
