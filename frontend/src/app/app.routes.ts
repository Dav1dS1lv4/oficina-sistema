import { Routes } from '@angular/router';
import { LoginComponent } from './features/auth/login/login.component';
import { RegisterComponent } from './features/auth/register/register.component';
import { DashboardComponent } from './features/dashboard/dashboard/dashboard.component';
import { UserListComponent } from './features/users/user-list/user-list.component';
import { VehicleList } from './features/vehicles/vehicle-list/vehicle-list';
import { PartList } from './features/parts/part-list/part-list';
import { authGuard } from './core/guards/auth.guard';
import { adminGuard } from './core/guards/admin.guard';

export const routes: Routes = [
  { path: '', redirectTo: 'login', pathMatch: 'full' },
  { path: 'login', component: LoginComponent },
  { path: 'register', component: RegisterComponent },
  { path: 'dashboard', component: DashboardComponent, canActivate: [authGuard] },
  { path: 'users', component: UserListComponent, canActivate: [authGuard, adminGuard] },
  { path: 'vehicles', component: VehicleList, canActivate: [authGuard] },
  { path: 'parts', component: PartList, canActivate: [authGuard] },
];