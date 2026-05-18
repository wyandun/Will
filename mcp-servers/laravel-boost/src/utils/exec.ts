import { execSync } from 'node:child_process';
import { DOCKER_CONTAINER, EXEC_TIMEOUT, DOCKER_CHECK_TTL } from '../config.js';

export interface ExecResult {
  stdout: string;
  stderr: string;
  exitCode: number;
}

let containerStatusCache: { running: boolean; ts: number } | null = null;

export function isContainerRunning(container = DOCKER_CONTAINER): boolean {
  const now = Date.now();
  if (containerStatusCache && now - containerStatusCache.ts < DOCKER_CHECK_TTL) {
    return containerStatusCache.running;
  }
  try {
    const result = execSync(
      `docker inspect --format="{{.State.Running}}" ${container}`,
      { timeout: 5000, encoding: 'utf-8' }
    );
    const running = result.trim() === 'true';
    containerStatusCache = { running, ts: now };
    return running;
  } catch {
    containerStatusCache = { running: false, ts: now };
    return false;
  }
}

export function invalidateContainerCache(): void {
  containerStatusCache = null;
}

export function execInDocker(cmd: string, container = DOCKER_CONTAINER): ExecResult {
  try {
    const stdout = execSync(`docker exec ${container} ${cmd}`, {
      timeout: EXEC_TIMEOUT,
      encoding: 'utf-8',
    });
    return { stdout: stdout ?? '', stderr: '', exitCode: 0 };
  } catch (err: unknown) {
    const e = err as { stdout?: string; stderr?: string; status?: number; message?: string };
    return {
      stdout: e.stdout ?? '',
      stderr: e.stderr ?? e.message ?? String(err),
      exitCode: e.status ?? 1,
    };
  }
}

export function execLocal(cmd: string, cwd?: string): ExecResult {
  try {
    const stdout = execSync(cmd, {
      timeout: EXEC_TIMEOUT,
      encoding: 'utf-8',
      cwd,
    });
    return { stdout: stdout ?? '', stderr: '', exitCode: 0 };
  } catch (err: unknown) {
    const e = err as { stdout?: string; stderr?: string; status?: number; message?: string };
    return {
      stdout: e.stdout ?? '',
      stderr: e.stderr ?? e.message ?? String(err),
      exitCode: e.status ?? 1,
    };
  }
}

export function dockerNotRunningError(container = DOCKER_CONTAINER): string {
  return `Error: Docker container '${container}' is not running. Start with: docker compose up -d`;
}
