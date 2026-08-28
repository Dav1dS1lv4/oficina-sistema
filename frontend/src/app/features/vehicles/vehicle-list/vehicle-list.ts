import { Component, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { VehicleService, Vehicle } from '../../../core/services/vehicle.service';
import { ClientService, Client } from '../../../core/services/client.service';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  selector: 'app-vehicle-list',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './vehicle-list.html',
  styleUrl: './vehicle-list.css'
})
export class VehicleList implements OnInit {
  vehicles = signal<Vehicle[]>([]);
  clients = signal<Client[]>([]);
  totalPages = signal(1);
  currentPage = signal(1);
  searchTerm = signal('');
  isLoading = signal(false);
  errorMessage = signal('');

  showModal = signal(false);
  isEditMode = signal(false);
  editingVehicleId: number | null = null;
  modalErrorMessage = signal('');

  vehicleForm: FormGroup;

  canManage = signal(false);

  clientSearchTerm = signal('');
  showClientDropdown = signal(false);

  filteredClients = computed(() => {
    const term = this.clientSearchTerm().toLowerCase().trim();
    if (term === '') {
      return this.clients();
    }
    return this.clients().filter(c =>
      c.name.toLowerCase().includes(term) ||
      (c.nif && c.nif.includes(term))
    );
  });

  constructor(
    private vehicleService: VehicleService,
    private clientService: ClientService,
    public authService: AuthService,
    private fb: FormBuilder
  ) {
    this.vehicleForm = this.fb.group({
      license_plate: ['', Validators.required],
      brand: ['', Validators.required],
      model: ['', Validators.required],
      year: ['', [Validators.required, Validators.min(1900)]],
      client_id: ['', Validators.required]
    });
  }

  ngOnInit(): void {
    const role = this.authService.currentUser()?.role;
    this.canManage.set(role === 'Administrador' || role === 'Funcionário');

    this.loadVehicles();
    this.loadClients();
  }

  loadVehicles(): void {
    this.isLoading.set(true);
    this.errorMessage.set('');

    this.vehicleService.getVehicles(this.currentPage(), 10, this.searchTerm()).subscribe({
      next: (response) => {
        this.vehicles.set(response.data);
        this.totalPages.set(response.pagination.total_pages);
        this.isLoading.set(false);
      },
      error: (err) => {
        this.isLoading.set(false);
        this.errorMessage.set(err.error?.error || 'Erro ao carregar veículos.');
      }
    });
  }

  loadClients(): void {
    this.clientService.getClients().subscribe({
      next: (response) => this.clients.set(response.data),
      error: () => {}
    });
  }

  onSearch(): void {
    this.currentPage.set(1);
    this.loadVehicles();
  }

  goToPage(page: number): void {
    if (page < 1 || page > this.totalPages()) return;
    this.currentPage.set(page);
    this.loadVehicles();
  }

  onClientInputChange(value: string): void {
    this.clientSearchTerm.set(value);
    this.showClientDropdown.set(true);
    this.vehicleForm.get('client_id')?.setValue('');
  }

  onClientInputFocus(): void {
    this.showClientDropdown.set(true);
  }

  onClientInputBlur(): void {
    setTimeout(() => this.showClientDropdown.set(false), 150);
  }

  selectClient(client: Client): void {
    this.vehicleForm.get('client_id')?.setValue(client.id);
    this.clientSearchTerm.set(client.name);
    this.showClientDropdown.set(false);
  }

  openCreateModal(): void {
    this.isEditMode.set(false);
    this.editingVehicleId = null;
    this.modalErrorMessage.set('');
    this.vehicleForm.reset();
    this.clientSearchTerm.set('');
    this.showModal.set(true);
  }

  openEditModal(vehicle: Vehicle): void {
    this.isEditMode.set(true);
    this.editingVehicleId = vehicle.id;
    this.modalErrorMessage.set('');
    this.vehicleForm.reset({
      license_plate: vehicle.license_plate,
      brand: vehicle.brand,
      model: vehicle.model,
      year: vehicle.year,
      client_id: vehicle.client_id
    });
    this.clientSearchTerm.set(vehicle.client_name);
    this.showModal.set(true);
  }

  closeModal(): void {
    this.showModal.set(false);
  }

  onSubmitForm(): void {
    if (this.vehicleForm.invalid) {
      this.vehicleForm.markAllAsTouched();
      return;
    }

    const formValue = this.vehicleForm.value;
    const payload = {
      license_plate: formValue.license_plate,
      brand: formValue.brand,
      model: formValue.model,
      year: Number(formValue.year),
      client_id: Number(formValue.client_id)
    };

    const request$ = this.isEditMode() && this.editingVehicleId !== null
      ? this.vehicleService.updateVehicle(this.editingVehicleId, payload)
      : this.vehicleService.createVehicle(payload);

    request$.subscribe({
      next: () => {
        this.showModal.set(false);
        this.loadVehicles();
      },
      error: (err) => {
        this.modalErrorMessage.set(err.error?.error || 'Erro ao guardar veículo.');
      }
    });
  }

  deleteVehicle(vehicle: Vehicle): void {
    if (!confirm(`Tens a certeza que queres remover o veículo "${vehicle.license_plate}"?`)) {
      return;
    }

    this.vehicleService.deleteVehicle(vehicle.id).subscribe({
      next: () => this.loadVehicles(),
      error: (err) => alert(err.error?.error || 'Erro ao remover veículo.')
    });
  }

  get license_plate() { return this.vehicleForm.get('license_plate'); }
  get brand() { return this.vehicleForm.get('brand'); }
  get model() { return this.vehicleForm.get('model'); }
  get year() { return this.vehicleForm.get('year'); }
  get client_id() { return this.vehicleForm.get('client_id'); }
}