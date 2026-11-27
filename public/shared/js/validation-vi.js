(() => {
    const validationMessage = (field) => {
        const validity = field.validity;
        if (validity.valueMissing) {
            return field.type === 'checkbox' || field.type === 'radio'
                ? 'Vui lòng chọn mục này.'
                : 'Vui lòng điền vào trường này.';
        }
        if (validity.typeMismatch) {
            if (field.type === 'email') return 'Vui lòng nhập địa chỉ email hợp lệ.';
            if (field.type === 'url') return 'Vui lòng nhập đường dẫn hợp lệ.';
            return 'Vui lòng nhập đúng định dạng yêu cầu.';
        }
        if (validity.tooShort) return `Vui lòng nhập ít nhất ${field.minLength} ký tự.`;
        if (validity.tooLong) return `Vui lòng nhập không quá ${field.maxLength} ký tự.`;
        if (validity.rangeUnderflow) return `Giá trị phải từ ${field.min} trở lên.`;
        if (validity.rangeOverflow) return `Giá trị không được lớn hơn ${field.max}.`;
        if (validity.stepMismatch) return 'Vui lòng chọn một giá trị hợp lệ.';
        if (validity.patternMismatch) return 'Vui lòng nhập đúng định dạng yêu cầu.';
        if (validity.badInput) return 'Vui lòng nhập giá trị hợp lệ.';
        if (validity.customError) return 'Thông tin chưa hợp lệ. Vui lòng kiểm tra lại.';
        return '';
    };

    document.addEventListener('invalid', (event) => {
        const field = event.target;
        if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) return;
        field.setCustomValidity(validationMessage(field));
    }, true);

    const clearMessage = (event) => {
        const field = event.target;
        if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement) {
            field.setCustomValidity('');
        }
    };
    document.addEventListener('input', clearMessage, true);
    document.addEventListener('change', clearMessage, true);
})();
