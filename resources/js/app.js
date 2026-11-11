import phone from './phone';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('phone', phone);
});
