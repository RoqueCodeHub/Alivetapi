import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';
import { ApiClientError } from '../../core/interceptors/api.interceptor';

@Component({
  selector: 'app-portal-login',
  imports: [ReactiveFormsModule, RouterLink],
  template: `
    <main>
      <form [formGroup]="form" (ngSubmit)="submit()">
        <h1>Portal del cliente</h1>
        <p><a routerLink="/login">Soy personal de la clínica</a></p>
        <label>Correo <input type="email" formControlName="email" /></label>
        <label>Contraseña <input type="password" formControlName="password" /></label>
        @if (error()) {
          <p>{{ error() }}</p>
        }
        <button type="submit" [disabled]="submitting()">Entrar</button>
      </form>
    </main>
  `,
})
export class PortalLogin {
  private readonly fb = inject(FormBuilder);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  readonly submitting = signal(false);
  readonly error = signal<string | null>(null);
  readonly form = this.fb.nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', Validators.required],
  });

  submit(): void {
    if (this.form.invalid || this.submitting()) {
      return;
    }
    this.submitting.set(true);
    const { email, password } = this.form.getRawValue();
    this.auth.login(email, password, true).subscribe({
      next: () => {
        this.submitting.set(false);
        void this.router.navigateByUrl('/portal');
      },
      error: (err: unknown) => {
        this.submitting.set(false);
        this.error.set(err instanceof ApiClientError ? err.message : 'No se pudo entrar');
      },
    });
  }
}
