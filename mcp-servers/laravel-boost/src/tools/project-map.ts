import { readdirSync, readFileSync } from 'node:fs';
import { resolve, join, basename } from 'node:path';
import { BACKEND_PATH } from '../config.js';
import { getCached, setCached } from '../utils/cache.js';
import { buildResponse, responseToText } from '../utils/response.js';
import {
  parseModel,
  parseService,
  parseController,
  parseRequest,
  parsePolicy,
  extractDependencies,
  type ParsedModel,
  type ParsedService,
  type ParsedController,
  type ParsedRequest,
  type ParsedPolicy,
  type ParsedDependencies,
} from '../utils/parser.js';

function readPhpFiles(dir: string, recursive = false): Array<{ path: string; content: string }> {
  const results: Array<{ path: string; content: string }> = [];
  try {
    const entries = readdirSync(dir, { withFileTypes: true });
    for (const entry of entries) {
      const full = join(dir, entry.name);
      if (entry.isDirectory() && recursive) {
        results.push(...readPhpFiles(full, true));
      } else if (entry.isFile() && entry.name.endsWith('.php')) {
        let content = getCached<string>(full);
        if (!content) {
          content = readFileSync(full, 'utf-8');
          setCached(full, content);
        }
        results.push({ path: full, content });
      }
    }
  } catch {
    // dir doesn't exist — return empty
  }
  return results;
}

function parseAll<T>(
  dir: string,
  parseFn: (content: string) => T,
  recursive = false
): T[] {
  return readPhpFiles(dir, recursive).map(({ content }) => parseFn(content));
}

export async function handleProjectMap(section?: string): Promise<string> {
  const startMs = Date.now();
  const sec = section ?? 'all';

  const modelsDir = resolve(BACKEND_PATH, 'app/Models');
  const servicesDir = resolve(BACKEND_PATH, 'app/Services');
  const controllersDir = resolve(BACKEND_PATH, 'app/Http/Controllers/Api');
  const requestsDir = resolve(BACKEND_PATH, 'app/Http/Requests');
  const policiesDir = resolve(BACKEND_PATH, 'app/Policies');
  const resourcesDir = resolve(BACKEND_PATH, 'app/Http/Resources');

  const result: Record<string, unknown> = {};

  if (sec === 'all' || sec === 'models') {
    const models: ParsedModel[] = parseAll(modelsDir, parseModel);
    result.models = models;
  }

  if (sec === 'all' || sec === 'services') {
    const services: ParsedService[] = parseAll(servicesDir, parseService);
    result.services = services;
  }

  if (sec === 'all' || sec === 'controllers') {
    const controllers: ParsedController[] = parseAll(controllersDir, parseController, true);
    result.controllers = controllers;
  }

  if (sec === 'all' || sec === 'requests') {
    const requests: ParsedRequest[] = parseAll(requestsDir, parseRequest, true);
    result.requests = requests;
  }

  if (sec === 'all' || sec === 'policies') {
    const policies: ParsedPolicy[] = parseAll(policiesDir, parsePolicy);
    result.policies = policies;
  }

  if (sec === 'all' || sec === 'resources') {
    const resourceFiles = readPhpFiles(resourcesDir, false);
    result.resources = resourceFiles.map(f => basename(f.path, '.php'));
  }

  if (sec === 'all' || sec === 'dependencies') {
    const controllerFiles = readPhpFiles(controllersDir, true);
    const serviceFiles = readPhpFiles(servicesDir, false);

    const serviceMap = new Map<string, string>();
    for (const sf of serviceFiles) {
      const match = sf.content.match(/class\s+(\w+Service)/);
      if (match) serviceMap.set(match[1], sf.content);
    }

    const deps: ParsedDependencies[] = controllerFiles.map(cf => {
      const ctorMatch = cf.content.match(/public\s+function\s+__construct\s*\(([^)]*)\)/s);
      let serviceContent: string | undefined;
      if (ctorMatch) {
        const m = ctorMatch[1].match(/(\w+Service)\s+\$/);
        if (m) serviceContent = serviceMap.get(m[1]);
      }
      return extractDependencies(cf.content, serviceContent);
    });
    result.dependencies = deps;
  }

  const response = buildResponse(result, { source: 'files', startMs });
  return responseToText(response);
}
