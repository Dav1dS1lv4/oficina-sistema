import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { PaymentService, Payment, PaymentPartLine } from '../../../core/services/payment.service';
import { AuthService } from '../../../core/services/auth.service';

// Dados do cabeçalho da fatura. Ajusta aos da tua oficina.
const COMPANY = {
  name: 'Oficina Auto',
  address: 'Rua Exemplo, 123, 4000-000 Porto',
  nif: '000000000',
  phone: '220 000 000'
};

// Tem de bater certo com a taxa usada no backend
const IVA_PERCENT = 23;

@Component({
  selector: 'app-payment-page',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './payment-page.html',
  styleUrl: './payment-page.css'
})
export class PaymentPage implements OnInit {
  readonly company = COMPANY;
  readonly ivaPercent = IVA_PERCENT;

  // Códigos guardados na base de dados e nomes mostrados no ecrã
  readonly methods = [
    { code: 'numerario', label: 'Numerário' },
    { code: 'mbway', label: 'MB Way' },
    { code: 'cartao', label: 'Cartão' },
    { code: 'transferencia', label: 'Transferência' }
  ];

  // ===== Lista =====
  payments = signal<Payment[]>([]);
  totalPages = signal(1);
  currentPage = signal(1);
  listSearch = signal('');
  isLoadingList = signal(false);
  listError = signal('');

  // ===== Pagamento aberto =====
  referenceInput = signal('');
  current = signal<Payment | null>(null);
  isLoadingDetail = signal(false);
  detailError = signal('');

  // ===== Permissões =====
  canManage = signal(false);
  isAdmin = signal(false);

  // ===== Registo do pagamento =====
  payForm: FormGroup;
  isPaying = signal(false);
  payError = signal('');

  // ===== Anulação (só Administrador) =====
  cancelForm: FormGroup;
  showCancelBox = signal(false);
  isCancelling = signal(false);
  cancelError = signal('');

  constructor(
    private paymentService: PaymentService,
    public authService: AuthService,
    private route: ActivatedRoute,
    private router: Router,
    private fb: FormBuilder
  ) {
    this.payForm = this.fb.group({
      payment_method: ['', Validators.required],
      paid_at: [this.today(), Validators.required]
    });

    this.cancelForm = this.fb.group({
      reason: ['', [Validators.required, Validators.minLength(5), Validators.maxLength(255)]]
    });
  }

  ngOnInit(): void {
    const role = this.authService.currentUser()?.role;
    this.canManage.set(role === 'Administrador' || role === 'Funcionário');
    this.isAdmin.set(role === 'Administrador');

    this.loadList();

    // Abre o pagamento indicado no endereço (/payments/PAG-2026-0001)
    this.route.paramMap.subscribe(params => {
      const reference = params.get('reference');
      if (reference) {
        this.referenceInput.set(reference);
        this.loadDetail(reference);
      } else {
        this.current.set(null);
        this.detailError.set('');
      }
    });
  }

  // ===== Lista =====
  loadList(): void {
    this.isLoadingList.set(true);
    this.listError.set('');

    this.paymentService.getPayments(this.currentPage(), 10, this.listSearch()).subscribe({
      next: (response) => {
        this.payments.set(response.data);
        this.totalPages.set(response.pagination.total_pages);
        this.isLoadingList.set(false);
      },
      error: (err) => {
        this.isLoadingList.set(false);
        this.listError.set(err.error?.error || 'Erro ao carregar pagamentos.');
      }
    });
  }

  onListSearch(): void {
    this.currentPage.set(1);
    this.loadList();
  }

  goToPage(page: number): void {
    if (page < 1 || page > this.totalPages()) return;
    this.currentPage.set(page);
    this.loadList();
  }

  // ===== Abrir um pagamento pelo número =====
  // Aceita "PAG-2026-0001" ou só o número ("1" vira "PAG-<ano atual>-0001")
  private normalizeReference(value: string): string {
    const text = value.trim().toUpperCase();
    if (/^\d{1,4}$/.test(text)) {
      return `PAG-${new Date().getFullYear()}-${text.padStart(4, '0')}`;
    }
    return text;
  }

  openFromInput(): void {
    const reference = this.normalizeReference(this.referenceInput());
    if (!reference) return;
    this.router.navigate(['/payments', reference]);
  }

