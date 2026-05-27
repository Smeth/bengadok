/**
 * Wrapper pour @laravel/vite-plugin-wayfinder (cwd + PHP explicite sous Windows).
 */
import { execSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const args = process.argv.slice(2).join(' ');

function resolvePhpBinary() {
    if (process.platform === 'win32') {
        try {
            const first = execSync('where php', {
                encoding: 'utf8',
                stdio: ['pipe', 'pipe', 'ignore'],
            })
                .trim()
                .split(/\r?\n/)[0]
                ?.trim();

            if (first) {
                return `"${first}"`;
            }
        } catch {
            // ignore
        }
    }

    return 'php';
}

const php = resolvePhpBinary();
const cmd = `${php} artisan wayfinder:generate${args ? ` ${args}` : ''}`;

execSync(cmd, { stdio: 'inherit', cwd: root });
