import { Component, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AbstractControl, FormArray, FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { MaintenanceService, MaintenanceRecord } from '../../../core/services/maintenance.service';
import { VehicleService, Vehicle } from '../../../core/services/vehicle.service';
import { PartService, Part } from '../../../core/services/part.service';
import { PaymentService } from '../../../core/services/payment.service';
import { AuthService } from '../../../core/services/auth.service';

// Taxa de IVA normal em Portugal continental (tem de bater certo com o backend)
const IVA_RATE = 0.23;
// Valor/hora sugerido por defeito (pode ser alterado em cada manutenção)
const DEFAULT_HOURLY_RATE = 40;

@Component({
  selector: 'app-maintenance-list',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './maintenance-list.html',
  styleUrl: './maintenance-list.css'
})
export class MaintenanceList implements OnInit {
  // ===== Listagem =====
  records = signal<MaintenanceRecord[]>([]);
  totalPages = signal(1);
  currentPage = signal(1);
  searchTerm = signal('');
  isLoading = signal(false);
  errorMessage = signal('');

  // ===== Permissões =====
  canManage = signal(false);
  isAdmin = signal(false);

  // ===== Modal =====
  showModal = signal(false);
  modalErrorMessage = signal('');
  isSaving = signal(false);

  // ===== Pagamento =====
  generatingId = signal<number | null>(null);

  maintenanceForm: FormGroup;

  // ===== Autocomplete de veículos =====
  vehicles = signal<Vehicle[]>([]);
  vehicleSearchTerm = signal('');
  showVehicleDropdown = signal(false);

  filteredVehicles = computed(() => {
    const term = this.vehicleSearchTerm().toLowerCase().trim();
    if (term === '') return this.vehicles();
    return this.vehicles().filter(v =>
      v.license_plate.toLowerCase().includes(term) ||
      v.brand.toLowerCase().includes(term) ||
      v.model.toLowerCase().includes(term) ||
      v.client_name.toLowerCase().includes(term)
    );
  });

  // ===== Autocomplete de peças =====
  parts = signal<Part[]>([]);
  partQuery = signal('');
  activePartIndex = signal<number | null>(null);

  filteredParts = computed(() => {
    const term = this.partQuery().toLowerCase().trim();
    if (term === '') return this.parts();
    return this.parts().filter(p => p.name.toLowerCase().includes(term));
  });

  // ===== Mão de obra e totais em tempo real =====
  laborMinutes = signal(0);
  timeError = signal('');
  partsTotal = signal(0);
  laborTotal = signal(0);
  subtotal = signal(0);
  taxAmount = signal(0);
  totalAmount = signal(0);
  readonly ivaPercent = IVA_RATE * 100;

  constructor(
    private maintenanceService: MaintenanceService,
    private vehicleService: VehicleService,
    private partService: PartService,
    private paymentService: PaymentService,
    private router: Router,
    public authService: AuthService,
    private fb: FormBuilder
  ) {
    this.maintenanceForm = this.fb.group({
      vehicle_id: ['', Validators.required],
      type: ['', Validators.required],
      service_date: [this.today(), Validators.required],
      start_time: [''],
      end_time: [''],
      hourly_rate: [DEFAULT_HOURLY_RATE, [Validators.required, Validators.min(0)]],
      notes: [''],
      parts: this.fb.array([])
    });

    // Sempre que qualquer campo muda, recalcula os totais
    this.maintenanceForm.valueChanges.subscribe(() => this.recalculateTotals());
  }

  ngOnInit(): void {
    const role = this.authService.currentUser()?.role;
    this.canManage.set(role === 'Administrador' || role === 'Funcionário');
    this.isAdmin.set(role === 'Administrador');

    this.loadRecords();
    this.loadVehicles();
    this.loadParts();
  }

  // ===== Carregamento de dados =====
  loadRecords(): void {
    this.isLoading.set(true);
    this.errorMessage.set('');

    this.maintenanceService.getMaintenance(this.currentPage(), 10, this.searchTerm()).subscribe({
      next: (response) => {
        this.records.set(response.data);
        this.totalPages.set(response.pagination.total_pages);
        this.isLoading.set(false);
      },
      error: (err) => {
        this.isLoading.set(false);
        this.errorMessage.set(err.error?.error || 'Erro ao carregar manutenções.');
      }
    });
  }

  loadVehicles(): void {
    this.vehicleService.getVehicles(1, 50, '').subscribe({
      next: (response) => this.vehicles.set(response.data),
      error: () => {}
    });
  }

  loadParts(): void {
    this.partService.getParts(1, 50, '').subscribe({
      next: (response) => this.parts.set(response.data),
      error: () => {}
    });
  }

  onSearch(): void {
    this.currentPage.set(1);
    this.loadRecords();
  }

  goToPage(page: number): void {
    if (page < 1 || page > this.totalPages()) return;
    this.currentPage.set(page);
    this.loadRecords();
  }

  // ===== Linhas de peças (FormArray) =====
  get partLines(): FormArray {
    return this.maintenanceForm.get('parts') as FormArray;
  }

  private createPartLine(): FormGroup {
    return this.fb.group({
      part_id: [null],
      name: ['', Validators.required],
      quantity: [1, [Validators.required, Validators.min(1)]],
      unit_price: [0, [Validators.required, Validators.min(0)]]
    });
  }

  addPartLine(): void {
    this.partLines.push(this.createPartLine());
  }

  removePartLine(index: number): void {
    this.partLines.removeAt(index);
    this.activePartIndex.set(null);
  }

  lineTotal(line: AbstractControl): number {
    const quantity = Number(line.get('quantity')?.value) || 0;
    const price = Number(line.get('unit_price')?.value) || 0;
    return quantity * price;
  }

  // ===== Autocomplete de peças: interações =====
  onPartNameInput(index: number, value: string): void {
    this.partLines.at(index).get('part_id')?.setValue(null);
    this.partQuery.set(value);
    this.activePartIndex.set(index);
  }

  onPartFocus(index: number): void {
    this.partQuery.set(this.partLines.at(index).get('name')?.value || '');
    this.activePartIndex.set(index);
  }

  onPartBlur(): void {
    setTimeout(() => this.activePartIndex.set(null), 150);
  }

  selectPart(index: number, part: Part): void {
    this.partLines.at(index).patchValue({
      part_id: part.id,
      name: part.name,
      unit_price: Number(part.price)
    });
    this.activePartIndex.set(null);
  }

  // ===== Autocomplete de veículos: interações =====
  onVehicleInputChange(value: string): void {
    this.vehicleSearchTerm.set(value);
    this.showVehicleDropdown.set(true);
    this.maintenanceForm.get('vehicle_id')?.setValue('');
  }

  onVehicleInputFocus(): void {
    this.showVehicleDropdown.set(true);
  }

  onVehicleInputBlur(): void {
    setTimeout(() => this.showVehicleDropdown.set(false), 150);
  }

  selectVehicle(vehicle: Vehicle): void {
    this.maintenanceForm.get('vehicle_id')?.setValue(vehicle.id);
    this.vehicleSearchTerm.set(`${vehicle.license_plate} — ${vehicle.brand} ${vehicle.model}`);
    this.showVehicleDropdown.set(false);
  }

  // ===== Mão de obra e totais =====
  private round2(value: number): number {
    return Math.round(value * 100) / 100;
  }

  private toMinutes(time: string): number | null {
    if (!/^\d{2}:\d{2}$/.test(time)) return null;
    const [hours, minutes] = time.split(':').map(Number);
    return hours * 60 + minutes;
  }

  // 150 minutos -> "2h30"
  formatDuration(totalMinutes: number): string {
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    return `${hours}h${String(minutes).padStart(2, '0')}`;
  }

  private recalculateTotals(): void {
    const rate = Number(this.maintenanceForm.get('hourly_rate')?.value) || 0;
    const start = this.maintenanceForm.get('start_time')?.value || '';
    const end = this.maintenanceForm.get('end_time')?.value || '';

    // Duração do serviço
    let minutes = 0;
    let timeError = '';
    if (start || end) {
      const startMinutes = this.toMinutes(start);
      const endMinutes = this.toMinutes(end);

      if (startMinutes === null || endMinutes === null) {
        timeError = 'Indica a hora de início e a hora de fim.';
      } else if (endMinutes <= startMinutes) {
        timeError = 'A hora de fim tem de ser depois da hora de início.';
      } else {
        minutes = endMinutes - startMinutes;
      }
    }
    this.timeError.set(timeError);
    this.laborMinutes.set(minutes);

    const labor = this.round2(minutes * rate / 60);

    let partsSum = 0;
    for (const line of this.partLines.controls) {
      partsSum += this.lineTotal(line);
    }

    const subtotal = this.round2(partsSum + labor);
    const tax = this.round2(subtotal * IVA_RATE);

    this.partsTotal.set(this.round2(partsSum));
    this.laborTotal.set(labor);
    this.subtotal.set(subtotal);
    this.taxAmount.set(tax);
    this.totalAmount.set(this.round2(subtotal + tax));
  }

  // ===== Modal =====
  private today(): string {
    const d = new Date();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${mm}-${dd}`;
  }

  openCreateModal(): void {
    this.modalErrorMessage.set('');
    this.partLines.clear();
    this.maintenanceForm.reset({
      vehicle_id: '',
      type: '',
      service_date: this.today(),
      start_time: '',
      end_time: '',
      hourly_rate: DEFAULT_HOURLY_RATE,
      notes: ''
    });
    this.vehicleSearchTerm.set('');
    this.recalculateTotals();
    this.showModal.set(true);
  }

  closeModal(): void {
    this.showModal.set(false);
  }

  onSubmitForm(): void {
    if (this.maintenanceForm.invalid) {
      this.maintenanceForm.markAllAsTouched();
      return;
    }

    if (this.timeError()) {
      this.modalErrorMessage.set(this.timeError());
      return;
    }

    if (this.isSaving()) return; // evita duplo clique (descontaria o stock duas vezes)

    const value = this.maintenanceForm.value;
    const payload = {
      vehicle_id: Number(value.vehicle_id),
      type: value.type,
      service_date: value.service_date,
      start_time: value.start_time || undefined,
      end_time: value.end_time || undefined,
      hourly_rate: Number(value.hourly_rate),
      notes: value.notes || undefined,
      parts: value.parts.map((line: any) => ({
        part_id: line.part_id ? Number(line.part_id) : undefined,
        name: line.part_id ? undefined : line.name,
        quantity: Number(line.quantity),
        unit_price: Number(line.unit_price)
      }))
    };

    this.isSaving.set(true);
    this.modalErrorMessage.set('');

    this.maintenanceService.createMaintenance(payload).subscribe({
      next: () => {
        this.isSaving.set(false);
        this.showModal.set(false);
        this.loadRecords();
        this.loadParts(); // o stock mudou e podem ter sido criadas peças novas
      },
      error: (err) => {
        this.isSaving.set(false);
        this.modalErrorMessage.set(err.error?.error || 'Erro ao registar manutenção.');
      }
    });
  }

  // ===== Pagamento =====
  generatePayment(record: MaintenanceRecord): void {
    if (this.generatingId() !== null) return; // evita cliques duplos

    this.generatingId.set(record.id);

    this.paymentService.createPayment(record.id).subscribe({
      next: (response) => {
        this.generatingId.set(null);
        this.router.navigate(['/payments', response.data.reference]);
      },
      error: (err) => {
        this.generatingId.set(null);
        // Se já existia pagamento, abre esse em vez de dar erro
        if (err.status === 409 && err.error?.reference) {
          this.router.navigate(['/payments', err.error.reference]);
          return;
        }
        alert(err.error?.error || 'Erro ao gerar o pagamento.');
      }
    });
  }

  deleteRecord(record: MaintenanceRecord): void {
    const message = `Remover a manutenção de ${record.license_plate} (${record.type})?\n\n` +
      `Atenção: o stock das peças usadas NÃO é reposto automaticamente.`;
    if (!confirm(message)) return;

    this.maintenanceService.deleteMaintenance(record.id).subscribe({
      next: () => this.loadRecords(),
      error: (err) => alert(err.error?.error || 'Erro ao remover manutenção.')
    });
  }

  get vehicle_id() { return this.maintenanceForm.get('vehicle_id'); }
  get type() { return this.maintenanceForm.get('type'); }
  get service_date() { return this.maintenanceForm.get('service_date'); }
  get hourly_rate() { return this.maintenanceForm.get('hourly_rate'); }
}