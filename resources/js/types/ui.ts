export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export type AppShellVariant = 'header' | 'sidebar';

export type DocumentExperienceGuardrails = {
    themeKey: string;
    wrappers: {
        root: string;
        hero: string;
        surface: string;
    };
    typography: {
        title: string;
        subtle: string;
        seal: string;
    };
    motion: {
        reveal: string;
        delay1: string;
        delay2: string;
    };
};
