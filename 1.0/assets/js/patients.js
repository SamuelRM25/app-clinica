document.addEventListener('DOMContentLoaded', function() {
    // Handle new patient form submission
    $('#newPatientForm').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: $(this).attr('action'),
            method: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: 'Paciente registrado correctamente',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(function() {
                        window.location.reload();
                    });
                    $('#newPatientModal').modal('hide');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No se pudo registrar el paciente'
                    });
                }
            }
        });
    });

    // Handle patient deletion
    window.deletePatient = function(id) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: "Esta acción no se puede revertir",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'delete_patient.php',
                    method: 'POST',
                    data: { id: id },
                    success: function(response) {
                        if (response === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Eliminado',
                                text: 'El paciente ha sido eliminado',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(function() {
                                window.location.reload();
                            });
                        }
                    }
                });
            }
        });
    };
});