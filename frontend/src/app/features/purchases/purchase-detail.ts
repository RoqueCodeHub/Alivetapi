import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormArray, FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response';

@Component({
  selector: 'app-purchase-detail',
  imports: [ReactiveFormsModule, RouterLink],
  template: `
    <p><a routerLink="/app/compras">Volver</a></p>
    <h1>{{ isNew() ? 'Nueva compra' : 'Compra' }}</h1>
    @if (message()) {
      <p>{{ message() }}</p>
    }
    <form [formGroup]="form" (ngSubmit)="save()">
      <select formControlName="supplier_id">
        <option [ngValue]="null">Proveedor</option>
        @for (s of suppliers(); track s.id) {
          <option [ngValue]="s.id">{{ s.name }}</option>
        }
      </select>
      <input placeholder="Comprobante" formControlName="comprobante" />
      <input type="date" formControlName="fecha" />
      <div formArrayName="items">
        @for (item of items.controls; track $index) {
          <div [formGroupName]="$index">
            <input placeholder="ID variante" formControlName="variant_id" />
            <input placeholder="Lote" formControlName="batch_code" />
            <input type="date" formControlName="expiry_date" />
            <input type="number" placeholder="Cantidad" formControlName="quantity" />
            <input type="number" placeholder="Costo" formControlName="cost" />
          </div>
        }
      </div>
      <button type="button" (click)="addItem()">Agregar ítem</button>
      @if (isNew() || purchase()?.status === 'draft') {
        <button type="submit">Guardar borrador</button>
      }
    </form>
    @if (!isNew() && purchase()?.status === 'draft') {
      <button type="button" (click)="confirm()">Confirmar (entra a stock)</button>
    }
    @if (purchase()?.status === 'confirmed') {
      <p>Total S/ {{ purchase()?.total }} — pagado S/ {{ purchase()?.paid_amount }} — saldo S/ {{ purchase()?.balance }}</p>
      <form [formGroup]="payForm" (ngSubmit)="pay()">
        <input type="number" formControlName="amount" placeholder="Monto" />
        <select formControlName="method">
          <option value="transferencia">Transferencia</option>
          <option value="efectivo">Efectivo</option>
          <option value="yape">Yape</option>
        </select>
        <button type="submit">Registrar pago</button>
      </form>
    }
  `,
})
export class PurchaseDetail {
  private readonly http = inject(HttpClient);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly fb = inject(FormBuilder);
  readonly suppliers = signal<{ id: number; name: string }[]>([]);
  readonly purchase = signal<any>(null);
  readonly isNew = signal(true);
  readonly message = signal('');
  readonly form = this.fb.group({
    supplier_id: [null as number | null, Validators.required],
    comprobante: [''],
    fecha: [new Date().toISOString().slice(0, 10), Validators.required],
    items: this.fb.array([this.itemGroup()]),
  });
  readonly payForm = this.fb.nonNullable.group({
    amount: [0, Validators.required],
    method: ['transferencia'],
  });

  get items(): FormArray {
    return this.form.get('items') as FormArray;
  }

  constructor() {
    this.http.get<ApiResponse<any[]>>(`${environment.apiBaseUrl}/suppliers`).subscribe({
      next: (res) => this.suppliers.set(res.data ?? []),
    });
    const id = this.route.snapshot.paramMap.get('id');
    if (id && id !== 'nueva') {
      this.isNew.set(false);
      this.http.get<ApiResponse<any>>(`${environment.apiBaseUrl}/purchases/${id}`).subscribe({
        next: (res) => {
          this.purchase.set(res.data);
          this.form.patchValue({
            supplier_id: res.data.supplier_id,
            comprobante: res.data.comprobante,
            fecha: res.data.fecha,
          });
        },
      });
    }
  }

  itemGroup() {
    return this.fb.group({
      variant_id: ['', Validators.required],
      batch_code: ['', Validators.required],
      expiry_date: [''],
      quantity: [1, Validators.required],
      cost: [0, Validators.required],
    });
  }

  addItem(): void {
    this.items.push(this.itemGroup());
  }

  save(): void {
    if (this.form.invalid) {
      return;
    }
    const body = this.form.getRawValue();
    const id = this.route.snapshot.paramMap.get('id');
    const req =
      id && id !== 'nueva'
        ? this.http.put<ApiResponse<any>>(`${environment.apiBaseUrl}/purchases/${id}`, body)
        : this.http.post<ApiResponse<any>>(`${environment.apiBaseUrl}/purchases`, body);
    req.subscribe({
      next: (res) => {
        if (this.isNew()) {
          void this.router.navigate(['/app/compras', res.data.id]);
        } else {
          this.purchase.set(res.data);
          this.message.set('Guardado');
        }
      },
      error: (err) => this.message.set(err.message || 'Error'),
    });
  }

  confirm(): void {
    const id = this.route.snapshot.paramMap.get('id');
    this.http.post<ApiResponse<any>>(`${environment.apiBaseUrl}/purchases/${id}/confirm`, {}).subscribe({
      next: (res) => {
        this.purchase.set(res.data);
        this.message.set('Compra confirmada, stock actualizado');
      },
      error: (err) => this.message.set(err.message || 'Error'),
    });
  }

  pay(): void {
    const id = this.route.snapshot.paramMap.get('id');
    this.http
      .post<ApiResponse<any>>(`${environment.apiBaseUrl}/purchases/${id}/payments`, this.payForm.getRawValue())
      .subscribe({
        next: (res) => {
          this.purchase.set(res.data);
          this.message.set('Pago registrado');
        },
        error: (err) => this.message.set(err.message || 'Error'),
      });
  }
}
