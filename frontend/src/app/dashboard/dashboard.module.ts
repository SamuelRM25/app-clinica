import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router'; // Asegúrate de que RouterModule esté importado
import { DashboardComponent } from './dashboard/dashboard.component';
import { HomeComponent } from './home/home.component';
import { CitasComponent } from './citas/citas.component';
import { HistoriaClinicaComponent } from './historia-clinica/historia-clinica.component';
import { InventarioComponent } from './inventario/inventario.component';
import { HistorialPacientesComponent } from './historial-pacientes/historial-pacientes.component';

@NgModule({
  declarations: [
    DashboardComponent,
    HomeComponent,
    CitasComponent,
    HistoriaClinicaComponent,
    InventarioComponent,
    HistorialPacientesComponent
  ],
  imports: [
    CommonModule,
    RouterModule // Asegúrate de que RouterModule esté importado
  ]
})
export class DashboardModule { }
