import { Component } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { FormsModule } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';

@Component({
  selector: 'app-login',
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.css'],
  standalone: true,
  imports: [FormsModule]
})
export class LoginComponent {
  usuario: string = '';
  password: string = '';

  constructor(private authService: AuthService, private router: Router) {}

  login(): void {
    this.authService.login(this.usuario, this.password).subscribe(
      () => {
        this.router.navigate(['/dashboard']);
      },
      (error: HttpErrorResponse) => {
        console.error('Error de login', error);
        if (error.error instanceof ErrorEvent) {
          // Error del lado del cliente
          alert(`Error: ${error.error.message}`);
        } else {
          // El backend retornó un código de error
          alert(`Error ${error.status}: ${error.error.message || 'Algo salió mal. Por favor, intenta de nuevo.'}`);
        }
      }
    );
  }
}