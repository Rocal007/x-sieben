document.addEventListener('DOMContentLoaded', function () {
  const container = document.querySelector('.layout-list');
  if (!container) return;

  const sortButtons = container.querySelectorAll('.sort-btn');
  sortButtons.forEach(btn => {
    btn.addEventListener('click', function () {
      const sortKey = this.dataset.sort;
      const order = this.dataset.order === 'asc' ? 'desc' : 'asc';
      this.dataset.order = order;

      const items = Array.from(container.querySelectorAll('.list-item'));
      items.sort((a, b) => {
        let valA = a.dataset[sortKey];
        let valB = b.dataset[sortKey];

        if (sortKey === 'price' || sortKey === 'date' || sortKey === 'units') {
          valA = parseFloat(valA) || 0;
          valB = parseFloat(valB) || 0;
        }

        return order === 'asc' ? valA - valB : valB - valA;
      });

      items.forEach(item => container.appendChild(item));
    });
  });
});