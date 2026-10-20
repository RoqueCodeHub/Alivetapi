import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

interface PresentationRow {
  product_id: number;
  variant_id: number | null;
  nombre: string;
  presentacion: string;
  categoria: string;
  lote_count: number;
  on_hand: number;
  precio_venta: number;
  stock_status: 'sin_stock' | 'bajo' | 'ok';
}

interface PageMeta {
  current_page: number;
  last_page: number;
  total: number;
}

@Component({
  selector: 'app-products',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './products.html',
  styleUrl: './products.scss',
})
export class Products {
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);
  readonly items = signal<PresentationRow[]>([]);
  readonly categories = signal<{ id: number; nombre: string }[]>([]);
  readonly brands = signal<{ id: number; nombre: string }[]>([]);
  readonly suppliers = signal<{ id: number; name: string }[]>([]);
  readonly meta = signal<PageMeta>({ current_page: 1, last_page: 1, total: 0 });
  readonly especies = ['Perro', 'Gato', 'Perro/Gato', 'Bovino', 'Equino', 'Aves', 'Porcino', 'Múltiple'];
  readonly tipos = [
    'Accesorio',
    'Ropa',
    'Cama',
    'Higiene',
    'Antiparasitario externo',
    'Medicamento',
    'Antibiótico',
    'Antiinflamatorio',
    'Vitamina/Suplemento',
  ];
  readonly filters = this.fb.nonNullable.group({
    q: [''],
    category_id: [''],
    especie: [''],
    tipo: [''],
    proveedor_id: [''],
    marca_id: [''],
    activo: [''],
    low_stock: [false],
    expired: [false],
    expiring: [false],
    per_page: ['15'],
  });

  constructor() {
    this.http.get<ApiResponse<any[]>>(`${environment.apiBaseUrl}/categories`).subscribe({
      next: (res) => this.categories.set(this.flatten(res.data ?? [])),
    });
    this.http.get<ApiResponse<any[]>>(`${environment.apiBaseUrl}/brands`).subscribe({
      next: (res) => this.brands.set(res.data ?? []),
    });
    this.http.get<ApiResponse<any[]>>(`${environment.apiBaseUrl}/suppliers`).subscribe({
      next: (res) => this.suppliers.set(res.data ?? []),
    });
    this.reload();
  }

  search(): void {
    this.meta.update((m) => ({ ...m, current_page: 1 }));
    this.reload();
  }

  goTo(page: number): void {
    if (page < 1 || page > this.meta().last_page) {
      return;
    }
    this.meta.update((m) => ({ ...m, current_page: page }));
    this.reload();
  }

  money(value: number): string {
    return Number(value || 0).toFixed(2);
  }

  statusLabel(status: PresentationRow['stock_status']): string {
    if (status === 'sin_stock') {
      return 'Sin stock';
    }
    if (status === 'bajo') {
      return 'Stock bajo';
    }
    return 'En stock';
  }

  reload(): void {
    const value = this.filters.getRawValue();
    const params: Record<string, string> = {
      page: String(this.meta().current_page),
      per_page: value.per_page,
    };
    for (const key of ['q', 'category_id', 'especie', 'tipo', 'proveedor_id', 'marca_id', 'activo'] as const) {
      if (value[key]) {
        params[key] = value[key];
      }
    }
    if (value.low_stock) params['low_stock'] = '1';
    if (value.expired) params['expired'] = '1';
    if (value.expiring) params['expiring'] = '1';
    this.http
      .get<ApiResponse<{ items: any[]; meta: PageMeta }>>(`${environment.apiBaseUrl}/products`, { params })
      .subscribe({
        next: (res) => {
          this.items.set(this.flattenPresentations(res.data?.items ?? []));
          if (res.data?.meta) {
            this.meta.set(res.data.meta);
          }
        },
      });
  }

  private flattenPresentations(products: any[]): PresentationRow[] {
    const rows: PresentationRow[] = [];
    for (const product of products) {
      const variants = Array.isArray(product.variantes) && product.variantes.length ? product.variantes : [null];
      for (const variant of variants) {
        const onHand = variant
          ? Number(variant.on_hand ?? (variant.batches ?? []).reduce((sum: number, b: any) => sum + Number(b.quantity || 0), 0))
          : Number(product.stock_actual || 0);
        const min = Number(product.stock_minimo || 0);
        const price = Number(variant?.sale_price || product.precio_venta || 0);
        let stock_status: PresentationRow['stock_status'] = 'ok';
        if (onHand <= 0) {
          stock_status = 'sin_stock';
        } else if (min > 0 && onHand <= min) {
          stock_status = 'bajo';
        }
        rows.push({
          product_id: product.id,
          variant_id: variant?.id ?? null,
          nombre: product.nombre,
          presentacion: variant?.presentation || product.presentacion || product.talla || 'Sin presentación',
          categoria: product.categoria?.nombre || 'Sin categoría',
          lote_count: Number(variant?.lote_count ?? product.lote_count ?? 0),
          on_hand: onHand,
          precio_venta: price,
          stock_status,
        });
      }
    }
    return rows;
  }

  private flatten(items: any[]): { id: number; nombre: string }[] {
    const out: { id: number; nombre: string }[] = [];
    const walk = (nodes: any[], prefix = '') => {
      for (const node of nodes) {
        out.push({ id: node.id, nombre: prefix + node.nombre });
        if (node.children?.length) {
          walk(node.children, prefix + '— ');
        }
      }
    };
    walk(items);
    return out;
  }
}
