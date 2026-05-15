import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { BACKEND_PATH, DOCKER_CONTAINER } from '../config.js';
import { isContainerRunning, execInDocker, dockerNotRunningError } from '../utils/exec.js';
import { getCached, setCached } from '../utils/cache.js';
import { buildResponse, buildError, responseToText } from '../utils/response.js';
import { parseModel } from '../utils/parser.js';
export async function handleDescribeModel(model) {
    const startMs = Date.now();
    if (!model || typeof model !== 'string') {
        return responseToText(buildError('model parameter is required', { startMs }));
    }
    const modelName = model.trim();
    const filePath = resolve(BACKEND_PATH, `app/Models/${modelName}.php`);
    // File-parsed data
    let fileParsed = null;
    try {
        let content = getCached(filePath);
        if (!content) {
            content = readFileSync(filePath, 'utf-8');
            setCached(filePath, content);
        }
        fileParsed = parseModel(content);
    }
    catch {
        return responseToText(buildError(`Model file not found: app/Models/${modelName}.php`, { startMs }));
    }
    const warnings = [];
    let dockerData = null;
    const containerRunning = isContainerRunning();
    if (containerRunning) {
        const result = execInDocker(`php artisan model:show ${modelName} --json`);
        if (result.exitCode === 0) {
            try {
                dockerData = JSON.parse(result.stdout);
            }
            catch {
                warnings.push('Docker returned non-JSON output from model:show');
            }
        }
        else {
            warnings.push(`model:show failed: ${result.stderr.slice(0, 200)}`);
        }
    }
    else {
        warnings.push(dockerNotRunningError(DOCKER_CONTAINER));
    }
    const data = {
        file_parsed: fileParsed,
        artisan_show: dockerData,
    };
    return responseToText(buildResponse(data, {
        source: containerRunning && dockerData ? 'mixed' : 'files',
        startMs,
        warnings: warnings.length > 0 ? warnings : undefined,
        containerStatus: containerRunning ? 'running' : 'stopped',
    }));
}
//# sourceMappingURL=describe-model.js.map