import { Routes } from '@angular/router';
import { LoginComponent } from './features/auth/login/login.component';
import { RegisterComponent } from './features/auth/register/register.component';
import { DashboardComponent } from './features/dashboard/dashboard/dashboard.component';
import { UserListComponent } from './features/users/user-list/user-list.component';
import { VehicleList } from './features/vehicles/vehicle-list/vehicle-list';
import { PartList } from './features/parts/part-list/part-list';
import { authGuard } from './core/guards/auth.guard';
import { adminGuard } from './core/guards/admin.guard';
import { MaintenanceList } from './features/maintenance/maintenance-list/maintenance-list';
import { ClientList } from './features/clients/client-list/client-list';
import { PaymentPage } from './features/payments/payment-page/payment-page';

export const routes: Routes = [
  { path: '', redirectTo: 'login', pathMatch: 'full' },
  { path: 'login', component: LoginComponent },
  { path: 'register', component: RegisterComponent },
  { path: 'dashboard', component: DashboardComponent, canActivate: [authGuard] },
  { path: 'users', component: UserListComponent, canActivate: [authGuard, adminGuard] },
  { path: 'vehicles', component: VehicleList, canActivate: [authGuard] },
  { path: 'parts', component: PartList, canActivate: [authGuard] },
  { path: 'maintenance', component: MaintenanceList, canActivate: [authGuard] },
    { path: 'clients', component: ClientList, canActivate: [authGuard] },
      { path: 'payments', component: PaymentPage, canActivate: [authGuard] },
  { path: 'payments/:reference', component: PaymentPage, canActivate: [authGuard] },
];