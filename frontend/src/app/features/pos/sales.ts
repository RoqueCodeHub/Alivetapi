import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';
import { AuthService } from '../../core/services/auth.service';
import { emptyPage, PageMeta, Pager, readPageMeta } from '../../shared/pager';

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
  imports: [ReactiveFormsModule, RouterLink, Pager],
  templateUrl: './sales.html',
  styleUrl: './sales.scss',
})
export class Sales {
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);
  readonly auth = inject(AuthService);
  readonly hits = signal<CatalogHit[]>([]);
  readonly categories = signal<{ id: number; nombre: string }[]>([]);
  readonly customers = signal<{ id: number; name: string }[]>([]);
  readonly customerId = signal<number | null>(null);
  readonly cart = signal<any[]>([]);
  readonly notes = signal('');
  readonly message = signal('');
  readonly payForm = this.fb.nonNullable.group({
    efectivo: [0],
    yape: [0],
    plin: [0],
    tarjeta: [0],
    transferencia: [0],
  });
  readonly meta = signal<PageMeta>(emptyPage());
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

  setPerPage(size: number): void {
    this.meta.update((m) => ({ ...m, per_page: size, current_page: 1 }));
    this.reload();
  }

  reload(): void {
    const value = this.filters.getRawValue();
    const params: Record<string, string> = {
      page: String(this.meta().current_page),
      per_page: String(this.meta().per_page),
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
          this.meta.set(readPageMeta(this.meta(), res.data?.meta));
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

  searchCustomers(raw: string): void {
    const q = raw.trim();
    this.customerId.set(null);
    if (!this.auth.hasPermission('sales.credit') || q.length < 2) {
      this.customers.set([]);
      return;
    }
    this.http
      .get<ApiResponse<{ items: { id: number; name: string }[] }>>(`${environment.apiBaseUrl}/customers`, {
        params: { q, per_page: '8' },
      })
      .subscribe({
        next: (res) => this.customers.set(res.data?.items ?? []),
      });
  }

  chooseCustomer(customer: { id: number; name: string }): void {
    this.customerId.set(customer.id);
    this.customers.set([customer]);
  }

  charge(): void {
    this.save(false);
  }

  credit(): void {
    this.save(true);
  }

  private save(pending: boolean): void {
    if (!this.cart().length) {
      return;
    }
    if (pending && !this.customerId()) {
      this.message.set('Elige un cliente para fiar.');
      return;
    }
    const payments = this.payments();
    if (!pending && !payments.length) {
      this.message.set('Indica el monto cobrado.');
      return;
    }
    const key = globalThis.crypto?.randomUUID?.() ?? `venta-${Date.now()}`;
    const body: Record<string, unknown> = {
      idempotency_key: key,
      pending,
      notes: this.notes() || null,
      items: this.cart(),
    };
    if (pending) {
      body['customer_id'] = this.customerId();
    } else {
      body['payments'] = payments;
    }
    this.http.post<ApiResponse<any>>(`${environment.apiBaseUrl}/sales`, body).subscribe({
      next: (res) => {
        const balance = Number(res.data?.balance ?? 0);
        const saldo = balance > 0 ? ` Saldo S/ ${balance}.` : '';
        this.message.set(`${res.message} Ticket ${res.data?.document}.${saldo}`);
        this.cart.set([]);
        this.notes.set('');
        this.customerId.set(null);
        this.customers.set([]);
        this.payForm.reset();
      },
      error: (err) => this.message.set(err?.error?.message || err.message || 'No se pudo guardar'),
    });
  }

  private payments(): { method: string; amount: number }[] {
    const pay = this.payForm.getRawValue();
    const rows: { method: string; amount: number }[] = [];
    (['efectivo', 'yape', 'plin', 'tarjeta', 'transferencia'] as const).forEach((method) => {
      if (pay[method] > 0) {
        rows.push({ method, amount: pay[method] });
      }
    });
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
