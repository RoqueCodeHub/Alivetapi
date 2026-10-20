import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

interface Category {
  id: number;
  nombre: string;
  descripcion?: string | null;
  parent_id: number | null;
  parent?: { id: number; nombre: string } | null;
  activo?: boolean;
  children?: Category[];
}

interface PageMeta {
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
}

@Component({
  selector: 'app-categories',
  imports: [ReactiveFormsModule],
  templateUrl: './categories.html',
  styleUrl: './categories.scss',
})
export class Categories {
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);
  readonly items = signal<Category[]>([]);
  readonly parents = signal<Category[]>([]);
  readonly meta = signal<PageMeta>({ current_page: 1, last_page: 1, total: 0, per_page: 15 });
  readonly form = this.fb.group({
    nombre: ['', Validators.required],
    parent_id: [null as number | null],
  });
  readonly message = signal('');
  readonly filters = this.fb.nonNullable.group({
    q: [''],
    parent_id: [''],
    activo: [''],
    per_page: ['15'],
  });

  constructor() {
    this.loadParents();
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
      per_page: value.per_page,
    };
    if (value.q.trim()) {
      params['q'] = value.q.trim();
    }
    if (value.parent_id) {
      params['parent_id'] = value.parent_id;
    }
    if (value.activo !== '') {
      params['activo'] = value.activo;
    }
    this.http
      .get<ApiResponse<{ items: Category[]; meta: PageMeta }>>(`${environment.apiBaseUrl}/categories`, { params })
      .subscribe({
        next: (res) => {
          this.items.set(res.data?.items ?? []);
          if (res.data?.meta) {
            this.meta.set(res.data.meta);
          }
        },
      });
  }

  create(): void {
    if (this.form.invalid) {
      return;
    }
    this.http.post(`${environment.apiBaseUrl}/categories`, this.form.getRawValue()).subscribe({
      next: () => {
        this.message.set('');
        this.form.reset({ parent_id: null });
        this.loadParents();
        this.reload();
      },
      error: (err) => this.message.set(err.message || 'No se pudo crear'),
    });
  }

  remove(item: Category): void {
    if (!confirm(`¿Eliminar la categoría "${item.nombre}"?`)) {
      return;
    }
    this.http.delete<ApiResponse<null>>(`${environment.apiBaseUrl}/categories/${item.id}`).subscribe({
      next: (res) => {
        this.message.set(res.message || 'Categoría eliminada');
        this.loadParents();
        this.reload();
      },
      error: (err) => this.message.set(err.message || 'No se pudo eliminar'),
    });
  }

  private loadParents(): void {
    this.http.get<ApiResponse<Category[]>>(`${environment.apiBaseUrl}/categories`).subscribe({
      next: (res) => this.parents.set(this.flatten(res.data ?? [])),
    });
  }

  private flatten(items: Category[]): Category[] {
    const out: Category[] = [];
    const walk = (rows: Category[]) => {
      for (const item of rows) {
        out.push(item);
        if (item.children?.length) {
          walk(item.children);
        }
      }
    };
    walk(items);
    return out;
  }
}
