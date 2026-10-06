import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync, unlinkSync, chmodSync, mkdtempSync, statSync, copyFileSync } from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');

// Configuración del servidor Hostinger
const SSH_USER = 'u483747408';
const SSH_HOST = '185.211.7.113';
const SSH_PORT = '65002';
const REMOTE_PATH = '/home/u483747408/domains/gold-trout-815009.hostingersite.com';

console.log('================================================================');
console.log('📥  DESCARGA DE BASE DE DATOS DE PRODUCCIÓN (PULL DATABASE)');
console.log('================================================================');
console.log('Este comando descargará la base de datos SQLite de producción');
console.log('y actualizará tu entorno local (database/database.sqlite).');
console.log('================================================================\n');

// 1. Helper de contraseña SSH
function getSshPassword() {
    if (process.env.HOSTINGER_SSH_PASSWORD) {
        return process.env.HOSTINGER_SSH_PASSWORD.trim().replace(/^['"]|['"]$/g, '');
    }
    const envPath = path.join(rootDir, '.env');
    if (existsSync(envPath)) {
        const content = readFileSync(envPath, 'utf8');
        const match = content.match(/^HOSTINGER_SSH_PASSWORD=(.*)$/m);
        if (match) {
            return match[1].trim().replace(/^['"]|['"]$/g, '');
        }
    }
    return null;
}

const sshPassword = getSshPassword();
let askpassFile = null;
let askpassDir = null;
const envVars = { ...process.env };

if (sshPassword) {
    askpassDir = mkdtempSync(path.join(os.tmpdir(), 'deploy-askpass-'));
    askpassFile = path.join(askpassDir, 'askpass.sh');

    writeFileSync(askpassFile, `#!/bin/sh\ncat << 'EOF_PASS'\n${sshPassword}\nEOF_PASS\n`);
    chmodSync(askpassFile, 0o700);

    envVars.SSH_ASKPASS = askpassFile;
    envVars.SSH_ASKPASS_REQUIRE = 'force';
    envVars.DISPLAY = 'dummy:0';
}

function cleanup() {
    if (askpassFile && existsSync(askpassFile)) {
        try { unlinkSync(askpassFile); } catch {}
    }
    if (askpassDir && existsSync(askpassDir)) {
        try {
            import('node:fs').then(fs => (fs.rmSync ? fs.rmSync(askpassDir, { recursive: true, force: true }) : fs.rmdirSync(askpassDir)));
        } catch {}
    }
}
process.on('exit', cleanup);
process.on('SIGINT', () => { cleanup(); process.exit(1); });

async function main() {
    const localDbPath = path.join(rootDir, 'database', 'database.sqlite');
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    const timestamp = `${now.getFullYear()}${pad(now.getMonth() + 1)}${pad(now.getDate())}_${pad(now.getHours())}${pad(now.getMinutes())}${pad(now.getSeconds())}`;
    const localBackupPath = path.join(rootDir, 'database', `database.sqlite.bak-${timestamp}`);

    // Paso 1: Respaldar base de datos local actual si existe y tiene contenido
    if (existsSync(localDbPath)) {
        const localStats = statSync(localDbPath);
        if (localStats.size > 0) {
            console.log(`🛡️  Creando respaldo local previo: database/database.sqlite.bak-${timestamp} (${(localStats.size / 1024 / 1024).toFixed(2)} MB)...`);
            copyFileSync(localDbPath, localBackupPath);
        }
    }

    // Paso 2: Descargar base de datos remota con rsync
    console.log('\n📡 Descargando base de datos SQLite desde producción...');
    const sshCommandStr = `ssh -p ${SSH_PORT} -o StrictHostKeyChecking=accept-new -o UserKnownHostsFile=/dev/null -o LogLevel=ERROR`;
    const tempDownloadPath = path.join(rootDir, 'database', `database.sqlite.temp-${timestamp}`);

    const rsyncArgs = [
        '-avz',
        '--progress',
        '-e', sshCommandStr,
        `${SSH_USER}@${SSH_HOST}:${REMOTE_PATH}/database/database.sqlite`,
        tempDownloadPath
    ];

    const rsyncResult = spawnSync('rsync', rsyncArgs, {
        env: envVars,
        stdio: 'inherit'
    });

    if (rsyncResult.status !== 0) {
        if (existsSync(tempDownloadPath)) {
            try { unlinkSync(tempDownloadPath); } catch {}
        }
        console.error(`\n❌ Error al transferir la base de datos remota (código ${rsyncResult.status})`);
        process.exit(1);
    }

    // Verificar que el archivo descargado es válido y tiene tamaño > 0
    if (!existsSync(tempDownloadPath)) {
        console.error('\n❌ Error: El archivo temporal descargado no existe.');
        process.exit(1);
    }

    const downloadedStats = statSync(tempDownloadPath);
    if (downloadedStats.size === 0) {
        try { unlinkSync(tempDownloadPath); } catch {}
        console.error('\n❌ Error: La base de datos descargada está vacía (0 bytes). Se canceló el reemplazo.');
        process.exit(1);
    }

    // Reemplazar de forma atómica
    copyFileSync(tempDownloadPath, localDbPath);
    try { unlinkSync(tempDownloadPath); } catch {}

    // Eliminar posibles archivos de journal/WAL locales que puedan quedar de versiones anteriores
    const walPath = `${localDbPath}-wal`;
    const shmPath = `${localDbPath}-shm`;
    if (existsSync(walPath)) { try { unlinkSync(walPath); } catch {} }
    if (existsSync(shmPath)) { try { unlinkSync(shmPath); } catch {} }

    console.log(`\n✅ Base de datos local actualizada: database/database.sqlite (${(downloadedStats.size / 1024 / 1024).toFixed(2)} MB)`);

    // Paso 3: Limpiar cachés locales de Laravel
    console.log('\n⚙️  Limpiando cachés locales de Laravel...');
    spawnSync('php', ['artisan', 'optimize:clear'], {
        cwd: rootDir,
        stdio: 'inherit'
    });

    console.log('\n================================================================');
    console.log('🎉 ¡Base de datos de producción sincronizada con éxito en local!');
    if (existsSync(localBackupPath)) {
        console.log(`💾 Respaldo local previo guardado en: database/database.sqlite.bak-${timestamp}`);
    }
    console.log('================================================================\n');
}

main();
