import { Collapse, Tooltip } from 'bootstrap';

const desktopSidebar = document.querySelector('[data-desktop-sidebar]');
const desktopToggle = document.querySelector('[data-desktop-sidebar-toggle]');

if (desktopSidebar && desktopToggle) {
    const root = document.documentElement;
    const desktopViewport = window.matchMedia('(min-width: 992px)');
    const storageKey = 'rs-sidebar-state';
    let tooltipInstances = [];

    const persistState = (collapsed) => {
        try {
            localStorage.setItem(storageKey, collapsed ? 'collapsed' : 'expanded');
        } catch {
            // Sidebar remains functional when localStorage is unavailable.
        }
    };

    const disposeTooltips = () => {
        tooltipInstances.forEach((tooltip) => tooltip.dispose());
        tooltipInstances = [];
    };

    const initializeTooltips = () => {
        disposeTooltips();

        if (!desktopViewport.matches || !root.classList.contains('rs-sidebar-collapsed')) {
            return;
        }

        tooltipInstances = [...desktopSidebar.querySelectorAll('[data-sidebar-tooltip]')]
            .map((element) => new Tooltip(element, {
                title: element.dataset.sidebarTooltip,
                placement: 'right',
                trigger: 'hover focus',
                container: 'body',
                boundary: 'viewport',
            }));
    };

    const updateToggleAccessibility = () => {
        const expanded = !root.classList.contains('rs-sidebar-collapsed');

        desktopToggle.setAttribute('aria-expanded', String(expanded));
        desktopToggle.setAttribute('aria-label', expanded ? 'Ciutkan sidebar' : 'Perluas sidebar');
    };

    const setCollapsed = (collapsed, shouldPersist = true) => {
        root.classList.toggle('rs-sidebar-collapsed', collapsed);

        if (shouldPersist) {
            persistState(collapsed);
        }

        updateToggleAccessibility();
        initializeTooltips();
    };

    desktopToggle.addEventListener('click', () => {
        if (!desktopViewport.matches) {
            return;
        }

        setCollapsed(!root.classList.contains('rs-sidebar-collapsed'));
    });

    desktopSidebar.querySelectorAll('[data-sidebar-report-toggle]').forEach((reportToggle) => {
        reportToggle.addEventListener('click', (event) => {
            if (!desktopViewport.matches || !root.classList.contains('rs-sidebar-collapsed')) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            setCollapsed(false);

            const targetSelector = reportToggle.getAttribute('data-bs-target');
            const reportMenu = targetSelector ? document.querySelector(targetSelector) : null;

            if (reportMenu) {
                Collapse.getOrCreateInstance(reportMenu, { toggle: false }).show();
            }
        });
    });

    const handleViewportChange = () => {
        updateToggleAccessibility();
        initializeTooltips();
    };

    if (typeof desktopViewport.addEventListener === 'function') {
        desktopViewport.addEventListener('change', handleViewportChange);
    } else {
        desktopViewport.addListener(handleViewportChange);
    }

    updateToggleAccessibility();
    initializeTooltips();
}

const mobileSidebar = document.querySelector('#rsMobileSidebar');
const mobileToggle = document.querySelector('[data-testid="mobile-sidebar-toggle"]');

if (mobileSidebar && mobileToggle) {
    const setMobileToggleState = (expanded) => {
        mobileToggle.setAttribute('aria-expanded', String(expanded));
        mobileToggle.setAttribute('aria-label', expanded ? 'Tutup menu navigasi' : 'Buka menu navigasi');
    };

    mobileSidebar.addEventListener('show.bs.offcanvas', () => setMobileToggleState(true));
    mobileSidebar.addEventListener('hidden.bs.offcanvas', () => setMobileToggleState(false));
}
