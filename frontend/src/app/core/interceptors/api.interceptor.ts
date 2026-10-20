import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, throwError } from 'rxjs';
import { AuthService } from '../services/auth.service';
import { getStoredToken } from '../auth/session';
import { ApiResponse } from '../models/api-response';

export class ApiClientError extends Error {
  constructor(
    message: string,
    readonly status: number,
    readonly body: ApiResponse | null,
  ) {
    super(message);
    this.name = 'ApiClientError';
  }
}

export const apiInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);
  const token = getStoredToken();
  const headers: Record<string, string> = { Accept: 'application/json' };

  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const request = req.clone({ setHeaders: headers });

  return next(request).pipe(
    catchError((error: HttpErrorResponse) => {
      const body = isApiResponse(error.error) ? error.error : null;
      const message =
        body?.message ||
        (error.status === 0
          ? 'No se pudo conectar con el servidor'
          : error.message || 'Error de red');

      if (error.status === 401 && !req.url.includes('/auth/login') && !req.url.includes('/portal/login')) {
        auth.logoutAndRedirect();
      }

      return throwError(() => new ApiClientError(message, error.status, body));
    }),
  );
};

function isApiResponse(value: unknown): value is ApiResponse {
  return (
    typeof value === 'object' &&
    value !== null &&
    'success' in value &&
    'message' in value
  );
}
