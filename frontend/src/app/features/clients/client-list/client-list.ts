import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { ClientService, Client } from '../../../core/services/client.service';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  selector: 'app-client-list',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './client-list.html',
  styleUrl: './client-list.css'
})
export class ClientList implements OnInit {
  clients = signal<Client[]>([]);
  searchTerm = signal('');
  isLoading = signal(false);
  errorMessage = signal('');

  showModal = signal(false);
  isEditMode = signal(false);
  editingClientId: number | null = null;
  modalErrorMessage = signal('');
  canManage = signal(false);

  clientForm: FormGroup;

  constructor(
    private clientService: ClientService,
    public authService: AuthService,
    private fb: FormBuilder
  ) {
    this.clientForm = this.fb.group({
      name: ['', [Validators.required, Validators.minLength(2)]],
      nif: ['', [Validators.pattern(/^\d{9}$/)]],
      email: ['', [Validators.email]],
      phone: [''],
      address: ['']
    });
  }

  ngOnInit(): void {
    const role = this.authService.currentUser()?.role;
    this.canManage.set(role === 'Administrador' || role === 'Funcionário');
    this.loadClients();
  }

  loadClients(): void {
    this.isLoading.set(true);
    this.errorMessage.set('');

    this.clientService.getClients(this.searchTerm()).subscribe({
      next: (response) => {
        this.clients.set(response.data);
        this.isLoading.set(false);
      },
      error: (err) => {
        this.isLoading.set(false);
        this.errorMessage.set(err.error?.error || 'Erro ao carregar clientes.');
      }
    });
  }

  onSearch(): void {
    this.loadClients();
  }

  openCreateModal(): void {
    this.isEditMode.set(false);
    this.editingClientId = null;
    this.modalErrorMessage.set('');
    this.clientForm.reset({ name: '', nif: '', email: '', phone: '', address: '' });
    this.showModal.set(true);
  }

  openEditModal(client: Client): void {
    this.isEditMode.set(true);
    this.editingClientId = client.id;
    this.modalErrorMessage.set('');
    this.clientForm.reset({
      name: client.name,
      nif: client.nif || '',
      email: client.email || '',
      phone: client.phone || '',
      address: client.address || ''
    });
    this.showModal.set(true);
  }

  closeModal(): void {
    this.showModal.set(false);
  }

  onSubmitForm(): void {
    if (this.clientForm.invalid) {
      this.clientForm.markAllAsTouched();
      return;
    }

    const value = this.clientForm.value;
    const payload = {
      name: value.name,
      nif: value.nif || undefined,
      email: value.email || undefined,
      phone: value.phone || undefined,
      address: value.address || undefined
    };

    const request$ = this.isEditMode() && this.editingClientId !== null
      ? this.clientService.updateClient(this.editingClientId, payload)
      : this.clientService.createClient(payload);

    request$.subscribe({
      next: () => {
        this.showModal.set(false);
        this.loadClients();
      },
      error: (err) => {
        this.modalErrorMessage.set(err.error?.error || 'Erro ao guardar cliente.');
      }
    });
  }

  deleteClient(client: Client): void {
    if (!confirm(`Remover o cliente "${client.name}"?`)) return;

    this.clientService.deleteClient(client.id).subscribe({
      next: () => this.loadClients(),
      // O backend recusa se o cliente ainda tiver veículos associados
      error: (err) => alert(err.error?.error || 'Erro ao remover cliente.')
    });
  }

  get name() { return this.clientForm.get('name'); }
  get nif() { return this.clientForm.get('nif'); }
  get email() { return this.clientForm.get('email'); }
}