import { runIsolatedModule } from './core/runtime';

const modules = [
    ['mobile-menu', async () => (await import('./storefront/ui')).setupMobileMenu()],
    ['floating-cart', async () => (await import('./storefront/ui')).setupFloatingCart()],
    ['registration-onboarding', async () => (await import('./storefront/ui')).setupRegistrationOnboarding()],
    ['font-awesome-picker', async () => (await import('./storefront/font-awesome-picker')).setupFontAwesomePicker()],
    ['guide-pet', async () => (await import('./storefront/guide-pet')).setupGuidePet()],
    ['cart-actions', async () => (await import('./storefront/cart-actions')).setupCartActions()],
    ['navigation-prefetch', async () => (await import('./storefront/navigation-prefetch')).setupStorefrontNavigationPrefetch()],
];

modules.forEach(([name, setup]) => runIsolatedModule(name, setup));
