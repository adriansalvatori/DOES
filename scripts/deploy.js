import { spawnSync, execSync } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync, unlinkSync, chmodSync, mkdtempSync } from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');

// Parámetros y banderas CLI
const isStatus = process.argv.includes('--status') || process.argv.includes('--diff');
const isDryRun = process.argv.includes('--dry-run');
const skipBuild = process.argv.includes('--no-build');
const skipMigrate = process.argv.includes('--no-migrate');

// Etiquetado (Tagging): permite --tag o --tag=v1.2.0
const tagArg = process.argv.find((arg) => arg.startsWith('--tag'));
let customTag = null;
if (tagArg) {
    if (tagArg.includes('=')) {
        customTag = tagArg.split('=')[1].trim();
    } else {
        const now = new Date();
        const pad = (n) => String(n).padStart(2, '0');
        customTag = `v${now.getFullYear()}.${pad(now.getMonth() + 1)}.${pad(now.getDate())}-${pad(now.getHours())}${pad(now.getMinutes())}`;
    }
}

// Configuración del servidor Hostinger
const SSH_USER = 'u483747408';
const SSH_HOST = '185.211.7.113';
const SSH_PORT = '65002';
const REMOTE_PATH = '/home/u483747408/domains/gold-trout-815009.hostingersite.com';
const REMOTE_PHP = '/opt/alt/php84/usr/bin/php';

// 1. Obtener la contraseña desde .env si existe
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

// 2. Configurar helper askpass para SSH/Rsync no interactivo
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

// Ejecutar comando SSH remoto
function runRemoteCommand(command, description, capture = false) {
    if (description && !capture) {
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
        stdio: capture ? ['ignore', 'pipe', 'pipe'] : 'inherit',
        encoding: 'utf-8'
    });

    if (result.status !== 0 && !capture) {
        throw new Error(`Fallo en comando remoto: ${description} (código ${result.status})`);
    }
    return result;
}

// -------------------------------------------------------------
// MODO --status / --diff: Comprobar qué cambios faltan en producción
// -------------------------------------------------------------
if (isStatus) {
    console.log('🔍 Consultando estado actual de producción en Hostinger...\n');
    const remoteRes = runRemoteCommand('cat deployed.json 2>/dev/null || echo "{}"', 'Obteniendo deployed.json', true);
    let remoteInfo = {};
    try {
        remoteInfo = JSON.parse(remoteRes.stdout.trim() || '{}');
    } catch {
        remoteInfo = {};
    }

    const localCommit = execSync('git rev-parse HEAD', { cwd: rootDir, encoding: 'utf-8' }).trim();
    const localShort = execSync('git rev-parse --short HEAD', { cwd: rootDir, encoding: 'utf-8' }).trim();
    const localBranch = execSync('git rev-parse --abbrev-ref HEAD', { cwd: rootDir, encoding: 'utf-8' }).trim();

    console.log('======================================================');
    console.log('📊 ESTADO DE PRODUCCIÓN (Hostinger)');
    console.log('======================================================');

    if (!remoteInfo.commit) {
        console.log('⚠️  No se encontró un registro previo de despliegue (deployed.json) en el servidor.');
        console.log('   (Es la primera vez que se usa el nuevo sistema de tracking)');
    } else {
        console.log(`📌 Commit en Producción : ${remoteInfo.short_commit || remoteInfo.commit.slice(0, 7)}`);
        console.log(`💬 Mensaje              : ${remoteInfo.commit_message || 'N/A'}`);
        console.log(`🌿 Rama desplegada      : ${remoteInfo.branch || 'main'}`);
        if (remoteInfo.tag) {
            console.log(`🏷️  Tag / Versión        : ${remoteInfo.tag}`);
        }
        console.log(`🕒 Fecha de despliegue  : ${remoteInfo.deployed_at || 'Desconocida'}`);
        console.log(`👤 Desplegado por       : ${remoteInfo.deployed_by || 'N/A'}`);
    }

    console.log('------------------------------------------------------');
    console.log(`💻 Estado Local Actual  : ${localShort} (${localBranch})`);
    console.log('------------------------------------------------------');

    if (remoteInfo.commit) {
        if (remoteInfo.commit === localCommit || (remoteInfo.short_commit && remoteInfo.short_commit === localShort)) {
            console.log('✅ ¡El servidor de producción está al día con tu commit local!');
        } else {
            try {
                const ref = remoteInfo.short_commit || remoteInfo.commit;
                const pendingCommits = execSync(`git log ${ref}..HEAD --oneline`, { cwd: rootDir, encoding: 'utf-8' }).trim();
                if (pendingCommits) {
                    const count = pendingCommits.split('\n').length;
                    console.log(`🚀 Commits pendientes por desplegar (${count}):\n`);
                    console.log(pendingCommits);
                    console.log('\n📄 Archivos modificados en estos commits:');
                    const diffStat = execSync(`git diff --stat ${ref}..HEAD`, { cwd: rootDir, encoding: 'utf-8' }).trim();
                    console.log(diffStat);
                } else {
                    console.log('ℹ️  Tu commit local no es un descendiente directo del commit en producción.');
                }
            } catch {
                console.log('ℹ️  No se pudo comparar el historial git (los commits pueden estar en ramas diferentes).');
            }
        }
    }

    // Comprobar cambios sin commitear
    const uncommitted = execSync('git status --short', { cwd: rootDir, encoding: 'utf-8' }).trim();
    if (uncommitted) {
        console.log('\n⚠️  CAMBIOS LOCALES SIN CONFIRMAR (Working Tree):');
        console.log('   (Estos archivos se subirían si ejecutas deploy ahora, pero no están en Git)');
        console.log(uncommitted);
    } else {
        console.log('\n✨ Directorio de trabajo local limpio (sin cambios pendientes por commitear).');
    }

    console.log('======================================================\n');
    process.exit(0);
}

