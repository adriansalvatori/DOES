import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync, unlinkSync, chmodSync, mkdtempSync, statSync } from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import { fileURLToPath } from 'node:url';
import readline from 'node:readline';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');

const isForce = process.argv.includes('--confirm') || process.argv.includes('--force');

// Configuración del servidor Hostinger
const SSH_USER = 'u483747408';
const SSH_HOST = '185.211.7.113';
const SSH_PORT = '65002';
const REMOTE_PATH = '/home/u483747408/domains/gold-trout-815009.hostingersite.com';
const REMOTE_PHP = '/opt/alt/php84/usr/bin/php';

console.log('================================================================');
console.log('⚠️  ¡ADVERTENCIA DE SEGURIDAD! REEMPLAZO DE BASE DE DATOS REMOTA');
console.log('================================================================');
console.log('Este comando reemplazará la base de datos SQLite del servidor de');
console.log('producción con tu base de datos local (database/database.sqlite).');
console.log('⚠️  ¡ESTO SOBREESCRIBIRÁ TODOS LOS DATOS ACTUALES EN PRODUCCIÓN! ⚠️');
console.log('================================================================\n');

// 1. Verificar existencia de base de datos local
const localDbPath = path.join(rootDir, 'database', 'database.sqlite');
if (!existsSync(localDbPath)) {
    console.error('❌ Error: No se encontró la base de datos local en database/database.sqlite');
    process.exit(1);
}

const stats = statSync(localDbPath);
if (stats.size === 0) {
    console.error('❌ Error: La base de datos local database/database.sqlite está vacía (0 bytes).');
    process.exit(1);
}

console.log(`📂 Base de datos local: database/database.sqlite (${(stats.size / 1024 / 1024).toFixed(2)} MB)\n`);

// 2. Proceso de Confirmación
async function confirmAction() {
    if (isForce) {
        return true;
    }
    if (!process.stdin.isTTY) {
        console.error('❌ Entorno no interactivo detectado. Utiliza --confirm para confirmar de forma explícita.');
        return false;
    }

    const rl = readline.createInterface({
        input: process.stdin,
        output: process.stdout
    });

    return new Promise((resolve) => {
        rl.question('👉 Escribe "REEMPLAZAR" para confirmar que deseas subir la BD local al servidor: ', (answer) => {
            rl.close();
            resolve(answer.trim().toUpperCase() === 'REEMPLAZAR');
        });
    });
}

// 3. Helper de contraseña SSH
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
        try { import('node:fs').then(fs => (fs.rmSync ? fs.rmSync(askpassDir, { recursive: true, force: true }) : fs.rmdirSync(askpassDir))); } catch {}
    }
}
process.on('exit', cleanup);
process.on('SIGINT', () => { cleanup(); process.exit(1); });

function runRemoteCommand(command, description) {
    if (description) {
        console.log(`📡 [Servidor] ${description}...`);
    }
    const sshArgs = [
        '-n',
        '-o', 'StrictHostKeyChecking=accept-new',
        '-o', 'UserKnownHostsFile=/dev/null',
        '-o', 'LogLevel=ERROR',
        '-p', SSH_PORT,
        `${SSH_USER}@${SSH_HOST}`,
        `cd ${REMOTE_PATH} && ${command}`
    ];

    const result = spawnSync('ssh', sshArgs, {
        env: envVars,
        stdio: 'inherit',
        encoding: 'utf-8'
    });

    if (result.status !== 0) {
        throw new Error(`Fallo en comando remoto: ${description} (código ${result.status})`);
    }
    return result;
}

// 4. Ejecución principal
async function main() {
    const confirmed = await confirmAction();
    if (!confirmed) {
        console.log('\n❌ Operación CANCELADA. No se realizó ningún cambio en el servidor.');
        process.exit(0);
    }

    console.log('\n🚀 Iniciando proceso de reemplazo de base de datos remota...\n');

    // Generar nombre de respaldo remoto con fecha y hora
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    const timestamp = `${now.getFullYear()}${pad(now.getMonth() + 1)}${pad(now.getDate())}_${pad(now.getHours())}${pad(now.getMinutes())}${pad(now.getSeconds())}`;
    const backupRemoteFile = `database/database.sqlite.bak-${timestamp}`;

    try {
        // Paso A: Crear copia de seguridad remota antes de reemplazar
        runRemoteCommand(
            `if [ -f database/database.sqlite ]; then cp database/database.sqlite ${backupRemoteFile}; echo "Respaldo creado: ${backupRemoteFile}"; fi`,
            `Creando respaldo de la base de datos remota actual en ${backupRemoteFile}`
        );

        // Paso B: Subir base de datos local usando rsync
        console.log('📤 Subiendo base de datos local al servidor...');
        const sshCommandStr = `ssh -p ${SSH_PORT} -o StrictHostKeyChecking=accept-new -o UserKnownHostsFile=/dev/null -o LogLevel=ERROR`;
        const rsyncArgs = [
            '-avz',
            '--progress',
            '-e', sshCommandStr,
            localDbPath,
            `${SSH_USER}@${SSH_HOST}:${REMOTE_PATH}/database/database.sqlite`
        ];

        const rsyncResult = spawnSync('rsync', rsyncArgs, {
            env: envVars,
            stdio: 'inherit'
        });

        if (rsyncResult.status !== 0) {
            throw new Error(`Fallo en la transferencia rsync (código ${rsyncResult.status})`);
        }

        // Paso C: Limpiar cachés de producción
        console.log('\n⚙️  Optimizando cachés de Laravel en el servidor...');
        runRemoteCommand(`${REMOTE_PHP} artisan optimize:clear`, 'Limpiando cachés de Laravel');
        runRemoteCommand(`${REMOTE_PHP} artisan optimize`, 'Compilando optimizaciones de Laravel');

        console.log('\n================================================================');
        console.log('🎉 ¡Base de datos local subida y reemplazada exitosamente!');
        console.log(`🛡️ Respaldo previo conservado en el servidor: ${backupRemoteFile}`);
        console.log('================================================================\n');

    } catch (err) {
        console.error(`\n❌ Error al reemplazar la base de datos: ${err.message}`);
        process.exit(1);
    }
}

main();
