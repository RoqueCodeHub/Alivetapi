import { Component, inject } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-layout',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  templateUrl: './layout.html',
  styleUrl: './layout.scss',
})
export class Layout {
  readonly auth = inject(AuthService);

  constructor() {
    if (this.auth.isAuthenticated()) {
      this.auth.me().subscribe();
    }
  }

  readonly menu = [
    { label: 'Inicio', path: '/app', permission: null as string | null },
    { label: 'Usuarios', path: '/app/usuarios', permission: 'users.view' },
    { label: 'Categorías', path: '/app/categorias', permission: 'products.view' },
    { label: 'Marcas', path: '/app/marcas', permission: 'products.view' },
    { label: 'Productos', path: '/app/productos', permission: 'products.view' },
    { label: 'Ingreso de stock', path: '/app/ingreso-stock', permission: 'inventory.adjust' },
    { label: 'Importar inventario', path: '/app/importar-inventario', permission: 'inventory.adjust' },
    { label: 'Pendientes de revisión', path: '/app/pendientes', permission: 'products.view' },
    { label: 'Inventario', path: '/app/stock', permission: 'inventory.view' },
    { label: 'Alertas', path: '/app/alertas', permission: 'inventory.view' },
    { label: 'Conteo físico', path: '/app/conteos', permission: 'inventory.count' },
    { label: 'Proveedores', path: '/app/proveedores', permission: 'purchases.view' },
    { label: 'Compras', path: '/app/compras', permission: 'purchases.view' },
    { label: 'Ventas', path: '/app/ventas', permission: 'sales.view' },
    { label: 'Caja', path: '/app/pos', permission: 'sales.charge' },
    { label: 'Facturación', path: '/app/facturacion', permission: 'invoicing.view' },
    { label: 'Clientes', path: '/app/clientes', permission: 'customers.view' },
    { label: 'Servicios clínicos', path: '/app/servicios-clinicos', permission: 'clinic.view' },
    { label: 'Agenda', path: '/app/agenda', permission: 'clinic.view' },
    { label: 'Recordatorios', path: '/app/recordatorios', permission: 'reminders.view' },
    { label: 'Reportes', path: '/app/reportes', permission: 'reports.view_all' },
    { label: 'Auditoría', path: '/app/auditoria', permission: 'audit.view' },
    { label: 'Perfil', path: '/app/perfil', permission: null },
  ];

  visibleMenu() {
    return this.menu.filter(
      (item) => !item.permission || this.auth.hasPermission(item.permission),
    );
  }

  logout(): void {
    this.auth.logout().subscribe({
      next: () => this.auth.logoutAndRedirect(),
      error: () => this.auth.logoutAndRedirect(),
    });
  }
}
