import { spawnSync, execSync } from 'node:child_process';
import { existsSync, unlinkSync, statSync, lstatSync, symlinkSync, readlinkSync, mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');

// Permite especificar el nombre del zip como argumento (ej. npm run zip -- mi-archivo.zip)
const customArg = process.argv.slice(2).find((arg) => !arg.startsWith('--') && arg.endsWith('.zip'));
const skipBuild = process.argv.includes('--no-build');
const outputZipName = customArg || 'kudosdoes-deploy.zip';
const outputZipPath = path.join(rootDir, outputZipName);

console.log('🚀 Iniciando proceso de empaquetado para producción...');

// 1. Validar que exista vendor
if (!existsSync(path.join(rootDir, 'vendor', 'autoload.php'))) {
    console.error('❌ Error: El directorio vendor/ no existe o no tiene autoload.php.');
    console.error('   Ejecuta "composer install --no-dev --optimize-autoloader" antes de crear el zip.');
    process.exit(1);
}

// 2. Compilar assets frontend (Vite)
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

// 3. Garantizar symlink relativo public_html -> public para Hostinger Cloud Hosting
const publicHtmlPath = path.join(rootDir, 'public_html');
try {
    let needsLink = true;
    if (existsSync(publicHtmlPath) || existsSync(publicHtmlPath, { throwIfNoEntry: false })) {
        try {
            const stat = lstatSync(publicHtmlPath);
            if (stat.isSymbolicLink()) {
                const target = readlinkSync(publicHtmlPath);
                if (target === 'public') {
                    needsLink = false;
                } else {
                    unlinkSync(publicHtmlPath);
                }
            } else {
                console.warn('⚠️  Aviso: public_html existe pero es un directorio/archivo regular, no un symlink.');
                needsLink = false;
            }
        } catch {
            unlinkSync(publicHtmlPath);
        }
    }
    if (needsLink) {
        console.log('🔗 Creando enlace simbólico relativo public_html -> public (Hostinger)...');
        symlinkSync('public', publicHtmlPath, 'dir');
    }
} catch (err) {
    console.warn('⚠️  No se pudo verificar/crear el symlink public_html:', err.message);
}

// 4. Garantizar symlink relativo public/storage -> ../storage/app/public (Avatares y uploads)
const publicStoragePath = path.join(rootDir, 'public', 'storage');
try {
    let needsStorageLink = true;
    if (existsSync(publicStoragePath) || existsSync(publicStoragePath, { throwIfNoEntry: false })) {
        try {
            const stat = lstatSync(publicStoragePath);
            if (stat.isSymbolicLink()) {
                const target = readlinkSync(publicStoragePath);
                if (target === '../storage/app/public') {
                    needsStorageLink = false;
                } else {
                    unlinkSync(publicStoragePath);
                }
            } else {
                unlinkSync(publicStoragePath);
            }
        } catch {
            unlinkSync(publicStoragePath);
        }
    }
    if (needsStorageLink) {
        console.log('🔗 Creando enlace simbólico relativo public/storage -> ../storage/app/public...');
        symlinkSync('../storage/app/public', publicStoragePath, 'dir');
    }
} catch (err) {
    console.warn('⚠️  No se pudo verificar/crear el symlink public/storage:', err.message);
}

// 5. Garantizar que los directorios críticos de storage existan y tengan .gitignore
const criticalStorageDirs = [
    'storage/framework/views',
    'storage/framework/sessions',
    'storage/framework/cache/data',
    'storage/framework/testing',
    'storage/logs',
    'storage/app/public',
    'storage/app/private'
];

for (const relDir of criticalStorageDirs) {
    const fullDir = path.join(rootDir, relDir);
    if (!existsSync(fullDir)) {
        mkdirSync(fullDir, { recursive: true });
    }
    const gitignorePath = path.join(fullDir, '.gitignore');
    if (!existsSync(gitignorePath)) {
        writeFileSync(gitignorePath, "*\n!.gitignore\n");
    }
}

// 5. Eliminar archivo zip previo si existe
if (existsSync(outputZipPath)) {
    console.log(`🧹 Eliminando archivo zip anterior (${outputZipName})...`);
    unlinkSync(outputZipPath);
}

// 6. Lista exhaustiva de exclusiones para producción
const exclusions = [
    // Dependencias frontend de desarrollo
    'node_modules/*',
    'node_modules',

    // Archivos de entorno y credenciales (evita sobreescribir el .env del servidor)
    '.env',
    '.env.*',
    '*.env',
    '*.env.*',

    // Bases de datos locales y backups SQLite (evita sobreescribir la BD en producción)
    '*.sqlite*',
    '*.sqlite',
    'database/*.sqlite*',
    'database/*.bak*',
    'database/*.wiped*',
    'database/backup*',
    'storage/app/backups/*',

    // Control de versiones y herramientas de desarrollo / IA
    '.git/*',
    '.git',
    '.github/*',
    '.github',
    '.gitattributes',
    '.agents/*',
    '.agents',
    '.ai/*',
    '.ai',
    'AGENTS.md',
    'boost.json',
    'opencode.json',
    'plan.md',

    // Tests y configuración de testing
    'tests/*',
    'tests',
    'phpunit.xml',
    '.phpunit.result.cache',
    '.phpunit.cache/*',

    // Documentación interna y configuraciones de editor
    'docs/*',
    'docs',
    '.editorconfig',
    '.vscode/*',
    '.idea/*',
    '.cursor/*',
    '.zed/*',

    // Archivos residuales/temporales
    '*\\}*',
    'company_name}*',
    'task_name}*',
    'trello_title}*',

    // Caches, logs y archivos temporales de Laravel (preservando los directorios y .gitignore)
    'storage/logs/*.log',
    'storage/framework/cache/data/[!.]*',
    'storage/framework/sessions/[!.]*',
    'storage/framework/views/*.php',
    'storage/framework/views/livewire/*',
    'storage/framework/testing/disks/*',
    'storage/framework/testing/backups/*',
    'storage/pail/*',
    'storage/*.key',
    'storage/app/private/livewire-tmp/*',

    // Archivos Vite dev
    'public/hot',

    // Archivos del sistema operativo y zips
    '*.DS_Store',
    '*/.DS_Store',
    'Thumbs.db',
    '*.zip'
];

console.log(`🗜️  Comprimiendo archivos en ${outputZipName}...`);

// Flag -y guarda enlaces simbólicos (como public_html -> public y public/storage -> ../storage/app/public) como symlinks reales
const zipArgs = ['-q', '-y', '-r', outputZipName, '.', '-x', ...exclusions];

const zipProcess = spawnSync('zip', zipArgs, {
    cwd: rootDir,
    stdio: 'inherit'
});

if (zipProcess.status !== 0) {
    console.error('❌ Ocurrió un error al ejecutar el comando zip.');
    process.exit(zipProcess.status || 1);
}

// 6. Verificar tamaño del archivo resultante
const stats = statSync(outputZipPath);
const sizeMB = (stats.size / (1024 * 1024)).toFixed(2);

console.log('\n========================================');
console.log(`✅ ¡Zip de producción generado con éxito!`);
console.log(`📁 Archivo: ${outputZipName}`);
console.log(`⚖️  Tamaño:  ${sizeMB} MB`);
console.log('========================================');
console.log('🛡️  Configuración aplicada:');
console.log('   ✓ Symlink relativo public_html -> public INCLUIDO (para Hostinger Cloud)');
console.log('   ✓ Symlink relativo public/storage -> ../storage/app/public INCLUIDO (avatares e imágenes)');
console.log('   ✓ vendor/ (dependencias PHP) INCLUIDO');
console.log('   ✓ public/build/ (assets Vite compilados) INCLUIDO');
console.log('   ✓ node_modules/ excluido');
console.log('   ✓ .env y variantes excluidos (listo para reutilizar el de producción)');
console.log('   ✓ Bases de datos SQLite y backups excluidos (sin riesgo de sobreescritura)');
console.log('   ✓ Tests, docs, .git y herramientas de AI excluidos');
console.log('   ✓ Caches transitorias y logs de Laravel limpiados');
console.log('========================================\n');
