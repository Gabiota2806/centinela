// Livewire 4 gestiona e inicializa Alpine.js automáticamente mediante @livewireScripts.

let mapModulePromise = null;

async function bootCentinelaLeafletMaps() {
    if (typeof document === 'undefined' || !document.querySelector('[data-centinela-map]')) {
        return;
    }

    mapModulePromise ??= import('./map.js');
    const { initAllLeafletMaps } = await mapModulePromise;
    await initAllLeafletMaps(document);
}

async function teardownCentinelaLeafletMaps() {
    if (!mapModulePromise) {
        return;
    }

    const { destroyAllLeafletMaps } = await mapModulePromise;
    destroyAllLeafletMaps();
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            void bootCentinelaLeafletMaps();
        });
    } else {
        void bootCentinelaLeafletMaps();
    }

    document.addEventListener('livewire:navigating', () => {
        void teardownCentinelaLeafletMaps();
    });

    document.addEventListener('livewire:navigated', () => {
        void bootCentinelaLeafletMaps();
    });
}
