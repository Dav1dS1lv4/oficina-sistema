import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { UserService, OficinaUser } from '../../../core/services/user.service';
import { AuthService } from '../../../core/services/auth.service';
import { Router, RouterLink } from '@angular/router';

@Component({
  selector: 'app-user-list',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './user-list.component.html',
  styleUrl: './user-list.component.css'
})
export class UserListComponent implements OnInit {
  users = signal<OficinaUser[]>([]);
  totalPages = signal(1);
  currentPage = signal(1);
  searchTerm = signal('');
  isLoading = signal(false);
  errorMessage = signal('');

  // Controlo do modal
  showModal = signal(false);
  isEditMode = signal(false);
  editingUserId: number | null = null;
  modalErrorMessage = signal('');

  userForm: FormGroup;

  constructor(
    private userService: UserService,
    public authService: AuthService,
    private router: Router,
    private fb: FormBuilder
  ) {
   this.userForm = this.fb.group({
  name: ['', [Validators.required, Validators.minLength(2)]],
  email: ['', [Validators.required, Validators.email]],
  password: [''],
  role_id: [3, Validators.required],
  nif: ['']
});
  }

  ngOnInit(): void {
    this.loadUsers();
  }

  loadUsers(): void {
    this.isLoading.set(true);
    this.errorMessage.set('');

    this.userService.getUsers(this.currentPage(), 10, this.searchTerm()).subscribe({
      next: (response) => {
        this.users.set(response.data);
        this.totalPages.set(response.pagination.total_pages);
        this.isLoading.set(false);
      },
      error: (err) => {
        this.isLoading.set(false);
        this.errorMessage.set(err.error?.error || 'Erro ao carregar utilizadores.');
      }
    });
  }

  onSearch(): void {
    this.currentPage.set(1);
    this.loadUsers();
  }

  goToPage(page: number): void {
    if (page < 1 || page > this.totalPages()) return;
    this.currentPage.set(page);
    this.loadUsers();
  }

  // ===== Modal: Criar =====
  openCreateModal(): void {
    this.isEditMode.set(false);
    this.editingUserId = null;
    this.modalErrorMessage.set('');
    this.userForm.reset({ role_id: 3 });
    this.userForm.get('password')?.setValidators([Validators.required, Validators.minLength(6)]);
    this.userForm.get('password')?.updateValueAndValidity();
    this.showModal.set(true);
  }

  // ===== Modal: Editar =====
  openEditModal(user: OficinaUser): void {
    this.isEditMode.set(true);
    this.editingUserId = user.id;
    this.modalErrorMessage.set('');
    this.userForm.reset({
  name: user.name,
  email: user.email,
  role_id: user.role_id,
  password: '',
  nif: user.nif || ''
});
    // Na edição, a password é opcional (só muda se preenchida)
    this.userForm.get('password')?.clearValidators();
    this.userForm.get('password')?.updateValueAndValidity();
    this.showModal.set(true);
  }

  closeModal(): void {
    this.showModal.set(false);
  }

  onSubmitForm(): void {
    if (this.userForm.invalid) {
      this.userForm.markAllAsTouched();
      return;
    }

    const formValue = this.userForm.value;

    if (this.isEditMode() && this.editingUserId !== null) {
      const payload: any = {
        name: formValue.name,
        email: formValue.email,
        role_id: Number(formValue.role_id),
        nif: formValue.nif || undefined
      };
      if (formValue.password) {
        payload.password = formValue.password;
      }

      this.userService.updateUser(this.editingUserId, payload).subscribe({
        next: () => {
          this.showModal.set(false);
          this.loadUsers();
        },
        error: (err) => {
          this.modalErrorMessage.set(err.error?.error || 'Erro ao atualizar utilizador.');
        }
      });
    } else {
      this.userService.createUser({
        name: formValue.name,
        email: formValue.email,
        password: formValue.password,
        role_id: Number(formValue.role_id),
        nif:formValue.nif || undefined
      }).subscribe({
        next: () => {
          this.showModal.set(false);
          this.loadUsers();
        },
        error: (err) => {
          this.modalErrorMessage.set(err.error?.error || 'Erro ao criar utilizador.');
        }
      });
    }
  }

  toggleActive(user: OficinaUser): void {
    this.userService.toggleActive(user.id).subscribe({
      next: () => this.loadUsers(),
      error: (err) => alert(err.error?.error || 'Erro ao alterar estado da conta.')
    });
  }

  deleteUser(user: OficinaUser): void {
    if (!confirm(`Tens a certeza que queres remover "${user.name}"? Esta ação não pode ser desfeita.`)) {
      return;
    }

    this.userService.deleteUser(user.id).subscribe({
      next: () => this.loadUsers(),
      error: (err) => alert(err.error?.error || 'Erro ao remover utilizador.')
    });
  }

  get name() { return this.userForm.get('name'); }
  get email() { return this.userForm.get('email'); }
  get password() { return this.userForm.get('password'); }
}