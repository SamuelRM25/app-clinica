function validateResult(input, min, max, statusId) {
    const value = parseFloat(input.value);
    const statusCell = document.getElementById(statusId);

    if (isNaN(value)) {
        statusCell.innerHTML = '<span class="text-warning">⚠️</span>';
        return;
    }

    if (value >= min && value <= max) {
        statusCell.innerHTML = '<span class="text-success">✅</span>';
    } else {
        statusCell.innerHTML = '<span class="text-danger">❌</span>';
    }
}