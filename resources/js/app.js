// Livewire 4 gestiona e inicializa Alpine.js automáticamente mediante @livewireScripts.

async function bootCentinelaLeafletMaps() {
    if (typeof document === 'undefined' || !document.querySelector('[data-centinela-map]')) {
        return;
    }

    const { initAllLeafletMaps } = await import('./map.js');
    await initAllLeafletMaps(document);
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            void bootCentinelaLeafletMaps();
        });
    } else {
        void bootCentinelaLeafletMaps();
    }

    document.addEventListener('livewire:navigated', () => {
        void bootCentinelaLeafletMaps();
    });
}
