import { readFileSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';
import { BACKEND_PATH } from '../config.js';
import { isContainerRunning, execInDocker, dockerNotRunningError } from '../utils/exec.js';
import { buildResponse, responseToText } from '../utils/response.js';

interface Route {
  method: string;
  uri: string;
  name?: string;
  action: string;
  middleware: string[];
}

function parseRoutesFile(filePath: string): Route[] {
  if (!existsSync(filePath)) return [];
  const content = readFileSync(filePath, 'utf-8');
  const routes: Route[] = [];

  const pattern = /Route::(get|post|put|patch|delete|any)\s*\(\s*'([^']+)'[^;]+\)/g;
  for (const m of content.matchAll(pattern)) {
    routes.push({
      method: m[1].toUpperCase(),
      uri: m[2],
      action: 'parsed-from-file',
      middleware: [],
    });
  }
  return routes;
}

export async function handleListRoutes(filter?: string): Promise<string> {
  const startMs = Date.now();
  const warnings: string[] = [];
  let routes: unknown[];
  let source: 'docker' | 'files' = 'docker';

  if (isContainerRunning()) {
    const result = execInDocker('php artisan route:list --json');
    if (result.exitCode === 0) {
      try {
        const parsed = JSON.parse(result.stdout) as unknown[];
        routes = parsed;
      } catch {
        warnings.push('Failed to parse route:list JSON, falling back to file parsing');
        routes = parseRoutesFile(resolve(BACKEND_PATH, 'routes/api.php'));
        source = 'files';
      }
    } else {
      warnings.push(`route:list failed: ${result.stderr.slice(0, 200)}`);
      routes = parseRoutesFile(resolve(BACKEND_PATH, 'routes/api.php'));
      source = 'files';
    }
  } else {
    warnings.push(dockerNotRunningError());
    routes = parseRoutesFile(resolve(BACKEND_PATH, 'routes/api.php'));
    source = 'files';
  }

  // Apply filter
  if (filter) {
    const lower = filter.toLowerCase();
    routes = routes.filter(r => {
      const s = JSON.stringify(r).toLowerCase();
      return s.includes(lower);
    });
  }

  const data = {
    total: routes.length,
    filter: filter ?? null,
    routes,
  };

  return responseToText(
    buildResponse(data, {
      source,
      startMs,
      warnings: warnings.length > 0 ? warnings : undefined,
      containerStatus: isContainerRunning() ? 'running' : 'stopped',
    })
  );
}
