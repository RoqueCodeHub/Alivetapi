import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';
import { AuthUser } from '../../core/models/auth-user';
import { ApiClientError } from '../../core/interceptors/api.interceptor';

@Component({
  selector: 'app-users',
  imports: [ReactiveFormsModule],
  templateUrl: './users.html',
  styleUrl: './users.scss',
})
export class Users {
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);

  readonly users = signal<AuthUser[]>([]);
  readonly roles = signal<string[]>([]);
  readonly error = signal<string | null>(null);
  readonly message = signal<string | null>(null);

  readonly form = this.fb.nonNullable.group({
    name: ['', Validators.required],
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required, Validators.minLength(8)]],
    roles: ['Operador de tienda', Validators.required],
  });

  constructor() {
    this.reload();
    this.http.get<ApiResponse<string[]>>(`${environment.apiBaseUrl}/roles`).subscribe({
      next: (res) => this.roles.set(res.data ?? []),
    });
  }

  reload(): void {
    this.http
      .get<ApiResponse<{ items: AuthUser[] }>>(`${environment.apiBaseUrl}/users`)
      .subscribe({
        next: (res) => this.users.set(res.data?.items ?? []),
        error: (err: unknown) => {
          this.error.set(err instanceof ApiClientError ? err.message : 'Error al cargar');
        },
      });
  }

  create(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    const value = this.form.getRawValue();
    this.http
      .post<ApiResponse<AuthUser>>(`${environment.apiBaseUrl}/users`, {
        ...value,
        roles: [value.roles],
      })
      .subscribe({
        next: () => {
          this.message.set('Usuario creado');
          this.form.reset({ roles: 'Operador de tienda' });
          this.reload();
        },
        error: (err: unknown) => {
          this.error.set(err instanceof ApiClientError ? err.message : 'No se pudo crear');
        },
      });
  }

  toggle(user: AuthUser): void {
    const path = user.is_active ? 'deactivate' : 'activate';
    this.http.patch(`${environment.apiBaseUrl}/users/${user.id}/${path}`, {}).subscribe({
      next: () => this.reload(),
    });
  }

  assignRole(user: AuthUser, role: string): void {
    if (!role || user.roles.includes(role)) {
      return;
    }
    this.http.put<ApiResponse<AuthUser>>(`${environment.apiBaseUrl}/users/${user.id}`, { roles: [role] }).subscribe({
      next: () => {
        this.message.set(`${user.name} ahora es ${role}. Cajero y administrador cobran en Caja; el operador solo registra ventas.`);
        this.error.set(null);
        this.reload();
      },
      error: (err: unknown) => {
        this.error.set(err instanceof ApiClientError ? err.message : 'No se pudo cambiar el rol');
      },
    });
  }
}
