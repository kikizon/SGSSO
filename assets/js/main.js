// Validación de formularios Bootstrap
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})();

// Confirmación global para enlaces con data-confirm
document.addEventListener('click', function(e) {
    const target = e.target.closest('[data-confirm]');
    if (target) {
        const message = target.getAttribute('data-confirm') || '¿Está seguro?';
        if (!confirm(message)) {
            e.preventDefault();
        }
    }
});

// ============================================================
// LIGHTBOX PARA IMÁGENES DE EVIDENCIA
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    // Crear modal lightbox si no existe en el DOM
    if (!document.getElementById('lightboxModal')) {
        const modalHTML = `
            <div class="modal fade modal-lightbox" id="lightboxModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        <div class="modal-body">
                            <img src="" alt="Evidencia" id="lightboxImage">
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    // Delegación de eventos para elementos con clase 'lightbox-trigger'
    document.body.addEventListener('click', function(e) {
        const trigger = e.target.closest('.lightbox-trigger');
        if (!trigger) return;
        e.preventDefault();
        
        // Obtener URL de la imagen (puede venir en href o data-img)
        const imgSrc = trigger.getAttribute('href') !== '#' ? trigger.getAttribute('href') : trigger.getAttribute('data-img');
        if (imgSrc) {
            const lightboxImg = document.getElementById('lightboxImage');
            lightboxImg.src = imgSrc;
            const modal = new bootstrap.Modal(document.getElementById('lightboxModal'));
            modal.show();
        }
    });
});

// ============================================================
// SPINNER DE CARGA GLOBAL
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    // Crear spinner si no existe
    if (!document.getElementById('loading-spinner')) {
        const spinnerHTML = `
            <div id="loading-spinner">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', spinnerHTML);
    }

    const spinner = document.getElementById('loading-spinner');
    
    // Mostrar spinner al enviar formularios (filtros, creación, edición, etc.)
    document.body.addEventListener('submit', function(e) {
        const form = e.target.closest('form');
        // Ignorar formularios con clase 'no-spinner' (ej. búsqueda en tiempo real)
        if (form && !form.classList.contains('no-spinner')) {
            spinner.style.display = 'flex';
        }
    });

    // Mostrar spinner al hacer clic en enlaces que provocan navegación
    document.body.addEventListener('click', function(e) {
        const link = e.target.closest('a');
        if (!link || !link.href) return;
        
        // Ignorar enlaces que no recargan la página (anclas, javascript, descargas, lightbox, modales)
        if (link.classList.contains('no-spinner') || 
            link.getAttribute('download') !== null ||
            link.getAttribute('href').startsWith('javascript:') ||
            link.getAttribute('href') === '#' ||
            link.classList.contains('lightbox-trigger') ||
            link.getAttribute('data-bs-toggle')) {
            return;
        }
        
        // Evitar mostrar spinner si el enlace abre en nueva pestaña (target="_blank")
        if (link.target === '_blank') return;
        
        spinner.style.display = 'flex';
    });

    // Ocultar spinner al cargar completamente la página
    window.addEventListener('load', function() {
        spinner.style.display = 'none';
    });

    // Ocultar spinner si la página se carga desde caché (back/forward)
    window.addEventListener('pageshow', function() {
        spinner.style.display = 'none';
    });
});