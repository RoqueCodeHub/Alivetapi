import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../core/services/auth.service';
import { ApiClientError } from '../../core/interceptors/api.interceptor';

@Component({
  selector: 'app-profile',
  imports: [ReactiveFormsModule],
  template: `
    <h1>Perfil</h1>
    <p>{{ auth.user()?.name }} — {{ auth.user()?.email }}</p>
    <form [formGroup]="form" (ngSubmit)="save()">
      <input type="password" placeholder="Contraseña actual" formControlName="current_password" />
      <input type="password" placeholder="Nueva contraseña" formControlName="password" />
      <input type="password" placeholder="Confirmar" formControlName="password_confirmation" />
      @if (error()) { <p>{{ error() }}</p> }
      @if (ok()) { <p>{{ ok() }}</p> }
      <button type="submit">Cambiar contraseña</button>
    </form>
  `,
})
export class Profile {
  readonly auth = inject(AuthService);
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);
  readonly error = signal<string | null>(null);
  readonly ok = signal<string | null>(null);

  readonly form = this.fb.nonNullable.group({
    current_password: ['', Validators.required],
    password: ['', [Validators.required, Validators.minLength(8)]],
    password_confirmation: ['', Validators.required],
  });

  save(): void {
    this.error.set(null);
    this.ok.set(null);
    this.http.put(`${environment.apiBaseUrl}/auth/password`, this.form.getRawValue()).subscribe({
      next: () => this.ok.set('Contraseña actualizada'),
      error: (err: unknown) => {
        this.error.set(err instanceof ApiClientError ? err.message : 'No se pudo actualizar');
      },
    });
  }
}
