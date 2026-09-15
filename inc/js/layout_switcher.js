document.addEventListener('click', function (e) {
    const switcherBtn = e.target.closest('.layout-switch');
    if (!switcherBtn) return;

    const layout = switcherBtn.dataset.layout; // 'x-sieben-card-rich', 'grid-card', 'list'
    console.log('Switch to layout:', layout);

    // Update all buttons' aria-pressed
    document.querySelectorAll('.layout-switch').forEach(btn =>
        btn.setAttribute('aria-pressed', 'false')
    );
    switcherBtn.setAttribute('aria-pressed', 'true');

    const container = document.querySelector('#courses-container');
    if (!container) return;

    // Reset layout classes on container
    container.classList.remove('layout-rich', 'courses', 'layout-list', 'x7-courses-grid');

    // Hide all layout blocks inside every wrapper
    container.querySelectorAll('.x-sieben-card-rich, .layout-grid-card, .layout-list')
        .forEach(div => div.style.display = 'none');

    // Apply correct class + show the right block
    let selectorToShow, classToAdd;
    switch (layout) {
        case 'x-sieben-card-rich':
            selectorToShow = '.x-sieben-card-rich';
            classToAdd = 'layout-rich';
            break;
        case 'grid-card':
            selectorToShow = '.layout-grid-card';
            classToAdd = 'x7-courses-grid';
            break;
        case 'list':
            selectorToShow = '.layout-list';
            classToAdd = 'layout-list';
            break;
    }

    // Show matching block inside each wrapper
    container.querySelectorAll(selectorToShow).forEach(div => {
        div.style.display = '';
    });

    // Add layout class to container
    if (classToAdd) container.classList.add(classToAdd);
});
// X SIEBEN: Grid-Ansicht als Standard beim Laden
document.addEventListener('DOMContentLoaded', function () {
    const gridButton = document.querySelector(
        '.layout-switch[data-layout="grid-card"]'
    );

    if (
        gridButton &&
        gridButton.getAttribute('aria-pressed') !== 'true'
    ) {
        gridButton.click();
    }
});