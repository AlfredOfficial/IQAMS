import QRCodeRenderer from 'qrcode';

class QRCode {
    constructor(target, options = {}) {
        this.target = typeof target === 'string' ? document.getElementById(target) : target;
        this.canvas = document.createElement('canvas');
        this.target?.appendChild(this.canvas);

        QRCodeRenderer.toCanvas(this.canvas, options.text || '', {
            width: options.width || 200,
            margin: 1,
            color: {
                dark: options.colorDark || '#000000',
                light: options.colorLight || '#ffffff',
            },
        }).catch(() => {
            if (this.target) this.target.textContent = 'QR code could not be generated.';
        });
    }

    clear() {
        this.target?.replaceChildren();
    }
}

window.QRCode = QRCode;

const loadImage = (source) => new Promise((resolve, reject) => {
    const image = new Image();
    image.onload = () => resolve(image);
    image.onerror = reject;
    image.src = source;
});

const fitText = (context, text, maxWidth, startingSize, weight = 700, minimumSize = 14) => {
    let size = startingSize;
    do {
        context.font = `${weight} ${size}px Arial, sans-serif`;
        size -= 2;
    } while (size > minimumSize && context.measureText(text).width > maxWidth);
};

const drawRoundedRect = (context, x, y, width, height, radius) => {
    context.beginPath();
    context.roundRect(x, y, width, height, radius);
    context.fill();
};

window.downloadIqamsIdCard = async (endpoint) => {
    const response = await fetch(endpoint, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(data.message || 'The ID card could not be downloaded.');
    }

    const card = document.createElement('canvas');
    card.width = 1012;
    card.height = 638;
    const context = card.getContext('2d');

    const gradient = context.createLinearGradient(0, 0, card.width, card.height);
    gradient.addColorStop(0, '#10294b');
    gradient.addColorStop(1, '#2563eb');
    context.fillStyle = gradient;
    drawRoundedRect(context, 0, 0, card.width, card.height, 38);

    context.fillStyle = '#ffffff';
    drawRoundedRect(context, 24, 24, 964, 590, 28);
    context.fillStyle = '#f0fdfa';
    drawRoundedRect(context, 48, 116, 596, 470, 22);

    const [logo, avatar] = await Promise.all([
        loadImage(data.logo_url).catch(() => null),
        loadImage(data.avatar_url).catch(() => null),
    ]);

    if (logo) context.drawImage(logo, 50, 42, 58, 58);
    context.fillStyle = '#10294b';
    context.font = '800 34px Arial, sans-serif';
    context.fillText('IQAMS', 124, 76);
    context.fillStyle = '#64748b';
    context.font = '500 16px Arial, sans-serif';
    context.fillText('QR ATTENDANCE IDENTIFICATION', 124, 98);

    context.save();
    context.beginPath();
    context.arc(176, 252, 94, 0, Math.PI * 2);
    context.clip();
    if (avatar) {
        const side = Math.min(avatar.naturalWidth, avatar.naturalHeight);
        const sourceX = (avatar.naturalWidth - side) / 2;
        const sourceY = (avatar.naturalHeight - side) / 2;
        context.drawImage(avatar, sourceX, sourceY, side, side, 82, 158, 188, 188);
    } else {
        context.fillStyle = '#cbd5e1';
        context.fillRect(82, 158, 188, 188);
    }
    context.restore();
    context.strokeStyle = '#ffffff';
    context.lineWidth = 8;
    context.beginPath();
    context.arc(176, 252, 98, 0, Math.PI * 2);
    context.stroke();

    context.fillStyle = '#10294b';
    fitText(context, data.name, 322, 34, 800, 20);
    context.fillText(data.name, 310, 188);
    context.fillStyle = '#2563eb';
    context.font = '700 20px Arial, sans-serif';
    context.fillText(data.role, 310, 220);

    const details = [
        [data.identifier_label, data.identifier],
        ['Department', data.department],
        ...(data.year_level ? [['Year Level', data.year_level]] : []),
    ];
    details.forEach(([label, value], index) => {
        const y = 282 + index * 82;
        context.fillStyle = '#64748b';
        context.font = '600 15px Arial, sans-serif';
        context.fillText(label.toUpperCase(), 310, y);
        context.fillStyle = '#0f172a';
        fitText(context, String(value), 300, 23, 700);
        context.fillText(String(value), 310, y + 29);
    });

    context.fillStyle = '#ffffff';
    drawRoundedRect(context, 676, 116, 280, 390, 22);
    context.strokeStyle = '#dbeafe';
    context.lineWidth = 3;
    context.strokeRect(677.5, 117.5, 277, 387);

    const qrCanvas = document.createElement('canvas');
    await QRCodeRenderer.toCanvas(qrCanvas, data.qr_code, {
        width: 252,
        margin: 4,
        errorCorrectionLevel: 'H',
        color: { dark: '#0f172a', light: '#ffffff' },
    });
    context.imageSmoothingEnabled = false;
    context.drawImage(qrCanvas, 690, 132, 252, 252);
    context.imageSmoothingEnabled = true;
    context.fillStyle = '#10294b';
    context.font = '700 17px Arial, sans-serif';
    context.textAlign = 'center';
    context.fillText('SCAN FOR ATTENDANCE', 816, 422);
    context.fillStyle = '#64748b';
    context.font = '500 14px Arial, sans-serif';
    context.fillText(data.identifier, 816, 451);
    context.textAlign = 'left';

    context.fillStyle = '#10294b';
    drawRoundedRect(context, 676, 526, 280, 60, 16);
    context.fillStyle = '#ffffff';
    context.font = '600 14px Arial, sans-serif';
    context.textAlign = 'center';
    context.fillText('Official IQAMS Identification Card', 816, 563);

    const blob = await new Promise((resolve, reject) => card.toBlob(
        (result) => result ? resolve(result) : reject(new Error('The ID card image could not be created.')),
        'image/png',
    ));
    const downloadUrl = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = downloadUrl;
    anchor.download = data.filename;
    anchor.click();
    URL.revokeObjectURL(downloadUrl);
    window.dispatchEvent(new CustomEvent('toast', {
        detail: { title: 'Success', message: 'ID card downloaded successfully.' },
    }));
};

window.dispatchEvent(new CustomEvent('qrcode:ready'));
