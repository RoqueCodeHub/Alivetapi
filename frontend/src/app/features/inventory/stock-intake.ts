import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { debounceTime, distinctUntilChanged } from 'rxjs';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

@Component({
  selector: 'app-stock-intake',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './stock-intake.html',
  styleUrl: './stock-intake.scss',
})
export class StockIntake {
  private readonly http = inject(HttpClient);
  private readonly route = inject(ActivatedRoute);
  private readonly fb = inject(FormBuilder);

  readonly matches = signal<any[]>([]);
  readonly selected = signal<any | null>(null);
  readonly variants = signal<any[]>([]);
  readonly message = signal('');

  readonly searchForm = this.fb.nonNullable.group({
    q: [''],
  });

  readonly form = this.fb.group({
    producto_id: [null as number | null, Validators.required],
    variant_id: [null as number | null],
    presentation: [''],
    batch_code: ['', Validators.required],
    quantity: [1, [Validators.required, Validators.min(1)]],
    cost: [null as number | null],
    sale_price: [null as number | null],
    expiry_date: [''],
    reason: ['Compra / ingreso de stock'],
  });

  constructor() {
    this.searchForm.controls.q.valueChanges
      .pipe(debounceTime(250), distinctUntilChanged(), takeUntilDestroyed())
      .subscribe((q) => this.search(q));

    const productoId = Number(this.route.snapshot.queryParamMap.get('producto_id') || 0);
    const variantId = Number(this.route.snapshot.queryParamMap.get('variant_id') || 0);
    if (productoId) {
      this.loadProduct(productoId, variantId || null);
    }
  }

  search(q: string): void {
    const term = q.trim();
    if (term.length < 2) {
      this.matches.set([]);
      return;
    }
    this.http
      .get<ApiResponse<{ items: any[] }>>(`${environment.apiBaseUrl}/products`, {
        params: { q: term, per_page: '10' },
      })
      .subscribe({
        next: (res) => this.matches.set(res.data?.items ?? []),
      });
  }

  pick(product: any): void {
    this.loadProduct(product.id, null);
    this.matches.set([]);
    this.searchForm.patchValue({ q: product.nombre }, { emitEvent: false });
  }

  loadProduct(id: number, variantId: number | null): void {
    this.http.get<ApiResponse<any>>(`${environment.apiBaseUrl}/products/${id}`).subscribe({
      next: (res) => {
        const product = res.data;
        this.selected.set(product);
        this.variants.set(product.variantes ?? []);
        const variant =
          (product.variantes ?? []).find((item: any) => item.id === variantId) ?? product.variantes?.[0];
        this.form.patchValue({
          producto_id: product.id,
          variant_id: variant?.id ?? null,
          presentation: variant?.presentation || product.presentacion || '',
          cost: variant?.cost_price ?? product.precio_compra ?? null,
          sale_price: variant?.sale_price ?? product.precio_venta ?? null,
        });
      },
    });
  }

  onVariantChange(id: string): void {
    const variant = this.variants().find((item) => String(item.id) === id);
    this.form.patchValue({
      variant_id: variant ? Number(variant.id) : null,
      presentation: variant?.presentation || this.form.value.presentation,
      cost: variant?.cost_price ?? this.form.value.cost,
      sale_price: variant?.sale_price ?? this.form.value.sale_price,
    });
  }

  save(): void {
    if (this.form.invalid) {
      this.message.set('Completa producto, lote y cantidad.');
      return;
    }
    const body = { ...this.form.getRawValue() };
    if (!body.expiry_date) {
      delete (body as any).expiry_date;
    }
    if (!body.variant_id) {
      delete (body as any).variant_id;
    }
    this.http.post<ApiResponse<any>>(`${environment.apiBaseUrl}/stock/receipts`, body).subscribe({
      next: (res) => {
        this.message.set(
          `${res.message}. Lote ${res.data?.batch?.batch_code} — disponible ${res.data?.on_hand}`
        );
        this.form.patchValue({ quantity: 1, batch_code: '' });
      },
      error: (err) => this.message.set(err.message || 'No se pudo registrar el ingreso'),
    });
  }
}
