import { Component, inject, signal } from '@angular/core';
import { HealthApiService } from '../../core/services/health-api.service';
import { ApiClientError } from '../../core/interceptors/api.interceptor';
import { HealthData } from '../../core/models/api-response';

@Component({
  selector: 'app-shell',
  templateUrl: './shell.html',
  styleUrl: './shell.scss',
})
export class Shell {
  private readonly healthApi = inject(HealthApiService);

  readonly loading = signal(true);
  readonly error = signal<string | null>(null);
  readonly health = signal<HealthData | null>(null);

  constructor() {
    this.healthApi.getHealth().subscribe({
      next: (response) => {
        this.health.set(response.data);
        this.error.set(null);
        this.loading.set(false);
      },
      error: (err: unknown) => {
        const message =
          err instanceof ApiClientError
            ? err.message
            : 'No se pudo consultar la API';
        this.error.set(message);
        this.health.set(null);
        this.loading.set(false);
      },
    });
  }
}
