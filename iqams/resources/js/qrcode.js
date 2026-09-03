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

const printCard = (data, qrImage) => `<article class="card"><div class="brand"><img src="${escapePrintText(data.logo_url)}" alt=""> <strong>IQAMS</strong><span>QR ATTENDANCE IDENTIFICATION</span></div><div class="body"><div class="identity"><img src="${escapePrintText(data.avatar_url)}" alt=""><h1>${escapePrintText(data.name)}</h1><h2>${escapePrintText(data.role)}</h2><p><small>${escapePrintText(data.identifier_label)}</small><br>${escapePrintText(data.identifier)}</p>${data.office ? `<p><small>OFFICE / UNIT</small><br>${escapePrintText(data.office)}</p>` : (data.department ? `<p><small>DEPARTMENT</small><br>${escapePrintText(data.department)}</p>` : '')}${data.course ? `<p><small>COURSE</small><br>${escapePrintText(data.course)}</p>` : ''}${data.section ? `<p><small>SECTION</small><br>${escapePrintText(data.section)}</p>` : ''}${data.year_level ? `<p><small>YEAR LEVEL</small><br>${escapePrintText(data.year_level)}</p>` : ''}</div><div class="qr"><img src="${qrImage}" alt="QR code"><strong>SCAN FOR ATTENDANCE</strong><span>${escapePrintText(data.identifier)}</span></div></div><footer>Official IQAMS Identification Card</footer></article>`;

const openPrintWindow = (cards) => {
    const printWindow = window.open('', '_blank');
    if (!printWindow) throw new Error('Please allow pop-ups to print ID cards.');
    printWindow.document.write(`<html><head><title>IQAMS ID Cards</title><style>@page{size:A4;margin:12mm}*{box-sizing:border-box}body{font-family:Arial,sans-serif;margin:0;color:#10294b}.card{width:85.6mm;height:54mm;border:1px solid #cbd5e1;border-radius:4mm;padding:4mm;margin:0 auto 8mm;page-break-after:always;overflow:hidden}.brand{display:flex;align-items:center;gap:2mm;height:8mm}.brand img{width:7mm;height:7mm}.brand strong{font-size:6mm}.brand span{font-size:2.2mm;color:#64748b;margin-left:1mm}.body{display:grid;grid-template-columns:1fr 30mm;gap:3mm;height:34mm;margin-top:2mm}.identity>img{width:18mm;height:18mm;object-fit:cover;border-radius:50%;float:left;margin:0 3mm 2mm 0}.identity h1{font-size:4.2mm;margin:2mm 0 1mm}.identity h2{font-size:2.8mm;color:#2563eb;margin:0 0 3mm}.identity p{font-size:3mm;margin:2mm 0;clear:both}.identity small{font-size:2mm;color:#64748b;font-weight:bold;text-transform:uppercase}.qr{border:1px solid #dbeafe;border-radius:2mm;padding:2mm;text-align:center}.qr img{display:block;width:25mm;height:25mm;margin:0 auto 2mm}.qr strong{display:block;font-size:2.2mm}.qr span{display:block;font-size:2mm;color:#64748b;margin-top:1mm}footer{background:#10294b;color:#fff;text-align:center;font-size:2.1mm;padding:2mm;border-radius:2mm;margin-top:1mm}@media print{.card{margin-bottom:0}}</style></head><body>${cards.join('')}</body></html>`);
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
        printWindow.document.body.innerHTML = `<style>@page{size:A4;margin:12mm}*{box-sizing:border-box}body{font-family:Arial,sans-serif;margin:0;color:#10294b}.card{width:85.6mm;height:54mm;border:1px solid #cbd5e1;border-radius:4mm;padding:4mm;overflow:hidden}.brand{display:flex;align-items:center;gap:2mm;height:8mm}.brand img{width:7mm;height:7mm}.brand strong{font-size:6mm}.brand span{font-size:2.2mm;color:#64748b;margin-left:1mm}.body{display:grid;grid-template-columns:1fr 30mm;gap:3mm;height:34mm;margin-top:2mm}.identity>img{width:18mm;height:18mm;object-fit:cover;border-radius:50%;float:left;margin:0 3mm 2mm 0}.identity h1{font-size:4.2mm;margin:2mm 0 1mm}.identity h2{font-size:2.8mm;color:#2563eb;margin:0 0 3mm}.identity p{font-size:3mm;margin:2mm 0;clear:both}.identity small{font-size:2mm;color:#64748b;font-weight:bold;text-transform:uppercase}.qr{border:1px solid #dbeafe;border-radius:2mm;padding:2mm;text-align:center}.qr img{display:block;width:25mm;height:25mm;margin:0 auto 2mm}.qr strong{display:block;font-size:2.2mm}.qr span{display:block;font-size:2mm;color:#64748b;margin-top:1mm}footer{background:#10294b;color:#fff;text-align:center;font-size:2.1mm;padding:2mm;border-radius:2mm;margin-top:1mm}</style>${printCard(data, qrImage)}`;
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
        printWindow.document.write(`<html><head><title>IQAMS ID Cards</title></head><body><style>@page{size:A4;margin:12mm}*{box-sizing:border-box}body{font-family:Arial,sans-serif;margin:0;color:#10294b}.card{width:85.6mm;height:54mm;border:1px solid #cbd5e1;border-radius:4mm;padding:4mm;margin:0 auto 8mm;page-break-after:always;overflow:hidden}.brand{display:flex;align-items:center;gap:2mm;height:8mm}.brand img{width:7mm;height:7mm}.brand strong{font-size:6mm}.brand span{font-size:2.2mm;color:#64748b;margin-left:1mm}.body{display:grid;grid-template-columns:1fr 30mm;gap:3mm;height:34mm;margin-top:2mm}.identity>img{width:18mm;height:18mm;object-fit:cover;border-radius:50%;float:left;margin:0 3mm 2mm 0}.identity h1{font-size:4.2mm;margin:2mm 0 1mm}.identity h2{font-size:2.8mm;color:#2563eb;margin:0 0 3mm}.identity p{font-size:3mm;margin:2mm 0;clear:both}.identity small{font-size:2mm;color:#64748b;font-weight:bold;text-transform:uppercase}.qr{border:1px solid #dbeafe;border-radius:2mm;padding:2mm;text-align:center}.qr img{display:block;width:25mm;height:25mm;margin:0 auto 2mm}.qr strong{display:block;font-size:2.2mm}.qr span{display:block;font-size:2mm;color:#64748b;margin-top:1mm}footer{background:#10294b;color:#fff;text-align:center;font-size:2.1mm;padding:2mm;border-radius:2mm;margin-top:1mm}@media print{.card{margin-bottom:0}}</style>${cards.join('')}</body></html>`);
        printWindow.document.close();
        printWindow.focus();
        printWindow.onload = () => { printWindow.print(); printWindow.close(); };
    } catch (error) {
        printWindow.close();
        throw error;
    }
};

window.dispatchEvent(new CustomEvent('qrcode:ready'));
