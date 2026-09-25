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

    const [logo, avatar, fallbackAvatar] = await Promise.all([
        loadImage(data.logo_url).catch(() => null),
        loadImage(data.avatar_url).catch(() => null),
        loadImage(data.fallback_avatar_url).catch(() => null),
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
    const profileImage = avatar || fallbackAvatar;
    if (profileImage) {
        const side = Math.min(profileImage.naturalWidth, profileImage.naturalHeight);
        const sourceX = (profileImage.naturalWidth - side) / 2;
        const sourceY = (profileImage.naturalHeight - side) / 2;
        context.drawImage(profileImage, sourceX, sourceY, side, side, 82, 158, 188, 188);
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
        ...(data.office ? [['Office / Unit', data.office]] : (data.department ? [['Department', data.department]] : [])),
        ...(data.course ? [['Course', data.course]] : []),
        ...(data.section ? [['Section', data.section]] : []),
        ...(data.year_level ? [['Year Level', data.year_level]] : []),
    ];
    details.forEach(([label, value], index) => {
        const y = 265 + index * 64;
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

const escapePrintText = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character]));

const fetchIdCard = async (endpoint) => {
    const response = await fetch(endpoint, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message || 'The ID card could not be loaded.');
    return data;
};

const qrDataUrl = async (value) => {
    const canvas = document.createElement('canvas');
    await QRCodeRenderer.toCanvas(canvas, value, { width: 260, margin: 4, errorCorrectionLevel: 'H', color: { dark: '#0f172a', light: '#ffffff' } });
    return canvas.toDataURL('image/png');
};

const printStyles = `<style>
@page{size:A4 portrait;margin:12mm}*{box-sizing:border-box}html,body{margin:0;padding:0}body{font-family:Arial,sans-serif;color:#10294b;background:#fff}.card{width:85.6mm;height:54mm;padding:3.5mm;border:1px solid #cbd5e1;border-radius:4mm;display:grid;grid-template-rows:8mm 1fr 5mm;gap:1.5mm;overflow:hidden;page-break-after:always;break-inside:avoid}.brand{min-width:0;display:flex;align-items:center;gap:1.5mm}.brand img{width:6.5mm;height:6.5mm;object-fit:contain;flex:none}.brand strong{font-size:5.5mm;line-height:1}.brand span{min-width:0;font-size:2mm;color:#64748b;white-space:nowrap}.body{min-height:0;display:grid;grid-template-columns:minmax(0,1fr) 28mm;gap:2.5mm;align-items:stretch}.identity{min-width:0;min-height:0;display:grid;grid-template-columns:17mm minmax(0,1fr);grid-template-rows:auto auto 1fr;column-gap:2.5mm;overflow:hidden}.identity>img{grid-row:1/3;width:17mm;height:17mm;object-fit:contain;border-radius:50%;background:#e2e8f0;align-self:start}.identity h1{min-width:0;margin:0;font-size:3.5mm;line-height:1.1;overflow-wrap:anywhere}.identity h2{min-width:0;margin:1mm 0 0;font-size:2.3mm;line-height:1.15;color:#2563eb;overflow-wrap:anywhere}.identity-details{grid-column:1/-1;min-height:0;margin-top:1.5mm;display:grid;grid-template-columns:1fr 1fr;column-gap:2.5mm;align-content:start;overflow:hidden}.identity p{min-width:0;margin:0 0 1mm;font-size:2.25mm;line-height:1.12;overflow-wrap:anywhere}.identity small{display:block;margin-bottom:.35mm;font-size:1.65mm;line-height:1;color:#64748b;font-weight:bold;text-transform:uppercase}.qr{min-width:0;min-height:0;border:1px solid #dbeafe;border-radius:2mm;padding:1.5mm;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;overflow:hidden}.qr img{display:block;width:22mm;height:22mm;object-fit:contain;flex:none;margin:0 auto 1.2mm}.qr strong{font-size:1.8mm;line-height:1.1}.qr span{max-width:100%;font-size:1.7mm;color:#64748b;overflow-wrap:anywhere}.card footer{min-width:0;background:#10294b;color:#fff;text-align:center;font-size:1.7mm;line-height:5mm;border-radius:1.5mm;white-space:nowrap;overflow:hidden}@media screen{body{padding:12px}.card{margin:0 auto 12px;box-shadow:0 8px 24px rgba(15,23,42,.12)}}@media print{body{padding:0}.card{margin:0;box-shadow:none}}
</style>`;

const printCard = (data, qrImage) => `<article class="card"><div class="brand"><img src="${escapePrintText(data.logo_url)}" alt=""><strong>IQAMS</strong><span>QR ATTENDANCE IDENTIFICATION</span></div><div class="body"><div class="identity"><img src="${escapePrintText(data.avatar_url)}" onerror="this.onerror=null;this.src='${escapePrintText(data.fallback_avatar_url || '')}'" alt=""><h1>${escapePrintText(data.name)}</h1><h2>${escapePrintText(data.role)}</h2><div class="identity-details"><p><small>${escapePrintText(data.identifier_label)}</small>${escapePrintText(data.identifier)}</p>${data.office ? `<p><small>OFFICE / UNIT</small>${escapePrintText(data.office)}</p>` : (data.department ? `<p><small>DEPARTMENT</small>${escapePrintText(data.department)}</p>` : '')}${data.course ? `<p><small>COURSE</small>${escapePrintText(data.course)}</p>` : ''}${data.section ? `<p><small>SECTION</small>${escapePrintText(data.section)}</p>` : ''}${data.year_level ? `<p><small>YEAR LEVEL</small>${escapePrintText(data.year_level)}</p>` : ''}</div></div><div class="qr"><img src="${qrImage}" alt="QR code"><strong>SCAN FOR ATTENDANCE</strong><span>${escapePrintText(data.identifier)}</span></div></div><footer>Official IQAMS Identification Card</footer></article>`;

const openPrintWindow = (cards) => {
    const printWindow = window.open('', '_blank');
    if (!printWindow) throw new Error('Please allow pop-ups to print ID cards.');
    printWindow.document.write(`<html><head><title>IQAMS ID Cards</title>${printStyles}</head><body>${cards.join('')}</body></html>`);
    printWindow.document.close();
    printWindow.focus();
    printWindow.onload = () => { printWindow.print(); printWindow.close(); };
};

window.printIqamsIdCard = async (endpoint) => {
    const printWindow = window.open('', '_blank');
    if (!printWindow) throw new Error('Please allow pop-ups to print ID cards.');
    try {
        const data = await fetchIdCard(endpoint);
        const qrImage = await qrDataUrl(data.qr_code);
        printWindow.document.write(`<html><head><title>IQAMS ID Card</title></head><body></body></html>`);
        printWindow.document.close();
        printWindow.document.body.innerHTML = `${printStyles}${printCard(data, qrImage)}`;
        printWindow.onload = () => { printWindow.print(); printWindow.close(); };
    } catch (error) {
        printWindow.close();
        throw error;
    }
};

window.printIqamsIdCards = async (endpoints) => {
    if (!endpoints.length) throw new Error('Select at least one user.');
    const printWindow = window.open('', '_blank');
    if (!printWindow) throw new Error('Please allow pop-ups to print ID cards.');
    try {
        const data = await Promise.all(endpoints.map(fetchIdCard));
        const cards = await Promise.all(data.map(async (card) => printCard(card, await qrDataUrl(card.qr_code))));
        printWindow.document.write(`<html><head><title>IQAMS ID Cards</title>${printStyles}</head><body>${cards.join('')}</body></html>`);
        printWindow.document.close();
        printWindow.focus();
        printWindow.onload = () => { printWindow.print(); printWindow.close(); };
    } catch (error) {
        printWindow.close();
        throw error;
    }
};

window.dispatchEvent(new CustomEvent('qrcode:ready'));
