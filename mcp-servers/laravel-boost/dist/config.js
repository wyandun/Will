import { resolve, dirname } from 'node:path';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
const __dirname = dirname(fileURLToPath(import.meta.url));
function loadConfig() {
    const configPath = resolve(__dirname, '../boost.config.json');
    const raw = readFileSync(configPath, 'utf-8');
    return JSON.parse(raw);
}
const cfg = loadConfig();
const serverRoot = resolve(__dirname, '../../..');
export const BACKEND_PATH = resolve(serverRoot, cfg.project.backendPath.replace(/^\.\.\/\.\.\//, ''));
export const FRONTEND_PATH = resolve(serverRoot, cfg.project.frontendPath.replace(/^\.\.\/\.\.\//, ''));
export const DOCKER_CONTAINER = cfg.project.dockerContainer;
export const POSTGRES_CONTAINER = cfg.project.postgresContainer;
export const REDIS_CONTAINER = cfg.project.redisContainer;
export const QUEUE_CONTAINER = cfg.project.queueContainer;
export const NGINX_CONTAINER = cfg.project.nginxContainer;
export const DOCUSEAL_CONTAINER = cfg.project.docusealContainer;
export const ALL_CONTAINERS = [
    DOCKER_CONTAINER,
    POSTGRES_CONTAINER,
    REDIS_CONTAINER,
    QUEUE_CONTAINER,
    NGINX_CONTAINER,
    DOCUSEAL_CONTAINER,
];
export const SECURITY_AUDIT_FILE = resolve(serverRoot, cfg.security.auditFile.replace(/^\.\.\/\.\.\//, ''));
export const SECURITY_DECISIONS_FILE = resolve(serverRoot, cfg.security.decisionsFile.replace(/^\.\.\/\.\.\//, ''));
export const SECURITY_DECISIONS_SECTION = cfg.security.decisionsSection;
export const ARTISAN_ALLOWLIST = cfg.artisan.allowlist;
export const ARTISAN_BLOCKLIST = cfg.artisan.blocklist;
export const CACHE_ENABLED = cfg.cache.enabled;
export const EXEC_TIMEOUT = 30_000;
export const DOCKER_CHECK_TTL = 10_000;
//# sourceMappingURL=config.js.map