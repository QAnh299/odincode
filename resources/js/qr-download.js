// Tải ảnh QR chuyển khoản của hóa đơn thành tệp PNG (Salesperson gửi khách qua Zalo / email).
// VietQR trả ảnh SVG; máy chủ chuyển tiếp ảnh qua cùng tên miền (InvoiceQrController) nên trình duyệt
// vẽ được lên canvas rồi xuất PNG. Nút: <button data-qr-download="url" data-qr-filename="QR-INV001.png">
const SCALE = 2;

function loadImage(src) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = reject;
        img.src = src;
    });
}

async function downloadQr(button) {
    const res = await fetch(button.dataset.qrDownload, { credentials: "same-origin" });
    if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
    }

    const blob = await res.blob();
    const svgUrl = URL.createObjectURL(blob);

    try {
        const img = await loadImage(svgUrl);
        const canvas = document.createElement("canvas");
        canvas.width = (img.naturalWidth || 540) * SCALE;
        canvas.height = (img.naturalHeight || 640) * SCALE;
        const ctx = canvas.getContext("2d");
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

        const png = await new Promise((resolve) => canvas.toBlob(resolve, "image/png"));
        const link = document.createElement("a");
        link.href = URL.createObjectURL(png);
        link.download = button.dataset.qrFilename || "QR.png";
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(link.href), 1000);
    } finally {
        URL.revokeObjectURL(svgUrl);
    }
}

document.addEventListener("click", async (event) => {
    const button = event.target.closest("[data-qr-download]");
    if (!button || button.disabled) {
        return;
    }

    event.preventDefault();
    button.disabled = true;
    try {
        await downloadQr(button);
    } catch (error) {
        console.error(error);
        window.alert(button.dataset.qrError || "Không tải được ảnh QR.");
    } finally {
        button.disabled = false;
    }
});
