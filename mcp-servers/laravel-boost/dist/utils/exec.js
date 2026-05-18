import { execSync } from 'node:child_process';
import { DOCKER_CONTAINER, EXEC_TIMEOUT, DOCKER_CHECK_TTL } from '../config.js';
let containerStatusCache = null;
export function isContainerRunning(container = DOCKER_CONTAINER) {
    const now = Date.now();
    if (containerStatusCache && now - containerStatusCache.ts < DOCKER_CHECK_TTL) {
        return containerStatusCache.running;
    }
    try {
        const result = execSync(`docker inspect --format="{{.State.Running}}" ${container}`, { timeout: 5000, encoding: 'utf-8' });
        const running = result.trim() === 'true';
        containerStatusCache = { running, ts: now };
        return running;
    }
    catch {
        containerStatusCache = { running: false, ts: now };
        return false;
    }
}
export function invalidateContainerCache() {
    containerStatusCache = null;
}
export function execInDocker(cmd, container = DOCKER_CONTAINER) {
    try {
        const stdout = execSync(`docker exec ${container} ${cmd}`, {
            timeout: EXEC_TIMEOUT,
            encoding: 'utf-8',
        });
        return { stdout: stdout ?? '', stderr: '', exitCode: 0 };
    }
    catch (err) {
        const e = err;
        return {
            stdout: e.stdout ?? '',
            stderr: e.stderr ?? e.message ?? String(err),
            exitCode: e.status ?? 1,
        };
    }
}
export function execLocal(cmd, cwd) {
    try {
        const stdout = execSync(cmd, {
            timeout: EXEC_TIMEOUT,
            encoding: 'utf-8',
            cwd,
        });
        return { stdout: stdout ?? '', stderr: '', exitCode: 0 };
    }
    catch (err) {
        const e = err;
        return {
            stdout: e.stdout ?? '',
            stderr: e.stderr ?? e.message ?? String(err),
            exitCode: e.status ?? 1,
        };
    }
}
export function dockerNotRunningError(container = DOCKER_CONTAINER) {
    return `Error: Docker container '${container}' is not running. Start with: docker compose up -d`;
}
//# sourceMappingURL=exec.js.map