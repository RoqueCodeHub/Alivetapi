import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { tap } from 'rxjs';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../models/api-response';
import { AuthUser, LoginData } from '../models/auth-user';
import { clearSession, getStoredToken, getStoredUser, storeSession } from '../auth/session';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);

  readonly user = signal<AuthUser | null>(getStoredUser<AuthUser>());
  readonly token = signal<string | null>(getStoredToken());
  readonly isAuthenticated = computed(() => !!this.token());

  login(email: string, password: string, portal = false) {
    const path = portal ? '/portal/login' : '/auth/login';
    return this.http
      .post<ApiResponse<LoginData>>(`${environment.apiBaseUrl}${path}`, { email, password })
      .pipe(
        tap((response) => {
          const data = response.data;
          if (!data) {
            return;
          }
          storeSession(data.token, data.user);
          this.token.set(data.token);
          this.user.set(data.user);
        }),
      );
  }

  logout() {
    return this.http.post<ApiResponse>(`${environment.apiBaseUrl}/auth/logout`, {}).pipe(
      tap(() => this.clearLocalSession()),
    );
  }

  me() {
    return this.http.get<ApiResponse<AuthUser>>(`${environment.apiBaseUrl}/auth/me`).pipe(
      tap((response) => {
        if (response.data) {
          this.user.set(response.data);
          const token = this.token();
          if (token) {
            storeSession(token, response.data);
          }
        }
      }),
    );
  }

  hasPermission(permission: string): boolean {
    return this.user()?.permissions.includes(permission) ?? false;
  }

  hasRole(role: string): boolean {
    return this.user()?.roles.includes(role) ?? false;
  }

  clearLocalSession(): void {
    clearSession();
    this.token.set(null);
    this.user.set(null);
  }

  isPortalCustomer(): boolean {
    return !!this.user()?.cliente_id;
  }

  homePath(): string {
    return this.isPortalCustomer() ? '/portal' : '/app';
  }

  logoutAndRedirect(): void {
    const portal = this.isPortalCustomer();
    this.clearLocalSession();
    void this.router.navigateByUrl(portal ? '/portal/login' : '/login');
  }
}
