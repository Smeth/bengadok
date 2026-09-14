import { reactive } from 'vue';

type GlobalToastState = {
    show: boolean;
    title: string;
    variant: 'success' | 'error';
};

export const globalToast = reactive<GlobalToastState>({
    show: false,
    title: '',
    variant: 'error',
});

export function showGlobalErrorToast(message: string): void {
    globalToast.title = message;
    globalToast.variant = 'error';
    globalToast.show = true;
}

export function showGlobalSuccessToast(message: string): void {
    globalToast.title = message;
    globalToast.variant = 'success';
    globalToast.show = true;
}
