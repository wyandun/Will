import { execSync } from 'node:child_process';
import { ALL_CONTAINERS, DOCKER_CONTAINER, POSTGRES_CONTAINER, REDIS_CONTAINER } from '../config.js';
import { execInDocker } from '../utils/exec.js';
import { buildResponse, responseToText } from '../utils/response.js';
function checkContainers() {
    return ALL_CONTAINERS.map(name => {
        try {
            const out = execSync(`docker inspect --format="{{.State.Status}}" ${name}`, { timeout: 5000, encoding: 'utf-8' }).trim();
            return { name, running: out === 'running', status: out };
        }
        catch {
            return { name, running: false, status: 'not found' };
        }
    });
}
function checkPostgres() {
    const result = execInDocker('pg_isready -U postgres', POSTGRES_CONTAINER);
    if (result.exitCode === 0) {
        return { healthy: true, detail: result.stdout.trim() };
    }
    return { healthy: false, detail: result.stderr || 'pg_isready failed' };
}
function checkRedis() {
    const result = execInDocker('redis-cli ping', REDIS_CONTAINER);
    const pong = result.stdout.trim().toUpperCase() === 'PONG';
    return { healthy: pong, detail: result.stdout.trim() || result.stderr };
}
function checkQueue() {
    const result = execInDocker('php artisan queue:failed --json', DOCKER_CONTAINER);
    if (result.exitCode === 0) {
        try {
            const jobs = JSON.parse(result.stdout);
            return { failedJobs: jobs.length, detail: `${jobs.length} failed job(s)` };
        }
        catch {
            return { failedJobs: 0, detail: result.stdout.slice(0, 200) };
        }
    }
    return { failedJobs: -1, detail: result.stderr.slice(0, 200) };
}
function checkApi() {
    try {
        const out = execSync('curl -s -o /dev/null -w "%{http_code}" http://localhost/api/v1/ping', { timeout: 5000, encoding: 'utf-8' }).trim();
        const code = parseInt(out, 10);
        return { healthy: code >= 200 && code < 400, statusCode: code, detail: `HTTP ${code}` };
    }
    catch (err) {
        return { healthy: false, detail: String(err).slice(0, 100) };
    }
}
function calcOverall(result) {
    const issues = [];
    if (result.containers) {
        const criticalDown = result.containers
            .filter(c => [DOCKER_CONTAINER, POSTGRES_CONTAINER].includes(c.name))
            .some(c => !c.running);
        if (criticalDown)
            return 'down';
        issues.push(result.containers.some(c => !c.running));
    }
    if (result.postgres && !result.postgres.healthy)
        return 'down';
    if (result.redis && !result.redis.healthy)
        issues.push(true);
    if (result.api && !result.api.healthy)
        issues.push(true);
    if (result.queue && result.queue.failedJobs > 0)
        issues.push(true);
    return issues.some(Boolean) ? 'degraded' : 'healthy';
}
export async function handleEnvironmentHealth(check) {
    const startMs = Date.now();
    const c = check ?? 'all';
    const result = { overall: 'healthy' };
    if (c === 'all' || c === 'docker') {
        result.containers = checkContainers();
    }
    if (c === 'all' || c === 'database') {
        result.postgres = checkPostgres();
    }
    if (c === 'all' || c === 'redis') {
        result.redis = checkRedis();
    }
    if (c === 'all' || c === 'queue') {
        result.queue = checkQueue();
    }
    if (c === 'all' || c === 'api') {
        result.api = checkApi();
    }
    result.overall = calcOverall(result);
    return responseToText(buildResponse(result, {
        source: 'docker',
        startMs,
        containerStatus: result.containers?.find(c => c.name === DOCKER_CONTAINER)?.running
            ? 'running'
            : 'stopped',
    }));
}
//# sourceMappingURL=environment-health.js.map