// -------------------------------------------------------------
// MODO DESPLIEGUE: Ejecutar build, sync y tareas remotas
// -------------------------------------------------------------
console.log('🚀 Iniciando despliegue automatizado a Hostinger...');
console.log(`🌐 Destino: ${SSH_USER}@${SSH_HOST}:${REMOTE_PATH}`);
if (isDryRun) {
    console.log('🔍 MODO SIMULACIÓN (--dry-run): No se modificarán archivos en el servidor.');
}

// 3. Obtener metadatos de Git locales
let gitCommit = 'unknown';
let gitShortCommit = 'unknown';
let gitBranch = 'unknown';
let gitMessage = 'N/A';
let gitAuthor = 'unknown';

try {
    gitCommit = execSync('git rev-parse HEAD', { cwd: rootDir, encoding: 'utf-8' }).trim();
    gitShortCommit = execSync('git rev-parse --short HEAD', { cwd: rootDir, encoding: 'utf-8' }).trim();
    gitBranch = execSync('git rev-parse --abbrev-ref HEAD', { cwd: rootDir, encoding: 'utf-8' }).trim();
    gitMessage = execSync('git log -1 --pretty=%s', { cwd: rootDir, encoding: 'utf-8' }).trim();
    gitAuthor = execSync('git log -1 --pretty=%an', { cwd: rootDir, encoding: 'utf-8' }).trim();
} catch (err) {
    console.warn('⚠️  No se pudieron obtener todos los metadatos de Git:', err.message);
}

// 4. Crear tag si se especificó
if (customTag && !isDryRun) {
    console.log(`🏷️  Creando tag local en Git: ${customTag}...`);
    try {
        execSync(`git tag -a ${customTag} -m "Deployed to production on ${new Date().toISOString()}"`, {
            cwd: rootDir,
            stdio: 'inherit'
        });
        console.log(`✅ Tag ${customTag} creado. Recuerda ejecutar "git push --tags" para subirlo a GitHub.`);
    } catch (err) {
        console.warn(`⚠️  No se pudo crear el tag en Git (quizás ya existe): ${err.message}`);
    }
}

// 5. Generar archivo deployed.json que se sincronizará con el servidor
const deployedInfo = {
    commit: gitCommit,
    short_commit: gitShortCommit,
    branch: gitBranch,
    commit_message: gitMessage,
    tag: customTag,
    deployed_at: new Date().toLocaleString('es-CO', { timeZone: 'America/Cancun' }) + ' (Cancun)',
    deployed_by: `${gitAuthor} (${os.userInfo().username}@${os.hostname()})`
};

const deployedJsonPath = path.join(rootDir, 'deployed.json');
if (!isDryRun) {
    writeFileSync(deployedJsonPath, JSON.stringify(deployedInfo, null, 2) + '\n');
}

// 6. Compilar assets de Vite
if (skipBuild) {
    console.log('⏩ Saltando compilación de Vite (--no-build detectado).');
} else {
    console.log('📦 Compilando assets para producción con Vite (npm run build)...');
    try {
        execSync('npm run build', { cwd: rootDir, stdio: 'inherit' });
    } catch {
        console.error('❌ Error al compilar assets con Vite.');
        process.exit(1);
    }
}

