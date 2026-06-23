import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type InertiaFlash = {
    status?: string | null;
    success?: string | null;
    error?: string | null;
    createdUsername?: string | null;
};

export type FlashToastState = {
    show: boolean;
    title: string;
    description?: string;
};

/** Message de réinitialisation mot de passe (mot de passe affiché dans le bandeau). */
export function isPasswordResetFlashMessage(
    message: string | undefined | null,
): boolean {
    if (!message?.trim()) {
        return false;
    }

    return /nouveau mot de passe\s*:/i.test(message);
}

function readFlash(page: ReturnType<typeof usePage>): InertiaFlash {
    return (page.props.flash as InertiaFlash | undefined) ?? {};
}

export function useFlashToast(options?: {
    /** Évite un doublon avec IdentifiantsCreesDialog / toast local à la création d’utilisateur. */
    skipStatusWhenCreatedUsername?: boolean;
}) {
    const page = usePage();
    const skipStatusWhenCreatedUsername =
        options?.skipStatusWhenCreatedUsername ?? true;

    const successToast = ref<FlashToastState>({ show: false, title: '' });
    const errorToast = ref<FlashToastState>({ show: false, title: '' });

    const inlinePasswordResetMessage = computed(() => {
        const status = readFlash(page).status;
        if (status && isPasswordResetFlashMessage(status)) {
            return status;
        }

        return undefined;
    });

    function syncFromFlash(): void {
        const flash = readFlash(page);

        const error = flash.error?.trim();
        if (error) {
            errorToast.value = { show: true, title: error };
        }

        const success = flash.success?.trim();
        if (success) {
            successToast.value = { show: true, title: success };
            return;
        }

        const status = flash.status?.trim();
        if (!status || isPasswordResetFlashMessage(status)) {
            return;
        }

        if (skipStatusWhenCreatedUsername && flash.createdUsername?.trim()) {
            return;
        }

        successToast.value = { show: true, title: status };
    }

    watch(() => page.props.flash, syncFromFlash, { immediate: true, deep: true });

    return {
        successToast,
        errorToast,
        inlinePasswordResetMessage,
    };
}
