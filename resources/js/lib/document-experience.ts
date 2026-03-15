import type { ClassValue } from 'clsx';
import { cn } from '@/lib/utils';
import type {
    DocumentExperienceGuardrails,
    DocumentStatus,
    MatterStatus,
} from '@/types';

type DocumentTypographyToken = keyof DocumentExperienceGuardrails['typography'];
type DocumentSurfaceDelay = 1 | 2 | null;
type UploadStatus = 'uploading' | 'completed' | 'failed';
type DocumentStatusLike = DocumentStatus | UploadStatus;

export function documentRootClass(
    documentExperience: DocumentExperienceGuardrails,
    ...inputs: ClassValue[]
): string {
    return cn(documentExperience.wrappers.root, ...inputs);
}

export function documentHeroClass(
    documentExperience: DocumentExperienceGuardrails,
    ...inputs: ClassValue[]
): string {
    return cn(
        documentExperience.wrappers.hero,
        documentExperience.motion.reveal,
        ...inputs,
    );
}

export function documentSurfaceClass(
    documentExperience: DocumentExperienceGuardrails,
    options: {
        reveal?: boolean;
        delay?: DocumentSurfaceDelay;
    } = {},
    ...inputs: ClassValue[]
): string {
    const reveal = options.reveal ?? true;
    const delay = options.delay ?? null;

    return cn(
        documentExperience.wrappers.surface,
        reveal && documentExperience.motion.reveal,
        delay === 1 && documentExperience.motion.delay1,
        delay === 2 && documentExperience.motion.delay2,
        ...inputs,
    );
}

export function documentTypographyClass(
    documentExperience: DocumentExperienceGuardrails,
    token: DocumentTypographyToken,
    ...inputs: ClassValue[]
): string {
    return cn(documentExperience.typography[token], ...inputs);
}

export function documentStatusToneClass(status: DocumentStatusLike): string {
    if (status === 'approved' || status === 'completed' || status === 'reviewed') {
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300';
    }

    if (status === 'ready_for_review') {
        return 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300';
    }

    if (
        status === 'failed' ||
        status === 'scan_failed' ||
        status === 'extraction_failed' ||
        status === 'classification_failed'
    ) {
        return 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300';
    }

    if (
        status === 'uploading' ||
        status === 'uploaded' ||
        status === 'scanning' ||
        status === 'scan_passed' ||
        status === 'extracting' ||
        status === 'classifying'
    ) {
        return 'bg-sky-100 text-sky-700 dark:bg-sky-900/50 dark:text-sky-300';
    }

    return 'bg-[var(--doc-seal)]/15 text-[var(--doc-seal)]';
}

export function matterStatusToneClass(status: MatterStatus): string {
    if (status === 'open') {
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300';
    }

    if (status === 'on_hold') {
        return 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300';
    }

    return 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
}
