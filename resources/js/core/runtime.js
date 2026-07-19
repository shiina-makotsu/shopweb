const reportModuleError = (name, error) => {
    console.error(`[ShopWeb:${name}]`, error);
    window.dispatchEvent(new CustomEvent('shopweb:module-error', {
        detail: { name, message: error instanceof Error ? error.message : String(error) },
    }));
};

export const runIsolatedModule = (name, setup) => {
    try {
        const result = setup();

        if (result instanceof Promise) {
            return result.catch((error) => {
                reportModuleError(name, error);
                return null;
            });
        }

        return result;
    } catch (error) {
        reportModuleError(name, error);

        return null;
    }
};
