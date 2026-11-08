import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';
import { emptyPage, PageMeta, Pager, readPageMeta } from '../../shared/pager';

@Component({
  selector: 'app-audit',
  imports: [Pager],
  template: `
    <h1>Auditoría y seguridad</h1>
    <p>{{ notes() }}</p>
    <h2>Accesos</h2>
    <ul>
      @for (item of logins(); track item.id) {
        <li>{{ item.created_at }} — {{ item.email }} — {{ item.success ? 'ok' : 'fallo' }} — {{ item.ip }}</li>
      }
    </ul>
    <app-pager
      [page]="loginMeta().current_page"
      [lastPage]="loginMeta().last_page"
      [total]="loginMeta().total"
      [perPage]="loginMeta().per_page"
      label="accesos"
      (pageChange)="goLogins($event)"
      (perPageChange)="setLoginPerPage($event)"
    />
    <h2>Cambios</h2>
    <ul>
      @for (item of logs(); track item.id) {
        <li>{{ item.created_at }} — {{ item.user }} — {{ item.action }} {{ item.model }} #{{ item.model_id }}</li>
      }
    </ul>
    <app-pager
      [page]="logMeta().current_page"
      [lastPage]="logMeta().last_page"
      [total]="logMeta().total"
      [perPage]="logMeta().per_page"
      label="cambios"
      (pageChange)="goLogs($event)"
      (perPageChange)="setLogPerPage($event)"
    />
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
  readonly logMeta = signal<PageMeta>(emptyPage());
  readonly loginMeta = signal<PageMeta>(emptyPage());
  readonly backups = signal<any[]>([]);
  readonly notes = signal('');

  constructor() {
    this.loadLogs();
    this.loadLogins();
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

  goLogs(page: number): void {
    if (page < 1 || page > this.logMeta().last_page) {
      return;
    }
    this.logMeta.update((m) => ({ ...m, current_page: page }));
    this.loadLogs();
  }

  setLogPerPage(size: number): void {
    this.logMeta.update((m) => ({ ...m, per_page: size, current_page: 1 }));
    this.loadLogs();
  }

  goLogins(page: number): void {
    if (page < 1 || page > this.loginMeta().last_page) {
      return;
    }
    this.loginMeta.update((m) => ({ ...m, current_page: page }));
    this.loadLogins();
  }

  setLoginPerPage(size: number): void {
    this.loginMeta.update((m) => ({ ...m, per_page: size, current_page: 1 }));
    this.loadLogins();
  }

  private loadLogs(): void {
    this.http
      .get<ApiResponse<{ items: any[]; meta: PageMeta }>>(`${environment.apiBaseUrl}/audit-logs`, {
        params: {
          page: String(this.logMeta().current_page),
          per_page: String(this.logMeta().per_page),
        },
      })
      .subscribe({
        next: (res) => {
          this.logs.set(res.data?.items ?? []);
          this.logMeta.set(readPageMeta(this.logMeta(), res.data?.meta));
        },
      });
  }

  private loadLogins(): void {
    this.http
      .get<ApiResponse<{ items: any[]; meta: PageMeta }>>(`${environment.apiBaseUrl}/login-logs`, {
        params: {
          page: String(this.loginMeta().current_page),
          per_page: String(this.loginMeta().per_page),
        },
      })
      .subscribe({
        next: (res) => {
          this.logins.set(res.data?.items ?? []);
          this.loginMeta.set(readPageMeta(this.loginMeta(), res.data?.meta));
        },
      });
  }
}
