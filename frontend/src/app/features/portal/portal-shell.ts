import { Component, inject } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-portal-shell',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  template: `
    <nav>
      <a routerLink="/portal" routerLinkActive="active" [routerLinkActiveOptions]="{ exact: true }">Inicio</a>
      <button type="button" (click)="logout()">Salir</button>
    </nav>
    <router-outlet />
  `,
})
export class PortalShell {
  readonly auth = inject(AuthService);

  logout(): void {
    this.auth.logout().subscribe({
      next: () => this.auth.logoutAndRedirect(),
      error: () => this.auth.logoutAndRedirect(),
    });
  }
}
