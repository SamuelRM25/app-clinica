import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './home.component.html',
  styleUrls: ['./home.component.css']
})
export class HomeComponent {
  pacientesMesPasado: number = 120;
  citasConfirmadas: number = 35;
  citasCanceladas: number = 5;
  totalVentasFarmacia: number = 1500;
  totalCitas: number = 40;

  // Your component logic here
}