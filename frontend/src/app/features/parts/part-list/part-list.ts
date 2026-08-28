import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { PartService, Part } from '../../../core/services/part.service';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  selector: 'app-part-list',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './part-list.html',
  styleUrl: './part-list.css'
})
export class PartList implements OnInit {
  parts = signal<Part[]>([]);
  totalPages = signal(1);
  currentPage = signal(1);
  searchTerm = signal('');
  isLoading = signal(false);
  errorMessage = signal('');

  showModal = signal(false);
  isEditMode = signal(false);
  editingPartId: number | null = null;
  modalErrorMessage = signal('');

  partForm: FormGroup;
  canManage = signal(false);

  constructor(
    private partService: PartService,
    public authService: AuthService,
    private fb: FormBuilder
  ) {
    this.partForm = this.fb.group({
      name: ['', Validators.required],
      category: [''],
      quantity: [0, [Validators.required, Validators.min(0)]],
      price: [0, [Validators.required, Validators.min(0)]],
      supplier: [''],
      low_stock_threshold: [5, [Validators.required, Validators.min(0)]]
    });
  }

  ngOnInit(): void {
    const role = this.authService.currentUser()?.role;
    this.canManage.set(role === 'Administrador' || role === 'Funcionário');
    this.loadParts();
  }

  loadParts(): void {
    this.isLoading.set(true);
    this.errorMessage.set('');

    this.partService.getParts(this.currentPage(), 10, this.searchTerm()).subscribe({
      next: (response) => {
        this.parts.set(response.data);
        this.totalPages.set(response.pagination.total_pages);
        this.isLoading.set(false);
      },
      error: (err) => {
        this.isLoading.set(false);
        this.errorMessage.set(err.error?.error || 'Erro ao carregar peças.');
      }
    });
  }

  onSearch(): void {
    this.currentPage.set(1);
    this.loadParts();
  }

  goToPage(page: number): void {
    if (page < 1 || page > this.totalPages()) return;
    this.currentPage.set(page);
    this.loadParts();
  }

  isLowStock(part: Part): boolean {
    return part.quantity <= part.low_stock_threshold;
  }

  openCreateModal(): void {
    this.isEditMode.set(false);
    this.editingPartId = null;
    this.modalErrorMessage.set('');
    this.partForm.reset({ quantity: 0, price: 0, low_stock_threshold: 5 });
    this.showModal.set(true);
  }

  openEditModal(part: Part): void {
    this.isEditMode.set(true);
    this.editingPartId = part.id;
    this.modalErrorMessage.set('');
    this.partForm.reset({
      name: part.name,
      category: part.category || '',
      quantity: part.quantity,
      price: part.price,
      supplier: part.supplier || '',
      low_stock_threshold: part.low_stock_threshold
    });
    this.showModal.set(true);
  }

  closeModal(): void {
    this.showModal.set(false);
  }

  onSubmitForm(): void {
    if (this.partForm.invalid) {
      this.partForm.markAllAsTouched();
      return;
    }

    const formValue = this.partForm.value;
    const payload = {
      name: formValue.name,
      category: formValue.category || undefined,
      quantity: Number(formValue.quantity),
      price: Number(formValue.price),
      supplier: formValue.supplier || undefined,
      low_stock_threshold: Number(formValue.low_stock_threshold)
    };

    const request$ = this.isEditMode() && this.editingPartId !== null
      ? this.partService.updatePart(this.editingPartId, payload)
      : this.partService.createPart(payload);

    request$.subscribe({
      next: () => {
        this.showModal.set(false);
        this.loadParts();
      },
      error: (err) => {
        this.modalErrorMessage.set(err.error?.error || 'Erro ao guardar peça.');
      }
    });
  }

  deletePart(part: Part): void {
    if (!confirm(`Tens a certeza que queres remover a peça "${part.name}"?`)) {
      return;
    }

    this.partService.deletePart(part.id).subscribe({
      next: () => this.loadParts(),
      error: (err) => alert(err.error?.error || 'Erro ao remover peça.')
    });
  }

  get name() { return this.partForm.get('name'); }
  get quantity() { return this.partForm.get('quantity'); }
  get price() { return this.partForm.get('price'); }
  get low_stock_threshold() { return this.partForm.get('low_stock_threshold'); }
}