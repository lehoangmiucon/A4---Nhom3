(() => {
    "use strict";

    const input = document.getElementById("evidence");
    const preview = document.getElementById("repair-image-preview");
    if (!input || !preview) return;

    let selected = [];
    const sizeFormat = new Intl.NumberFormat("vi-VN", {
        maximumFractionDigits: 1,
    });

    function syncInput() {
        const transfer = new DataTransfer();
        selected.forEach((item) => transfer.items.add(item.file));
        input.files = transfer.files;
    }

    function render() {
        preview.replaceChildren();
        preview.hidden = selected.length === 0;

        selected.forEach((item, index) => {
            const card = document.createElement("div");
            card.className = "repair-image-item";

            const link = document.createElement("a");
            link.className = "repair-image-open js-image-preview";
            link.href = item.url;
            link.title = item.file.name;

            const image = document.createElement("img");
            image.src = item.url;
            image.alt = `Ảnh xem trước: ${item.file.name}`;
            link.append(image);

            const caption = document.createElement("div");
            caption.className = "repair-image-caption";
            const name = document.createElement("strong");
            name.textContent = item.file.name;
            name.title = item.file.name;
            const size = document.createElement("small");
            size.textContent = `${sizeFormat.format(item.file.size / (1024 * 1024))} MB`;
            caption.append(name, size);

            const remove = document.createElement("button");
            remove.className = "repair-image-remove";
            remove.type = "button";
            remove.textContent = "×";
            remove.setAttribute("aria-label", `Bỏ ảnh ${item.file.name}`);
            remove.addEventListener("click", () => {
                URL.revokeObjectURL(item.url);
                selected.splice(index, 1);
                syncInput();
                render();
            });

            card.append(link, caption, remove);
            preview.append(card);
        });
    }

    input.addEventListener("change", () => {
        const files = Array.from(input.files || []);
        const errors = [];
        if (files.length > 5) errors.push("Chỉ được chọn tối đa 5 ảnh.");
        if (files.some((file) => !file.type.startsWith("image/")))
            errors.push("Tệp đã chọn phải là hình ảnh.");
        if (
            files.some((file) => file.size === 0 || file.size > 5 * 1024 * 1024)
        )
            errors.push("Mỗi ảnh phải nhỏ hơn hoặc bằng 5 MB.");

        if (errors.length) {
            selected.forEach((item) => URL.revokeObjectURL(item.url));
            selected = [];
            syncInput();
            render();
            window.alert(errors.join("\n"));
            return;
        }

        selected.forEach((item) => URL.revokeObjectURL(item.url));
        selected = files.map((file) => ({
            file,
            url: URL.createObjectURL(file),
        }));
        syncInput();
        render();
    });

    input.form?.addEventListener("reset", () => {
        selected.forEach((item) => URL.revokeObjectURL(item.url));
        selected = [];
        render();
    });

    window.addEventListener("pagehide", () => {
        selected.forEach((item) => URL.revokeObjectURL(item.url));
    });
})();
