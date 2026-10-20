import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { debounceTime, distinctUntilChanged } from 'rxjs';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

@Component({
  selector: 'app-product-detail',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './product-detail.html',
  styleUrl: './product-detail.scss',
})
export class ProductDetail {
  private readonly http = inject(HttpClient);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly fb = inject(FormBuilder);

  readonly categories = signal<{ id: number; nombre: string }[]>([]);
  readonly brands = signal<{ id: number; nombre: string }[]>([]);
  readonly suppliers = signal<{ id: number; name: string }[]>([]);
  readonly variants = signal<any[]>([]);
  readonly suggestions = signal<any[]>([]);
  readonly isNew = signal(true);
  readonly message = signal('');
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

  readonly productForm = this.fb.group({
    nombre: ['', Validators.required],
    descripcion: [''],
    codigo: [''],
    categoria_id: [null as number | null],
    marca_id: [null as number | null],
    proveedor_id: [null as number | null],
    presentacion: [''],
    talla: [''],
    especie: ['Múltiple'],
    tipo: ['Accesorio'],
    precio_compra: [0],
    precio_venta: [0],
    stock_minimo: [0],
    unidad_medida: ['unidad'],
    requiere_receta: [false],
    requires_batch: [true],
    activo: [true],
  });

  readonly variantForm = this.fb.group({
    sku: [''],
    barcode: [''],
    presentation: [''],
    unit: ['unidad'],
    sale_price: [null as number | null],
    cost_price: [null as number | null],
    needs_review: [false],
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

    this.productForm.controls.nombre.valueChanges
      .pipe(debounceTime(280), distinctUntilChanged(), takeUntilDestroyed())
      .subscribe((nombre) => this.lookup(nombre || ''));

    const id = this.route.snapshot.paramMap.get('id');
    if (id && id !== 'nuevo') {
      this.isNew.set(false);
      this.http.get<ApiResponse<any>>(`${environment.apiBaseUrl}/products/${id}`).subscribe({
        next: (res) => {
          const p = res.data;
          this.productForm.patchValue(p);
          this.variants.set(p.variantes ?? []);
        },
      });
    }
  }

  lookup(nombre: string): void {
    if (!this.isNew() || nombre.trim().length < 3) {
      this.suggestions.set([]);
      return;
    }
    this.http
      .get<ApiResponse<{ items: any[] }>>(`${environment.apiBaseUrl}/products`, {
        params: { q: nombre.trim(), per_page: '8' },
      })
      .subscribe({
        next: (res) => {
          const current = nombre.trim().toLowerCase();
          this.suggestions.set(
            (res.data?.items ?? []).filter((item) => item.nombre?.toLowerCase().includes(current))
          );
        },
      });
  }

  useExisting(product: any): void {
    void this.router.navigate(['/app/productos', product.id]);
  }

  flatten(items: any[]): { id: number; nombre: string }[] {
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

  saveProduct(): void {
    if (this.productForm.invalid) {
      return;
    }
    const id = this.route.snapshot.paramMap.get('id');
    const body = { ...this.productForm.getRawValue() };
    if (!body.codigo) {
      delete (body as any).codigo;
    }
    const request =
      id && id !== 'nuevo'
        ? this.http.put<ApiResponse<any>>(`${environment.apiBaseUrl}/products/${id}`, body)
        : this.http.post<ApiResponse<any>>(`${environment.apiBaseUrl}/products`, body);

    request.subscribe({
      next: (res) => {
        this.message.set(res.message);
        if (this.isNew()) {
          void this.router.navigate(['/app/ingreso-stock'], { queryParams: { producto_id: res.data.id } });
        }
      },
      error: (err) => this.message.set(err.message || 'No se pudo guardar'),
    });
  }

  routeId(): string {
    return this.route.snapshot.paramMap.get('id') || '';
  }

  saveVariant(): void {
    const id = this.route.snapshot.paramMap.get('id');
    if (!id || id === 'nuevo') {
      return;
    }
    this.http
      .post<ApiResponse<any>>(`${environment.apiBaseUrl}/products/${id}/variants`, this.variantForm.getRawValue())
      .subscribe({
        next: (res) => {
          this.variants.update((items) => [...items, res.data]);
          this.variantForm.reset({ unit: 'unidad', needs_review: false });
        },
      });
  }
}