// 7. Exclusiones de sincronización (seguridad crítica)
const rsyncExcludes = [
    // Entorno y credenciales
    '.env',
    '.env.*',
    '*.env',
    '*.env.*',

    // Bases de datos locales
    '*.sqlite*',
    'database/*.sqlite*',
    'database/*.bak*',
    'database/*.wiped*',
    'database/backup*',
    'storage/app/backups/*',

    // Control de versiones y herramientas de desarrollo / IA
    '.git',
    '.git/*',
    '.github',
    '.github/*',
    '.agents',
    '.agents/*',
    '.ai',
    '.ai/*',
    'AGENTS.md',
    'boost.json',
    'opencode.json',
    'plan.md',

    // Tests
    'tests',
    'tests/*',
    'phpunit.xml',
    '.phpunit.result.cache',
    '.phpunit.cache',

    // Documentación interna y configuraciones de editor
    'docs',
    'docs/*',
    '.editorconfig',
    '.vscode',
    '.idea',
    '.cursor',
    '.zed',

    // Dependencias frontend locales (no necesarias en servidor)
    'node_modules',
    'node_modules/*',

    // Archivos temporales, zips y logs
    'storage/logs/*',
    'storage/framework/cache/data/*',
    'storage/framework/sessions/*',
    'storage/framework/views/*',
    'storage/pail/*',
    'storage/app/public/*',
    'storage/app/private/*',
    'public/hot',
    '*.DS_Store',
    '*/.DS_Store',
    'Thumbs.db',
    '*.zip'
];

// 8. Sincronizar archivos con rsync
console.log('🔄 Sincronizando archivos con el servidor vía rsync...');
const excludeArgs = rsyncExcludes.flatMap((exc) => ['--exclude', exc]);

// Filtros de protección de rsync: Evita que --delete borre archivos generados en producción
const protectArgs = [
    '--filter=P .env*',
    '--filter=P database/*.sqlite*',
    '--filter=P database/*.bak*',
    '--filter=P storage/**',
    '--filter=P deployed.json',
    '--filter=P kudosdoes-deploy.zip'
];

const sshCommandStr = `ssh -p ${SSH_PORT} -o StrictHostKeyChecking=accept-new -o UserKnownHostsFile=/dev/null -o LogLevel=ERROR`;
const rsyncArgs = [
    '-avz',
    '--delete-after',
    '--progress',
    '-e', sshCommandStr,
    ...protectArgs,
    ...excludeArgs,
    ...(isDryRun ? ['--dry-run'] : []),
    rootDir + '/',
    `${SSH_USER}@${SSH_HOST}:${REMOTE_PATH}/`
];

const rsyncResult = spawnSync('rsync', rsyncArgs, {
    env: envVars,
    stdio: 'inherit'
});

if (rsyncResult.status !== 0) {
    console.error('❌ Error durante la sincronización rsync.');
    process.exit(rsyncResult.status || 1);
}

if (isDryRun) {
    console.log('\n✅ Simulación completada con éxito. No se ejecutaron tareas post-despliegue.');
    process.exit(0);
}

// 9. Tareas post-despliegue en el servidor remoto
console.log('\n⚙️  Ejecutando tareas post-despliegue en Hostinger...');

try {
    // A. Migraciones de base de datos
    if (!skipMigrate) {
        runRemoteCommand(`${REMOTE_PHP} artisan migrate --force`, 'Ejecutando migraciones de base de datos');
    }

    // B. Enlace simbólico de storage
    runRemoteCommand(`${REMOTE_PHP} artisan storage:link`, 'Verificando enlace simbólico storage:link');

    // C. Limpiar y optimizar cachés de Laravel
    runRemoteCommand(`${REMOTE_PHP} artisan optimize:clear`, 'Limpiando cachés de Laravel');
    runRemoteCommand(`${REMOTE_PHP} artisan optimize`, 'Compilando optimizaciones de Laravel (rutas, config)');

    console.log('\n======================================================');
    console.log('🎉 ¡Despliegue finalizado con éxito!');
    console.log(`📌 Versión desplegada: ${gitShortCommit} (${gitBranch})`);
    if (customTag) {
        console.log(`🏷️  Tag asignado      : ${customTag}`);
    }
    console.log(`🔗 Sitio web          : https://gold-trout-815009.hostingersite.com`);
    console.log('======================================================\n');
} catch (err) {
    console.error(`\n❌ Error en las tareas post-despliegue: ${err.message}`);
    process.exit(1);
}
