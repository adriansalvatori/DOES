/**
 * Kudos Design Ops - Client QR Card Generator & Downloader
 * Renders a high-resolution 9:16 JPG card with Kudos logo, client name, QR code,
 * decorative sunshine accents, scan badge, and footer ribbon decor.
 */
export async function downloadClientQrCard({ clientName, portalUrl, qrSvgDataUri, logoUrl = '/images/logo-kudos.svg', footerDecorUrl = '/images/footer-decor.svg' }) {
    const width = 800;
    const height = 1422; // 9:16 aspect ratio

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    // Helper to load image
    const loadImage = (src) => new Promise((resolve, reject) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => resolve(img);
        img.onerror = (e) => reject(e);
        img.src = src;
    });

    try {
        // Load external SVG assets
        const [logoImg, footerImg, qrImg] = await Promise.all([
            loadImage(logoUrl),
            loadImage(footerDecorUrl),
            loadImage(qrSvgDataUri)
        ]);

        // 1. Clean White Background
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, width, height);

        // 2. Kudos Logo (Centered at top)
        const logoWidth = 280;
        const logoHeight = (logoWidth / 146.25) * 83.99; // 160.8px
        const logoX = (width - logoWidth) / 2;
        const logoY = 80;
        ctx.drawImage(logoImg, logoX, logoY, logoWidth, logoHeight);

        // 3. Client Name (Uppercase, bold)
        const cleanName = (clientName || 'CLIENTE').toUpperCase();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'alphabetic';

        // Auto-size font if name is very long
        let fontSize = 42;
        ctx.font = `800 ${fontSize}px "DM Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif`;
        let textMetrics = ctx.measureText(cleanName);
        while (textMetrics.width > (width - 140) && fontSize > 24) {
            fontSize -= 2;
            ctx.font = `800 ${fontSize}px "DM Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif`;
            textMetrics = ctx.measureText(cleanName);
        }

        const nameY = 345;
        ctx.fillStyle = '#111827';
        ctx.fillText(cleanName, width / 2, nameY);

        // 4. Yellow Underline Accent under Client Name
        const lineWidth = Math.min(textMetrics.width * 0.9, width - 180);
        const lineX = (width - lineWidth) / 2;
        const lineY = nameY + 16;
        ctx.beginPath();
        ctx.strokeStyle = '#eda621';
        ctx.lineWidth = 6;
        ctx.lineCap = 'round';
        ctx.moveTo(lineX, lineY);
        ctx.lineTo(lineX + lineWidth, lineY);
        ctx.stroke();

        // 5. Subtitle
        ctx.font = '500 23px "DM Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        ctx.fillStyle = '#272626';
        ctx.fillText('Consulta el estado de tus', width / 2, 415);
        ctx.fillText('pedidos en línea.', width / 2, 448);

        // 6. QR Code Outer Container with Yellow Border & Soft Glow
        const boxSize = 400;
        const boxX = (width - boxSize) / 2;
        const boxY = 490;
        const boxRadius = 32;

        ctx.save();
        // Golden Ambient Glow
        ctx.shadowColor = 'rgba(237, 166, 33, 0.40)';
        ctx.shadowBlur = 35;
        ctx.shadowOffsetX = 0;
        ctx.shadowOffsetY = 0;

        ctx.beginPath();
        ctx.roundRect(boxX, boxY, boxSize, boxSize, boxRadius);
        ctx.fillStyle = '#ffffff';
        ctx.fill();
        ctx.lineWidth = 7;
        ctx.strokeStyle = '#eda621';
        ctx.stroke();
        ctx.restore();

        // 7. Radiating Sunshine Rays (Left & Right)
        const drawRay = (cx, cy, angleDeg, len = 28, strokeW = 6) => {
            ctx.save();
            ctx.translate(cx, cy);
            ctx.rotate((angleDeg * Math.PI) / 180);
            ctx.beginPath();
            ctx.strokeStyle = '#eda621';
            ctx.lineWidth = strokeW;
            ctx.lineCap = 'round';
            ctx.moveTo(-len / 2, 0);
            ctx.lineTo(len / 2, 0);
            ctx.stroke();
            ctx.restore();
        };

        const midBoxY = boxY + boxSize / 2;
        // Left rays
        drawRay(boxX - 35, midBoxY - 50, -25);
        drawRay(boxX - 42, midBoxY, 0);
        drawRay(boxX - 35, midBoxY + 50, 25);

        // Right rays
        drawRay(boxX + boxSize + 35, midBoxY - 50, 25);
        drawRay(boxX + boxSize + 42, midBoxY, 0);
        drawRay(boxX + boxSize + 35, midBoxY + 50, -25);

        // 8. QR Code Image (centered inside container)
        const qrSize = 340;
        const qrX = boxX + (boxSize - qrSize) / 2;
        const qrY = boxY + (boxSize - qrSize) / 2;
        ctx.drawImage(qrImg, qrX, qrY, qrSize, qrSize);

        // 9. Pill Badge: "Escanea para acceder a tus órdenes"
        const pillWidth = 510;
        const pillHeight = 76;
        const pillX = (width - pillWidth) / 2;
        const pillY = 945;
        const pillRadius = 38;

        // Pill shape fill
        ctx.save();
        ctx.beginPath();
        ctx.roundRect(pillX, pillY, pillWidth, pillHeight, pillRadius);
        ctx.fillStyle = '#eda621';
        ctx.fill();

        // Smartphone icon inside badge
        const phoneX = pillX + 55;
        const phoneY = pillY + 19;
        const phoneW = 24;
        const phoneH = 38;
        const phoneR = 5;

        ctx.strokeStyle = '#18181b';
        ctx.lineWidth = 3;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.fillStyle = '#ffffff';

        // Outer phone
        ctx.beginPath();
        ctx.roundRect(phoneX, phoneY, phoneW, phoneH, phoneR);
        ctx.stroke();

        // Phone screen notch / speaker line
        ctx.beginPath();
        ctx.moveTo(phoneX + 8, phoneY + 5);
        ctx.lineTo(phoneX + phoneW - 8, phoneY + 5);
        ctx.stroke();

        // Phone home button / bar
        ctx.beginPath();
        ctx.moveTo(phoneX + 9, phoneY + phoneH - 5);
        ctx.lineTo(phoneX + phoneW - 9, phoneY + phoneH - 5);
        ctx.stroke();

        // Badge Text
        ctx.textAlign = 'center';
        ctx.fillStyle = '#18181b';
        ctx.font = '800 20px "DM Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        const textCenterX = pillX + (pillWidth / 2) + 20;
        ctx.fillText('Escanea para acceder', textCenterX, pillY + 34);
        ctx.fillText('a tus órdenes', textCenterX, pillY + 58);
        ctx.restore();

        // 10. Footer Ribbon Decor (spans edge-to-edge at bottom)
        const footerHeight = (width / 858.8) * 134.06 * 1.55; // ~190px tall
        const footerY = height - footerHeight;
        ctx.drawImage(footerImg, 0, footerY, width, footerHeight);

        // 11. Trigger Download as high-quality JPG
        canvas.toBlob((blob) => {
            if (!blob) return;
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            const sanitizedName = cleanName.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
            a.download = `kudos-card-${sanitizedName || 'cliente'}.jpg`;
            a.href = url;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            setTimeout(() => URL.revokeObjectURL(url), 1000);
        }, 'image/jpeg', 0.95);

    } catch (err) {
        console.error('Error generando tarjeta QR JPG:', err);
        alert('No se pudo generar la tarjeta QR en JPG. Intenta de nuevo.');
    }
}

// Make it globally accessible on window
if (typeof window !== 'undefined') {
    window.downloadClientQrCard = downloadClientQrCard;
}
