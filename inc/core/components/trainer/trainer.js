document.addEventListener('DOMContentLoaded', function () {
    // Fade-In für Karten
    const cards = document.querySelectorAll('.x-sieben-members-card');
    const observerCards = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if(entry.isIntersecting){
                entry.target.classList.add('x-sieben-visible');
                observerCards.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    cards.forEach(card => observerCards.observe(card));

    // Lazy-Load für Bilder
    const lazyImages = document.querySelectorAll('.x-sieben-members-img.x-sieben-lazy');
    const observerImages = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if(entry.isIntersecting){
                const img = entry.target;
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                    img.onload = () => img.classList.add('x-sieben-loaded');
                    observerImages.unobserve(img);
                }
            }
        });
    }, { threshold: 0.1 });
    lazyImages.forEach(img => observerImages.observe(img));
});