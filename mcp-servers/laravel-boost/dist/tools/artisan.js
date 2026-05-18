import { ARTISAN_ALLOWLIST, ARTISAN_BLOCKLIST } from '../config.js';
import { isContainerRunning, execInDocker, dockerNotRunningError } from '../utils/exec.js';
import { buildResponse, buildError, responseToText } from '../utils/response.js';
function isAllowed(command) {
    const base = command.trim().split(/\s+/)[0];
    // Check blocklist first (exact match or prefix)
    for (const blocked of ARTISAN_BLOCKLIST) {
        if (base === blocked || command.startsWith(blocked + ' '))
            return false;
    }
    // Check allowlist (supports * wildcard suffix like "make:*")
    for (const allowed of ARTISAN_ALLOWLIST) {
        if (allowed.endsWith('*')) {
            const prefix = allowed.slice(0, -1);
            if (base.startsWith(prefix))
                return true;
        }
        else if (base === allowed) {
            return true;
        }
    }
    return false;
}
export async function handleArtisanCommand(command) {
    const startMs = Date.now();
    if (!command || typeof command !== 'string') {
        return responseToText(buildError('command parameter is required', { startMs }));
    }
    const cmd = command.trim();
    if (!isAllowed(cmd)) {
        return responseToText(buildError(`Command '${cmd}' is not in the allowlist. Allowed: ${ARTISAN_ALLOWLIST.join(', ')}`, { startMs }));
    }
    if (!isContainerRunning()) {
        return responseToText(buildError(dockerNotRunningError(), { source: 'docker', startMs }));
    }
    const result = execInDocker(`php artisan ${cmd}`);
    // Try to parse as JSON, fall back to raw output
    let output;
    try {
        output = JSON.parse(result.stdout);
    }
    catch {
        output = result.stdout || result.stderr;
    }
    const data = {
        command: `php artisan ${cmd}`,
        exitCode: result.exitCode,
        success: result.exitCode === 0,
        output,
    };
    return responseToText(buildResponse(data, {
        source: 'docker',
        startMs,
        containerStatus: 'running',
    }));
}
//# sourceMappingURL=artisan.js.map