const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode-terminal');
const express = require('express');
const cors = require('cors');

const app = express();
app.use(express.json());
app.use(cors());

// CONFIGURACIÓN ROBUSTA PARA WINDOWS
const client = new Client({
    authStrategy: new LocalAuth(),
    puppeteer: {
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-accelerated-2d-canvas',
            '--no-first-run',
            '--no-zygote',
            '--single-process', // Importante para Windows
            '--disable-gpu'
        ]
    }
});

client.on('qr', (qr) => {
    console.log('📱 ESCANEA EL CÓDIGO QR NUEVAMENTE:');
    qrcode.generate(qr, { small: true });
});

client.on('ready', () => {
    console.log('✅ Cliente de WhatsApp está LISTO.');
});

// AUTO-RECONEXIÓN: Si se desconecta, intenta volver a iniciar
client.on('disconnected', (reason) => {
    console.log('⚠️ Cliente desconectado:', reason);
    client.initialize();
});

client.initialize();

app.post('/enviar', async (req, res) => {
    let { telefono, mensaje } = req.body;

    if (!telefono || !mensaje) {
        return res.status(400).json({ status: 'error', message: 'Faltan datos' });
    }

    try {
        // Validación de formato
        telefono = telefono.replace(/\D/g, '');
        if (telefono.length === 8) { // Ajuste para Bolivia
            telefono = '591' + telefono;
        }

        // VERIFICACIÓN DE ESTADO DEL CLIENTE
        // Si el cliente no está listo, no intentamos enviar para evitar el crash
        if (client.info === undefined) {
             return res.status(503).json({ 
                 status: 'error', 
                 message: 'El sistema de WhatsApp se está reiniciando, intenta en unos segundos.' 
             });
        }

        const numberDetails = await client.getNumberId(telefono);

        if (numberDetails) {
            await client.sendMessage(numberDetails._serialized, mensaje);
            console.log(`Mensaje enviado a ${telefono}`);
            res.json({ status: 'success', message: 'Mensaje enviado' });
        } else {
            console.log(`El número ${telefono} no tiene WhatsApp`);
            res.status(404).json({ status: 'error', message: 'El número no tiene WhatsApp' });
        }

    } catch (error) {
        console.error('❌ Error enviando mensaje:', error.message);
        // No matamos el proceso, solo respondemos error
        res.status(500).json({ status: 'error', message: 'Error interno de conexión con WhatsApp' });
    }
});

const PORT = 3000;
app.listen(PORT, () => {
    console.log(`🚀 Servidor API escuchando en el puerto ${PORT}`);
});