<?php
// laboratory/buscar_paciente.php - Search for patients
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

$page_title = "Buscar Paciente - Laboratorio";
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    
    <style>
    .search-container {
        max-width: 800px;
        margin: 2rem auto;
    }
    
    .search-box {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .patient-result {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 1.25rem;
        margin-bottom: 1rem;
        transition: all 0.2s ease;
        cursor: pointer;
    }
    
    .patient-result:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
        border-color: var(--color-primary);
    }
    
    .patient-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .patient-name {
        font-weight: 600;
        color: var(--color-text);
        margin-bottom: 0.25rem;
    }
    
    .patient-details {
        font-size: 0.85rem;
        color: var(--color-text-light);
    }
    </style>
</head>
<body>
    <div class="marble-effect"></div>
    
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <img src="../../assets/img/herrerasaenz.png" alt="CMHS" class="brand-logo">
                <div class="header-controls">
                    <div class="theme-toggle">
                        <button id="themeSwitch" class="theme-btn">
                            <i class="bi bi-sun theme-icon sun-icon"></i>
                            <i class="bi bi-moon theme-icon moon-icon"></i>
                        </button>
                    </div>
                    <a href="index.php" class="action-btn secondary">
                        <i class="bi bi-arrow-left"></i>
                        Volver
                    </a>
                </div>
            </div>
        </header>
        
        <main class="main-content">
            <div class="search-container">
                <div class="page-header">
                    <h1 class="page-title">
                        <i class="bi bi-search text-primary"></i>
                        Buscar Paciente
                    </h1>
                    <p class="page-subtitle">Encuentre pacientes por nombre, DPI o expediente</p>
                </div>
                
                <div class="search-box">
                    <div class="mb-3">
                        <label class="form-label">Buscar por nombre, DPI o expediente</label>
                        <input type="text" id="searchInput" class="form-control" placeholder="Ingrese nombre, DPI o número de expediente..." autofocus>
                    </div>
                    <button class="action-btn w-100" onclick="searchPatients()">
                        <i class="bi bi-search"></i>
                        Buscar
                    </button>
                </div>
                
                <div id="results"></div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') searchPatients();
    });
    
    function searchPatients() {
        const query = document.getElementById('searchInput').value.trim();
        if (!query) {
            Swal.fire('Error', 'Por favor ingrese un término de búsqueda', 'warning');
            return;
        }
        
        fetch(`../patients/search_patients.php?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                const resultsDiv = document.getElementById('results');
                if (data.length === 0) {
                    resultsDiv.innerHTML = '<p class="text-center text-muted">No se encontraron pacientes</p>';
                    return;
                }
                
                let html = '';
                data.forEach(patient => {
                    html += `
                        <div class="patient-result" onclick="viewPatient(${patient.id_paciente})">
                            <div class="patient-info">
                                <div>
                                    <div class="patient-name">${patient.nombre} ${patient.apellido}</div>
                                    <div class="patient-details">
                                        <i class="bi bi-card-text"></i> DPI: ${patient.dpi || 'N/A'} | 
                                        <i class="bi bi-calendar"></i> ${calculateAge(patient.fecha_nacimiento)} años
                                    </div>
                                </div>
                                <i class="bi bi-chevron-right"></i>
                            </div>
                        </div>
                    `;
                });
                resultsDiv.innerHTML = html;
            })
            .catch(error => {
                Swal.fire('Error', 'Error al buscar pacientes', 'error');
            });
    }
    
    function calculateAge(birthDate) {
        const today = new Date();
        const birth = new Date(birthDate);
        let age = today.getFullYear() - birth.getFullYear();
        const m = today.getMonth() - birth.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
        return age;
    }
    
    function viewPatient(id) {
        window.location.href = `../patients/view_patient.php?id=${id}`;
    }
    
    // Theme JS
    document.addEventListener('DOMContentLoaded', function() {
        if (localStorage.getItem('dashboard-theme') === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
        document.getElementById('themeSwitch')?.addEventListener('click', () => {
            const current = document.documentElement.getAttribute('data-theme');
            const target = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', target);
            localStorage.setItem('dashboard-theme', target);
        });
    });
    </script>
</body>
</html>
