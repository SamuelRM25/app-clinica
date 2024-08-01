import { Component, OnInit } from '@angular/core';
import { CalendarOptions } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import { HttpClient } from '@angular/common/http';

@Component({
  selector: 'app-citas',
  templateUrl: './citas.component.html',
  styleUrls: ['./citas.component.css']
})
export class CitasComponent implements OnInit {
  calendarOptions: CalendarOptions = {
    plugins: [dayGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    events: [], // Aquí se cargarán las citas desde la base de datos
    dateClick: this.handleDateClick.bind(this),
  };

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadEvents();
  }

  loadEvents() {
    this.http.get('http://localhost:3000/api/citas').subscribe((events: any) => {
      this.calendarOptions.events = events;
    });
  }

  handleDateClick(arg: any) {
    // Implementar la lógica para reservar una nueva cita
  }
}
