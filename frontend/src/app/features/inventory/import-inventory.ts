import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

interface ImportSummary {
  processed: number;
  imported: number;
  rejected: number;
  pending_review: number;
  errors: { row: number; message: string }[];
}

@Component({
  selector: 'app-import-inventory',
  template: `
    <h1>Importar inventario</h1>
    <p>
      Columnas: categoría, producto, presentación, cantidad, unidad, F.V., costo, precio,
      observaciones.
    </p>
    <p>
      <button type="button" (click)="downloadTemplate()">Descargar plantilla</button>
    </p>
    <input type="file" accept=".xlsx,.xlsm,.csv,.txt" (change)="onFile($event)" />
    <button type="button" (click)="upload()" [disabled]="!file() || busy()">Importar</button>
    @if (message()) {
      <p>{{ message() }}</p>
    }
    @if (summary(); as data) {
      <ul>
        <li>Filas procesadas: {{ data.processed }}</li>
        <li>Filas importadas: {{ data.imported }}</li>
        <li>Filas rechazadas: {{ data.rejected }}</li>
        <li>Filas pendientes de revisión: {{ data.pending_review }}</li>
      </ul>
      @if (data.errors.length) {
        <h2>Errores</h2>
        <ul>
          @for (err of data.errors; track err.row + err.message) {
            <li>Fila {{ err.row }}: {{ err.message }}</li>
          }
        </ul>
      }
    }
  `,
})
export class ImportInventory {
  private readonly http = inject(HttpClient);
  readonly file = signal<File | null>(null);
  readonly busy = signal(false);
  readonly message = signal('');
  readonly summary = signal<ImportSummary | null>(null);

  onFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.file.set(input.files?.[0] ?? null);
  }

  downloadTemplate(): void {
    this.http
      .get(`${environment.apiBaseUrl}/import/inventory/template`, { responseType: 'blob' })
      .subscribe({
        next: (blob) => {
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = 'plantilla_inventario.csv';
          a.click();
          URL.revokeObjectURL(url);
        },
      });
  }

  upload(): void {
    const file = this.file();
    if (!file) {
      return;
    }
    const body = new FormData();
    body.append('file', file);
    this.busy.set(true);
    this.message.set('');
    this.http.post<ApiResponse<ImportSummary>>(`${environment.apiBaseUrl}/import/inventory`, body).subscribe({
      next: (res) => {
        this.summary.set(res.data);
        this.message.set(res.message);
        this.busy.set(false);
      },
      error: (err) => {
        this.message.set(err.message || 'No se pudo importar');
        this.busy.set(false);
      },
    });
  }
}
