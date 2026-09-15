document.addEventListener("DOMContentLoaded", function () {
    const table = document.querySelector(".js-sort-table");
    if (!table) return;

    const getCellValue = (tr, idx) => {
        const input = tr.children[idx].querySelector("input");
        return input ? input.value : tr.children[idx].innerText.trim();
    };

    const comparer = (idx, asc) => (a, b) => {
        const v1 = getCellValue(a, idx);
        const v2 = getCellValue(b, idx);

        const d1 = Date.parse(v1);
        const d2 = Date.parse(v2);

        if (!isNaN(d1) && !isNaN(d2)) {
            return asc ? d1 - d2 : d2 - d1;
        }

        return v1.localeCompare(v2, 'de', { numeric: true }) * (asc ? 1 : -1);
    };

    const headers = table.querySelectorAll("th");
    headers.forEach((th, idx) => {
        th.style.cursor = "pointer";
        th.dataset.sort = "none"; // "none", "asc", "desc"

        const indicator = document.createElement("span");
        indicator.className = "sort-indicator";
        indicator.style.marginLeft = "5px";
        indicator.textContent = "⇅";
        th.appendChild(indicator);

        th.addEventListener("click", () => {
            const current = th.dataset.sort;
            const asc = current !== "asc";
            th.dataset.sort = asc ? "asc" : "desc";
            th.querySelector(".sort-indicator").textContent = asc ? "↑" : "↓";

            // Reset other headers
            headers.forEach((other) => {
                if (other !== th) {
                    other.dataset.sort = "none";
                    other.querySelector(".sort-indicator").textContent = "⇅";
                }
            });

            const tbody = table.querySelector("tbody");
            Array.from(tbody.querySelectorAll("tr"))
                .sort(comparer(idx, asc))
                .forEach(tr => tbody.appendChild(tr));
        });
    });

    // Echtzeitsuche
    const searchInput = document.getElementById("courseTableSearch");
    if (!searchInput) return;

    searchInput.addEventListener("input", function () {
        const filter = this.value.toLowerCase();
        const rows = table.querySelectorAll("tbody tr");

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(filter) ? "" : "none";
        });
    });
});
