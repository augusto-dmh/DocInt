import type { ComputedRef, Ref } from 'vue';
import { computed, ref } from 'vue';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
};

const appearance = ref<Appearance>('light');

export function updateTheme(): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.classList.remove('dark');
}

export function initializeTheme(): void {
    if (typeof localStorage !== 'undefined') {
        localStorage.setItem('appearance', 'light');
    }

    if (typeof document !== 'undefined') {
        document.cookie =
            'appearance=light;path=/;max-age=31536000;SameSite=Lax';
    }

    updateTheme();
}

export function useAppearance(): UseAppearanceReturn {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    function updateAppearance(value: Appearance): void {
        appearance.value = 'light';
        initializeTheme();
    }

    const resolvedAppearance = computed<ResolvedAppearance>(() => 'light');

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
    };
}
