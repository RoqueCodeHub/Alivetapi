import { Routes } from '@angular/router';
import { guestGuard } from './core/guards/guest.guard';
import { permissionGuard } from './core/guards/permission.guard';
import { portalGuard, staffGuard } from './core/guards/portal.guard';

export const routes: Routes = [
  {
    path: 'login',
    canActivate: [guestGuard],
    loadComponent: () => import('./features/auth/login').then((m) => m.Login),
  },
  {
    path: 'app',
    canActivate: [staffGuard],
    loadComponent: () => import('./features/shell/layout').then((m) => m.Layout),
    children: [
      {
        path: '',
        loadComponent: () => import('./features/shell/home').then((m) => m.Home),
      },
      {
        path: 'usuarios',
        canActivate: [permissionGuard],
        data: { permission: 'users.view' },
        loadComponent: () => import('./features/users/users').then((m) => m.Users),
      },
      {
        path: 'categorias',
        canActivate: [permissionGuard],
        data: { permission: 'products.view' },
        loadComponent: () => import('./features/catalog/categories').then((m) => m.Categories),
      },
      {
        path: 'marcas',
        canActivate: [permissionGuard],
        data: { permission: 'products.view' },
        loadComponent: () => import('./features/catalog/brands').then((m) => m.Brands),
      },
      {
        path: 'productos',
        canActivate: [permissionGuard],
        data: { permission: 'products.view' },
        loadComponent: () => import('./features/catalog/products').then((m) => m.Products),
      },
      {
        path: 'ingreso-stock',
        canActivate: [permissionGuard],
        data: { permission: 'inventory.adjust' },
        loadComponent: () => import('./features/inventory/stock-intake').then((m) => m.StockIntake),
      },
      {
        path: 'productos/:id/lotes',
        canActivate: [permissionGuard],
        data: { permission: 'products.view' },
        loadComponent: () => import('./features/catalog/product-lots').then((m) => m.ProductLots),
      },
      {
        path: 'productos/:id',
        canActivate: [permissionGuard],
        data: { permission: 'products.view' },
        loadComponent: () => import('./features/catalog/product-detail').then((m) => m.ProductDetail),
      },
      {
        path: 'importar-inventario',
        canActivate: [permissionGuard],
        data: { permission: 'inventory.adjust' },
        loadComponent: () =>
          import('./features/inventory/import-inventory').then((m) => m.ImportInventory),
      },
      {
        path: 'pendientes',
        canActivate: [permissionGuard],
        data: { permission: 'products.view' },
        loadComponent: () =>
          import('./features/inventory/pending-review').then((m) => m.PendingReview),
      },
      {
        path: 'stock',
        canActivate: [permissionGuard],
        data: { permission: 'inventory.view' },
        loadComponent: () => import('./features/inventory/stock-list').then((m) => m.StockList),
      },
      {
        path: 'stock/:id',
        canActivate: [permissionGuard],
        data: { permission: 'inventory.view' },
        loadComponent: () => import('./features/inventory/stock-detail').then((m) => m.StockDetail),
      },
      {
        path: 'kardex/:id',
        canActivate: [permissionGuard],
        data: { permission: 'inventory.view' },
        loadComponent: () => import('./features/inventory/kardex').then((m) => m.Kardex),
      },
      {
        path: 'alertas',
        canActivate: [permissionGuard],
        data: { permission: 'inventory.view' },
        loadComponent: () => import('./features/inventory/stock-alerts').then((m) => m.StockAlerts),
      },
      {
        path: 'conteos',
        canActivate: [permissionGuard],
        data: { permission: 'inventory.count' },
        loadComponent: () => import('./features/inventory/stock-counts').then((m) => m.StockCounts),
      },
      {
        path: 'conteos/:id',
        canActivate: [permissionGuard],
        data: { permission: 'inventory.count' },
        loadComponent: () =>
          import('./features/inventory/stock-count-detail').then((m) => m.StockCountDetail),
      },
      {
        path: 'proveedores',
        canActivate: [permissionGuard],
        data: { permission: 'purchases.view' },
        loadComponent: () => import('./features/purchases/suppliers').then((m) => m.Suppliers),
      },
      {
        path: 'compras',
        canActivate: [permissionGuard],
        data: { permission: 'purchases.view' },
        loadComponent: () => import('./features/purchases/purchases').then((m) => m.Purchases),
      },
      {
        path: 'compras/nueva',
        canActivate: [permissionGuard],
        data: { permission: 'purchases.create' },
        loadComponent: () =>
          import('./features/purchases/purchase-detail').then((m) => m.PurchaseDetail),
      },
      {
        path: 'compras/:id',
        canActivate: [permissionGuard],
        data: { permission: 'purchases.view' },
        loadComponent: () =>
          import('./features/purchases/purchase-detail').then((m) => m.PurchaseDetail),
      },
      {
        path: 'cuentas-por-pagar',
        canActivate: [permissionGuard],
        data: { permission: 'purchases.view' },
        loadComponent: () => import('./features/purchases/payables').then((m) => m.Payables),
      },
      {
        path: 'pos',
        canActivate: [permissionGuard],
        data: { permission: 'sales.charge' },
        loadComponent: () => import('./features/pos/pos').then((m) => m.Pos),
      },
      {
        path: 'caja',
        canActivate: [permissionGuard],
        data: { permission: 'sales.charge' },
        loadComponent: () => import('./features/pos/cash-session').then((m) => m.CashSessionPage),
      },
      {
        path: 'ventas',
        canActivate: [permissionGuard],
        data: { permission: 'sales.view' },
        loadComponent: () => import('./features/pos/sales').then((m) => m.Sales),
      },
      {
        path: 'ventas/:id',
        canActivate: [permissionGuard],
        data: { permission: 'sales.view' },
        loadComponent: () => import('./features/pos/sale-detail').then((m) => m.SaleDetail),
      },
      {
        path: 'facturacion',
        canActivate: [permissionGuard],
        data: { permission: 'invoicing.view' },
        loadComponent: () => import('./features/invoices/invoices').then((m) => m.Invoices),
      },
      {
        path: 'clientes',
        canActivate: [permissionGuard],
        data: { permission: 'customers.view' },
        loadComponent: () => import('./features/customers/customers').then((m) => m.Customers),
      },
      {
        path: 'clientes/:id',
        canActivate: [permissionGuard],
        data: { permission: 'customers.view' },
        loadComponent: () =>
          import('./features/customers/customer-detail').then((m) => m.CustomerDetail),
      },
      {
        path: 'servicios-clinicos',
        canActivate: [permissionGuard],
        data: { permission: 'clinic.view' },
        loadComponent: () =>
          import('./features/clinic/clinic-services').then((m) => m.ClinicServices),
      },
      {
        path: 'agenda',
        canActivate: [permissionGuard],
        data: { permission: 'clinic.view' },
        loadComponent: () => import('./features/clinic/agenda').then((m) => m.Agenda),
      },
      {
        path: 'mascotas/:id',
        canActivate: [permissionGuard],
        data: { permission: 'clinic.view' },
        loadComponent: () => import('./features/clinic/pet-clinic').then((m) => m.PetClinic),
      },
      {
        path: 'recordatorios',
        canActivate: [permissionGuard],
        data: { permission: 'reminders.view' },
        loadComponent: () => import('./features/reminders/reminders').then((m) => m.Reminders),
      },
      {
        path: 'reportes',
        canActivate: [permissionGuard],
        data: { permission: 'reports.view_all' },
        loadComponent: () => import('./features/reports/reports').then((m) => m.Reports),
      },
      {
        path: 'auditoria',
        canActivate: [permissionGuard],
        data: { permission: 'audit.view' },
        loadComponent: () => import('./features/audit/audit').then((m) => m.Audit),
      },
      {
        path: 'perfil',
        loadComponent: () => import('./features/profile/profile').then((m) => m.Profile),
      },
    ],
  },
  {
    path: 'portal/login',
    canActivate: [guestGuard],
    loadComponent: () => import('./features/portal/portal-login').then((m) => m.PortalLogin),
  },
  {
    path: 'portal',
    canActivate: [portalGuard],
    loadComponent: () => import('./features/portal/portal-shell').then((m) => m.PortalShell),
    children: [
      {
        path: '',
        loadComponent: () => import('./features/portal/portal-home').then((m) => m.PortalHome),
      },
      {
        path: 'mascotas/:id/carnet',
        loadComponent: () => import('./features/portal/portal-carnet').then((m) => m.PortalCarnet),
      },
    ],
  },
  { path: '', pathMatch: 'full', redirectTo: 'app' },
  { path: '**', redirectTo: 'login' },
];
