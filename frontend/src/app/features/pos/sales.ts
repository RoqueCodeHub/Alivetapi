import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';
import { AuthService } from '../../core/services/auth.service';

interface CatalogHit {
  variant_id: number;
  nombre: string;
  presentacion?: string;
  talla?: string;
  categoria?: string;
  sale_price: number;
  on_hand: number;
}

@Component({
  selector: 'app-sales',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './sales.html',
  styleUrl: './sales.scss',
})
export class Sales {
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);
  readonly auth = inject(AuthService);
  readonly hits = signal<CatalogHit[]>([]);
  readonly categories = signal<{ id: number; nombre: string }[]>([]);
  readonly cart = signal<any[]>([]);
  readonly notes = signal('');
  readonly message = signal('');
  readonly meta = signal({ current_page: 1, last_page: 1, total: 0, per_page: 12 });
  readonly filters = this.fb.nonNullable.group({
    q: [''],
    category_id: [''],
  });

  constructor() {
    this.http.get<ApiResponse<any[]>>(`${environment.apiBaseUrl}/categories`).subscribe({
      next: (res) => this.categories.set(this.flatten(res.data ?? [])),
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

  reload(): void {
    const value = this.filters.getRawValue();
    const params: Record<string, string> = {
      page: String(this.meta().current_page),
      per_page: '12',
    };
    if (value.q.trim()) {
      params['q'] = value.q.trim();
    }
    if (value.category_id) {
      params['category_id'] = value.category_id;
    }
    this.http
      .get<ApiResponse<{ items: CatalogHit[]; meta: any }>>(`${environment.apiBaseUrl}/pos/search`, { params })
      .subscribe({
        next: (res) => {
          this.hits.set(res.data?.items ?? []);
          if (res.data?.meta) {
            this.meta.set(res.data.meta);
          }
        },
      });
  }

  add(hit: CatalogHit, event: Event): void {
    const host = event.currentTarget as HTMLElement;
    const input = host.parentElement?.querySelector('input[type="number"]') as HTMLInputElement | null;
    const price = Number(input?.value || hit.sale_price);
    if (price <= 0) {
      this.message.set('Indica un precio de venta mayor a 0.');
      return;
    }
    const current = this.cart().find((line) => line.variant_id === hit.variant_id)?.quantity ?? 0;
    if (current + 1 > Number(hit.on_hand || 0)) {
      this.message.set('No hay stock suficiente en esta presentación.');
      return;
    }
    this.cart.update((items) => {
      const i = items.findIndex((line) => line.variant_id === hit.variant_id);
      if (i >= 0) {
        const copy = [...items];
        copy[i] = { ...copy[i], quantity: copy[i].quantity + 1 };
        return copy;
      }
      return [
        ...items,
        {
          item_type: 'product',
          variant_id: hit.variant_id,
          description: `${hit.nombre} ${hit.presentacion || ''} ${hit.talla || ''}`.trim(),
          quantity: 1,
          unit_price: price,
          discount: 0,
        },
      ];
    });
    this.message.set('');
  }

  remove(index: number): void {
    this.cart.update((items) => items.filter((_, i) => i !== index));
  }

  total(): number {
    return this.cart().reduce((sum, line) => sum + line.quantity * Number(line.unit_price), 0);
  }

  save(): void {
    if (!this.cart().length) {
      return;
    }
    const key = globalThis.crypto?.randomUUID?.() ?? `venta-${Date.now()}`;
    this.http
      .post<ApiResponse<any>>(`${environment.apiBaseUrl}/sales`, {
        idempotency_key: key,
        pending: true,
        notes: this.notes() || null,
        items: this.cart(),
      })
      .subscribe({
        next: (res) => {
          this.message.set(`${res.message} Ticket ${res.data?.document}`);
          this.cart.set([]);
          this.notes.set('');
        },
        error: (err) => this.message.set(err.message || 'No se pudo guardar'),
      });
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
