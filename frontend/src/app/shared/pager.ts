import { Component, input, output } from '@angular/core';

export const PAGE_SIZES = [10, 20, 50] as const;

export type PageSizeOption = (typeof PAGE_SIZES)[number];

export interface PageMeta {
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
}

export function isPageSize(value: number): value is PageSizeOption {
  return (PAGE_SIZES as readonly number[]).includes(value);
}

export function emptyPage(perPage: PageSizeOption = 10): PageMeta {
  return { current_page: 1, last_page: 1, total: 0, per_page: perPage };
}

export function readPageMeta(current: PageMeta, incoming?: Partial<PageMeta> | null): PageMeta {
  if (!incoming) {
    return current;
  }
  const perPage = isPageSize(Number(incoming.per_page)) ? Number(incoming.per_page) : current.per_page;

  return {
    current_page: Number(incoming.current_page ?? current.current_page) || 1,
    last_page: Math.max(1, Number(incoming.last_page ?? 1) || 1),
    total: Number(incoming.total ?? 0) || 0,
    per_page: perPage,
  };
}

export function pageWindow<T>(
  items: readonly T[],
  page: number,
  perPage: number,
): { rows: T[]; meta: PageMeta } {
  const size = isPageSize(perPage) ? perPage : 10;
  const total = items.length;
  const last = Math.max(1, Math.ceil(total / size));
  const current = Math.min(Math.max(1, page), last);
  const start = (current - 1) * size;

  return {
    rows: items.slice(start, start + size),
    meta: { current_page: current, last_page: last, total, per_page: size },
  };
}

@Component({
  selector: 'app-pager',
  template: `
    <div class="pager">
      <label>
        Mostrar
        <select [value]="'' + perPage()" (change)="changeSize($any($event.target).value)">
          @for (size of sizes; track size) {
            <option [value]="'' + size">{{ size }}</option>
          }
        </select>
      </label>
      <button type="button" [disabled]="page() <= 1" (click)="go(page() - 1)">Anterior</button>
      <span>Página {{ page() }} de {{ lastPage() }} — {{ total() }} {{ label() }}</span>
      <button type="button" [disabled]="page() >= lastPage()" (click)="go(page() + 1)">Siguiente</button>
    </div>
  `,
  styles: `
    .pager {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem;
      align-items: center;
      margin-top: 1rem;
    }

    select,
    button {
      padding: 0.45rem 0.6rem;
    }
  `,
})
export class Pager {
  readonly sizes = PAGE_SIZES;
  readonly page = input(1);
  readonly lastPage = input(1);
  readonly total = input(0);
  readonly perPage = input(10);
  readonly label = input('registros');
  readonly pageChange = output<number>();
  readonly perPageChange = output<number>();

  go(page: number): void {
    if (page < 1 || page > this.lastPage()) {
      return;
    }
    this.pageChange.emit(page);
  }

  changeSize(value: string): void {
    const size = Number(value);
    if (!isPageSize(size) || size === this.perPage()) {
      return;
    }
    this.perPageChange.emit(size);
  }
}
