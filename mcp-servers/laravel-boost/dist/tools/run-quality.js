import { isContainerRunning, execInDocker, dockerNotRunningError } from '../utils/exec.js';
import { buildResponse, buildError, responseToText } from '../utils/response.js';
export async function handleRunPint(fix, path) {
    const startMs = Date.now();
    if (!isContainerRunning()) {
        return responseToText(buildError(dockerNotRunningError(), { source: 'docker', startMs }));
    }
    const flag = fix ? '' : '--test';
    const target = path ? ` ${path}` : '';
    const cmd = `./vendor/bin/pint${flag ? ` ${flag}` : ''}${target}`;
    const result = execInDocker(cmd);
    const data = {
        mode: fix ? 'fix' : 'dry-run',
        path: path ?? 'all',
        exitCode: result.exitCode,
        output: result.stdout || result.stderr,
        clean: result.exitCode === 0,
    };
    return responseToText(buildResponse(data, {
        source: 'docker',
        startMs,
        containerStatus: 'running',
        warnings: result.exitCode !== 0 && !fix ? ['Style issues found. Run with fix=true to auto-fix.'] : undefined,
    }));
}
export async function handleRunPhpstan(path) {
    const startMs = Date.now();
    if (!isContainerRunning()) {
        return responseToText(buildError(dockerNotRunningError(), { source: 'docker', startMs }));
    }
    const target = path ?? 'app/';
    const cmd = `./vendor/bin/phpstan analyse ${target} --error-format=json --no-progress`;
    const result = execInDocker(cmd);
    let parsed = null;
    const warnings = [];
    try {
        parsed = JSON.parse(result.stdout);
    }
    catch {
        if (result.stderr) {
            warnings.push(`PHPStan stderr: ${result.stderr.slice(0, 500)}`);
        }
        parsed = { raw: result.stdout.slice(0, 2000) };
    }
    const data = {
        path: target,
        exitCode: result.exitCode,
        passed: result.exitCode === 0,
        result: parsed,
    };
    return responseToText(buildResponse(data, {
        source: 'docker',
        startMs,
        containerStatus: 'running',
        warnings: warnings.length > 0 ? warnings : undefined,
    }));
}
export async function handleRunTests(file, filter, suite) {
    const startMs = Date.now();
    if (!isContainerRunning()) {
        return responseToText(buildError(dockerNotRunningError(), { source: 'docker', startMs }));
    }
    let cmd = 'php artisan test';
    if (suite)
        cmd += ` --testsuite=${suite}`;
    if (filter)
        cmd += ` --filter="${filter}"`;
    if (file)
        cmd += ` ${file}`;
    const result = execInDocker(cmd);
    const data = {
        command: `docker exec sm_backend ${cmd}`,
        file: file ?? null,
        filter: filter ?? null,
        suite: suite ?? null,
        exitCode: result.exitCode,
        passed: result.exitCode === 0,
        output: (result.stdout || result.stderr).slice(0, 5000),
    };
    return responseToText(buildResponse(data, {
        source: 'docker',
        startMs,
        containerStatus: 'running',
    }));
}
//# sourceMappingURL=run-quality.js.map