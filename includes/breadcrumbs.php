<?php
/**
 * includes/breadcrumbs.php
 * Renderiza breadcrumbs de navegación.
 * Uso: render_breadcrumbs([['label' => 'Inicio', 'url' => '/php/dashboard/index.php'], ['label' => 'Pacientes']]);
 */
function render_breadcrumbs(array $items): void {
    if (empty($items)) return;
    echo '<nav aria-label="breadcrumb"><ol class="breadcrumb" style="background: none; padding: 0.5rem 0; margin: 0; font-size: 0.85rem;">';
    $last = array_key_last($items);
    foreach ($items as $i => $item) {
        if ($i === $last || empty($item['url'])) {
            echo '<li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars($item['label']) . '</li>';
        } else {
            echo '<li class="breadcrumb-item"><a href="' . htmlspecialchars($item['url']) . '">' . htmlspecialchars($item['label']) . '</a></li>';
        }
    }
    echo '</ol></nav>';
}
