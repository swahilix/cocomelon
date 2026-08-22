import { nextTick, onBeforeUnmount, onMounted } from 'vue';

export function useAdminSelect2(rootSelector) {
    let observer = null;
    let raf = null;

    const enhance = async () => {
        await nextTick();

        const $ = window.jQuery;
        if (! $?.fn?.select2) return;

        const root = document.querySelector(rootSelector);
        if (! root) return;

        root.querySelectorAll('select:not([data-native-select])').forEach((select) => {
            const $select = $(select);

            if ($select.hasClass('select2-hidden-accessible')) {
                $select.trigger('change.select2');
                return;
            }

            const firstOption = select.options?.[0];
            const placeholder = select.dataset.placeholder
                || (firstOption && firstOption.value === '' ? firstOption.text : undefined);

            $select.select2({
                width: '100%',
                placeholder,
                minimumResultsForSearch: 0,
                dropdownAutoWidth: false,
            });

            // Select2 uses jQuery events. Dispatch a native change as well so
            // Vue v-model always receives the selected value.
            $select.on('select2:select.adminSelect2 select2:unselect.adminSelect2 select2:clear.adminSelect2', () => {
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    };

    const scheduleEnhance = () => {
        if (raf) cancelAnimationFrame(raf);
        raf = requestAnimationFrame(() => {
            raf = null;
            enhance();
        });
    };

    onMounted(async () => {
        await enhance();
        const root = document.querySelector(rootSelector);
        if (! root) return;

        observer = new MutationObserver((mutations) => {
            const $ = window.jQuery;
            if ($?.fn?.select2) {
                mutations.forEach((mutation) => {
                    const select = mutation.target instanceof HTMLSelectElement
                        ? mutation.target
                        : mutation.target?.closest?.('select');
                    if (select && $(select).hasClass('select2-hidden-accessible')) {
                        $(select).trigger('change.select2');
                    }
                });
            }
            scheduleEnhance();
        });

        observer.observe(root, { childList: true, subtree: true });
    });

    onBeforeUnmount(() => {
        if (raf) cancelAnimationFrame(raf);
        observer?.disconnect();

        const $ = window.jQuery;
        if (! $?.fn?.select2) return;

        document.querySelectorAll(`${rootSelector} select.select2-hidden-accessible`).forEach((select) => {
            const $select = $(select);
            $select.off('.adminSelect2');
            $select.select2('destroy');
        });
    });
}