  private loadDetail(reference: string): void {
    this.isLoadingDetail.set(true);
    this.detailError.set('');
    this.payError.set('');
    this.cancelError.set('');
    this.showCancelBox.set(false);
    this.current.set(null);
    this.payForm.reset({ payment_method: '', paid_at: this.today() });
    this.cancelForm.reset({ reason: '' });

    this.paymentService.getByReference(reference).subscribe({
      next: (response) => {
        this.current.set(response.data);
        this.isLoadingDetail.set(false);
      },
      error: (err) => {
        this.isLoadingDetail.set(false);
        this.detailError.set(err.error?.error || 'Erro ao carregar o pagamento.');
      }
    });
  }

  // ===== Registar pagamento =====
  registerPayment(): void {
    const payment = this.current();
    if (!payment || this.isPaying()) return;

    if (this.payForm.invalid) {
      this.payError.set('Escolhe o método e a data do pagamento.');
      return;
    }

    this.isPaying.set(true);
    this.payError.set('');

    const { payment_method, paid_at } = this.payForm.value;

    this.paymentService.markPaid(payment.reference, payment_method, paid_at).subscribe({
      next: (response) => {
        this.current.set(response.data);
        this.isPaying.set(false);
        this.loadList();
      },
      error: (err) => {
        this.isPaying.set(false);
        this.payError.set(err.error?.error || 'Erro ao registar o pagamento.');
      }
    });
  }

  reopenPayment(): void {
    const payment = this.current();
    if (!payment) return;
    if (!confirm(`Reabrir o pagamento ${payment.reference} como pendente?`)) return;

    this.paymentService.reopen(payment.reference).subscribe({
      next: (response) => {
        this.current.set(response.data);
        this.loadList();
      },
      error: (err) => alert(err.error?.error || 'Erro ao reabrir o pagamento.')
    });
  }

  // ===== Anular pagamento (só Administrador) =====
  toggleCancelBox(): void {
    this.showCancelBox.set(!this.showCancelBox());
    this.cancelError.set('');
  }

  cancelPayment(): void {
    const payment = this.current();
    if (!payment || this.isCancelling()) return;

    if (this.cancelForm.invalid) {
      this.cancelForm.markAllAsTouched();
      this.cancelError.set('Indica o motivo da anulação (mínimo 5 caracteres).');
      return;
    }

    const message = `Anular o pagamento ${payment.reference}?\n\nEsta ação não pode ser desfeita.`;
    if (!confirm(message)) return;

    this.isCancelling.set(true);
    this.cancelError.set('');

    const reason = String(this.cancelForm.value.reason ?? '').trim();

    this.paymentService.cancel(payment.reference, reason).subscribe({
      next: (response) => {
        this.current.set(response.data);
        this.isCancelling.set(false);
        this.showCancelBox.set(false);
        this.cancelForm.reset({ reason: '' });
        this.loadList();
      },
      error: (err) => {
        this.isCancelling.set(false);
        this.cancelError.set(err.error?.error || 'Erro ao anular o pagamento.');
      }
    });
  }

  // ===== Fatura =====
  // Abre o diálogo de impressão. O nome do documento serve de nome ao PDF.
  printInvoice(): void {
    const payment = this.current();
    if (!payment) return;

    const previousTitle = document.title;
    document.title = `Fatura ${payment.reference}`;
    window.addEventListener('afterprint', () => { document.title = previousTitle; }, { once: true });
    window.print();
  }

  // ===== Auxiliares do ecrã =====
  methodLabel(code: string | null): string {
    if (!code) return '—';
    return this.methods.find(m => m.code === code)?.label ?? code;
  }

  lineTotal(part: PaymentPartLine): number {
    return Number(part.quantity_used) * Number(part.unit_price);
  }

  // created_at vem como "2026-10-18 11:35:00": usamos só a data
  issueDate(payment: Payment): string {
    return payment.created_at.substring(0, 10);
  }

  // "2026-10-18 22:05:00" -> "18/10/2026 22:05"
  formatDateTime(value: string | null): string {
    if (!value) return '—';
    const date = value.substring(0, 10);
    const time = value.substring(11, 16);
    return `${date.substring(8, 10)}/${date.substring(5, 7)}/${date.substring(0, 4)} ${time}`.trim();
  }

  // "09:00:00" -> "09:00"
  shortTime(time: string | null): string {
    return time ? time.substring(0, 5) : '';
  }

  // 2.5 -> "2h30"
  formatHours(hours: number | string): string {
    const totalMinutes = Math.round(Number(hours) * 60);
    const h = Math.floor(totalMinutes / 60);
    const m = totalMinutes % 60;
    return `${h}h${String(m).padStart(2, '0')}`;
  }

  private today(): string {
    const d = new Date();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${mm}-${dd}`;
  }
}